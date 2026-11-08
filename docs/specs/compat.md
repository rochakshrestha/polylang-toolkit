# Compatibility (COMPAT)

Status: draft

Delta over free Polylang: free Polylang already integrates Yoast SEO. COMPAT makes our modules coexist with AutoPoly, EPICWP AI, PolyTranslate AI, Yoast SEO and Rank Math, and documents hooks for AI add-ons.

## Acceptance criteria
- **COMPAT-01** Duplication and sync never overwrite content that an AI add-on already translated.
- **COMPAT-02** Documented hooks let an AI add-on run after our duplication (`tkpll_after_duplicate`).
- **COMPAT-03** Activating the toolkit next to each listed AI add-on causes no PHP notice or console error.
- **COMPAT-04** Rank Math and Yoast sitemaps and canonical output contain translated URLs when SLUG is on.
