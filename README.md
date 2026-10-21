# DimensionVista Converters

WordPress plugin for [dimensionvista.com](https://dimensionvista.com): server-rendered measurement converters with an answer box (fraction first), live converter, scaled ruler visual, HTML chart, FAQ and schema (WebApplication, FAQPage, BreadcrumbList via Rank Math).

All conversions use exact integer fractions, so `=` is shown only for exact results and `≈` otherwise. 1 inch is exactly 25.4 mm ([NIST SP 811](https://www.nist.gov/pml/special-publication-811/nist-guide-si-appendix-b-conversion-factors/nist-guide-si-appendix-b9)).

## Shortcodes

See `readme.txt` for the full guide: `[dv_converter]`, `[dv_chart]`, `[dv_faq]` / `[dv_q]`, `[dv_facts]`, `[dv_breadcrumbs]`.

## Updates from this private repo

1. Create a fine-grained personal access token (GitHub > Settings > Developer settings): this repo only, **Contents: read-only**.
2. Add to `wp-config.php`: `define( 'DV_CONVERTERS_GITHUB_TOKEN', 'github_pat_...' );`
3. Publish a **Release** with a tag such as `v1.0.3` (the tag must match the `Version:` header). WordPress then shows the update under Dashboard > Updates.

Uses [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) (MIT) in `lib/`.

## License

GPL-2.0-or-later.
