# Translated URL slugs (SLUG)

Status: draft

Delta over free Polylang: free Polylang translates content slugs only per post, and ships a preview-only tile for translated base slugs. SLUG adds per-language post-type and taxonomy base slugs (`/en/products/` and `/fr/produits/`), translatable archive, author, search and feed bases, and lets translations share the same post, page or term slug. Extends `pll_rewrite_rules`, `pll_post_type_link`, `pll_term_link` and `pll_translation_url`.

## Acceptance criteria
- **SLUG-01** A base slug set for a language in settings is used by rewrite rules, `get_permalink` and term links for that language.
- **SLUG-02** `/fr/produits/chaise/` returns the French product and `/fr/products/chaise/` answers 301 to it.
- **SLUG-03** The language switcher and the hreflang set emit the translated URL for every language, absolute and reciprocal, still one set per page (via `pll_rel_hreflang_attributes`).
- **SLUG-04** Canonical URLs self-reference the translated URL.
- **SLUG-05** Core, Yoast SEO and Rank Math sitemaps list translated URLs and are valid XML.
- **SLUG-06** With shared slugs enabled, two translations may use the same slug and each resolves in its own language.
- **SLUG-07** Rewrite rules are flushed once when slug settings change, never per request.
- **SLUG-08** Archive, author, search and feed bases are translatable per language.
- **SLUG-09** The module is off by default, and off while Polylang Pro is active.
