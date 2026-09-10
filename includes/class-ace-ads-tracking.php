<?php
/**
 * Click tracking. The front-end script beacons clicks to a tiny REST endpoint
 * which keeps a per-ad daily count for the overview and fires `ace_ads_click`
 * so a site can attribute the click in its own system (e.g. bet-link tracking).
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Ads_Tracking {

    const NS = 'ace-ads/v1';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'routes' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function enqueue(): void {
        if ( ! Ace_Ads_Manager_Settings::get( 'track_clicks', 1 ) ) {
            return;
        }
        $asset_file = ACE_ADS_PATH . 'build/view.asset.php';
        $asset      = file_exists( $asset_file ) ? include $asset_file : [ 'dependencies' => [], 'version' => ACE_ADS_VERSION ];
        wp_register_script( 'ace-ads-view', ACE_ADS_URL . 'build/view.js', $asset['dependencies'] ?? [], $asset['version'] ?? ACE_ADS_VERSION, [ 'strategy' => 'defer', 'in_footer' => true ] );
        wp_add_inline_script( 'ace-ads-view', 'window.ace_ads_view=' . wp_json_encode( [
            'endpoint' => esc_url_raw( rest_url( self::NS . '/click' ) ),
            'post_id'  => is_singular() ? get_queried_object_id() : 0,
        ] ) . ';', 'before' );
        wp_enqueue_script( 'ace-ads-view' );
        wp_enqueue_style( 'ace-ads', ACE_ADS_URL . 'assets/css/ads.css', [], ACE_ADS_VERSION );
    }

    public function routes(): void {
        register_rest_route( self::NS, '/click', [
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => '__return_true',
            'callback'            => [ $this, 'click' ],
            'args'                => [
                'ad_id'   => [ 'required' => true, 'type' => 'integer' ],
                'post_id' => [ 'type' => 'integer', 'default' => 0 ],
                'slot'    => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_key' ],
            ],
        ] );
    }

    public function click( WP_REST_Request $request ): WP_REST_Response {
        $ad_id = (int) $request->get_param( 'ad_id' );
        if ( Ace_Ads_Manager::POST_TYPE !== get_post_type( $ad_id ) ) {
            return new WP_REST_Response( [ 'ok' => false ], 404 );
        }
        $post_id = (int) $request->get_param( 'post_id' );
        $slot    = (string) $request->get_param( 'slot' );
        $day     = gmdate( 'Y-m-d' );

        $counts = (array) get_post_meta( $ad_id, Ace_Ads_Manager::META_CLICKS, true );
        $counts[ $day ] = (int) ( $counts[ $day ] ?? 0 ) + 1;
        $counts = array_slice( $counts, -90, null, true );
        update_post_meta( $ad_id, Ace_Ads_Manager::META_CLICKS, $counts );

        /**
         * A tracked ad click. Sites attribute it however they like: e.g. a ppnews
         * mu-plugin forwards it to bet-api's click log with the post's byline.
         *
         * @param int    $ad_id
         * @param int    $post_id  Post the ad was shown on (0 on archives).
         * @param string $slot
         * @param array  $extra    Request data (referer, user agent).
         */
        do_action( 'ace_ads_click', $ad_id, $post_id, $slot, [
            'referer'    => (string) $request->get_header( 'referer' ),
            'user_agent' => (string) $request->get_header( 'user_agent' ),
        ] );

        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    public static function clicks_total( int $ad_id, int $days = 30 ): int {
        $counts = (array) get_post_meta( $ad_id, Ace_Ads_Manager::META_CLICKS, true );
        $since  = gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
        $total  = 0;
        foreach ( $counts as $day => $count ) {
            if ( $day >= $since ) {
                $total += (int) $count;
            }
        }
        return $total;
    }
}
