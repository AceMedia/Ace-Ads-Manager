=== Ace Ads Manager ===
Contributors: shanerounce
Tags: ads, advertising, block, placement, banners
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Central ads with placement rules. One Ad block, targeted by archive, term, post type, loop position or parent block.

== Description ==

Central ads with placement rules. One Ad block, targeted by archive, term, post type, loop position or parent block.

Part of the Ace plugin family (Ace Crawl Enhancer, Ace Redis Cache, Ace Community Events). Generic by design: anything site-specific stays behind settings or hooks so the same plugin can be dropped into any site.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install the zip from Plugins > Add New.
2. Activate it through the Plugins screen.
3. Configure it from the plugin's settings screen where one exists.

== Frequently Asked Questions ==

= Where do I report a bug or ask for a feature? =

Open an issue on the plugin's GitHub repository.

== Changelog ==

= 0.3.0 =
* "Except" on any target, for everywhere-except-this-category rules.
* Image-only banner format: the featured image is the whole ad, linked, for the old pasted banners.
* Ad block in the Site Editor explains that it resolves per page instead of showing an empty box.
* News sites: ad clicks can land in a site-level click log with byline or automation attribution (pinned block = author, rule = automation).
= 0.2.2 =
* A settings save now takes effect immediately in the same request.
= 0.2.1 =
* The Ad edit screen is back on the block editor: Offer and Placement rules are panels in the sidebar, with term and post pickers that search by name.
= 0.2.0 =
* Tracking rebuilt: impressions, viewable impressions and clicks per ad, rule, slot, page, device and day, with unique visitors and referrer. Bots and repeat impressions are filtered.
* Settings → Ads Manager now holds Placements, Analytics (with CSV export), Slots, Rendering, Tracking and Guide.
* Per-ad link rel, new-tab and minimum-paragraph settings; start and end dates respect the site time zone.
= 0.1.0 =
* First cut: Ad post type, Ad block resolved by placement rules, in-content injection, overview, click beacon, WP-CLI find/replace for pasted offers.
