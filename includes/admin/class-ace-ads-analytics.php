<?php
/**
 * Analytics tab: impressions, viewable, clicks, CTR, uniques by ad, slot,
 * page, day and device for a date range, with CSV export.
 *
 * Everything reads the daily roll-up except uniques, which need raw events.
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Ads_Analytics {

    const EXPORT_ACTION = 'ace_ads_export';

    public function __construct() {
        add_action( 'ace_ads_settings_tab_content', [ $this, 'render' ] );
        add_action( 'admin_post_' . self::EXPORT_ACTION, [ $this, 'export' ] );
    }

    // ---------------------------------------------------------------- queries

    /**
     * Read the filters from the query string: from, to (Y-m-d), ad (id).
     */
    public static function filters(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $to   = isset( $_GET['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['to'] ) ? (string) $_GET['to'] : current_time( 'Y-m-d' );
        $from = isset( $_GET['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['from'] ) ? (string) $_GET['from'] : gmdate( 'Y-m-d', strtotime( $to ) - 29 * DAY_IN_SECONDS );
        $ad   = isset( $_GET['ad'] ) ? (int) $_GET['ad'] : 0;
        // phpcs:enable
        return [ 'from' => $from, 'to' => $to, 'ad' => $ad ];
    }

    private static function where( array $f, string $alias = '' ): array {
        global $wpdb;
        $p     = $alias ? $alias . '.' : '';
        $sql   = $wpdb->prepare( "{$p}day BETWEEN %s AND %s", $f['from'], $f['to'] );
        if ( $f['ad'] ) {
            $sql .= $wpdb->prepare( " AND {$p}ad_id = %d", $f['ad'] );
        }
        return [ $sql ];
    }

    public static function summary( array $f ): array {
        global $wpdb;
        [ $where ] = self::where( $f );
        $row = $wpdb->get_row( 'SELECT COALESCE(SUM(impressions),0) impressions, COALESCE(SUM(viewable),0) viewable, COALESCE(SUM(clicks),0) clicks FROM ' . Ace_Ads_Tracking::daily_table() . " WHERE {$where}", ARRAY_A ) ?: [ 'impressions' => 0, 'viewable' => 0, 'clicks' => 0 ];
        $uniques_sql = $wpdb->prepare( 'SELECT COUNT(DISTINCT visitor) FROM ' . Ace_Ads_Tracking::events_table() . ' WHERE created_at BETWEEN %s AND %s' . ( $f['ad'] ? $wpdb->prepare( ' AND ad_id = %d', $f['ad'] ) : '' ), get_gmt_from_date( $f['from'] . ' 00:00:00' ), get_gmt_from_date( $f['to'] . ' 23:59:59' ) );
        $row['uniques'] = (int) $wpdb->get_var( $uniques_sql );
        return array_map( 'intval', $row );
    }

    /**
     * Group the daily table by one column. $group: ad_id | slot | post_id | day | device | rule_index.
     */
    public static function breakdown( array $f, string $group, int $limit = 50 ): array {
        global $wpdb;
        if ( ! in_array( $group, [ 'ad_id', 'slot', 'post_id', 'day', 'device', 'rule_index' ], true ) ) {
            return [];
        }
        [ $where ] = self::where( $f );
        $order = 'day' === $group ? 'day ASC' : 'impressions DESC, clicks DESC';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT {$group} AS k, SUM(impressions) impressions, SUM(viewable) viewable, SUM(clicks) clicks FROM " . Ace_Ads_Tracking::daily_table() . " WHERE {$where} GROUP BY {$group} ORDER BY {$order} LIMIT %d",
            $limit
        ), ARRAY_A ) ?: [];
    }

    /**
     * Per ad × rule, so a rule's performance can be compared with a pinned placement.
     */
    public static function by_placement( array $f, int $limit = 100 ): array {
        global $wpdb;
        [ $where ] = self::where( $f );
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT ad_id, slot, rule_index, SUM(impressions) impressions, SUM(viewable) viewable, SUM(clicks) clicks FROM ' . Ace_Ads_Tracking::daily_table() . " WHERE {$where} GROUP BY ad_id, slot, rule_index ORDER BY impressions DESC LIMIT %d",
            $limit
        ), ARRAY_A ) ?: [];
    }

    public static function rate( int $part, int $whole ): string {
        return $whole ? number_format_i18n( 100 * $part / $whole, 2 ) . '%' : '—';
    }

    // ---------------------------------------------------------------- UI

    public function render( string $tab_id ): void {
        if ( 'analytics' !== $tab_id ) {
            return;
        }
        $f       = self::filters();
        $summary = self::summary( $f );
        $ads     = get_posts( [ 'post_type' => Ace_Ads_Manager::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 500, 'orderby' => 'title', 'order' => 'ASC' ] );
        $slots   = Ace_Ads_Manager::slots();
        $export  = wp_nonce_url( add_query_arg( [ 'action' => self::EXPORT_ACTION, 'from' => $f['from'], 'to' => $f['to'], 'ad' => $f['ad'] ], admin_url( 'admin-post.php' ) ), self::EXPORT_ACTION );
        ?>
        <form method="get" class="ace-filter-bar">
            <input type="hidden" name="page" value="<?php echo esc_attr( Ace_Ads_Manager_Admin::PAGE ); ?>">
            <label><?php esc_html_e( 'From', 'ace-ads-manager' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $f['from'] ); ?>"></label>
            <label><?php esc_html_e( 'To', 'ace-ads-manager' ); ?> <input type="date" name="to" value="<?php echo esc_attr( $f['to'] ); ?>"></label>
            <label><?php esc_html_e( 'Ad', 'ace-ads-manager' ); ?>
                <select name="ad">
                    <option value="0"><?php esc_html_e( 'All ads', 'ace-ads-manager' ); ?></option>
                    <?php foreach ( $ads as $ad ) : ?>
                        <option value="<?php echo (int) $ad->ID; ?>" <?php selected( $f['ad'], $ad->ID ); ?>><?php echo esc_html( $ad->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button button-primary" formaction="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>#analytics"><?php esc_html_e( 'Apply', 'ace-ads-manager' ); ?></button>
            <a class="button" href="<?php echo esc_url( $export ); ?>"><span class="dashicons dashicons-download" aria-hidden="true" style="vertical-align:text-bottom"></span> <?php esc_html_e( 'Export CSV', 'ace-ads-manager' ); ?></a>
        </form>

        <div class="ace-stat-grid">
            <?php
            $stats = [
                __( 'Impressions', 'ace-ads-manager' )   => number_format_i18n( $summary['impressions'] ),
                __( 'Viewable', 'ace-ads-manager' )      => number_format_i18n( $summary['viewable'] ),
                __( 'Viewability', 'ace-ads-manager' )   => self::rate( $summary['viewable'], $summary['impressions'] ),
                __( 'Clicks', 'ace-ads-manager' )        => number_format_i18n( $summary['clicks'] ),
                __( 'CTR', 'ace-ads-manager' )           => self::rate( $summary['clicks'], $summary['impressions'] ),
                __( 'Viewable CTR', 'ace-ads-manager' )  => self::rate( $summary['clicks'], $summary['viewable'] ),
                __( 'Unique visitors', 'ace-ads-manager' ) => number_format_i18n( $summary['uniques'] ),
            ];
            foreach ( $stats as $label => $value ) :
                ?>
                <div class="ace-stat"><span class="ace-stat__label"><?php echo esc_html( $label ); ?></span><span class="ace-stat__value"><?php echo esc_html( $value ); ?></span></div>
            <?php endforeach; ?>
        </div>

        <?php
        $this->table( __( 'By ad', 'ace-ads-manager' ), self::breakdown( $f, 'ad_id' ), static function ( $k ) {
            $title = get_the_title( (int) $k );
            return '<a href="' . esc_url( get_edit_post_link( (int) $k ) ) . '">' . esc_html( $title ?: '#' . $k ) . '</a>';
        } );
        $this->table( __( 'By placement (ad × rule)', 'ace-ads-manager' ), array_map( static function ( $r ) use ( $slots ) {
            $r['k'] = get_the_title( (int) $r['ad_id'] ) . ' → ' . ( $slots[ $r['slot'] ] ?? $r['slot'] ) . ' (' . ( -1 === (int) $r['rule_index'] ? __( 'pinned', 'ace-ads-manager' ) : sprintf( __( 'rule %d', 'ace-ads-manager' ), (int) $r['rule_index'] + 1 ) ) . ')';
            return $r;
        }, self::by_placement( $f ) ), 'esc_html' );
        $this->table( __( 'By slot', 'ace-ads-manager' ), self::breakdown( $f, 'slot' ), static function ( $k ) use ( $slots ) {
            return esc_html( $slots[ $k ] ?? ( $k ?: '—' ) );
        } );
        $this->table( __( 'By page (top 50)', 'ace-ads-manager' ), self::breakdown( $f, 'post_id' ), static function ( $k ) {
            if ( ! (int) $k ) {
                return '<em>' . esc_html__( 'Archives and other views', 'ace-ads-manager' ) . '</em>';
            }
            return '<a href="' . esc_url( (string) get_permalink( (int) $k ) ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( (int) $k ) ?: '#' . $k ) . '</a>';
        } );
        $this->table( __( 'By device', 'ace-ads-manager' ), self::breakdown( $f, 'device' ), 'esc_html' );
        $this->table( __( 'By day', 'ace-ads-manager' ), self::breakdown( $f, 'day', 400 ), static function ( $k ) {
            return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $k ) ) );
        } );
    }

    private function table( string $title, array $rows, callable $label ): void {
        ?>
        <h3><?php echo esc_html( $title ); ?></h3>
        <table class="widefat striped ace-ads-table">
            <thead><tr>
                <th></th>
                <th><?php esc_html_e( 'Impressions', 'ace-ads-manager' ); ?></th>
                <th><?php esc_html_e( 'Viewable', 'ace-ads-manager' ); ?></th>
                <th><?php esc_html_e( 'Viewability', 'ace-ads-manager' ); ?></th>
                <th><?php esc_html_e( 'Clicks', 'ace-ads-manager' ); ?></th>
                <th><?php esc_html_e( 'CTR', 'ace-ads-manager' ); ?></th>
            </tr></thead>
            <tbody>
            <?php if ( empty( $rows ) ) : ?>
                <tr><td colspan="6"><?php esc_html_e( 'No data for this range.', 'ace-ads-manager' ); ?></td></tr>
            <?php endif; ?>
            <?php foreach ( $rows as $row ) : ?>
                <tr>
                    <td><?php echo wp_kses_post( $label( (string) $row['k'] ) ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( (int) $row['impressions'] ) ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( (int) $row['viewable'] ) ); ?></td>
                    <td><?php echo esc_html( self::rate( (int) $row['viewable'], (int) $row['impressions'] ) ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( (int) $row['clicks'] ) ); ?></td>
                    <td><?php echo esc_html( self::rate( (int) $row['clicks'], (int) $row['impressions'] ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function export(): void {
        if ( ! current_user_can( Ace_Ads_Manager_Admin::CAP ) ) {
            wp_die( esc_html__( 'You do not have permission to export.', 'ace-ads-manager' ) );
        }
        check_admin_referer( self::EXPORT_ACTION );
        global $wpdb;
        $f = self::filters();
        [ $where ] = self::where( $f );
        $rows = $wpdb->get_results( 'SELECT day, ad_id, slot, rule_index, post_id, device, impressions, viewable, clicks FROM ' . Ace_Ads_Tracking::daily_table() . " WHERE {$where} ORDER BY day, ad_id, slot", ARRAY_A );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="ace-ads-' . $f['from'] . '-to-' . $f['to'] . '.csv"' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'day', 'ad_id', 'ad', 'slot', 'placement', 'post_id', 'page', 'device', 'impressions', 'viewable', 'clicks', 'ctr' ] );
        foreach ( $rows as $r ) {
            fputcsv( $out, [
                $r['day'],
                $r['ad_id'],
                get_the_title( (int) $r['ad_id'] ),
                $r['slot'],
                -1 === (int) $r['rule_index'] ? 'pinned' : 'rule ' . ( (int) $r['rule_index'] + 1 ),
                $r['post_id'],
                (int) $r['post_id'] ? get_the_title( (int) $r['post_id'] ) : '',
                $r['device'],
                $r['impressions'],
                $r['viewable'],
                $r['clicks'],
                (int) $r['impressions'] ? round( 100 * $r['clicks'] / $r['impressions'], 2 ) : 0,
            ] );
        }
        fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }
}
