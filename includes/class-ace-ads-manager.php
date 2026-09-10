<?php
/**
 * Ace Ads Manager core: the Ad post type, its meta, and the version stamp that
 * invalidates every resolved-ad cache when an ad, rule or setting changes.
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Ads_Manager {

    const POST_TYPE   = 'ace_ad';
    const CACHE_GROUP = 'ace_ads';

    /** Meta keys on an Ad. */
    const META_OFFER_CODE = '_ace_ad_offer_code';
    const META_LINK       = '_ace_ad_link';
    const META_CTA        = '_ace_ad_cta';
    const META_START      = '_ace_ad_start';
    const META_END        = '_ace_ad_end';
    const META_IMAGE_MODE = '_ace_ad_image_mode';
    const META_RULES      = '_ace_ad_rules';
    const META_REL        = '_ace_ad_rel';

    private static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        require_once ACE_ADS_PATH . 'includes/class-ace-ads-rules.php';
        require_once ACE_ADS_PATH . 'includes/class-ace-ads-render.php';
        require_once ACE_ADS_PATH . 'includes/class-ace-ads-tracking.php';

        new Ace_Ads_Rules();
        new Ace_Ads_Render();
        new Ace_Ads_Tracking();

        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'init', [ $this, 'register_meta' ] );
        add_action( 'init', [ $this, 'register_block' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'block_editor_config' ] );
        // Ads are a headline and a sentence of copy: the classic editor keeps the offer
        // fields and placement rules directly under the copy instead of folded away.
        add_filter( 'use_block_editor_for_post_type', [ $this, 'classic_editor_for_ads' ], 10, 2 );

        add_action( 'save_post_' . self::POST_TYPE, [ $this, 'bump_version' ] );
        add_action( 'deleted_post', [ $this, 'bump_version_on_delete' ], 10, 2 );
        add_action( 'transition_post_status', [ $this, 'bump_version_on_status' ], 10, 3 );
        add_action( 'ace_ads_settings_saved', [ $this, 'bump_version' ] );

        if ( is_admin() ) {
            require_once ACE_ADS_PATH . 'includes/admin/class-ace-ads-meta-box.php';
            require_once ACE_ADS_PATH . 'includes/admin/class-ace-ads-overview.php';
            require_once ACE_ADS_PATH . 'includes/admin/class-ace-ads-analytics.php';
            new Ace_Ads_Meta_Box();
            new Ace_Ads_Overview();
            new Ace_Ads_Analytics();
            foreach ( [ 'load-post.php', 'load-post-new.php', 'load-edit.php' ] as $hook ) {
                add_action( $hook, [ 'Ace_Ads_Guide', 'post_screen_help' ] );
            }
        }

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once ACE_ADS_PATH . 'includes/class-ace-ads-cli.php';
            WP_CLI::add_command( 'ace-ads', 'Ace_Ads_CLI' );
        }
    }

    public static function activate(): void {
        update_option( 'ace_ads_version', ACE_ADS_VERSION, false );
        Ace_Ads_Tracking::create_tables();
        self::instance()->register_post_type();
        flush_rewrite_rules();
    }

    public function register_post_type(): void {
        register_post_type( self::POST_TYPE, [
            'labels'              => [
                'name'               => __( 'Ads', 'ace-ads-manager' ),
                'singular_name'      => __( 'Ad', 'ace-ads-manager' ),
                'add_new_item'       => __( 'Add new ad', 'ace-ads-manager' ),
                'edit_item'          => __( 'Edit ad', 'ace-ads-manager' ),
                'all_items'          => __( 'All ads', 'ace-ads-manager' ),
                'search_items'       => __( 'Search ads', 'ace-ads-manager' ),
                'not_found'          => __( 'No ads found.', 'ace-ads-manager' ),
                'featured_image'     => __( 'Ad image', 'ace-ads-manager' ),
                'set_featured_image' => __( 'Set ad image', 'ace-ads-manager' ),
            ],
            'description'         => __( 'Offers and promotions placed through the Ad block and placement rules.', 'ace-ads-manager' ),
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'menu_icon'           => 'dashicons-megaphone',
            'menu_position'       => 26,
            'supports'            => [ 'title', 'editor', 'thumbnail', 'revisions', 'author' ],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'exclude_from_search' => true,
            'has_archive'         => false,
            'rewrite'             => false,
        ] );
    }

    public function register_meta(): void {
        $string = [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => static function () {
                return current_user_can( 'edit_posts' );
            },
        ];
        register_post_meta( self::POST_TYPE, self::META_OFFER_CODE, $string );
        register_post_meta( self::POST_TYPE, self::META_LINK, array_merge( $string, [ 'sanitize_callback' => 'esc_url_raw' ] ) );
        register_post_meta( self::POST_TYPE, self::META_CTA, $string );
        register_post_meta( self::POST_TYPE, self::META_START, $string );
        register_post_meta( self::POST_TYPE, self::META_END, $string );
        register_post_meta( self::POST_TYPE, self::META_IMAGE_MODE, $string );
        register_post_meta( self::POST_TYPE, self::META_REL, $string );
        register_post_meta( self::POST_TYPE, self::META_RULES, [
            'type'              => 'array',
            'single'            => true,
            'show_in_rest'      => [ 'schema' => [ 'type' => 'array', 'items' => [ 'type' => 'object', 'additionalProperties' => true ] ] ],
            'sanitize_callback' => [ 'Ace_Ads_Rules', 'sanitise_rules' ],
            'auth_callback'     => static function () {
                return current_user_can( 'edit_posts' );
            },
        ] );
    }

    public function register_block(): void {
        $dir = ACE_ADS_PATH . 'build/blocks/slot';
        if ( file_exists( $dir . '/block.json' ) ) {
            register_block_type( $dir );
        }
    }

    public function classic_editor_for_ads( bool $use, string $post_type ): bool {
        return self::POST_TYPE === $post_type ? (bool) apply_filters( 'ace_ads_use_block_editor', false ) : $use;
    }

    /**
     * Slot names for the block inspector.
     */
    public function block_editor_config(): void {
        wp_add_inline_script( 'ace-ads-slot-editor-script', 'window.ace_ads_block=' . wp_json_encode( [ 'slots' => self::slots() ] ) . ';', 'before' );
    }

    /**
     * Named slots from settings: slug => label.
     */
    public static function slots(): array {
        $slots = [];
        foreach ( Ace_Ads_Manager_Settings::get_list( 'slots' ) as $line ) {
            $parts = array_map( 'trim', explode( '|', $line, 2 ) );
            $slug  = sanitize_key( $parts[0] );
            if ( $slug ) {
                $slots[ $slug ] = $parts[1] ?? ucfirst( $slug );
            }
        }
        return apply_filters( 'ace_ads_slots', $slots );
    }

    /**
     * Cache version. Bumping it orphans every resolved-ad cache entry at once,
     * which is the only invalidation that survives the Ace Redis Cache namespace flush.
     */
    public static function version(): int {
        return (int) get_option( 'ace_ads_cache_version', 1 );
    }

    public function bump_version(): void {
        update_option( 'ace_ads_cache_version', self::version() + 1, false );
        do_action( 'ace_ads_cache_invalidated' );
    }

    public function bump_version_on_delete( int $post_id, $post ): void {
        if ( $post instanceof WP_Post && self::POST_TYPE === $post->post_type ) {
            $this->bump_version();
        }
    }

    public function bump_version_on_status( string $new, string $old, WP_Post $post ): void {
        if ( self::POST_TYPE === $post->post_type && $new !== $old ) {
            $this->bump_version();
        }
    }

    /**
     * Is an ad live right now (published and inside its date window)?
     */
    public static function is_live( int $ad_id, ?int $now = null ): bool {
        if ( 'publish' !== get_post_status( $ad_id ) ) {
            return false;
        }
        // Dates are stored in the site time zone (datetime-local input); compare in UTC.
        $now   = $now ?? time();
        $start = (string) get_post_meta( $ad_id, self::META_START, true );
        $end   = (string) get_post_meta( $ad_id, self::META_END, true );
        if ( $start && (int) get_gmt_from_date( str_replace( 'T', ' ', $start ), 'U' ) > $now ) {
            return false;
        }
        if ( $end && (int) get_gmt_from_date( str_replace( 'T', ' ', $end ), 'U' ) < $now ) {
            return false;
        }
        return (bool) apply_filters( 'ace_ads_is_live', true, $ad_id );
    }
}
