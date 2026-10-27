<?php
/**
 * Tracking: impressions, viewable impressions and clicks.
 *
 * The browser batches events and beacons them to /ace-ads/v1/events. Each
 * event lands in a raw events table (pruned after the retention period) and
 * is rolled up immediately into a daily table (kept for good), so reports are
 * one indexed query. Bots, headless browsers and (optionally) logged-in users
 * are dropped; impressions are de-duplicated per page view.
 *
 * No personal data: the visitor key is a salted hash of IP + user agent that
 * rotates daily and only serves de-duplication and a rough unique count.
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Ads_Tracking {

    const NS         = 'ace-ads/v1';
    const DB_VERSION = '3';
    const EVENTS     = [ 'impression', 'viewable', 'click' ];
    const CRON       = 'ace_ads_prune_events';

    /** Max events accepted from one visitor per minute. */
    const RATE_LIMIT = 120;

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'routes' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'init', [ $this, 'maybe_upgrade' ] );
        add_action( self::CRON, [ __CLASS__, 'prune' ] );
        if ( ! wp_next_scheduled( self::CRON ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
        }
    }

    // ---------------------------------------------------------------- tables

    public static function events_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ace_ads_events';
    }

    public static function daily_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ace_ads_daily';
    }

    public static function rate_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ace_ads_rate';
    }

    public function maybe_upgrade(): void {
        if ( get_option( 'ace_ads_db_version' ) !== self::DB_VERSION ) {
            self::create_tables();
        }
    }

    /**
     * Plain CREATE TABLE (no IF NOT EXISTS: dbDelta misparses it) and verify before
     * recording the version, so a silent failure retries next load.
     */
    public static function create_tables(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $events  = self::events_table();
        $daily   = self::daily_table();

        dbDelta( "CREATE TABLE {$events} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event varchar(12) NOT NULL,
            ad_id bigint(20) unsigned NOT NULL,
            slot varchar(64) NOT NULL DEFAULT '',
            rule_index smallint(6) NOT NULL DEFAULT -1,
            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            view varchar(20) NOT NULL DEFAULT '',
            post_type varchar(20) NOT NULL DEFAULT '',
            term varchar(64) NOT NULL DEFAULT '',
            device varchar(10) NOT NULL DEFAULT '',
            referer_host varchar(191) NOT NULL DEFAULT '',
            visitor varchar(40) NOT NULL DEFAULT '',
            pageview varchar(32) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY dedupe (pageview, ad_id, slot, event),
            KEY ad_created (ad_id, created_at),
            KEY event_created (event, created_at),
            KEY visitor_created (visitor, created_at)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$daily} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            day date NOT NULL,
            ad_id bigint(20) unsigned NOT NULL,
            slot varchar(64) NOT NULL DEFAULT '',
            rule_index smallint(6) NOT NULL DEFAULT -1,
            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            device varchar(10) NOT NULL DEFAULT '',
            impressions int(10) unsigned NOT NULL DEFAULT 0,
            viewable int(10) unsigned NOT NULL DEFAULT 0,
            clicks int(10) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY bucket (day, ad_id, slot, rule_index, post_id, device),
            KEY ad_day (ad_id, day),
            KEY day (day)
        ) {$charset};" );

        $rate = self::rate_table();
        dbDelta( "CREATE TABLE {$rate} (
            visitor varchar(40) NOT NULL,
            minute int(10) unsigned NOT NULL,
            hits int(10) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (visitor, minute),
            KEY minute (minute)
        ) {$charset};" );

        $ok = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events ) ) === $events
            && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rate ) ) === $rate
            && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $daily ) ) === $daily;
        if ( $ok ) {
            update_option( 'ace_ads_db_version', self::DB_VERSION, false );
        } else {
            error_log( 'Ace Ads Manager: failed to create tracking tables.' );
        }
    }

    // ---------------------------------------------------------------- front end

    public function enqueue(): void {
        wp_enqueue_style( 'ace-ads', ACE_ADS_URL . 'assets/css/ads.css', [], ACE_ADS_VERSION );

        if ( ! Ace_Ads_Manager_Settings::get( 'track_impressions', 1 ) && ! Ace_Ads_Manager_Settings::get( 'track_clicks', 1 ) ) {
            return;
        }
        $asset_file = ACE_ADS_PATH . 'build/view.asset.php';
        $asset      = file_exists( $asset_file ) ? include $asset_file : [ 'dependencies' => [], 'version' => ACE_ADS_VERSION ];
        wp_register_script( 'ace-ads-view', ACE_ADS_URL . 'build/view.js', $asset['dependencies'] ?? [], $asset['version'] ?? ACE_ADS_VERSION, [ 'strategy' => 'defer', 'in_footer' => true ] );

        $context = Ace_Ads_Rules::request_context();
        wp_add_inline_script( 'ace-ads-view', 'window.ace_ads_view=' . wp_json_encode( [
            'endpoint'    => esc_url_raw( rest_url( self::NS . '/events' ) ),
            'impressions' => (bool) Ace_Ads_Manager_Settings::get( 'track_impressions', 1 ),
            'clicks'      => (bool) Ace_Ads_Manager_Settings::get( 'track_clicks', 1 ),
            'post_id'     => (int) $context['post_id'],
            'view'        => (string) $context['view'],
            'post_type'   => (string) $context['post_type'],
            'term'        => (string) ( $context['term'] ?: ( $context['terms'][0] ?? '' ) ),
        ] ) . ';', 'before' );
        wp_enqueue_script( 'ace-ads-view' );
    }

    // ---------------------------------------------------------------- REST

    public function routes(): void {
        register_rest_route( self::NS, '/events', [
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => '__return_true',
            'callback'            => [ $this, 'receive' ],
            'args'                => [
                'pv'        => [ 'required' => true, 'type' => 'string' ],
                'events'    => [ 'required' => true, 'type' => 'array' ],
                'post_id'   => [ 'type' => 'integer', 'default' => 0 ],
                'view'      => [ 'type' => 'string', 'default' => '' ],
                'post_type' => [ 'type' => 'string', 'default' => '' ],
                'term'      => [ 'type' => 'string', 'default' => '' ],
                'ref'       => [ 'type' => 'string', 'default' => '' ],
                'li'        => [ 'type' => 'integer', 'default' => 0 ],
                'wd'        => [ 'type' => 'integer', 'default' => 0 ],
            ],
        ] );
    }

    public function receive( WP_REST_Request $request ): WP_REST_Response {
        $user_agent = (string) $request->get_header( 'user_agent' );
        if ( $request->get_param( 'wd' ) || self::is_bot( $user_agent ) ) {
            return new WP_REST_Response( [ 'ok' => true, 'stored' => 0 ], 202 );
        }
        if ( $request->get_param( 'li' ) && ! Ace_Ads_Manager_Settings::get( 'track_logged_in', 0 ) ) {
            return new WP_REST_Response( [ 'ok' => true, 'stored' => 0 ], 202 );
        }

        $visitor = self::visitor_key( $user_agent );
        if ( ! self::within_rate_limit( $visitor ) ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'rate' ], 429 );
        }

        $page = [
            'pageview'     => substr( preg_replace( '/[^a-z0-9]/i', '', (string) $request->get_param( 'pv' ) ), 0, 32 ),
            'post_id'      => (int) $request->get_param( 'post_id' ),
            'view'         => substr( sanitize_key( (string) $request->get_param( 'view' ) ), 0, 20 ),
            'post_type'    => substr( sanitize_key( (string) $request->get_param( 'post_type' ) ), 0, 20 ),
            'term'         => substr( preg_replace( '/[^a-z0-9_\-:]/i', '', (string) $request->get_param( 'term' ) ), 0, 64 ),
            'device'       => self::device( $user_agent ),
            'referer_host' => substr( (string) wp_parse_url( (string) $request->get_param( 'ref' ), PHP_URL_HOST ), 0, 191 ),
            'visitor'      => $visitor,
        ];
        if ( '' === $page['pageview'] ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'pageview' ], 400 );
        }

        $stored = 0;
        foreach ( array_slice( (array) $request->get_param( 'events' ), 0, 50 ) as $raw ) {
            if ( ! is_array( $raw ) ) {
                continue;
            }
            $event = sanitize_key( (string) ( $raw['e'] ?? '' ) );
            $ad_id = (int) ( $raw['ad'] ?? 0 );
            if ( ! in_array( $event, self::EVENTS, true ) || ! $ad_id ) {
                continue;
            }
            if ( 'click' === $event && ! Ace_Ads_Manager_Settings::get( 'track_clicks', 1 ) ) {
                continue;
            }
            if ( 'click' !== $event && ! Ace_Ads_Manager_Settings::get( 'track_impressions', 1 ) ) {
                continue;
            }
            $row = $page + [
                'event'      => $event,
                'ad_id'      => $ad_id,
                'slot'       => substr( sanitize_key( (string) ( $raw['slot'] ?? '' ) ), 0, 64 ),
                'rule_index' => isset( $raw['rule'] ) && '' !== $raw['rule'] ? max( -1, (int) $raw['rule'] ) : -1,
            ];
            if ( self::record( $row ) ) {
                $stored++;
            }
        }
        return new WP_REST_Response( [ 'ok' => true, 'stored' => $stored ], 200 );
    }

    // ---------------------------------------------------------------- storage

    /**
     * Store one event and roll it into the daily bucket. Returns false when dropped
     * (unknown ad, filtered out, or a duplicate within the page view).
     */
    public static function record( array $row ): bool {
        global $wpdb;
        static $known = [];

        if ( ! isset( $known[ $row['ad_id'] ] ) ) {
            $known[ $row['ad_id'] ] = Ace_Ads_Manager::POST_TYPE === get_post_type( $row['ad_id'] );
        }
        if ( ! $known[ $row['ad_id'] ] ) {
            return false;
        }
        /**
         * Drop an event before it is stored (return false), or adjust it.
         */
        $row = apply_filters( 'ace_ads_track_event', $row );
        if ( ! is_array( $row ) ) {
            return false;
        }
        $row['created_at'] = current_time( 'mysql', true );

        // Clicks are never de-duplicated; impressions and viewable are, per page view.
        if ( 'click' === $row['event'] ) {
            $row['pageview'] = $row['pageview'] . ':' . substr( md5( (string) microtime( true ) . wp_rand() ), 0, 8 );
        }
        $inserted = $wpdb->query( $wpdb->prepare(
            'INSERT IGNORE INTO ' . self::events_table() . ' (event, ad_id, slot, rule_index, post_id, view, post_type, term, device, referer_host, visitor, pageview, created_at) VALUES (%s, %d, %s, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s)',
            $row['event'], $row['ad_id'], $row['slot'], $row['rule_index'], $row['post_id'], $row['view'], $row['post_type'], $row['term'], $row['device'], $row['referer_host'], $row['visitor'], $row['pageview'], $row['created_at']
        ) );
        if ( ! $inserted ) {
            return false;
        }

        $column = [ 'impression' => 'impressions', 'viewable' => 'viewable', 'click' => 'clicks' ][ $row['event'] ];
        $wpdb->query( $wpdb->prepare(
            'INSERT INTO ' . self::daily_table() . " (day, ad_id, slot, rule_index, post_id, device, {$column}) VALUES (%s, %d, %s, %d, %d, %s, 1) ON DUPLICATE KEY UPDATE {$column} = {$column} + 1",
            self::site_day( strtotime( $row['created_at'] . ' UTC' ) ), $row['ad_id'], $row['slot'], $row['rule_index'], $row['post_id'], $row['device']
        ) );

        do_action( 'ace_ads_event', $row['event'], $row['ad_id'], $row );
        if ( 'click' === $row['event'] ) {
            /**
             * A tracked ad click. Sites attribute it however they like, e.g. forward it
             * into bet-link click tracking against the post byline.
             *
             * @param int    $ad_id
             * @param int    $post_id  Post the ad was shown on (0 on archives).
             * @param string $slot
             * @param array  $row      Full event row (rule_index, device, referer_host, ...).
             */
            do_action( 'ace_ads_click', $row['ad_id'], $row['post_id'], $row['slot'], $row );
        }
        return true;
    }

    public static function prune(): void {
        global $wpdb;
        $days = max( 7, (int) Ace_Ads_Manager_Settings::get( 'retention_days', 90 ) );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::events_table() . ' WHERE created_at < %s', gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::rate_table() . ' WHERE minute < %d', intdiv( time(), 60 ) - 2 ) );
    }

    // ---------------------------------------------------------------- helpers

    public static function is_bot( string $user_agent ): bool {
        if ( '' === $user_agent ) {
            return true;
        }
        $pattern = '/bot|crawl|spider|slurp|headless|phantom|lighthouse|pagespeed|gtmetrix|pingdom|facebookexternalhit|preview|monitor|curl|wget|python-requests|go-http-client/i';
        return (bool) apply_filters( 'ace_ads_is_bot', (bool) preg_match( $pattern, $user_agent ), $user_agent );
    }

    public static function device( string $user_agent ): string {
        if ( preg_match( '/ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))/i', $user_agent ) ) {
            return 'tablet';
        }
        if ( preg_match( '/mobile|iphone|ipod|android|blackberry|opera mini|windows phone/i', $user_agent ) ) {
            return 'mobile';
        }
        return 'desktop';
    }

    /**
     * Salted, daily-rotating hash of IP + user agent. Not reversible, not stored anywhere else.
     */
    public static function visitor_key( string $user_agent ): string {
        $salt = get_option( 'ace_ads_visitor_salt' );
        if ( ! $salt ) {
            $salt = wp_generate_password( 32, false );
            update_option( 'ace_ads_visitor_salt', $salt, false );
        }
        $ip = self::client_ip( $_SERVER ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        return substr( hash( 'sha256', $salt . '|' . gmdate( 'Y-m-d' ) . '|' . $ip . '|' . $user_agent ), 0, 40 );
    }

    /**
     * The client address. Forwarded headers are only believed when the request
     * arrives from a trusted proxy, so a client cannot rotate a header to look
     * like a new visitor. CF-Connecting-IP needs a Cloudflare peer; for
     * X-Forwarded-For the nearest address that is not itself a proxy wins.
     *
     * @param array $server $_SERVER or a test double.
     */
    public static function client_ip( array $server ): string {
        $remote = isset( $server['REMOTE_ADDR'] ) ? trim( (string) $server['REMOTE_ADDR'] ) : '';
        if ( '' === $remote || ! self::ip_in( $remote, self::trusted_proxies() ) ) {
            return $remote;
        }
        $cf = isset( $server['HTTP_CF_CONNECTING_IP'] ) ? trim( (string) $server['HTTP_CF_CONNECTING_IP'] ) : '';
        if ( '' !== $cf && filter_var( $cf, FILTER_VALIDATE_IP ) && self::ip_in( $remote, self::CLOUDFLARE ) ) {
            return $cf;
        }
        if ( ! empty( $server['HTTP_X_FORWARDED_FOR'] ) ) {
            $hops = array_reverse( array_map( 'trim', explode( ',', (string) $server['HTTP_X_FORWARDED_FOR'] ) ) );
            foreach ( $hops as $hop ) {
                if ( ! filter_var( $hop, FILTER_VALIDATE_IP ) ) {
                    break;
                }
                if ( ! self::ip_in( $hop, self::trusted_proxies() ) ) {
                    return $hop;
                }
            }
        }
        return $remote;
    }

    /** Cloudflare's published ranges (cloudflare.com/ips). */
    const CLOUDFLARE = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /**
     * Proxies whose forwarded headers are believed: Cloudflare plus loopback and
     * private ranges (a load balancer in front of the site).
     */
    public static function trusted_proxies(): array {
        $ranges = array_merge( self::CLOUDFLARE, [ '127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '::1/128', 'fc00::/7' ] );
        return (array) apply_filters( 'ace_ads_trusted_proxies', $ranges );
    }

    /** Whether $ip falls in any of the CIDR ranges (IPv4 or IPv6). */
    public static function ip_in( string $ip, array $ranges ): bool {
        $bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        if ( false === $bin ) {
            return false;
        }
        foreach ( $ranges as $range ) {
            $parts = explode( '/', (string) $range, 2 );
            $net   = @inet_pton( $parts[0] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            if ( false === $net || strlen( $net ) !== strlen( $bin ) ) {
                continue;
            }
            $bits  = isset( $parts[1] ) ? (int) $parts[1] : strlen( $bin ) * 8;
            $bytes = intdiv( $bits, 8 );
            if ( substr( $bin, 0, $bytes ) !== substr( $net, 0, $bytes ) ) {
                continue;
            }
            $rest = $bits % 8;
            if ( $rest ) {
                $mask = chr( ( 0xff << ( 8 - $rest ) ) & 0xff );
                if ( ( $bin[ $bytes ] & $mask ) !== ( $net[ $bytes ] & $mask ) ) {
                    continue;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * At most RATE_LIMIT events per visitor per minute. One upsert counts the hit
     * and hands back the new total through LAST_INSERT_ID(), under the row lock,
     * so parallel hits cannot overshoot. (The object cache is not used because a
     * drop-in may keep visitor requests runtime-only, which would count per request.)
     */
    private static function within_rate_limit( string $visitor ): bool {
        global $wpdb;
        $done = $wpdb->query( $wpdb->prepare(
            'INSERT INTO ' . self::rate_table() . ' (visitor, minute, hits) VALUES (%s, %d, LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE hits = LAST_INSERT_ID(hits + 1)',
            $visitor,
            intdiv( time(), 60 )
        ) );
        if ( false === $done ) {
            return true; // table missing: fail open, as the tracker always has
        }
        return (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' ) <= self::RATE_LIMIT;
    }

    /**
     * Clicks in the last N days for an ad (list column + overview).
     */
    public static function clicks_total( int $ad_id, int $days = 30 ): int {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(SUM(clicks), 0) FROM ' . self::daily_table() . ' WHERE ad_id = %d AND day >= %s',
            $ad_id,
            // Day buckets are written in site time, so read them in site time too.
            self::site_day( time() - $days * DAY_IN_SECONDS )
        ) );
    }

    /** The site-time Y-m-d of a Unix timestamp: the one clock for day buckets. */
    public static function site_day( int $timestamp ): string {
        return wp_date( 'Y-m-d', $timestamp );
    }
}
