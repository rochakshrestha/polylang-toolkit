# Free Polylang capability inventory

Pinned version: **Polylang 3.9.1** (wordpress.org, requires WP 6.8, PHP 7.4). Built by reading the free plugin's source only. **No Polylang Pro code was read.**

Re-run this inventory whenever `TKPLL_MIN_POLYLANG` or the tested Polylang version changes. If a hook here disappears, every module that cites it must be updated in the same PR.

Rule for agents: before you rely on a hook, function or behaviour, check that it is listed here. If it is not listed, add it (with file path and version) in a `spec-change` PR first.

## Load order and detection

| Fact | Where (3.9.1) |
|---|---|
| Constants `POLYLANG_VERSION`, `POLYLANG_FILE`, `POLYLANG_DIR`, `POLYLANG_BASENAME`, `POLYLANG_ACTIVE` | `polylang.php` |
| Polylang refuses to load (returns early, no `POLYLANG_ACTIVE`) on old WP/PHP | `polylang.php` → `PLL_Usable::can_activate()` |
| Pro defines `POLYLANG_PRO`; free checks it with `defined( 'POLYLANG_PRO' )` | `src/admin/admin-notices.php`, `src/settings/view-about.php` |
| Main init on `plugins_loaded` priority **1** | `src/class-polylang.php:42` |
| `do_action_ref_array( 'pll_init', [ &$polylang ] )` at the end of `init_context()`. Not fired when no context is built: front end with no language, a failed upgrade, or a PHPUnit run (not admin, cron or WP-CLI) unless `PLL_ADMIN` is defined first | `src/class-polylang.php:200-289` |
| `PLL()` returns the context object (`PLL_Admin`, `PLL_Frontend`, `PLL_REST_Request`, `PLL_Settings`) | `src/api.php:754` |
| `PLL()->model`, `PLL()->links_model`, `PLL()->links`, `PLL()->options` | `src/base.php` |
| Admin menu parent slug is **`mlang`** (tabs are `mlang_<tab>`) | `src/admin/admin-base.php:694` |

## Public API functions (`src/api.php`)

`pll_the_languages`, `pll_current_language`, `pll_default_language`, `pll_get_post`, `pll_get_term`, `pll_home_url`, `pll_register_string`, `pll__`, `pll_esc_html__`, `pll_esc_attr__`, `pll_e`, `pll_esc_html_e`, `pll_esc_attr_e`, `pll_translate_string`, `pll_is_translated_post_type`, `pll_is_translated_taxonomy`, `pll_languages_list`, `pll_set_post_language`, `pll_set_term_language`, `pll_save_post_translations`, `pll_save_term_translations`, `pll_get_post_language`, `pll_get_term_language`, `pll_get_post_translations`, `pll_get_term_translations`, `pll_count_posts`, `pll_insert_post`, `pll_insert_term`, `pll_update_post`, `pll_update_term`.

Prefer these over anything else. Everything else goes through `src/Polylang/Adapter.php`.

## Hooks confirmed in 3.9.1

### Lifecycle
| Hook | Type | Where | Notes |
|---|---|---|---|
| `pll_pre_init` | action | `class-polylang.php` | before context objects |
| `pll_init` | action | `class-polylang.php:289` | **our boot point** |
| `pll_language_defined` | action | `frontend/choose-lang.php:137`, `admin/admin-base.php:439`, `rest-request.php:131` | args `( $slug, PLL_Language )` |
| `pll_no_language_defined` | action | frontend | |
| `pll_model`, `pll_links_model`, `pll_context` | filter | `class-polylang.php` | swap core classes. **We never use these** (Pro conflict risk). |

### Posts, sync, duplication
| Hook | Type | Where | Notes |
|---|---|---|---|
| `pll_save_post` | action | `crud-posts.php:151` | `( $post_id, WP_Post, array $translations )` |
| `pll_copy_post_metas` | filter | `modules/sync/sync-post-metas.php:78` | `( $keys, bool $sync, $from, $to, $lang )` — decides which meta keys are copied/synced |
| `pll_copy_term_metas` | filter | `modules/sync/sync-metas.php:142` (dynamic `pll_copy_{type}_metas`) | same signature |
| `pll_translate_post_meta` / `pll_translate_term_meta` | filter | `modules/sync/sync-metas.php:115` (dynamic) | `( $value, $key, $lang, $from, $to )` — map a meta value to the target language |
| `pll_copy_taxonomies` | filter | `modules/sync/sync-tax.php:73` | `( $taxonomies, bool $sync, $from, $to, $lang )` |
| `pll_pre_current_user_can_synchronize_post` / `_term` | filter | `translated-object.php:382` (dynamic) | |
| `pll_get_post_types`, `pll_get_taxonomies` | filter | `translated-post.php:186`, `settings/settings-cpt.php` | which types are translatable |
| `pll_get_new_post_translation_link` | filter | `admin/admin-links.php` | the "+" link |
| `pll_before_post_translation_{lang}` | action | `admin/view-translations-post.php:57` | languages metabox row |

**New-translation request.** The "+" link opens `post-new.php?post_type=…&from_post=ID&new_lang=slug&_wpnonce=…` (nonce action `new-post-translation`). `PLL()->links->get_data_from_new_post_translation_request()` (since 3.7) returns `[ 'from_post' => WP_Post, 'new_lang' => PLL_Language ]` or `[]` and runs `check_admin_referer`. Free Polylang's sync copies taxonomies and metas on the `use_block_editor_for_post` filter at priority **5000**. Its comment says "After content duplication", so **content duplication must run earlier (priority < 5000)**.

### Media
| Fact | Where |
|---|---|
| `media_support` option gates media translation | `src/Options/Business/Media_Support.php` |
| `PLL_Translated_Post::create_media_translation()` copies the attachment post (title, caption = excerpt, description = content), metadata, file, and **alt text** | `translated-post.php:330-390` |
| `pll_translate_media` action `( $src_id, $tr_id, $lang_slug )` after a media translation is created | `translated-post.php:384` |
| `pll_enable_duplicate_media` is **not applied** anywhere in free. Free only *disables* it during `create_media_translation()`, so automatic duplication on upload is not a free feature. | `translated-post.php:340` |
| Edits to alt/caption on one media item are **not** propagated to its translations. | (no code path) |

### URLs, rewrite, SEO
| Hook | Type | Where | Notes |
|---|---|---|---|
| `pll_rewrite_rules` | filter | `links-permalinks.php:204` | list of rewrite-rule filter prefixes Polylang modifies |
| `pll_modify_rewrite_rule` | filter | `links-directory.php:212,227` | per-rule opt-out |
| `pll_post_type_link`, `pll_term_link` | filter | `filters-links.php:131` | final link for a post/term in a language |
| `pll_translation_url` | filter | `frontend/frontend-links.php:178` | `( $url, $lang_slug )` URL of the current page in another language (switcher + hreflang) |
| `pll_pre_translation_url` | filter | frontend-links | short-circuit |
| `pll_get_archive_url`, `pll_language_home_url`, `pll_language_search_url` | filter | links | |
| `pll_check_canonical_url` | filter | frontend canonical redirect | |
| `pll_rel_hreflang_attributes` | filter | `frontend/frontend-filters-links.php:246` | `( array $hreflangs )` — **the only way we change hreflang output**; never echo our own `<link rel="alternate" hreflang>` |
| `pll_the_language_link` | filter | switcher | |

### Settings / admin
`pll_settings_modules`, `pll_settings_tabs`, `pll_settings_active_tab_{tab}`, `pll_admin_current_language`, `pll_can_display_notice`.

## What free Polylang already does (do not rebuild)
- **Sync options** (`options['sync']`): `taxonomies`, `post_meta`, `comment_status`, `ping_status`, `sticky_posts`, `post_date`, `post_format`, `post_parent`, `_wp_page_template`, `menu_order`, `_thumbnail_id` (featured image). Applied when a translation is created and on every save (`modules/sync/`).
- **Media translations** with alt text copied on creation (when `media_support` is on).
- **hreflang** tags in `wp_head`, and multilingual core sitemaps (`modules/sitemaps/`).
- **Yoast SEO integration** (`integrations/wpseo/`): strings, OpenGraph locale, copies Yoast metas through `pll_copy_post_metas`.
- **REST**: only `pll/v1/languages` and `pll/v1/settings` routes (`modules/REST/V1/`). There is no `lang` field on posts.
- **Block editor**: language switcher blocks (`modules/Blocks/`).
- Integrations shipped in free: aqua-resizer, cache, custom-field-template, domain-mapping, **duplicate-post**, jetpack, no-category-base, twenty-seventeen, wp-importer, wp-offload-media, wp-sweep, **wpseo**, yarpp.

## Pro-only previews in free (settings tiles that only advertise Pro)
`modules/translate-slugs/`, `modules/share-slug/`, `modules/machine-translation/`. Each is a `PLL_Settings_Preview_*` tile with no behaviour. Our SLUG module occupies this space. When `POLYLANG_PRO` is defined, SLUG and DUP must switch off.

## Language management (3.9.1)
`PLL()->model->add_language( array $args )` is a documented `@method` on `PLL_Model` (`src/model.php`) mapped by `__call` to `Model\Languages::add()` (`src/Model/Languages.php:185`); returns `PLL_Language|WP_Error`. Args: `name, slug, locale, rtl, term_group, flag`. Used only by test/dev setup (`bin/wp-env-after-start.sh`, PHPUnit `set_up()`), never by plugin code. Activation by WP-CLI sets transient `pll_activation_redirect` (30 s) for the setup wizard (`src/modules/wizard/wizard.php:102`).
