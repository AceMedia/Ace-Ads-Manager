<?php
// Parallel hits never exceed RATE_LIMIT. On a site without Ace Ads Manager active:
//   wp eval-file tests/rate-limit-wp.php setup
//   for i in 1 2 3 4 5 6; do wp eval-file tests/rate-limit-wp.php KEY & done; wait
//   wp eval-file tests/rate-limit-wp.php teardown
// Six copies try 40 hits each for one visitor; the accepted totals must sum to 120.
if ( ! class_exists( 'Ace_Ads_Tracking' ) ) {
    require_once dirname( __DIR__ ) . '/includes/class-ace-ads-tracking.php';
}
global $wpdb;
$arg   = isset( $args[0] ) ? (string) $args[0] : 'test';
$table = Ace_Ads_Tracking::rate_table();
if ( 'setup' === $arg ) {
    $wpdb->query( "CREATE TABLE IF NOT EXISTS {$table} (visitor varchar(40) NOT NULL, minute int(10) unsigned NOT NULL, hits int(10) unsigned NOT NULL DEFAULT 0, PRIMARY KEY (visitor, minute))" );
    return;
}
if ( 'teardown' === $arg ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
    return;
}
$limit = Closure::bind( function ( $v ) { return self::within_rate_limit( $v ); }, null, 'Ace_Ads_Tracking' );
$ok    = 0;
for ( $i = 0; $i < 40; $i++ ) {
    $ok += $limit( $arg ) ? 1 : 0;
}
echo "$ok\n";
