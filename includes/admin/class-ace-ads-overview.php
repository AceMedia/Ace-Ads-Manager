<?php
/**
 * Overview screen: every placement across every ad, filterable by ad, plus a
 * "what shows here" preview for a chosen post type or term.
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Ads_Overview {

    const PAGE = 'ace-ads-overview';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'ace_ads_settings_sidebar', [ $this, 'sidebar_link' ] );
    }

    public function menu(): void {
        add_submenu_page(
            'edit.php?post_type=' . Ace_Ads_Manager::POST_TYPE,
            __( 'Placements overview', 'ace-ads-manager' ),
            __( 'Overview', 'ace-ads-manager' ),
            'edit_posts',
            self::PAGE,
            [ $this, 'render' ],
            1
        );
    }

    public function sidebar_link(): void {
        printf(
            '<a href="%s" class="nav-tab"><span class="dashicons dashicons-visibility" aria-hidden="true"></span>%s</a>',
            esc_url( admin_url( 'edit.php?post_type=ace_ad&page=' . self::PAGE ) ),
            esc_html__( 'Overview', 'ace-ads-manager' )
        );
    }

    public function enqueue( string $hook ): void {
        if ( false !== strpos( $hook, self::PAGE ) ) {
            wp_enqueue_style( 'ace-ads-admin', ACE_ADS_URL . 'assets/css/admin.css', [], ACE_ADS_VERSION );
        }
    }

    private function rows( int $only_ad = 0 ): array {
        $rows = [];
        $ads  = get_posts( [
            'post_type'      => Ace_Ads_Manager::POST_TYPE,
            'post_status'    => [ 'publish', 'draft', 'pending', 'future' ],
            'posts_per_page' => 500,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'include'        => $only_ad ? [ $only_ad ] : [],
        ] );
        foreach ( $ads as $ad ) {
            foreach ( (array) get_post_meta( $ad->ID, Ace_Ads_Manager::META_RULES, true ) as $rule ) {
                $rows[] = [ 'ad' => $ad, 'rule' => $rule, 'live' => Ace_Ads_Manager::is_live( $ad->ID ) ];
            }
        }
        usort( $rows, static function ( $a, $b ) {
            return [ $a['rule']['slot'], -$a['rule']['priority'] ] <=> [ $b['rule']['slot'], -$b['rule']['priority'] ];
        } );
        return $rows;
    }

    public function render(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'ace-ads-manager' ) );
        }
        $only_ad = isset( $_GET['ad'] ) ? (int) $_GET['ad'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $slots   = Ace_Ads_Manager::slots();
        $rows    = $this->rows( $only_ad );
        $ads     = get_posts( [ 'post_type' => Ace_Ads_Manager::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 500, 'orderby' => 'title', 'order' => 'ASC' ] );
        $preview = $this->preview_context();
        ?>
        <div class="wrap ace-redis-settings ace-ads-overview">
            <h1><?php esc_html_e( 'Placements overview', 'ace-ads-manager' ); ?></h1>

            <form method="get" class="ace-ads-filter">
                <input type="hidden" name="post_type" value="<?php echo esc_attr( Ace_Ads_Manager::POST_TYPE ); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
                <label for="ace-ads-filter-ad"><?php esc_html_e( 'Show placements for', 'ace-ads-manager' ); ?></label>
                <select id="ace-ads-filter-ad" name="ad" onchange="this.form.submit()">
                    <option value="0"><?php esc_html_e( 'All ads', 'ace-ads-manager' ); ?></option>
                    <?php foreach ( $ads as $ad ) : ?>
                        <option value="<?php echo (int) $ad->ID; ?>" <?php selected( $only_ad, $ad->ID ); ?>><?php echo esc_html( $ad->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <table class="widefat striped ace-ads-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Slot', 'ace-ads-manager' ); ?></th>
                        <th><?php esc_html_e( 'Ad', 'ace-ads-manager' ); ?></th>
                        <th><?php esc_html_e( 'Shows on', 'ace-ads-manager' ); ?></th>
                        <th><?php esc_html_e( 'Priority', 'ace-ads-manager' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'ace-ads-manager' ); ?></th>
                        <th><?php esc_html_e( 'Clicks (30d)', 'ace-ads-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $rows ) ) : ?>
                    <tr><td colspan="6"><?php esc_html_e( 'No placement rules yet. Add rules on an ad.', 'ace-ads-manager' ); ?></td></tr>
                <?php endif; ?>
                <?php foreach ( $rows as $row ) : ?>
                    <tr class="<?php echo $row['live'] ? '' : 'ace-ads-row--inactive'; ?>">
                        <td><code><?php echo esc_html( $slots[ $row['rule']['slot'] ] ?? $row['rule']['slot'] ); ?></code></td>
                        <td><a href="<?php echo esc_url( get_edit_post_link( $row['ad']->ID ) ); ?>"><?php echo esc_html( $row['ad']->post_title ); ?></a></td>
                        <td><?php echo esc_html( Ace_Ads_Rules::describe( $row['rule'] ) ); ?></td>
                        <td><?php echo (int) $row['rule']['priority']; ?></td>
                        <td><?php echo $row['live'] ? esc_html__( 'Live', 'ace-ads-manager' ) : esc_html( get_post_status_object( $row['ad']->post_status )->label ?? $row['ad']->post_status ); ?></td>
                        <td><?php echo (int) Ace_Ads_Tracking::clicks_total( $row['ad']->ID, 30 ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e( 'What shows where', 'ace-ads-manager' ); ?></h2>
            <form method="get" class="ace-ads-preview-form">
                <input type="hidden" name="post_type" value="<?php echo esc_attr( Ace_Ads_Manager::POST_TYPE ); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
                <label><?php esc_html_e( 'Single post of type', 'ace-ads-manager' ); ?>
                    <select name="preview_type">
                        <option value=""><?php esc_html_e( '— none —', 'ace-ads-manager' ); ?></option>
                        <?php foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $type ) : ?>
                            <option value="<?php echo esc_attr( $type->name ); ?>" <?php selected( $preview['post_type'] ?? '', $type->name ); ?>><?php echo esc_html( $type->labels->singular_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?php esc_html_e( 'in term', 'ace-ads-manager' ); ?>
                    <input type="text" name="preview_term" placeholder="taxonomy:id" value="<?php echo esc_attr( $preview['term'] ?? '' ); ?>" class="regular-text">
                </label>
                <button class="button"><?php esc_html_e( 'Preview', 'ace-ads-manager' ); ?></button>
            </form>
            <?php if ( $preview ) : ?>
                <table class="widefat striped ace-ads-table">
                    <thead><tr><th><?php esc_html_e( 'Slot', 'ace-ads-manager' ); ?></th><th><?php esc_html_e( 'Resolved ad', 'ace-ads-manager' ); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ( $slots as $slug => $label ) :
                        $resolved = Ace_Ads_Rules::resolve( $slug, $preview + [ 'slot' => $slug ] ); ?>
                        <tr>
                            <td><code><?php echo esc_html( $label ); ?></code></td>
                            <td><?php echo $resolved ? esc_html( get_the_title( $resolved['ad_id'] ) ) : '<em>' . esc_html__( 'nothing', 'ace-ads-manager' ) . '</em>'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Build a synthetic context from the preview form, or null when unset.
     */
    private function preview_context(): ?array {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $type = isset( $_GET['preview_type'] ) ? sanitize_key( wp_unslash( $_GET['preview_type'] ) ) : '';
        $term = isset( $_GET['preview_term'] ) ? preg_replace( '/[^a-z0-9_\-:]/i', '', wp_unslash( $_GET['preview_term'] ) ) : '';
        // phpcs:enable
        if ( ! $type && ! $term ) {
            return null;
        }
        $context = [
            'view'         => $type ? 'singular' : 'taxonomy',
            'post_type'    => $type,
            'post_id'      => 0,
            'terms'        => $term ? [ $term ] : [],
            'taxonomy'     => $term ? strtok( $term, ':' ) : '',
            'term'         => $term,
            'author'       => 0,
            'loop_index'   => 0,
            'parent_block' => '',
        ];
        return $context;
    }
}
