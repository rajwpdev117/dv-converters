=== DimensionVista Converters ===
Contributors: rajeshkumar-dv
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-2.0-or-later

Server-rendered measurement converters: answer box (fraction first), live converter,
scaled ruler visual, HTML chart, FAQ and schema. Built for dimensionvista.com
(GeneratePress + Rank Math + WP Rocket).

== Install ==

1. Plugins > Add New > Upload Plugin > dv-converters.zip > Activate.
2. Media > Add New: upload mm-to-inches-chart.pdf and copy its URL.
3. Build the page with the shortcodes below (a Shortcode block or a Classic block works).

== Shortcodes ==

[dv_converter from="mm" to="in"]
  Main tool page. Answer box states the exact definition (1 inch = 25.4 mm),
  converter starts at 10 mm.
  Options: default="10" dp="4" fractions="16,32,64" visual="yes"

[dv_converter from="mm" to="in" value="8"]
  Value page. Answer box: "8 mm ≈ 5/16″ · Slightly more than 5/16″ (by 0.06 mm) · ≈ 0.315 in".
  Option: label="8 mm" (breadcrumb text; defaults to the value).

[dv_chart from="mm" to="in" start="1" end="100" highlight="8" pdf="PDF-URL"]
  HTML chart: mm, decimal inches (3 places), nearest 1/16″ and 1/32″ with +/−/= marks.
  Rows link automatically to value pages that exist (slug like 8-mm-to-inches).
  Options: step="1" dp="3" fractions="16,32" link="auto|no" caption="" print="yes"

[dv_chart from="in" to="mm" start="1/64" end="1" step="1/64"]
  Fractional inch to mm chart. These values are exact and shown in full.

[dv_scale value="60" unit="ft"]
  To-scale comparison bars: your length against up to 4 reference lengths (official or nominal,
  e.g. bowling lane 60 ft, baseball pitching distance 60 ft 6 in, 40 ft shipping container).
  Options: refs="bowling,pitch,container40" (default: the nearest shorter and longer ones)
  show="ft,m" (units printed beside each bar). Extend with the dv_converters_scale_refs filter.

[dv_faq]
[dv_q q="How many inches is 8 mm?"]8 mm is about 5/16 inch (slightly more).[/dv_q]
[dv_q q="..."]...[/dv_q]
[/dv_faq]
  Visible FAQ (H2 + H3 questions) and FAQPage schema from the same text.

[dv_facts updated="2026-10-21"]
  Facts line: inch = exactly 25.4 mm with the NIST SP 811 link, precision, free/no sign-up,
  privacy note. "Last updated" appears only when you set updated="" (change it only when
  the content truly changes).

[dv_breadcrumbs]
  Not needed: breadcrumbs are added automatically at the top of the content on any page
  with [dv_converter] (filter dv_converters_auto_breadcrumbs to turn off). Trail: Home › Length › mm to inches › 8 mm. The Length step appears
  only once a page with the slug "length" exists.

== Weight units ==

Units g, kg, lb and oz work in [dv_converter] and [dv_chart] (exact: 1 lb = 0.45359237 kg, 1 oz = 1/16 lb).
Example: [dv_converter from="kg" to="lb" value="20" visual="no" label="20 kg"]. Converting to pounds also
shows "pounds and ounces". Use [dv_facts basis="pound"] for the NIST factor line. No ruler visual for weights.

== Schema ==

With Rank Math active, these nodes join Rank Math's own graph (Organization, WebSite and
WebPage stay as Rank Math makes them):
- WebApplication (free Offer, publisher = #organization, creator = page author)
- BreadcrumbList (replaces Rank Math's breadcrumb node on these pages)
- FAQPage (from [dv_q] items)
Do not also add a Rank Math FAQ block to the same page, or FAQPage is output twice.
Without Rank Math the plugin prints its own JSON-LD.

== WP Rocket ==

- Works with "Delay JavaScript execution": answers, results and chart are already in the HTML,
  and the script picks up anything typed before it loaded.
- If "Remove Unused CSS" is on, add  dv-  to the CSS safelist (classes such as dv-printing are
  added by the script).
- Clear the WP Rocket cache after editing a converter page.

== Adding a pair ==

mm→in, cm→in, in→mm, ft→m and m→ft are registered. Add more with the dv_converters_pairs filter
(units: mm, cm, m, in, ft; add units with dv_converters_units).

== Accuracy ==

All math uses exact integer fractions (no floating point): "=" only when a value is exact,
"≈" otherwise. Rounding is half-up. Checked against an independent implementation for
3,230 inputs (0.1 to 300 mm, 0.25 to 50 cm, inch fractions), and the live JavaScript was
checked to give identical results to the server.

== Tool page clean-up (automatic since 1.0.1) ==

On pages with these shortcodes the plugin removes the GeneratePress sidebar, Easy TOC's
table of contents and Rank Math's default Article schema. Turn each off with the filters
dv_converters_no_sidebar, dv_converters_no_toc, dv_converters_drop_article_schema.

== 1.0.3 ==

- New [dv_scale] to-scale comparison of a length against reference lengths.
- New pairs: feet to meters, meters to feet. Exact note: 1 foot = exactly 0.3048 m.
- [dv_facts basis="foot"] states the foot definition.
- Breadcrumbs link to a tool page only when that page exists.

== 1.0.4 ==

- Tool pages get body class dv-page; Easy TOC is hidden on them (works for posts too).
- Rank Math BlogPosting/NewsArticle nodes are dropped like Article.
- [dv_scale] auto-pick now shows near matches plus the two nearest shorter and longer references.

== 1.0.5 ==

- Restores the GitHub updater that was dropped from 1.0.3 and 1.0.4. Install this version once by upload; later versions update from the WordPress Updates screen.

== 1.1.0 ==

- Weight units (g, kg, lb, oz) in [dv_converter] and [dv_chart], with a pounds-and-ounces row when converting to pounds.
- kg/lb and g/oz tool pairs for breadcrumbs and schema; exact definition on tool pages.
- [dv_facts basis="pound"] states the pound definition. Length output is unchanged.
