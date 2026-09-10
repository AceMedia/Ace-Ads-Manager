<?php
/**
 * Uninstall Ace Ads Manager: remove options only. Tracked data is left in place
 * on purpose so deleting the plugin never destroys history or content.
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'ace_ads_options' );
delete_option( 'ace_ads_version' );
