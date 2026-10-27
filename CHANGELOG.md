# Ace Ads Manager changelog

Plain-English record of what changed in each release. Dates are when the version was pushed.

## 0.3.1 - 27 October 2026
- Click totals read day buckets on the same site clock they are written with, so late evening clicks in BST land on the right day.
- The tracking rate limit is one atomic upsert in a small per-minute table, so parallel hits cannot exceed it.
- CF-Connecting-IP and X-Forwarded-For are only believed from Cloudflare or private proxy addresses (filter: ace_ads_trusted_proxies).

## 0.3.0 - 10 September 2026
- "Except" on any target, for everywhere-except-this-category rules.
- Image-only banner format: the featured image is the whole ad, linked, for the old pasted banners.
- Ad block in the Site Editor explains that it resolves per page instead of showing an empty box.
- News sites: ad clicks can land in a site-level click log with byline or automation attribution (pinned block = author, rule = automation).

## 0.2.2 - 10 September 2026
- A settings save now takes effect immediately in the same request.

## 0.2.1 - 10 September 2026
- The Ad edit screen is back on the block editor: Offer and Placement rules are panels in the sidebar, with term and post pickers that search by name.

## 0.2.0 - 10 September 2026
- Tracking rebuilt: impressions, viewable impressions and clicks per ad, rule, slot, page, device and day, with unique visitors and referrer. Bots and repeat impressions are filtered.
- Settings → Ads Manager now holds Placements, Analytics (with CSV export), Slots, Rendering, Tracking and Guide.
- Per-ad link rel, new-tab and minimum-paragraph settings; start and end dates respect the site time zone.

## 0.1.0 - 10 September 2026
- First cut: Ad post type, Ad block resolved by placement rules, in-content injection, overview, click beacon, WP-CLI find/replace for pasted offers.
