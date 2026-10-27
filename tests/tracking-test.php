<?php
// Standalone: client address, day clock. Run: php8.4 tests/tracking-test.php
define( 'ABSPATH', __DIR__ . '/' ); define( 'MINUTE_IN_SECONDS', 60 ); define( 'DAY_IN_SECONDS', 86400 );
function add_action( ...$a ) {} function add_filter( ...$a ) {}
function apply_filters( $hook, $value ) { return $value; }
function wp_date( $format, $ts ) { return ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( new DateTimeZone( 'Europe/London' ) )->format( $format ); }
require dirname( __DIR__ ) . '/includes/class-ace-ads-tracking.php';
function check( $ok, $m ) { if ( ! $ok ) { fwrite( STDERR, "FAIL $m\n" ); exit( 1 ); } echo "PASS $m\n"; }
$t = 'Ace_Ads_Tracking';

// 23:30 BST on 1 July is 22:30 UTC; write and read must both call it 1 July.
$click = strtotime( '2026-07-01 22:30:00 UTC' );
$write = $t::site_day( strtotime( gmdate( 'Y-m-d H:i:s', $click ) . ' UTC' ) );
check( '2026-07-01' === $write, 'a 23:30 BST click is written to 1 July' );
check( $t::site_day( $click ) === $write, 'the read side uses the same clock' );

check( '203.0.113.9' === $t::client_ip( [ 'REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '198.51.100.1' ] ), 'spoofed X-Forwarded-For from a non-proxy is ignored' );
check( '203.0.113.9' === $t::client_ip( [ 'REMOTE_ADDR' => '203.0.113.9', 'HTTP_CF_CONNECTING_IP' => '198.51.100.1' ] ), 'spoofed CF-Connecting-IP from a non-proxy is ignored' );
check( '198.51.100.1' === $t::client_ip( [ 'REMOTE_ADDR' => '162.158.1.2', 'HTTP_CF_CONNECTING_IP' => '198.51.100.1' ] ), 'CF-Connecting-IP from Cloudflare is used' );
check( '198.51.100.1' === $t::client_ip( [ 'REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.1' ] ), 'X-Forwarded-For takes the nearest non-proxy hop, not the spoofable first one' );
check( '10.0.0.5' === $t::client_ip( [ 'REMOTE_ADDR' => '10.0.0.5', 'HTTP_CF_CONNECTING_IP' => '198.51.100.1' ] ), 'CF-Connecting-IP needs a Cloudflare peer' );
check( $t::ip_in( '2606:4700::1', $t::CLOUDFLARE ) && ! $t::ip_in( '2001:db8::1', $t::CLOUDFLARE ), 'IPv6 ranges' );
