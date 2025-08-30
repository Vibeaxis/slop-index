=== VAX Slop Index ===
Contributors: vibeaxis
Tags: readability, content-audit, seo-writing, text-analysis, wordpress-plugin, editor-tool
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.1.2
License: MIT
License URI: https://opensource.org/licenses/MIT

Quantifies synthetic, empty, or manipulative language (AI slop) as a 0–100 score. Paste text or fetch a URL. No iframes.

== Description ==
**VAX Slop Index** scores slop (template-y AI/SEO sludge) using repetition, burstiness, lexical diversity, clichés, hedges, passive voice, buzzwords, paragraph rhythm, and simplicity. High score = more slop.

- Native widget (no iframes). Shortcode: `[vax_slop_index]`
- Hardened fetch (cache, rate-limit, SSRF guard)
- Inline highlights + signal meters
- One-click JSON export and copyable summary

== Installation ==
1. Upload the ZIP via `Plugins → Add New → Upload Plugin`.
2. Activate the plugin.
3. Add `[vax_slop_index]` to any post or page.

== Frequently Asked Questions ==
= Does it send my text anywhere? =
No. Analysis runs client-side. Only the optional URL fetch calls the remote site (via WordPress HTTP API).

= Is this open-source? =
Yes. MIT-licensed.

== Screenshots ==
1. Slop Index widget (score + signals)
2. Highlights and JSON export

== Changelog ==
= 1.1.2 =
* Public release. No-iframe widget, improved tooltips, JSON export.

== Upgrade Notice ==
= 1.1.2 =
Initial public release.
