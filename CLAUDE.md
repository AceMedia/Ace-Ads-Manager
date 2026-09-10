# Ace Ads Manager (`ace_ads`)

Central ads with placement rules. One Ad block, targeted by archive, term, post type, loop position or parent block. Canonical repo: `git@github.com:AceMedia/Ace-Ads-Manager.git`, checked out at
`/var/www/html/plugins/ace-ads-manager`. For AceMedia-wide conventions (British English, commit rules, deploy
patterns) use the **speedforce** skill; this file only holds plugin-specific facts.

**Canonical plan = the GitHub issues on this repo.** Pick work up from there and keep them current.

## Shared plugin rules

- Shared across sites as a submodule. **Keep it generic**: no domains, site slugs, or another site's CPT/meta
  keys. Site-specific behaviour lives in that site's must-use plugin and reaches this plugin through filters.
- Native WP APIs only, no new runtime dependencies. Capability checks, nonces, sanitise on input, escape late.
  Multisite safe (per-site options, `ace_ads_options`).
- Negligible frontend cost: at most one cached lookup per request; invalidate on save. Cache through the object
  cache / Ace-Redis-Cache with a version stamp option, never bare transients (see speedforce conventions).
- Plays nicely with Ace Crawl Enhancer meta (`_ace_seo_*`) and the Ace Redis Cache drop-ins.
- **Bump `ACE_ADS_VERSION` and the `Version:` header on every release** so asset URLs bust caches.

## Layout

- `ace-ads-manager.php` - header, constants, loader.
- `includes/class-ace-ads-manager-settings.php` - option schema + sanitiser. Add a setting by adding one entry to `fields()`.
- `includes/class-ace-ads-manager.php` - plugin core (hooks wired in `__construct`).
- `includes/admin/` - settings page (`class-ace-ads-manager-admin.php` + `views/settings.php`); same two-column
  layout and SaveBar as Ace Crawl Enhancer.
- `src/` - admin JS (wp-scripts). `styles/scss/admin.scss` - compiles to `assets/css/admin.css`.
- `build/` and `assets/css/` are committed: consuming sites do not run a build.

## Build & verify

```bash
npm install
npm run build        # wp-scripts + sass
npm run lint:php     # php8.4 -l over every file
```

Verify on a `.pi` site with `node /var/www/html/pi-verify.cjs https://<site>.pi/wp-admin/...` or in the
WordPress Playground MCP.

## Gotchas

- The block's inspector reads `window.ace_ads_block` (slot list) from an inline script attached to the handle
  `ace-ads-slot-editor-script`, which is the handle core generates for `editorScript` in `build/blocks/slot/block.json`.
  Renaming the block changes the handle.
- `Ace_Ads_Rules::request_context()` is memoised per request; loop/parent context is layered on top in
  `Ace_Ads_Render::context_for()` and never written back.
- Parent-block detection is a render stack (`render_block_data` push, `render_block` pop). It only knows about
  block-rendered ancestors, not PHP template wrappers.
- Settings class is `Ace_Ads_Manager_Settings` (prefix `ace_ads`, class prefix `Ace_Ads_Manager`).
