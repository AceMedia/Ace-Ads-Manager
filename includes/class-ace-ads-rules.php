<?php
/**
 * Placement rules: what an ad targets, the request context it is matched
 * against, and the resolver that picks one ad for a slot.
 *
 * A rule: [
 *   'slot'             => 'top',
 *   'priority'         => 10,
 *   'targets'          => [ [ 'type' => 'taxonomy_term', 'values' => [ 'category:12' ] ], ... ],  // AND across targets, OR within values
 *   'loop_index'       => '',      // '' any | '3' third item in a loop | '3n' every third
 *   'parent_block'     => '',      // e.g. core/group, core/query
 *   'after_paragraphs' => 0,       // in-content slot only; 0 = plugin default
 * ]
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Ads_Rules {

    /** Target types the rule editor offers, with human labels. */
    public static function target_types(): array {
        return apply_filters( 'ace_ads_target_types', [
            'everywhere'        => __( 'Everywhere', 'ace-ads-manager' ),
            'front'             => __( 'Front page', 'ace-ads-manager' ),
            'home'              => __( 'Blog index', 'ace-ads-manager' ),
            'post_type'         => __( 'Single post of type', 'ace-ads-manager' ),
            'post_type_archive' => __( 'Post type archive', 'ace-ads-manager' ),
            'taxonomy_term'     => __( 'Term (archive or posts in it)', 'ace-ads-manager' ),
            'taxonomy_archive'  => __( 'Any archive of taxonomy', 'ace-ads-manager' ),
            'post'              => __( 'Specific posts', 'ace-ads-manager' ),
            'author'            => __( 'Author archive', 'ace-ads-manager' ),
            'search'            => __( 'Search results', 'ace-ads-manager' ),
            'date'              => __( 'Date archive', 'ace-ads-manager' ),
            'not_found'         => __( '404 page', 'ace-ads-manager' ),
        ] );
    }

    public static function sanitise_rules( $rules ): array {
        $clean = [];
        foreach ( (array) $rules as $rule ) {
            if ( ! is_array( $rule ) ) {
                continue;
            }
            $targets = [];
            foreach ( (array) ( $rule['targets'] ?? [] ) as $target ) {
                $type = sanitize_key( $target['type'] ?? '' );
                if ( ! isset( self::target_types()[ $type ] ) ) {
                    continue;
                }
                $values = array_values( array_filter( array_map( static function ( $v ) {
                    return preg_replace( '/[^a-z0-9_\-:]/i', '', (string) $v );
                }, (array) ( $target['values'] ?? [] ) ) ) );
                $targets[] = [ 'type' => $type, 'values' => $values ];
            }
            $slot = sanitize_key( $rule['slot'] ?? '' );
            if ( '' === $slot ) {
                continue;
            }
            $clean[] = [
                'slot'             => $slot,
                'priority'         => (int) ( $rule['priority'] ?? 10 ),
                'targets'          => $targets ?: [ [ 'type' => 'everywhere', 'values' => [] ] ],
                'loop_index'       => preg_replace( '/[^0-9n]/', '', (string) ( $rule['loop_index'] ?? '' ) ),
                'parent_block'     => preg_replace( '/[^a-z0-9\/\-]/', '', (string) ( $rule['parent_block'] ?? '' ) ),
                'after_paragraphs' => max( 0, (int) ( $rule['after_paragraphs'] ?? 0 ) ),
            ];
        }
        return $clean;
    }

    /**
     * Describe the current main query once per request.
     */
    public static function request_context(): array {
        static $context = null;
        if ( null !== $context ) {
            return $context;
        }
        $context = [
            'view'      => 'other',
            'post_type' => '',
            'post_id'   => 0,
            'terms'     => [],
            'taxonomy'  => '',
            'term'      => '',
            'author'    => 0,
        ];
        if ( is_front_page() ) {
            $context['view'] = 'front';
        } elseif ( is_home() ) {
            $context['view'] = 'home';
        } elseif ( is_singular() ) {
            $context['view'] = 'singular';
        } elseif ( is_post_type_archive() ) {
            $context['view']      = 'post_type_archive';
            $context['post_type'] = (string) get_query_var( 'post_type' );
        } elseif ( is_tax() || is_category() || is_tag() ) {
            $term = get_queried_object();
            if ( $term instanceof WP_Term ) {
                $context['view']     = 'taxonomy';
                $context['taxonomy'] = $term->taxonomy;
                $context['term']     = $term->taxonomy . ':' . $term->term_id;
                $context['terms']    = [ $context['term'] ];
            }
        } elseif ( is_author() ) {
            $context['view']   = 'author';
            $context['author'] = (int) get_queried_object_id();
        } elseif ( is_search() ) {
            $context['view'] = 'search';
        } elseif ( is_date() ) {
            $context['view'] = 'date';
        } elseif ( is_404() ) {
            $context['view'] = 'not_found';
        }
        if ( is_singular() || is_front_page() ) {
            $post_id = (int) get_queried_object_id();
            if ( $post_id ) {
                $context = array_merge( $context, self::post_context( $post_id ) );
            }
        }
        $context = apply_filters( 'ace_ads_request_context', $context );
        return $context;
    }

    /**
     * Post-level context: type and every term across public taxonomies.
     */
    public static function post_context( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [];
        }
        $terms = [];
        foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
            $assigned = get_the_terms( $post_id, $taxonomy );
            if ( is_array( $assigned ) ) {
                foreach ( $assigned as $term ) {
                    $terms[] = $taxonomy . ':' . $term->term_id;
                    // Parents count too, so a rule on "Football" matches a post in "Premier League".
                    foreach ( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) as $ancestor ) {
                        $terms[] = $taxonomy . ':' . $ancestor;
                    }
                }
            }
        }
        return [
            'post_type' => $post->post_type,
            'post_id'   => $post_id,
            'terms'     => array_values( array_unique( $terms ) ),
            'author'    => (int) $post->post_author,
        ];
    }

    public static function target_matches( array $target, array $context ): bool {
        $values = $target['values'] ?? [];
        switch ( $target['type'] ) {
            case 'everywhere':
                return true;
            case 'front':
            case 'home':
            case 'search':
            case 'date':
            case 'not_found':
                return $context['view'] === $target['type'];
            case 'post_type':
                return in_array( $context['view'], [ 'singular', 'loop', 'front' ], true ) && in_array( $context['post_type'], $values, true );
            case 'post_type_archive':
                return 'post_type_archive' === $context['view'] && in_array( $context['post_type'], $values, true );
            case 'taxonomy_term':
                return (bool) array_intersect( $values, $context['terms'] );
            case 'taxonomy_archive':
                return 'taxonomy' === $context['view'] && in_array( $context['taxonomy'], $values, true );
            case 'post':
                return $context['post_id'] && in_array( (string) $context['post_id'], $values, true );
            case 'author':
                return 'author' === $context['view'] && ( empty( $values ) || in_array( (string) $context['author'], $values, true ) );
        }
        return (bool) apply_filters( 'ace_ads_target_matches', false, $target, $context );
    }

    public static function rule_matches( array $rule, array $context ): bool {
        if ( '' !== ( $rule['parent_block'] ?? '' ) && ( $context['parent_block'] ?? '' ) !== $rule['parent_block'] ) {
            return false;
        }
        $loop = (string) ( $rule['loop_index'] ?? '' );
        if ( '' !== $loop ) {
            $index = (int) ( $context['loop_index'] ?? 0 );
            if ( ! $index ) {
                return false;
            }
            if ( 'n' === substr( $loop, -1 ) ) {
                $every = (int) rtrim( $loop, 'n' );
                if ( $every < 1 || 0 !== $index % $every ) {
                    return false;
                }
            } elseif ( (int) $loop !== $index ) {
                return false;
            }
        }
        foreach ( $rule['targets'] as $target ) {
            if ( ! self::target_matches( $target, $context ) ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Every rule for a slot across published ads, cached by version. Date windows are
     * checked at resolve time so the cache never goes stale by the clock.
     *
     * @return array<int, array{ad_id:int, rule:array}>
     */
    public static function candidates( string $slot ): array {
        $key   = 'v' . Ace_Ads_Manager::version() . ':slot:' . $slot;
        $found = wp_cache_get( $key, Ace_Ads_Manager::CACHE_GROUP );
        if ( is_array( $found ) ) {
            return $found;
        }
        $found = [];
        $ads   = get_posts( [
            'post_type'        => Ace_Ads_Manager::POST_TYPE,
            'post_status'      => 'publish',
            'posts_per_page'   => 200,
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'suppress_filters' => false,
        ] );
        foreach ( $ads as $ad_id ) {
            foreach ( (array) get_post_meta( $ad_id, Ace_Ads_Manager::META_RULES, true ) as $rule ) {
                if ( ( $rule['slot'] ?? '' ) === $slot ) {
                    $found[] = [ 'ad_id' => (int) $ad_id, 'rule' => $rule ];
                }
            }
        }
        wp_cache_set( $key, $found, Ace_Ads_Manager::CACHE_GROUP, DAY_IN_SECONDS );
        return $found;
    }

    /**
     * Pick the ad for a slot: highest priority, then most specific, then newest.
     *
     * @return array{ad_id:int, rule:array}|null
     */
    public static function resolve( string $slot, array $context ): ?array {
        $best = null;
        foreach ( self::candidates( $slot ) as $candidate ) {
            if ( ! self::rule_matches( $candidate['rule'], $context ) || ! Ace_Ads_Manager::is_live( $candidate['ad_id'] ) ) {
                continue;
            }
            $candidate['score'] = [ (int) $candidate['rule']['priority'], self::specificity( $candidate['rule'] ), $candidate['ad_id'] ];
            if ( null === $best || $candidate['score'] > $best['score'] ) {
                $best = $candidate;
            }
        }
        return apply_filters( 'ace_ads_resolved', $best, $slot, $context );
    }

    private static function specificity( array $rule ): int {
        $score = 0;
        foreach ( $rule['targets'] as $target ) {
            $score += 'everywhere' === $target['type'] ? 0 : 10 + count( $target['values'] );
        }
        $score += '' !== $rule['loop_index'] ? 5 : 0;
        $score += '' !== $rule['parent_block'] ? 5 : 0;
        return $score;
    }

    /**
     * Human-readable description of a rule's targets for the overview screen.
     */
    public static function describe( array $rule ): string {
        $types = self::target_types();
        $parts = [];
        foreach ( $rule['targets'] as $target ) {
            $label  = $types[ $target['type'] ] ?? $target['type'];
            $values = [];
            foreach ( $target['values'] as $value ) {
                if ( 'taxonomy_term' === $target['type'] && false !== strpos( $value, ':' ) ) {
                    [ $taxonomy, $id ] = explode( ':', $value, 2 );
                    $term     = get_term( (int) $id, $taxonomy );
                    $values[] = $term instanceof WP_Term ? $term->name : $value;
                } elseif ( 'post' === $target['type'] ) {
                    $values[] = get_the_title( (int) $value ) ?: $value;
                } elseif ( 'post_type' === $target['type'] || 'post_type_archive' === $target['type'] ) {
                    $object   = get_post_type_object( $value );
                    $values[] = $object ? $object->labels->singular_name : $value;
                } else {
                    $values[] = $value;
                }
            }
            $parts[] = $label . ( $values ? ': ' . implode( ', ', $values ) : '' );
        }
        if ( '' !== $rule['loop_index'] ) {
            $parts[] = sprintf( __( 'loop item %s', 'ace-ads-manager' ), $rule['loop_index'] );
        }
        if ( '' !== $rule['parent_block'] ) {
            $parts[] = sprintf( __( 'inside %s', 'ace-ads-manager' ), $rule['parent_block'] );
        }
        return implode( ' + ', $parts );
    }
}
