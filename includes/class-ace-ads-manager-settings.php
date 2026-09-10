<?php
/**
 * Settings store for Ace Ads Manager.
 *
 * One option (ace_ads_options), one schema, one sanitiser. The settings page
 * renders from the same schema: tabs → sections (fieldsets) → fields.
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
     * tab / section place the field on the settings page.
     */
    public static function fields(): array {
        $fields = [
            'slots' => [
                'tab' => "slots",
                'section' => "slots-list",
                'type' => "textarea",
                'label' => "Named slots",
                'help' => "One slot per line as slug|Label. The Ad block and placement rules pick from this list.",
                'default' => "top|Top\nin-content|In content\nsidebar|Sidebar\nbottom|Bottom",
                'rows' => 5,
            ],
            'in_content_post_types' => [
                'tab' => "slots",
                'section' => "slots-incontent",
                'type' => "post_types",
                'label' => "Inject the in-content slot on these post types",
                'help' => "Singular views only. Leave empty to disable automatic injection.",
            ],
            'in_content_paragraphs' => [
                'tab' => "slots",
                'section' => "slots-incontent",
                'type' => "number",
                'label' => "Default position (after N paragraphs)",
                'help' => "A rule can override this per ad.",
                'default' => 3,
                'min' => 1,
                'max' => 50,
            ],
            'in_content_min_paragraphs' => [
                'tab' => "slots",
                'section' => "slots-incontent",
                'type' => "number",
                'label' => "Minimum paragraphs before injecting",
                'help' => "Shorter posts get no automatic in-content ad.",
                'default' => 4,
                'min' => 1,
                'max' => 100,
            ],
            'wrapper_class' => [
                'tab' => "rendering",
                'section' => "rendering-markup",
                'type' => "text",
                'label' => "Extra wrapper class",
                'help' => "Added to every rendered ad.",
                'default' => "",
            ],
            'cta_label' => [
                'tab' => "rendering",
                'section' => "rendering-markup",
                'type' => "text",
                'label' => "Default call to action label",
                'help' => "Used when an ad has no label of its own.",
                'default' => "Claim offer",
            ],
            'nofollow' => [
                'tab' => "rendering",
                'section' => "rendering-links",
                'type' => "checkbox",
                'label' => "Add rel=\"nofollow sponsored\" to ad links",
                'help' => "Recommended for paid placements. Individual ads can opt out.",
                'default' => 1,
            ],
            'new_tab' => [
                'tab' => "rendering",
                'section' => "rendering-links",
                'type' => "checkbox",
                'label' => "Open ad links in a new tab",
                'default' => 1,
            ],
            'track_impressions' => [
                'tab' => "tracking",
                'section' => "tracking-events",
                'type' => "checkbox",
                'label' => "Record impressions and viewable impressions",
                'default' => 1,
            ],
            'track_clicks' => [
                'tab' => "tracking",
                'section' => "tracking-events",
                'type' => "checkbox",
                'label' => "Record clicks",
                'help' => "Also fires the ace_ads_click action for site-level attribution.",
                'default' => 1,
            ],
            'track_logged_in' => [
                'tab' => "tracking",
                'section' => "tracking-events",
                'type' => "checkbox",
                'label' => "Record events from logged-in users",
                'help' => "Off keeps editors previewing pages out of the numbers.",
                'default' => 0,
            ],
            'retention_days' => [
                'tab' => "tracking",
                'section' => "tracking-retention",
                'type' => "number",
                'label' => "Keep raw events for (days)",
                'help' => "Older rows are pruned nightly. Daily totals are kept indefinitely.",
                'default' => 90,
                'min' => 7,
                'max' => 730,
            ],
        ];

        /**
         * Lets a site (via a must-use plugin) add or adjust settings fields.
         *
         * @param array $fields Schema keyed by option key.
         */
        return apply_filters( 'ace_ads_settings_fields', $fields );
    }

    /**
     * Tabs: id, label, dashicon, help (guide panel text), sections; custom => true
     * renders through the ace_ads_settings_tab_content action instead of fields.
     */
    public static function tabs(): array {
        $tabs = [[
            'id' => "overview",
            'label' => "Placements",
            'icon' => "visibility",
            'custom' => true,
            'help' => "Every placement rule across every ad, and a preview of what resolves for a given post type or term. Filter by ad to see everywhere it appears. Ads themselves are edited under the Ads menu.",
            'sections' => [],
        ], [
            'id' => "analytics",
            'label' => "Analytics",
            'icon' => "chart-bar",
            'custom' => true,
            'help' => "Impressions are counted when an ad is rendered in the browser, viewable when at least half of it has been on screen for a second (the IAB standard), and clicks when a reader follows the link. Break down by ad, slot, page, day and device. Bots and repeat impressions within a page view are filtered.",
            'sections' => [],
        ], [
            'id' => "slots",
            'label' => "Slots",
            'icon' => "layout",
            'help' => "A slot is a named position: top, in-content, sidebar, bottom, or anything you add. The Ad block picks a slot; placement rules on each ad decide which ad fills it. The in-content slot can also be injected automatically after a number of paragraphs.",
            'sections' => [[
                'id' => "slots-list",
                'title' => "Named slots",
                'icon' => "layout",
                'description' => "",
            ], [
                'id' => "slots-incontent",
                'title' => "In-content injection",
                'icon' => "editor-paragraph",
                'description' => "Automatic injection is skipped on posts that already contain an Ad block.",
            ]],
        ], [
            'id' => "rendering",
            'label' => "Rendering",
            'icon' => "art",
            'help' => "Ads render as real HTML - headline, copy, offer code and a link - not an image or a third-party embed. That makes them indexable content and keeps them out of ad blockers. Style them from the theme with the .ace-ad classes and custom properties.",
            'sections' => [[
                'id' => "rendering-markup",
                'title' => "Markup",
                'icon' => "editor-code",
                'description' => "",
            ], [
                'id' => "rendering-links",
                'title' => "Links",
                'icon' => "admin-links",
                'description' => "",
            ]],
        ], [
            'id' => "tracking",
            'label' => "Tracking",
            'icon' => "chart-line",
            'help' => "Tracking is a small beacon sent from the browser, so it works behind full-page caching. No personal data is stored: the visitor key is a salted hash of IP and user agent that rotates daily and only exists to de-duplicate impressions. Every event also fires a PHP action so a site can forward it to its own analytics.",
            'sections' => [[
                'id' => "tracking-events",
                'title' => "Events",
                'icon' => "chart-line",
                'description' => "",
            ], [
                'id' => "tracking-retention",
                'title' => "Retention",
                'icon' => "clock",
                'description' => "",
            ]],
        ]];
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
        $all   = self::all();
        $value = array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
        return apply_filters( 'ace_ads_setting', $value, $key );
    }

    /**
     * Newline-separated textarea setting as a clean list.
     */
    public static function get_list( string $key ): array {
        $raw   = (string) self::get( $key, '' );
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
                    $value         = is_array( $value ) ? $value : [];
                    $clean[ $key ] = array_values( array_filter( array_map( 'sanitize_key', $value ) ) );
                    break;
                case 'select':
                    $options       = $field['options'] ?? [];
                    $value         = sanitize_key( (string) $value );
                    $clean[ $key ] = isset( $options[ $value ] ) ? $value : ( $field['default'] ?? '' );
                    break;
                default:
                    $clean[ $key ] = sanitize_text_field( wp_unslash( (string) $value ) );
            }
        }
        return apply_filters( 'ace_ads_sanitise_settings', $clean, $raw );
    }
}
