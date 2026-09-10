<?php
/**
 * Placements tab on Settings → Ads Manager: every placement across every ad,
 * filterable by ad, plus a "what shows here" preview for a post type or term.
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Ads_Overview {

    public function __construct() {
        add_action( 'ace_ads_settings_tab_content', [ $this, 'render' ] );
    }

    public static function url( int $ad_id = 0 ): string {
        $url = Ace_Ads_Manager_Admin::url( 'overview' );
        return $ad_id ? add_query_arg( 'ad', $ad_id, admin_url( 'options-general.php?page=' . Ace_Ads_Manager_Admin::PAGE ) ) . '#overview' : $url;
    }

    private function rows( int $only_ad = 0 ): array {
        $rows = [];
        $args = [
            'post_type'      => Ace_Ads_Manager::POST_TYPE,
            'post_status'    => [ 'publish', 'draft', 'pending', 'future' ],
            'posts_per_page' => 500,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        if ( $only_ad ) {
            $args['include'] = [ $only_ad ];
        }
        foreach ( get_posts( $args ) as $ad ) {
            foreach ( (array) get_post_meta( $ad->ID, Ace_Ads_Manager::META_RULES, true ) as $index => $rule ) {
                $rows[] = [ 'ad' => $ad, 'rule' => $rule, 'index' => (int) $index, 'live' => Ace_Ads_Manager::is_live( $ad->ID ) ];
            }
        }
        usort( $rows, static function ( $a, $b ) {
            return [ $a['rule']['slot'], -$a['rule']['priority'] ] <=> [ $b['rule']['slot'], -$b['rule']['priority'] ];
        } );
        return $rows;
    }

    public function render( string $tab_id ): void {
        if ( 'overview' !== $tab_id ) {
            return;
        }
        $only_ad = isset( $_GET['ad'] ) ? (int) $_GET['ad'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $slots   = Ace_Ads_Manager::slots();
        $rows    = $this->rows( $only_ad );
        $ads     = get_posts( [ 'post_type' => Ace_Ads_Manager::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 500, 'orderby' => 'title', 'order' => 'ASC' ] );
        $preview = $this->preview_context();
        $page    = Ace_Ads_Manager_Admin::PAGE;
        ?>
        <form method="get" class="ace-filter-bar" action="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>#overview">
            <input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>">
            <label for="ace-ads-filter-ad"><?php esc_html_e( 'Show placements for', 'ace-ads-manager' ); ?></label>
            <select id="ace-ads-filter-ad" name="ad" onchange="this.form.submit()">
                <option value="0"><?php esc_html_e( 'All ads', 'ace-ads-manager' ); ?></option>
                <?php foreach ( $ads as $ad ) : ?>
                    <option value="<?php echo (int) $ad->ID; ?>" <?php selected( $only_ad, $ad->ID ); ?>><?php echo esc_html( $ad->post_title ); ?></option>
                <?php endforeach; ?>
            </select>
            <a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Ace_Ads_Manager::POST_TYPE ) ); ?>"><?php esc_html_e( 'Add new ad', 'ace-ads-manager' ); ?></a>
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
                <tr><td colspan="6"><?php esc_html_e( 'No placement rules yet. Open an ad and add a rule under Placement rules.', 'ace-ads-manager' ); ?></td></tr>
            <?php endif; ?>
            <?php foreach ( $rows as $row ) : ?>
                <tr class="<?php echo $row['live'] ? '' : 'ace-ads-row--inactive'; ?>">
                    <td><code><?php echo esc_html( $slots[ $row['rule']['slot'] ] ?? $row['rule']['slot'] ); ?></code></td>
                    <td><a href="<?php echo esc_url( get_edit_post_link( $row['ad']->ID ) ); ?>"><?php echo esc_html( $row['ad']->post_title ); ?></a> <small>#<?php echo (int) $row['index'] + 1; ?></small></td>
                    <td><?php echo esc_html( Ace_Ads_Rules::describe( $row['rule'] ) ); ?></td>
                    <td><?php echo (int) $row['rule']['priority']; ?></td>
                    <td><?php echo $row['live'] ? esc_html__( 'Live', 'ace-ads-manager' ) : esc_html( get_post_status_object( $row['ad']->post_status )->label ?? $row['ad']->post_status ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( Ace_Ads_Tracking::clicks_total( $row['ad']->ID, 30 ) ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h3><?php esc_html_e( 'What shows where', 'ace-ads-manager' ); ?></h3>
        <p class="description"><?php esc_html_e( 'Pick a post type and/or a term to see which ad each slot resolves to, using the same rules as the front end.', 'ace-ads-manager' ); ?></p>
        <form method="get" class="ace-filter-bar" action="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>#overview">
            <input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>">
            <input type="hidden" name="ad" value="<?php echo (int) $only_ad; ?>">
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
                <thead><tr><th><?php esc_html_e( 'Slot', 'ace-ads-manager' ); ?></th><th><?php esc_html_e( 'Resolved ad', 'ace-ads-manager' ); ?></th><th><?php esc_html_e( 'Because', 'ace-ads-manager' ); ?></th></tr></thead>
                <tbody>
                <?php foreach ( $slots as $slug => $label ) :
                    $resolved = Ace_Ads_Rules::resolve( $slug, $preview + [ 'slot' => $slug ] ); ?>
                    <tr>
                        <td><code><?php echo esc_html( $label ); ?></code></td>
                        <td><?php echo $resolved ? '<a href="' . esc_url( get_edit_post_link( $resolved['ad_id'] ) ) . '">' . esc_html( get_the_title( $resolved['ad_id'] ) ) . '</a>' : '<em>' . esc_html__( 'nothing', 'ace-ads-manager' ) . '</em>'; ?></td>
                        <td><?php echo $resolved ? esc_html( sprintf( __( 'rule %1$d: %2$s (priority %3$d)', 'ace-ads-manager' ), (int) $resolved['index'] + 1, Ace_Ads_Rules::describe( $resolved['rule'] ), (int) $resolved['rule']['priority'] ) ) : ''; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }

    private function preview_context(): ?array {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $type = isset( $_GET['preview_type'] ) ? sanitize_key( wp_unslash( $_GET['preview_type'] ) ) : '';
        $term = isset( $_GET['preview_term'] ) ? preg_replace( '/[^a-z0-9_\-:]/i', '', wp_unslash( $_GET['preview_term'] ) ) : '';
        // phpcs:enable
        if ( ! $type && ! $term ) {
            return null;
        }
        return [
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
    }
}
