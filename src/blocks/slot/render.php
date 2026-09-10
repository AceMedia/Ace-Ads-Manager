<?php
/**
 * Ad slot block render. $attributes, $content, $block are provided by core.
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slot  = sanitize_key( $attributes['slot'] ?? 'top' );
$ad_id = (int) ( $attributes['adId'] ?? 0 );
$html  = Ace_Ads_Render::slot( $slot, $block->context ?? [], $ad_id );

if ( '' === $html ) {
    return;
}

$wrapper = get_block_wrapper_attributes( [ 'class' => 'ace-ad-slot ace-ad-slot--' . $slot ] );
echo '<div ' . $wrapper . '>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Ace_Ads_Render.
