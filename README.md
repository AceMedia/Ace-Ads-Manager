# Ace Ads Manager

Ace Ads Manager keeps your ads in one place and decides where they appear. You write each ad once, then use placement rules to target it by archive, term, post type, loop position or parent block, all through a single **Ad** block. It also tracks impressions, viewable impressions and clicks.

## Features

- **Ads** custom post type, edited in the block editor, with Offer and Placement rules panels in the sidebar.
- One **Ad** block (`ace-ads/slot`) that resolves to the right ad for the current page, with an "Except" option for everywhere-except-this-category rules.
- In-content injection and an image-only banner format.
- Per-ad link rel, new-tab and minimum-paragraph settings, with start and end dates that respect the site time zone.
- Tracking of impressions, viewable impressions and clicks, with bot and repeat-impression filtering, a daily roll-up and CSV export.
- WP-CLI commands to find and replace pasted offers with the ad block.
- Developer hooks for settings and tracking.

## Requirements

- WordPress 6.4 or later
- PHP 8.1 or later

## Installation

### From a release

1. Download the zip from the [latest GitHub Release](https://github.com/AceMedia/Ace-Ads-Manager/releases/latest).
2. In WordPress, go to **Plugins > Add New > Upload Plugin**, choose the zip and install.
3. Activate **Ace Ads Manager**.

### From source

```bash
git clone https://github.com/AceMedia/Ace-Ads-Manager.git
cd Ace-Ads-Manager
npm install
npm run build
npm run plugin-zip   # optional: package an installable zip
```

Build output is committed, so a plain clone also works without a build.

## Usage

1. Go to **Ads** in the admin menu and create an ad.
2. Fill in the Offer and Placement rules panels in the editor sidebar.
3. Insert the **Ad** block where ads may appear, or let in-content injection place it.
4. Review rules and a what-shows-where preview under **Settings > Ads Manager**.

## Configuration

**Settings > Ads Manager** has the tabs Placements, Analytics, Slots, Rendering, Tracking and Guide.

Tracking uses two tables, `{prefix}ace_ads_events` (pruned nightly after the retention period) and `{prefix}ace_ads_daily` (kept indefinitely). The beacon endpoint is `POST /wp-json/ace-ads/v1/events`. Uninstalling removes options only and leaves tracked data in place.

WP-CLI:

```bash
wp ace-ads find --pattern=<text>
wp ace-ads replace --pattern=<text> --ad=<id> --slot=<slot> --yes
```

Hooks: `ace_ads_settings_fields`, `ace_ads_settings_tabs`, `ace_ads_setting`, `ace_ads_settings_saved`, `ace_ads_track_event`, `ace_ads_event` and `ace_ads_click`.

## Development

```bash
npm install
npm start          # watch build
npm run build      # scripts and CSS
npm run lint:js
```

See [CHANGELOG.md](CHANGELOG.md) for release notes.

## Licence

GPLv2 or later. See `LICENSE`.

## Author and repository

Shane Rounce, [AceMedia](https://acemedia.ninja).
Repository: https://github.com/AceMedia/Ace-Ads-Manager
