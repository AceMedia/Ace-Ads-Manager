<?php
/**
 * Ad edit screen: loads the block-editor sidebar panels (Offer, Placement
 * rules) and adds the list columns. Fields save as post meta over REST, so
 * there is no form handling here.
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Ads_Meta_Box {

    public function __construct() {
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue' ] );
        add_filter( 'manage_' . Ace_Ads_Manager::POST_TYPE . '_posts_columns', [ $this, 'columns' ] );
        add_action( 'manage_' . Ace_Ads_Manager::POST_TYPE . '_posts_custom_column', [ $this, 'column' ], 10, 2 );
    }

    public function enqueue(): void {
        $screen = get_current_screen();
        if ( ! $screen || Ace_Ads_Manager::POST_TYPE !== $screen->post_type ) {
            return;
        }
        $asset_file = ACE_ADS_PATH . 'build/ad-editor.asset.php';
        $asset      = file_exists( $asset_file ) ? include $asset_file : [ 'dependencies' => [], 'version' => ACE_ADS_VERSION ];
        wp_enqueue_script( 'ace-ads-editor', ACE_ADS_URL . 'build/ad-editor.js', $asset['dependencies'] ?? [], $asset['version'] ?? ACE_ADS_VERSION, true );
        wp_enqueue_style( 'ace-ads-admin', ACE_ADS_URL . 'assets/css/admin.css', [], ACE_ADS_VERSION );

        $taxonomies = [];
        foreach ( get_taxonomies( [ 'public' => true, 'show_in_rest' => true ], 'objects' ) as $taxonomy ) {
            $taxonomies[ $taxonomy->name ] = $taxonomy->labels->name;
        }
        $post_types = [];
        foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $type ) {
            if ( Ace_Ads_Manager::POST_TYPE !== $type->name ) {
                $post_types[ $type->name ] = $type->labels->singular_name;
            }
        }
        wp_add_inline_script( 'ace-ads-editor', 'window.ace_ads_editor=' . wp_json_encode( [
            'slots'           => Ace_Ads_Manager::slots(),
            'target_types'    => Ace_Ads_Rules::target_types(),
            'taxonomies'      => $taxonomies,
            'post_types'      => $post_types,
            'cta_default'     => (string) Ace_Ads_Manager_Settings::get( 'cta_label', '' ),
            'guide_url'       => Ace_Ads_Manager_Admin::url( 'guide', 'guide-ads' ),
            'rules_guide_url' => Ace_Ads_Manager_Admin::url( 'guide', 'guide-rules' ),
            'overview_url'    => Ace_Ads_Overview::url(),
        ] ) . ';', 'before' );
    }

    public function columns( array $columns ): array {
        $out = [];
        foreach ( $columns as $key => $label ) {
            $out[ $key ] = $label;
            if ( 'title' === $key ) {
                $out['ace_offer']  = __( 'Offer code', 'ace-ads-manager' );
                $out['ace_window'] = __( 'Window', 'ace-ads-manager' );
                $out['ace_rules']  = __( 'Placements', 'ace-ads-manager' );
                $out['ace_clicks'] = __( 'Clicks (30d)', 'ace-ads-manager' );
            }
        }
        return $out;
    }

    public function column( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'ace_offer':
                echo esc_html( (string) get_post_meta( $post_id, Ace_Ads_Manager::META_OFFER_CODE, true ) );
                break;
            case 'ace_window':
                $start = (string) get_post_meta( $post_id, Ace_Ads_Manager::META_START, true );
                $end   = (string) get_post_meta( $post_id, Ace_Ads_Manager::META_END, true );
                $live  = Ace_Ads_Manager::is_live( $post_id );
                printf(
                    '<span class="ace-ad-live ace-ad-live--%1$s">%2$s</span><br><small>%3$s → %4$s</small>',
                    $live ? 'yes' : 'no',
                    $live ? esc_html__( 'Live', 'ace-ads-manager' ) : esc_html__( 'Not live', 'ace-ads-manager' ),
                    esc_html( $start ? str_replace( 'T', ' ', $start ) : '—' ),
                    esc_html( $end ? str_replace( 'T', ' ', $end ) : '—' )
                );
                break;
            case 'ace_rules':
                $rules = (array) get_post_meta( $post_id, Ace_Ads_Manager::META_RULES, true );
                printf(
                    '<a href="%s">%s</a>',
                    esc_url( Ace_Ads_Overview::url( $post_id ) ),
                    esc_html( sprintf( _n( '%d rule', '%d rules', count( $rules ), 'ace-ads-manager' ), count( $rules ) ) )
                );
                break;
            case 'ace_clicks':
                echo esc_html( number_format_i18n( Ace_Ads_Tracking::clicks_total( $post_id, 30 ) ) );
                break;
        }
    }
}
