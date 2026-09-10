# Ace Ads Manager

Central ads with placement rules. One Ad block, targeted by archive, term, post type, loop position or parent block.

Part of the Ace plugin family (Ace Crawl Enhancer, Ace Redis Cache, Ace Community Events). Generic by design:
anything site-specific stays behind settings or hooks so the same plugin can be submoduled into every site.

**Requires:** WordPress 6.4+, PHP 8.1+. **Licence:** GPLv2 or later.

## Install

Add as a submodule in the site's plugins directory and activate:

```bash
git submodule add git@github.com:AceMedia/Ace-Ads-Manager.git assets/plugins/ace-ads-manager
```

Build output is committed, so no build step is needed on deploy.

## Develop

```bash
npm install
npm run build
```

## Hooks

- `ace_ads_settings_fields` - add or adjust settings fields (schema array).
- `ace_ads_settings_tabs` - add or adjust settings tabs.
- `ace_ads_setting` - filter a single resolved setting value.
- `ace_ads_settings_saved` - action after settings are saved.

Plugin-specific hooks are documented in the source next to each `apply_filters` / `do_action`.

## Where things are

- **Ads** (top-level menu): the ads themselves, with Offer and Placement rules panels in the editor sidebar.
- **Settings → Ads Manager**: Placements (every rule, what-shows-where preview), Analytics, Slots, Rendering, Tracking, Guide.

## Tracking data

- `{prefix}ace_ads_events`: one row per event (impression | viewable | click) with ad, slot, rule index (-1 = pinned), post, view type, post type, primary term, device, referrer host, hashed visitor, page view id, timestamp. Pruned nightly after the retention period.
- `{prefix}ace_ads_daily`: roll-up per day × ad × slot × rule × post × device with impressions, viewable and clicks. Kept indefinitely; this is what the Analytics tab and CSV export read.
- Beacon endpoint: `POST /wp-json/ace-ads/v1/events`. Filter `ace_ads_track_event` to drop or adjust an event; actions `ace_ads_event` and `ace_ads_click`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a plain-English record of every release.
