<?php
/**
 * WP-CLI: migrate hand-pasted offer markup to Ad blocks.
 *
 *   wp ace-ads find --pattern=<text> [--regex] [--post-type=<type>] [--format=<format>]
 *       Read-only. Lists every post whose content contains the pattern, with match counts.
 *
 *   wp ace-ads replace --pattern=<text> (--with=<text> | --ad=<id> --slot=<slot>) [--regex] [--post-type=<type>] [--ids=<csv>] [--yes]
 *       Replaces each match with plain text, or with an Ad block referencing one ad.
 *       Dry run unless --yes. Runs under one Ace Revisions batch id when that plugin is active.
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Ads_CLI {

    private function matches( array $assoc ): array {
        global $wpdb;
        if ( empty( $assoc['pattern'] ) ) {
            WP_CLI::error( 'Pass --pattern=<text>.' );
        }
        $pattern   = (string) $assoc['pattern'];
        $regex     = ! empty( $assoc['regex'] );
        $post_type = $assoc['post-type'] ?? 'post';
        $where     = $wpdb->prepare( 'post_type = %s AND post_status NOT IN ("trash","auto-draft")', $post_type );
        if ( ! empty( $assoc['ids'] ) ) {
            $ids    = implode( ',', array_map( 'intval', explode( ',', $assoc['ids'] ) ) );
            $where .= " AND ID IN ({$ids})";
        }
        $where .= $regex
            ? $wpdb->prepare( ' AND post_content REGEXP %s', $pattern )
            : $wpdb->prepare( ' AND post_content LIKE %s', '%' . $wpdb->esc_like( $pattern ) . '%' );

        $rows = $wpdb->get_results( "SELECT ID, post_title, post_content, post_status FROM {$wpdb->posts} WHERE {$where} ORDER BY ID DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $out  = [];
        foreach ( $rows as $row ) {
            $count = $regex ? preg_match_all( '/' . str_replace( '/', '\/', $pattern ) . '/u', $row->post_content ) : substr_count( $row->post_content, $pattern );
            if ( $count ) {
                $out[] = [ 'ID' => (int) $row->ID, 'status' => $row->post_status, 'title' => $row->post_title, 'matches' => (int) $count, 'content' => $row->post_content ];
            }
        }
        return [ $out, $pattern, $regex ];
    }

    /**
     * List posts containing a pattern. Read-only.
     */
    public function find( array $args, array $assoc ): void {
        [ $rows ] = $this->matches( $assoc );
        WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, [ 'ID', 'status', 'title', 'matches' ] );
        WP_CLI::log( sprintf( '%d posts, %d matches.', count( $rows ), array_sum( array_column( $rows, 'matches' ) ) ) );
    }

    /**
     * Replace a pattern with text or an Ad block. Dry run unless --yes.
     */
    public function replace( array $args, array $assoc ): void {
        [ $rows, $pattern, $regex ] = $this->matches( $assoc );
        if ( isset( $assoc['ad'] ) ) {
            $ad_id = (int) $assoc['ad'];
            if ( Ace_Ads_Manager::POST_TYPE !== get_post_type( $ad_id ) ) {
                WP_CLI::error( "Ad {$ad_id} not found." );
            }
            $slot        = sanitize_key( $assoc['slot'] ?? 'in-content' );
            $replacement = sprintf( '<!-- wp:ace-ads/slot {"slot":"%s","adId":%d} /-->', $slot, $ad_id );
        } elseif ( isset( $assoc['with'] ) ) {
            $replacement = (string) $assoc['with'];
        } else {
            WP_CLI::error( 'Pass --with=<text> or --ad=<id>.' );
        }
        $apply = ! empty( $assoc['yes'] );
        if ( $apply && class_exists( 'Ace_Revisions' ) ) {
            Ace_Revisions::set_batch( 'ads_replace_' . gmdate( 'Ymd_His' ) );
        }
        $touched = 0;
        foreach ( $rows as $row ) {
            $new = $regex
                ? preg_replace( '/' . str_replace( '/', '\/', $pattern ) . '/u', $replacement, $row['content'] )
                : str_replace( $pattern, $replacement, $row['content'] );
            if ( $new === $row['content'] ) {
                continue;
            }
            WP_CLI::log( sprintf( '%s #%d "%s" (%d matches)', $apply ? 'Updating' : 'Would update', $row['ID'], $row['title'], $row['matches'] ) );
            if ( $apply ) {
                $result = wp_update_post( [ 'ID' => $row['ID'], 'post_content' => $new ], true );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::warning( "#{$row['ID']}: " . $result->get_error_message() );
                    continue;
                }
            }
            $touched++;
        }
        WP_CLI::success( sprintf( '%s %d posts.', $apply ? 'Updated' : 'Would update', $touched ) . ( $apply ? '' : ' Re-run with --yes to apply.' ) );
    }
}
