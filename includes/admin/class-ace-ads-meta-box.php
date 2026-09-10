<?php
/**
 * Ad edit screen: offer fields and the placement rules editor.
 *
 * Rules are edited by src/ad-editor.js and posted back as JSON in one hidden
 * field, then sanitised through Ace_Ads_Rules::sanitise_rules().
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Ads_Meta_Box {

    const NONCE = 'ace_ads_meta_box';

    public function __construct() {
        add_action( 'add_meta_boxes_' . Ace_Ads_Manager::POST_TYPE, [ $this, 'add' ] );
        add_action( 'save_post_' . Ace_Ads_Manager::POST_TYPE, [ $this, 'save' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_filter( 'manage_' . Ace_Ads_Manager::POST_TYPE . '_posts_columns', [ $this, 'columns' ] );
        add_action( 'manage_' . Ace_Ads_Manager::POST_TYPE . '_posts_custom_column', [ $this, 'column' ], 10, 2 );
    }

    public function add(): void {
        add_meta_box( 'ace-ad-offer', __( 'Offer', 'ace-ads-manager' ), [ $this, 'render_offer' ], Ace_Ads_Manager::POST_TYPE, 'normal', 'high' );
        add_meta_box( 'ace-ad-rules', __( 'Placement rules', 'ace-ads-manager' ), [ $this, 'render_rules' ], Ace_Ads_Manager::POST_TYPE, 'normal', 'default' );
    }

    public function enqueue( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || Ace_Ads_Manager::POST_TYPE !== get_current_screen()->post_type ) {
            return;
        }
        wp_enqueue_style( 'ace-ads-admin', ACE_ADS_URL . 'assets/css/admin.css', [], ACE_ADS_VERSION );
        $asset_file = ACE_ADS_PATH . 'build/ad-editor.asset.php';
        $asset      = file_exists( $asset_file ) ? include $asset_file : [ 'dependencies' => [], 'version' => ACE_ADS_VERSION ];
        wp_enqueue_script( 'ace-ads-editor', ACE_ADS_URL . 'build/ad-editor.js', $asset['dependencies'] ?? [], $asset['version'] ?? ACE_ADS_VERSION, true );

        $taxonomies = [];
        foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $taxonomy ) {
            $taxonomies[ $taxonomy->name ] = $taxonomy->labels->name;
        }
        $post_types = [];
        foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $type ) {
            $post_types[ $type->name ] = $type->labels->singular_name;
        }
        wp_localize_script( 'ace-ads-editor', 'ace_ads_editor', [
            'rest_url'     => esc_url_raw( rest_url() ),
            'nonce'        => wp_create_nonce( 'wp_rest' ),
            'slots'        => Ace_Ads_Manager::slots(),
            'target_types' => Ace_Ads_Rules::target_types(),
            'taxonomies'   => $taxonomies,
            'post_types'   => $post_types,
        ] );
    }

    public function render_offer( WP_Post $post ): void {
        wp_nonce_field( self::NONCE, self::NONCE );
        $meta = static function ( string $key ) use ( $post ) {
            return (string) get_post_meta( $post->ID, $key, true );
        };
        $image_mode = $meta( Ace_Ads_Manager::META_IMAGE_MODE ) ?: 'background';
        ?>
        <p class="description"><?php esc_html_e( 'The ad title is the headline; the editor content above is the copy. The featured image is optional and used as a background by default.', 'ace-ads-manager' ); ?></p>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="ace-ad-offer-code"><?php esc_html_e( 'Offer code', 'ace-ads-manager' ); ?></label></th>
                <td><input type="text" id="ace-ad-offer-code" name="ace_ad[offer_code]" class="regular-text" value="<?php echo esc_attr( $meta( Ace_Ads_Manager::META_OFFER_CODE ) ); ?>"></td>
            </tr>
            <tr>
                <th><label for="ace-ad-link"><?php esc_html_e( 'Link', 'ace-ads-manager' ); ?></label></th>
                <td><input type="url" id="ace-ad-link" name="ace_ad[link]" class="large-text" value="<?php echo esc_attr( $meta( Ace_Ads_Manager::META_LINK ) ); ?>"></td>
            </tr>
            <tr>
                <th><label for="ace-ad-cta"><?php esc_html_e( 'Call to action', 'ace-ads-manager' ); ?></label></th>
                <td><input type="text" id="ace-ad-cta" name="ace_ad[cta]" class="regular-text" value="<?php echo esc_attr( $meta( Ace_Ads_Manager::META_CTA ) ); ?>" placeholder="<?php echo esc_attr( Ace_Ads_Manager_Settings::get( 'cta_label', '' ) ); ?>"></td>
            </tr>
            <tr>
                <th><label for="ace-ad-start"><?php esc_html_e( 'Start', 'ace-ads-manager' ); ?></label></th>
                <td><input type="datetime-local" id="ace-ad-start" name="ace_ad[start]" value="<?php echo esc_attr( $meta( Ace_Ads_Manager::META_START ) ); ?>"> <span class="description"><?php esc_html_e( 'Leave empty to start immediately.', 'ace-ads-manager' ); ?></span></td>
            </tr>
            <tr>
                <th><label for="ace-ad-end"><?php esc_html_e( 'End', 'ace-ads-manager' ); ?></label></th>
                <td><input type="datetime-local" id="ace-ad-end" name="ace_ad[end]" value="<?php echo esc_attr( $meta( Ace_Ads_Manager::META_END ) ); ?>"> <span class="description"><?php esc_html_e( 'Leave empty to run until unpublished.', 'ace-ads-manager' ); ?></span></td>
            </tr>
            <tr>
                <th><label for="ace-ad-image-mode"><?php esc_html_e( 'Image', 'ace-ads-manager' ); ?></label></th>
                <td>
                    <select id="ace-ad-image-mode" name="ace_ad[image_mode]">
                        <option value="background" <?php selected( $image_mode, 'background' ); ?>><?php esc_html_e( 'Background behind the copy', 'ace-ads-manager' ); ?></option>
                        <option value="above" <?php selected( $image_mode, 'above' ); ?>><?php esc_html_e( 'Above the copy', 'ace-ads-manager' ); ?></option>
                        <option value="none" <?php selected( $image_mode, 'none' ); ?>><?php esc_html_e( 'Do not show', 'ace-ads-manager' ); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_rules( WP_Post $post ): void {
        $rules = (array) get_post_meta( $post->ID, Ace_Ads_Manager::META_RULES, true );
        ?>
        <p class="description"><?php esc_html_e( 'Each rule places this ad into a slot when every target on the rule matches. The Ad block resolves the slot wherever it sits; the in-content slot is injected automatically on the post types chosen in settings.', 'ace-ads-manager' ); ?></p>
        <div id="ace-ad-rules-app" class="ace-ad-rules"></div>
        <textarea id="ace-ad-rules-json" name="ace_ad_rules_json" class="ace-ad-rules-json" rows="4" hidden><?php echo esc_textarea( wp_json_encode( array_values( $rules ) ) ); ?></textarea>
        <p><a href="#" class="ace-ad-rules-toggle-json"><?php esc_html_e( 'Edit as JSON', 'ace-ads-manager' ); ?></a></p>
        <?php
    }

    public function save( int $post_id, WP_Post $post ): void {
        if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
            return;
        }
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        $fields = isset( $_POST['ace_ad'] ) && is_array( $_POST['ace_ad'] ) ? wp_unslash( $_POST['ace_ad'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $map    = [
            'offer_code' => [ Ace_Ads_Manager::META_OFFER_CODE, 'sanitize_text_field' ],
            'link'       => [ Ace_Ads_Manager::META_LINK, 'esc_url_raw' ],
            'cta'        => [ Ace_Ads_Manager::META_CTA, 'sanitize_text_field' ],
            'start'      => [ Ace_Ads_Manager::META_START, [ __CLASS__, 'sanitise_datetime' ] ],
            'end'        => [ Ace_Ads_Manager::META_END, [ __CLASS__, 'sanitise_datetime' ] ],
            'image_mode' => [ Ace_Ads_Manager::META_IMAGE_MODE, [ __CLASS__, 'sanitise_image_mode' ] ],
        ];
        foreach ( $map as $field => [ $key, $sanitiser ] ) {
            $value = call_user_func( $sanitiser, (string) ( $fields[ $field ] ?? '' ) );
            if ( '' === $value ) {
                delete_post_meta( $post_id, $key );
            } else {
                update_post_meta( $post_id, $key, $value );
            }
        }
        if ( isset( $_POST['ace_ad_rules_json'] ) ) {
            $decoded = json_decode( wp_unslash( $_POST['ace_ad_rules_json'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            update_post_meta( $post_id, Ace_Ads_Manager::META_RULES, Ace_Ads_Rules::sanitise_rules( is_array( $decoded ) ? $decoded : [] ) );
        }
    }

    public static function sanitise_datetime( string $value ): string {
        $value = sanitize_text_field( $value );
        return ( $value && strtotime( $value ) ) ? gmdate( 'Y-m-d\TH:i', strtotime( $value ) ) : '';
    }

    public static function sanitise_image_mode( string $value ): string {
        return in_array( $value, [ 'background', 'above', 'none' ], true ) ? $value : 'background';
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
                    esc_html( $start ?: '—' ),
                    esc_html( $end ?: '—' )
                );
                break;
            case 'ace_rules':
                $rules = (array) get_post_meta( $post_id, Ace_Ads_Manager::META_RULES, true );
                printf(
                    '<a href="%s">%s</a>',
                    esc_url( admin_url( 'edit.php?post_type=ace_ad&page=ace-ads-overview&ad=' . $post_id ) ),
                    esc_html( sprintf( _n( '%d rule', '%d rules', count( $rules ), 'ace-ads-manager' ), count( $rules ) ) )
                );
                break;
            case 'ace_clicks':
                echo (int) Ace_Ads_Tracking::clicks_total( $post_id, 30 );
                break;
        }
    }
}
