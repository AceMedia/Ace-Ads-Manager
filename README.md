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

## Changelog

### 0.1.0
- Initial scaffold: settings page, options store, build tooling.

## How it works

- **Ad** post type (`ace_ad`): title = headline, content = copy, featured image optional, plus offer code, link,
  call to action, start/end window and image mode. Rules live on the ad.
- **Ad block** (`ace-ads/slot`): drop it anywhere and pick a slot. Leave the ad on "resolve by rules" and the
  placement rules decide what shows there, or pin one ad. Inside a Query Loop the block sees the looped post,
  so rules can target the post's terms, the loop position (`3` or `3n`) and the parent block.
- **In-content** slot is injected after N paragraphs on the post types chosen in settings, unless the post
  already contains an Ad block.
- **Resolver**: highest priority wins, then the most specific rule, then the newest ad. Candidates per slot are
  cached in the object cache under a version key bumped on every ad/rule/settings save.
- **Overview** (Ads → Overview): every placement across every ad, filter by ad, and a "what shows where" preview.
- **Clicks**: a deferred beacon hits `/wp-json/ace-ads/v1/click`; the plugin keeps a 90-day daily count per ad
  and fires `ace_ads_click` so a site can attribute the click elsewhere.

Ads render as plain HTML (`<aside class="ace-ad">` with title, copy, code and a link) - real content, not an
embed, so it is indexable and not blocked by ad blockers. Style via `.ace-ad` and its custom properties.

## WP-CLI

```bash
wp ace-ads find --pattern='<text>' [--regex] [--post-type=post]
wp ace-ads replace --pattern='<text>' --ad=<id> --slot=in-content [--post-type=post] [--ids=<csv>] [--yes]
wp ace-ads replace --pattern='<text>' --with='<text>' [--yes]
```

`replace` is a dry run until `--yes`. Changes are grouped under one Ace Revisions batch id.

## Plugin hooks

- `ace_ads_slots`, `ace_ads_target_types`, `ace_ads_request_context`, `ace_ads_slot_context`.
- `ace_ads_target_matches` (custom target types), `ace_ads_resolved`, `ace_ads_is_live`.
- `ace_ads_render_html`, `ace_ads_empty_slot`.
- `ace_ads_click` (ad_id, post_id, slot, extra), `ace_ads_cache_invalidated`.
