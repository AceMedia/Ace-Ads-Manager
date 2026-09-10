<?php
/**
 * Settings page for Ace Ads Manager (Settings → Ace Ads Manager).
 *
 * Same two-column layout, sidebar sub-navigation, fieldset groups and fixed
 * save bar as Ace Crawl Enhancer / Ace Redis Cache. Every tab carries a guide
 * panel, the screen has WordPress help tabs, and a Guide tab holds the manual.
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Ads_Manager_Admin {

    const PAGE  = 'ace-ads-manager';
    const CAP   = 'manage_options';
    const NONCE = 'ace_ads_admin_nonce';

    private static $instance = null;
    private $hook = '';

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'wp_ajax_ace_ads_save_settings', [ $this, 'ajax_save_settings' ] );
        add_filter( 'plugin_action_links_' . ACE_ADS_BASENAME, [ $this, 'action_links' ] );
    }

    public function register_menu(): void {
        $this->hook = add_options_page(
            'Ace Ads Manager',
            'Ads Manager',
            self::CAP,
            self::PAGE,
            [ $this, 'render_settings_page' ]
        );
        add_action( 'load-' . $this->hook, [ $this, 'help_tabs' ] );
    }

    public function action_links( array $links ): array {
        array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'ace-ads-manager' ) . '</a>' );
        $links[] = '<a href="' . esc_url( self::url( 'guide' ) ) . '">' . esc_html__( 'Guide', 'ace-ads-manager' ) . '</a>';
        return $links;
    }

    /**
     * URL of the settings page, optionally deep-linked to a tab (and section).
     */
    public static function url( string $tab = '', string $section = '' ): string {
        $url = admin_url( 'options-general.php?page=' . self::PAGE );
        if ( $tab ) {
            $url .= '#' . $tab . ( $section ? '/' . $section : '' );
        }
        return $url;
    }

    public function is_plugin_screen( string $hook ): bool {
        return $hook === $this->hook;
    }

    public function enqueue( string $hook ): void {
        if ( ! $this->is_plugin_screen( $hook ) ) {
            return;
        }

        wp_enqueue_style( 'ace-ads-manager-admin', ACE_ADS_URL . 'assets/css/admin.css', [], ACE_ADS_VERSION );

        $asset_file = ACE_ADS_PATH . 'build/admin.asset.php';
        $asset      = file_exists( $asset_file ) ? include $asset_file : [ 'dependencies' => [], 'version' => ACE_ADS_VERSION ];
        $deps       = array_unique( array_merge( (array) ( $asset['dependencies'] ?? [] ), [ 'jquery' ] ) );

        wp_enqueue_script( 'ace-ads-manager-admin', ACE_ADS_URL . 'build/admin.js', $deps, $asset['version'] ?? ACE_ADS_VERSION, true );
        wp_localize_script( 'ace-ads-manager-admin', 'ace_ads_admin', [
            'ajax_url'    => admin_url( 'admin-ajax.php' ),
            'rest_url'    => rest_url(),
            'nonce'       => wp_create_nonce( self::NONCE ),
            'rest_nonce'  => wp_create_nonce( 'wp_rest' ),
            'save_action' => 'ace_ads_save_settings',
            'storage_key' => 'ace_ads_auto_save_enabled',
        ] );

        /**
         * Other screens folded into this settings page (batch editor, overview...) enqueue here.
         */
        do_action( 'ace_ads_settings_enqueue', $hook );
    }

    /**
     * WordPress contextual help (the "Help" pull-down top right), from the guide.
     */
    public function help_tabs(): void {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }
        foreach ( Ace_Ads_Manager_Guide::sections() as $id => $section ) {
            $screen->add_help_tab( [
                'id'      => 'ace-ads-manager-' . $id,
                'title'   => $section['title'],
                'content' => wp_kses_post( $section['content'] ),
            ] );
        }
        $screen->set_help_sidebar(
            '<p><strong>' . esc_html__( 'More', 'ace-ads-manager' ) . '</strong></p>' .
            '<p><a href="' . esc_url( self::url( 'guide' ) ) . '">' . esc_html__( 'Full guide', 'ace-ads-manager' ) . '</a></p>' .
            '<p><a href="https://github.com/AceMedia/Ace-Ads-Manager/issues" target="_blank" rel="noopener">' . esc_html__( 'Roadmap and issues', 'ace-ads-manager' ) . '</a></p>'
        );
    }

    public function render_settings_page(): void {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'ace-ads-manager' ) );
        }
        $options = Ace_Ads_Manager_Settings::all();
        $fields  = Ace_Ads_Manager_Settings::fields();
        $tabs    = Ace_Ads_Manager_Settings::tabs();
        $tabs[]  = [ 'id' => 'guide', 'label' => __( 'Guide', 'ace-ads-manager' ), 'icon' => 'book', 'custom' => true, 'help' => '' ];
        $intro   = "One Ad block, placed anywhere, filled by rules. Every impression, view and click is recorded so you can see what each placement earns.";
        $admin   = $this;
        include ACE_ADS_PATH . 'includes/admin/views/settings.php';
    }

    public function ajax_save_settings(): void {
        if ( ! current_user_can( self::CAP ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ace-ads-manager' ) ], 403 );
        }
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::NONCE ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'ace-ads-manager' ) ], 400 );
        }
        // Sanitisation happens field-by-field in the settings store.
        $clean = Ace_Ads_Manager_Settings::update( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        wp_send_json_success( [ 'message' => __( 'Settings saved.', 'ace-ads-manager' ), 'options' => $clean ] );
    }

    /**
     * Render one setting row (label + field + description) from the schema. Escaping happens here, late.
     */
    public function render_row( string $key, array $field, $value ): void {
        $id = esc_attr( self::PAGE . '-' . $key );
        echo '<div class="setting-row">';
        echo '<div class="setting-label"><label for="' . $id . '">' . esc_html( $field['label'] ) . '</label></div>';
        echo '<div class="setting-field">';
        $this->render_field( $key, $field, $value );
        if ( ! empty( $field['help'] ) ) {
            echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
        }
        echo '</div></div>';
    }

    public function render_field( string $key, array $field, $value ): void {
        $id = esc_attr( self::PAGE . '-' . $key );
        switch ( $field['type'] ) {
            case 'checkbox':
                printf(
                    '<label class="ace-switch" for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s><span class="ace-slider"></span></label>',
                    $id,
                    esc_attr( $key ),
                    checked( ! empty( $value ), true, false )
                );
                break;
            case 'number':
                printf(
                    '<input type="number" id="%1$s" name="%2$s" value="%3$s" class="small-text" min="%4$s" max="%5$s">',
                    $id,
                    esc_attr( $key ),
                    esc_attr( (string) $value ),
                    esc_attr( (string) ( $field['min'] ?? 0 ) ),
                    esc_attr( (string) ( $field['max'] ?? 9999 ) )
                );
                break;
            case 'textarea':
                printf(
                    '<textarea id="%1$s" name="%2$s" rows="%4$d" class="large-text code">%3$s</textarea>',
                    $id,
                    esc_attr( $key ),
                    esc_textarea( (string) $value ),
                    (int) ( $field['rows'] ?? 5 )
                );
                break;
            case 'select':
                printf( '<select id="%1$s" name="%2$s">', $id, esc_attr( $key ) );
                foreach ( (array) ( $field['options'] ?? [] ) as $opt_value => $opt_label ) {
                    printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $opt_value ), esc_html( $opt_label ), selected( $value, $opt_value, false ) );
                }
                echo '</select>';
                break;
            case 'post_types':
            case 'taxonomies':
                $objects = 'post_types' === $field['type']
                    ? get_post_types( [ 'show_ui' => true ], 'objects' )
                    : get_taxonomies( [ 'show_ui' => true ], 'objects' );
                $chosen = is_array( $value ) ? $value : [];
                echo '<div class="ace-check-grid">';
                foreach ( $objects as $object ) {
                    if ( 'post_types' === $field['type'] && in_array( $object->name, [ 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles', 'wp_font_family', 'wp_font_face', 'ace_ad' ], true ) ) {
                        continue;
                    }
                    printf(
                        '<label><input type="checkbox" name="%1$s[]" value="%2$s" %4$s> %3$s <code>%2$s</code></label>',
                        esc_attr( $key ),
                        esc_attr( $object->name ),
                        esc_html( $object->labels->name ),
                        checked( in_array( $object->name, $chosen, true ), true, false )
                    );
                }
                echo '</div>';
                break;
            default:
                printf(
                    '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text">',
                    $id,
                    esc_attr( $key ),
                    esc_attr( (string) $value )
                );
        }
    }

    /**
     * Collapsible guide panel shown at the top of a tab (Redis "Traffic Scope Guide" style).
     */
    public static function guide_panel( string $tab_id, string $text ): void {
        if ( '' === trim( $text ) ) {
            return;
        }
        ?>
        <div class="ace-guide-panel" data-guide="<?php echo esc_attr( $tab_id ); ?>">
            <div class="ace-guide-panel__head">
                <h3><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><?php esc_html_e( 'How this works', 'ace-ads-manager' ); ?></h3>
                <button type="button" class="button button-secondary ace-guide-panel__toggle" aria-expanded="true">
                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                    <span class="ace-guide-panel__toggle-text"><?php esc_html_e( 'Hide guide', 'ace-ads-manager' ); ?></span>
                </button>
            </div>
            <div class="ace-guide-panel__body"><?php echo wp_kses_post( wpautop( $text ) ); ?></div>
        </div>
        <?php
    }
}

require_once ACE_ADS_PATH . 'includes/admin/class-ace-ads-manager-guide.php';
Ace_Ads_Manager_Admin::instance();
