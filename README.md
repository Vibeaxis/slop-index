# VAX Slop Index

Quantifies synthetic, empty, or manipulative language in content — a quick **slop score (0–100)** based on repetition, burstiness, lexical diversity, clichés, hedges, passive voice, buzzwords, paragraph rhythm, and simplicity. _High score = more slop._

- **WordPress shortcode:** `[vax_slop_index]` (native widget, **no iframes**)
- **Status:** Public, MIT-licensed. Single-file plugin.
- **Why:** Kill AI-slop and corporate-speak before it ships.

## Features
- Paste text or **fetch a URL** (with SSRF guard, caching, and rate limiting)
- Inline highlights + signal meters
- One-click JSON export and copyable summary
- Lightweight styles, no external deps

## Install (WordPress)
1. Download the latest release ZIP.
2. `Plugins → Add New → Upload Plugin` and choose the ZIP.
3. Activate, then add `[vax_slop_index]` to any post/page.

## Privacy
- No tracking. No external calls except when **you** fetch a URL for analysis.
- Fetch requests use WordPress HTTP API with SSRF guards.

## Security & Perf Notes
- **SSRF guard:** blocks private/loopback IPs, enforces `http(s)`
- **Rate-limit:** 10 req / 60s / IP for fetch
- **Cache:** 10 min per URL (transients)

## Shortcode
```
[vax_slop_index]
```

## Changelog
### 1.1.2
- Public release, no-iframe widget, improved tooltips and JSON export.

---

**VibeAxis** — [https://vibeaxis.com](https://vibeaxis.com)
