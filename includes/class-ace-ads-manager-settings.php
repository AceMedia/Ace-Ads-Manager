<?php
/**
 * Settings store for Ace Ads Manager.
 *
 * One option (ace_ads_options), one schema, one sanitiser. The admin page
 * renders from the same schema so a new setting is a one-line addition here.
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Ads_Manager_Settings {

    const OPTION = 'ace_ads_options';

    /**
     * Field schema keyed by option key.
     * type: checkbox | text | textarea | number | post_types | taxonomies | select
     */
    public static function fields(): array {
        $fields = [
            'slots' => [
                'tab' => 'slots',
                'type' => 'textarea',
                'label' => 'Named slots',
                'help' => 'One slot per line as slug|Label. The Ad block and placement rules pick from this list.',
                'default' => "top|Top\nin-content|In content\nsidebar|Sidebar\nbottom|Bottom",
            ],
            'in_content_paragraphs' => [
                'tab' => 'slots',
                'type' => 'number',
                'label' => 'Default in-content position (after N paragraphs)',
                'default' => 3,
                'min' => 1,
                'max' => 50,
            ],
            'in_content_post_types' => [
                'tab' => 'slots',
                'type' => 'post_types',
                'label' => 'Inject the in-content slot on these post types',
                'help' => 'Only singular views. Leave empty to disable automatic in-content injection.',
            ],
            'wrapper_class' => [
                'tab' => 'rendering',
                'type' => 'text',
                'label' => 'Extra wrapper class',
                'default' => '',
            ],
            'cta_label' => [
                'tab' => 'rendering',
                'type' => 'text',
                'label' => 'Default call to action label',
                'default' => 'Claim offer',
            ],
            'nofollow' => [
                'tab' => 'rendering',
                'type' => 'checkbox',
                'label' => 'Add rel="nofollow sponsored" to ad links',
                'default' => 1,
            ],
            'track_clicks' => [
                'tab' => 'tracking',
                'type' => 'checkbox',
                'label' => 'Record ad clicks',
                'help' => 'Fires the ace_ads_click action so a site can attribute clicks (e.g. to bet-link tracking).',
                'default' => 1,
            ],
        ];

        /**
         * Lets a site (via a must-use plugin) add or adjust settings fields.
         *
         * @param array $fields Schema keyed by option key.
         */
        return apply_filters( 'ace_ads_settings_fields', $fields );
    }

    public static function tabs(): array {
        $tabs = [['slots', 'Slots', 'layout'], ['rendering', 'Rendering', 'art'], ['tracking', 'Tracking', 'chart-line']];
        return apply_filters( 'ace_ads_settings_tabs', $tabs );
    }

    public static function defaults(): array {
        $defaults = [];
        foreach ( self::fields() as $key => $field ) {
            $defaults[ $key ] = $field['default'] ?? ( in_array( $field['type'], [ 'post_types', 'taxonomies' ], true ) ? [] : '' );
        }
        return $defaults;
    }

    public static function all(): array {
        static $cache = null;
        if ( null === $cache ) {
            $stored = get_option( self::OPTION, [] );
            $cache  = wp_parse_args( is_array( $stored ) ? $stored : [], self::defaults() );
        }
        return $cache;
    }

    public static function get( string $key, $fallback = null ) {
        $all = self::all();
        $value = array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
        return apply_filters( 'ace_ads_setting', $value, $key );
    }

    /**
     * Newline-separated textarea setting as a clean list.
     */
    public static function get_list( string $key ): array {
        $raw = (string) self::get( $key, '' );
        $lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ) );
        return array_values( array_unique( $lines ) );
    }

    public static function update( array $raw ): array {
        $clean = self::sanitise( $raw );
        update_option( self::OPTION, $clean, false );
        update_option( 'ace_ads_version', ACE_ADS_VERSION, false );
        do_action( 'ace_ads_settings_saved', $clean );
        return $clean;
    }

    public static function sanitise( array $raw ): array {
        $clean = [];
        foreach ( self::fields() as $key => $field ) {
            $value = $raw[ $key ] ?? null;
            switch ( $field['type'] ) {
                case 'checkbox':
                    $clean[ $key ] = empty( $value ) ? 0 : 1;
                    break;
                case 'number':
                    $number = is_numeric( $value ) ? (int) $value : (int) ( $field['default'] ?? 0 );
                    if ( isset( $field['min'] ) ) {
                        $number = max( (int) $field['min'], $number );
                    }
                    if ( isset( $field['max'] ) ) {
                        $number = min( (int) $field['max'], $number );
                    }
                    $clean[ $key ] = $number;
                    break;
                case 'textarea':
                    $clean[ $key ] = sanitize_textarea_field( wp_unslash( (string) $value ) );
                    break;
                case 'post_types':
                case 'taxonomies':
                    $value = is_array( $value ) ? $value : [];
                    $clean[ $key ] = array_values( array_filter( array_map( 'sanitize_key', $value ) ) );
                    break;
                case 'select':
                    $options = $field['options'] ?? [];
                    $value   = sanitize_key( (string) $value );
                    $clean[ $key ] = isset( $options[ $value ] ) ? $value : ( $field['default'] ?? '' );
                    break;
                default:
                    $clean[ $key ] = sanitize_text_field( wp_unslash( (string) $value ) );
            }
        }
        return apply_filters( 'ace_ads_sanitise_settings', $clean, $raw );
    }
}
