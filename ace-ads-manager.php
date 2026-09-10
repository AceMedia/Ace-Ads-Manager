<?php
/**
 * Plugin Name: Ace Ads Manager
 * Plugin URI: https://github.com/AceMedia/Ace-Ads-Manager
 * Description: Central ads with placement rules. One Ad block, targeted by archive, term, post type, loop position or parent block.
 * Version: 0.3.0
 * Author: AceMedia
 * Author URI: https://acemedia.ninja
 * Text Domain: ace-ads-manager
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Bump on every release: drives asset cache-busting and the options migration check.
define( 'ACE_ADS_VERSION', '0.3.0' );
define( 'ACE_ADS_FILE', __FILE__ );
define( 'ACE_ADS_PATH', plugin_dir_path( __FILE__ ) );
define( 'ACE_ADS_URL', plugin_dir_url( __FILE__ ) );
define( 'ACE_ADS_BASENAME', plugin_basename( __FILE__ ) );

require_once ACE_ADS_PATH . 'includes/class-ace-ads-manager-settings.php';
require_once ACE_ADS_PATH . 'includes/class-ace-ads-manager.php';

if ( is_admin() ) {
    require_once ACE_ADS_PATH . 'includes/admin/class-ace-ads-manager-admin.php';
}

register_activation_hook( __FILE__, [ 'Ace_Ads_Manager', 'activate' ] );

add_action( 'plugins_loaded', [ 'Ace_Ads_Manager', 'instance' ] );
