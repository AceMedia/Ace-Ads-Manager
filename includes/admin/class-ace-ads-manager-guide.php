<?php
/**
 * The manual: rendered on the Guide tab and as WordPress help tabs.
 *
 * @package Ace_Ads_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Ads_Manager_Guide {

    /**
     * @return array<string, array{title:string, icon:string, content:string}>
     */
    public static function sections(): array {
        $sections = [
            'start' => [
                'title'   => __( 'Getting started', 'ace-ads-manager' ),
                'icon'    => 'welcome-learn-more',
                'content' => '
<p>Ads are content, not embeds. An <strong>Ad</strong> is a post with a headline, copy, offer code, link and an optional image. You place <strong>one Ad block</strong> wherever you want a slot, and <strong>placement rules</strong> on each ad decide which ad fills that slot on which pages. Change the ad once and every placement updates.</p>
<ol>
<li><strong>Ads → Add new.</strong> Title is the headline, the editor content is the copy. Fill in the Offer box (code, link, dates) and add at least one placement rule.</li>
<li><strong>Put an Ad block</strong> in a template, a pattern or a post, and pick its slot. Leave the ad on "resolve by rules".</li>
<li><strong>Check Placements</strong> here to see what resolves where, and <strong>Analytics</strong> once traffic arrives.</li>
</ol>
<p>The in-content slot needs no block at all: switch on automatic injection on the Slots tab and it appears after N paragraphs.</p>',
            ],
            'ads' => [
                'title'   => __( 'Creating an ad', 'ace-ads-manager' ),
                'icon'    => 'megaphone',
                'content' => '
<table>
<tr><th>Title</th><td>The headline shown on the ad</td></tr>
<tr><th>Content</th><td>The copy. Keep it to a sentence or two; the terms link can live here</td></tr>
<tr><th>Featured image</th><td>Optional. Background behind the copy (default), above the copy, or hidden</td></tr>
<tr><th>Offer code</th><td>Shown as "Code: XXXX" when set</td></tr>
<tr><th>Link</th><td>Where the call to action goes. No link, no button</td></tr>
<tr><th>Call to action</th><td>Button label; falls back to the default from the Rendering tab</td></tr>
<tr><th>Start / End</th><td>The ad only shows inside this window (site time zone). Empty means open-ended</td></tr>
<tr><th>Link rel</th><td>Follow the site default, or force nofollow/sponsored on or off for this ad</td></tr>
</table>
<p>An ad is <strong>live</strong> when it is published and inside its window. Drafts and expired ads never render, but their rules still appear in Placements greyed out so you can see what will come back.</p>',
            ],
            'rules' => [
                'title'   => __( 'Placement rules', 'ace-ads-manager' ),
                'icon'    => 'randomize',
                'content' => '
<p>A rule says: put this ad in <strong>slot</strong> X when <strong>all</strong> of these targets match. Values within one target are OR (any of these categories); separate targets are AND (this category <em>and</em> this post type).</p>
<table>
<tr><th>Everywhere</th><td>Any page. Use with a low priority as the fallback</td></tr>
<tr><th>Front page / Blog index / Search / Date / 404</th><td>Those views</td></tr>
<tr><th>Single post of type</th><td>Singular views of the chosen post types</td></tr>
<tr><th>Post type archive</th><td>The archive of the chosen post types</td></tr>
<tr><th>Term</th><td>The term archive <em>and</em> any post in the term. Parents count, so a rule on Football matches a post in Premier League</td></tr>
<tr><th>Any archive of taxonomy</th><td>Every term archive of that taxonomy</td></tr>
<tr><th>Specific posts</th><td>By id</td></tr>
<tr><th>Author archive</th><td>Optionally limited to author ids</td></tr>
</table>
<p><strong>Loop item</strong> targets a position inside a Query Loop: <code>3</code> is the third card only, <code>3n</code> is every third. The Ad block must sit inside the loop\'s Post Template for this to apply.</p>
<p><strong>Inside block</strong> restricts the rule to Ad blocks whose direct parent is that block, e.g. <code>core/group</code> or a sidebar template part. Handy when the same slot name is used in two places.</p>
<p><strong>Priority</strong> decides between competing ads: highest wins, then the more specific rule (more targets, more values), then the newest ad. Use 10 for normal rules, 1 for fallbacks, 100 for a takeover.</p>',
            ],
            'block' => [
                'title'   => __( 'The Ad block and slots', 'ace-ads-manager' ),
                'icon'    => 'layout',
                'content' => '
<p>Slots are named positions defined on the Slots tab. The defaults are top, in-content, sidebar and bottom; add your own as <code>slug|Label</code>.</p>
<p>The Ad block has two settings: the <strong>slot</strong> it represents and, optionally, a <strong>pinned ad</strong>. Leave the ad unpinned and rules decide. Pin an ad when an author wants a specific offer in a specific post; that placement is attributed to the author in analytics rather than to a rule.</p>
<p>In the editor the block shows a live preview of what resolves. In the Site Editor there is no post to resolve against, so it shows a placeholder.</p>
<p>The <strong>in-content</strong> slot can also be injected automatically after N paragraphs on the post types you choose. It is skipped when the post already has an Ad block, or is shorter than the minimum paragraph count.</p>',
            ],
            'tracking' => [
                'title'   => __( 'Tracking and analytics', 'ace-ads-manager' ),
                'icon'    => 'chart-line',
                'content' => '
<p>Three events are recorded per ad, per placement:</p>
<table>
<tr><th>Impression</th><td>The ad was rendered in a browser. One per ad per page view</td></tr>
<tr><th>Viewable</th><td>At least 50% of the ad was on screen for a continuous second (the IAB / MRC standard used by Flashtalking and Google)</td></tr>
<tr><th>Click</th><td>A reader followed the link</td></tr>
</table>
<p>Each event carries the slot, the rule that placed it (or "pinned"), the page it appeared on, its post type and terms, the device class, the referrer host and a hashed visitor key. The visitor key is a salted hash of IP and user agent, rotated daily, kept only to de-duplicate impressions and to give a rough unique count; no personal data is stored.</p>
<p>Events are sent from the browser with <code>sendBeacon</code>, so they work behind full-page caching and do not slow the page. Known bots, headless browsers and (by default) logged-in users are excluded. Raw events are pruned after the retention period; daily totals per ad, slot and page are kept.</p>
<p><strong>Analytics</strong> shows impressions, viewable impressions, viewability rate, clicks, CTR and unique visitors, broken down by ad, slot, page, day and device, for any date range, with CSV export.</p>
<p>Every event also fires <code>ace_ads_event</code> (and clicks fire <code>ace_ads_click</code>) so a site can forward it: on a betting site, into the bet-link click log against the article byline.</p>',
            ],
            'rendering' => [
                'title'   => __( 'Rendering and styling', 'ace-ads-manager' ),
                'icon'    => 'art',
                'content' => '
<p>An ad renders as:</p>
<pre>&lt;aside class="ace-ad ace-ad--top ace-ad--image-background" data-ace-ad="12" data-ace-slot="top" data-ace-rule="0"&gt;
  &lt;div class="ace-ad__body"&gt;
    &lt;h3 class="ace-ad__title"&gt;Bet £5 get £40&lt;/h3&gt;
    &lt;div class="ace-ad__copy"&gt;…&lt;/div&gt;
    &lt;p class="ace-ad__code"&gt;Code: &lt;strong&gt;PP40&lt;/strong&gt;&lt;/p&gt;
    &lt;a class="ace-ad__cta" href="…" rel="nofollow sponsored noopener"&gt;Claim offer&lt;/a&gt;
  &lt;/div&gt;
&lt;/aside&gt;</pre>
<p>Real text and a real link: indexable, accessible, and not something an ad blocker strips. Theme it from your stylesheet with the <code>.ace-ad</code> classes or the custom properties <code>--ace-ad-bg</code>, <code>--ace-ad-fg</code>, <code>--ace-ad-accent</code>, <code>--ace-ad-radius</code>. The background image arrives as <code>--ace-ad-image</code>.</p>
<p>Filter <code>ace_ads_render_html</code> to change the markup entirely.</p>',
            ],
            'migration' => [
                'title'   => __( 'Migrating pasted offers', 'ace-ads-manager' ),
                'icon'    => 'migrate',
                'content' => '
<p>Old offers pasted into posts by hand can be found and swapped for Ad blocks from the command line. It is read-only until you say otherwise.</p>
<pre>wp ace-ads find --pattern=\'Bet 5 Get 30\' --post-type=post
wp ace-ads replace --pattern=\'&lt;the pasted markup&gt;\' --ad=12 --slot=in-content            # dry run
wp ace-ads replace --pattern=\'&lt;the pasted markup&gt;\' --ad=12 --slot=in-content --yes      # apply</pre>
<p>Each match becomes <code>&lt;!-- wp:ace-ads/slot {"slot":"in-content","adId":12} /--&gt;</code>, so the offer is edited in one place from then on. The run is grouped under one Ace Revisions batch id and every post keeps a revision.</p>',
            ],
            'hooks' => [
                'title'   => __( 'Hooks for developers', 'ace-ads-manager' ),
                'icon'    => 'admin-plugins',
                'content' => '
<table>
<tr><th><code>ace_ads_slots</code></th><td>Filter the slot list</td></tr>
<tr><th><code>ace_ads_target_types</code>, <code>ace_ads_target_matches</code></th><td>Add custom target types</td></tr>
<tr><th><code>ace_ads_request_context</code>, <code>ace_ads_slot_context</code></th><td>Adjust the context rules match against</td></tr>
<tr><th><code>ace_ads_resolved</code></th><td>Override the winning ad for a slot</td></tr>
<tr><th><code>ace_ads_is_live</code></th><td>Extra live conditions for an ad</td></tr>
<tr><th><code>ace_ads_render_html</code>, <code>ace_ads_empty_slot</code></th><td>Markup</td></tr>
<tr><th><code>ace_ads_event</code></th><td>Action on every tracked event (type, ad_id, data)</td></tr>
<tr><th><code>ace_ads_click</code></th><td>Action on a click (ad_id, post_id, slot, data)</td></tr>
<tr><th><code>ace_ads_track_event</code></th><td>Filter: return false to drop an event before it is stored</td></tr>
<tr><th><code>ace_ads_cache_invalidated</code></th><td>Action after the resolver cache version bumps</td></tr>
</table>',
            ],
        ];
        return apply_filters( 'ace_ads_guide_sections', $sections );
    }

    public static function render(): void {
        echo '<div class="ace-guide">';
        foreach ( self::sections() as $id => $section ) {
            printf( '<section id="guide-%1$s"><h3><span class="dashicons dashicons-%2$s" aria-hidden="true"></span>%3$s</h3>%4$s</section>', esc_attr( $id ), esc_attr( $section['icon'] ), esc_html( $section['title'] ), wp_kses_post( $section['content'] ) );
        }
        echo '</div>';
    }

    /**
     * Help tabs on the Ad edit screen and list.
     */
    public static function post_screen_help(): void {
        $screen = get_current_screen();
        if ( ! $screen || Ace_Ads_Manager::POST_TYPE !== $screen->post_type ) {
            return;
        }
        $sections = self::sections();
        foreach ( [ 'ads', 'rules', 'block' ] as $id ) {
            $screen->add_help_tab( [ 'id' => 'ace-ads-' . $id, 'title' => $sections[ $id ]['title'], 'content' => wp_kses_post( $sections[ $id ]['content'] ) ] );
        }
        $screen->set_help_sidebar( '<p><a href="' . esc_url( Ace_Ads_Manager_Admin::url( 'guide' ) ) . '">' . esc_html__( 'Full guide', 'ace-ads-manager' ) . '</a></p>' );
    }
}

class_alias( Ace_Ads_Manager_Guide::class, 'Ace_Ads_Guide' );
