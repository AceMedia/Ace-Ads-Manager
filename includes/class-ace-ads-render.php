<?php
/**
 * Rendering: ad markup, the Ad slot block, in-content injection, and the
 * parent-block / loop-index context the rules can target.
 *
 * Ads render as real HTML (text + link, image optional as a background) so they
 * are indexable content and are not stripped by ad blockers.
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Ads_Render {

    /** @var string[] Names of blocks currently rendering, outermost first. */
    private static $stack = [];

    /** @var array<string,int> Render counter per slot + query, for loop_index rules. */
    private static $loop_counts = [];

    public function __construct() {
        add_filter( 'render_block_data', [ $this, 'push_block' ], 1 );
        add_filter( 'render_block', [ $this, 'pop_block' ], PHP_INT_MAX, 2 );
        add_filter( 'the_content', [ $this, 'inject_in_content' ], 20 );
    }

    public function push_block( array $parsed ): array {
        self::$stack[] = (string) ( $parsed['blockName'] ?? '' );
        return $parsed;
    }

    public function pop_block( string $html, array $block ): string {
        array_pop( self::$stack );
        return $html;
    }

    /**
     * Nearest ancestor block name for the block currently rendering (the top of
     * the stack is the block itself).
     */
    public static function parent_block(): string {
        $count = count( self::$stack );
        return $count >= 2 ? self::$stack[ $count - 2 ] : '';
    }

    /**
     * Context for one slot render: request context, overridden by the looped
     * post when inside a Query Loop, plus loop index and parent block.
     */
    public static function context_for( string $slot, array $block_context = [] ): array {
        $context = Ace_Ads_Rules::request_context();
        $query_id = (string) ( $block_context['queryId'] ?? '' );
        $post_id  = (int) ( $block_context['postId'] ?? 0 );

        if ( $post_id && ( '' !== $query_id || $post_id !== (int) $context['post_id'] ) ) {
            $context = array_merge( $context, Ace_Ads_Rules::post_context( $post_id ), [ 'view' => 'loop' ] );
            $counter = $slot . '|' . $query_id;
            self::$loop_counts[ $counter ] = ( self::$loop_counts[ $counter ] ?? 0 ) + 1;
            $context['loop_index'] = self::$loop_counts[ $counter ];
        }
        $context['parent_block'] = self::parent_block();
        $context['slot']         = $slot;
        return apply_filters( 'ace_ads_slot_context', $context, $slot, $block_context );
    }

    /**
     * Resolve and render a slot. $ad_id forces a specific ad (block set to one ad).
     */
    public static function slot( string $slot, array $block_context = [], int $ad_id = 0 ): string {
        $context = self::context_for( $slot, $block_context );
        if ( $ad_id ) {
            $resolved = Ace_Ads_Manager::is_live( $ad_id ) ? [ 'ad_id' => $ad_id, 'rule' => [], 'index' => -1 ] : null;
        } else {
            $resolved = Ace_Ads_Rules::resolve( $slot, $context );
        }
        if ( ! $resolved ) {
            return (string) apply_filters( 'ace_ads_empty_slot', '', $slot, $context );
        }
        $context['rule_index'] = (int) ( $resolved['index'] ?? -1 );
        return self::ad( (int) $resolved['ad_id'], $slot, $context );
    }

    public static function ad( int $ad_id, string $slot = '', array $context = [] ): string {
        $ad = get_post( $ad_id );
        if ( ! $ad || Ace_Ads_Manager::POST_TYPE !== $ad->post_type ) {
            return '';
        }
        $link       = (string) get_post_meta( $ad_id, Ace_Ads_Manager::META_LINK, true );
        $code       = (string) get_post_meta( $ad_id, Ace_Ads_Manager::META_OFFER_CODE, true );
        $cta        = (string) get_post_meta( $ad_id, Ace_Ads_Manager::META_CTA, true ) ?: (string) Ace_Ads_Manager_Settings::get( 'cta_label', '' );
        $image_mode = (string) get_post_meta( $ad_id, Ace_Ads_Manager::META_IMAGE_MODE, true ) ?: 'background';
        $image      = get_the_post_thumbnail_url( $ad_id, 'large' );
        $copy       = apply_filters( 'the_content', $ad->post_content );
        $classes    = array_filter( [ 'ace-ad', 'ace-ad--' . sanitize_html_class( $slot ?: 'inline' ), 'ace-ad--image-' . $image_mode, (string) Ace_Ads_Manager_Settings::get( 'wrapper_class', '' ) ] );
        $rel_mode   = (string) get_post_meta( $ad_id, Ace_Ads_Manager::META_REL, true );
        $nofollow   = 'nofollow' === $rel_mode || ( 'follow' !== $rel_mode && Ace_Ads_Manager_Settings::get( 'nofollow', 1 ) );
        $rel        = $nofollow ? 'nofollow sponsored noopener' : 'noopener';
        $target     = Ace_Ads_Manager_Settings::get( 'new_tab', 1 ) ? ' target="_blank"' : '';
        $rule_index = (int) ( $context['rule_index'] ?? -1 );

        $style = ( $image && 'background' === $image_mode ) ? ' style="--ace-ad-image:url(' . esc_url( $image ) . ')"' : '';

        $html  = '<aside class="' . esc_attr( implode( ' ', $classes ) ) . '" data-ace-ad="' . (int) $ad_id . '" data-ace-slot="' . esc_attr( $slot ) . '" data-ace-rule="' . $rule_index . '"' . $style . ' aria-label="' . esc_attr__( 'Advertisement', 'ace-ads-manager' ) . '">';
        if ( $image && 'above' === $image_mode ) {
            $html .= '<img class="ace-ad__image" src="' . esc_url( $image ) . '" alt="" loading="lazy" decoding="async">';
        }
        $html .= '<div class="ace-ad__body">';
        $html .= '<h3 class="ace-ad__title">' . esc_html( get_the_title( $ad ) ) . '</h3>';
        $html .= '<div class="ace-ad__copy">' . wp_kses_post( $copy ) . '</div>';
        if ( $code ) {
            $html .= '<p class="ace-ad__code">' . esc_html__( 'Code:', 'ace-ads-manager' ) . ' <strong>' . esc_html( $code ) . '</strong></p>';
        }
        if ( $link ) {
            $html .= '<a class="ace-ad__cta" href="' . esc_url( $link ) . '" rel="' . esc_attr( $rel ) . '"' . $target . '>' . esc_html( $cta ?: __( 'Find out more', 'ace-ads-manager' ) ) . '</a>';
        }
        $html .= '</div></aside>';

        /**
         * Final ad markup. Sites can restyle or wrap it (e.g. add a bet-link tracking attribute).
         */
        return (string) apply_filters( 'ace_ads_render_html', $html, $ad_id, $slot, $context );
    }

    /**
     * Insert the in-content slot after N paragraphs on singular views of chosen post types.
     */
    public function inject_in_content( string $content ): string {
        if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }
        $types = (array) Ace_Ads_Manager_Settings::get( 'in_content_post_types', [] );
        if ( ! in_array( get_post_type(), $types, true ) || ! isset( Ace_Ads_Manager::slots()['in-content'] ) ) {
            return $content;
        }
        if ( has_block( 'ace-ads/slot', get_the_ID() ) ) {
            return $content; // The author placed one by hand; do not double up.
        }
        $context  = self::context_for( 'in-content' );
        $resolved = Ace_Ads_Rules::resolve( 'in-content', $context );
        if ( ! $resolved ) {
            return $content;
        }
        $after = (int) ( $resolved['rule']['after_paragraphs'] ?? 0 ) ?: (int) Ace_Ads_Manager_Settings::get( 'in_content_paragraphs', 3 );
        $pieces = preg_split( '/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
        $total  = count( array_filter( $pieces, static function ( $piece ) {
            return 0 === strcasecmp( $piece, '</p>' );
        } ) );
        if ( $total < (int) Ace_Ads_Manager_Settings::get( 'in_content_min_paragraphs', 4 ) ) {
            return $content;
        }
        $context['rule_index'] = (int) ( $resolved['index'] ?? -1 );
        $html = self::ad( (int) $resolved['ad_id'], 'in-content', $context );
        if ( ! $html ) {
            return $content;
        }
        $out    = '';
        $count  = 0;
        $done   = false;
        foreach ( $pieces as $piece ) {
            $out .= $piece;
            if ( ! $done && 0 === strcasecmp( $piece, '</p>' ) ) {
                $count++;
                if ( $count >= $after ) {
                    $out .= $html;
                    $done = true;
                }
            }
        }
        return $done ? $out : $content . $html;
    }
}
