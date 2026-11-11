# Content duplication (DUP)

Status: draft

Owner approval: 2026-11-11. The status line changes to `approved` in the tests PR, which adds the pending tests that gate 5 requires for an approved spec.

Delta over free Polylang: Polylang's "+" link opens a new translation and its sync copies taxonomies, the selected metas and the featured image (on `use_block_editor_for_post`, priority 5000), but title, content and excerpt stay empty. DUP fills those three fields from the source post and replaces media and post references inside the content by their translations. It runs before priority 5000 and copies nothing that Polylang's sync copies.

## Polylang hooks and functions used
| Hook or function | Why |
|---|---|
| `PLL()->links->get_data_from_new_post_translation_request()` (through `Adapter::new_post_translation_request()`) | The only source of the source post and target language. It verifies the `new-post-translation` nonce. The module never reads `$_GET['from_post']` or `$_GET['new_lang']` itself. |
| `pll_get_post( $id, $lang )` | Translation of an attachment, post or synced pattern. |
| `pll_is_translated_post_type( $type )` | Whether the post type takes part at all. |
| WordPress `default_title`, `default_content`, `default_excerpt` | Pre-fill the new post. They run in `get_default_post_to_edit()`, before Polylang's sync. |

## Settings
None beyond the module's on/off switch.

## Data stored
None.

## Acceptance criteria
"A new-translation request" below means: `post-new.php` opened from Polylang's "+" link, for which the Adapter returns a source post and a target language.

- **DUP-01** Given a published source post with a title, content and an excerpt, when a user opens a new-translation request for it, then the new post's title, content and excerpt equal the source's (after the replacements of DUP-02 and DUP-03), in the block editor and in the classic editor.
- **DUP-02** Given the source content references attachment `A` and `A` has a translation `A'` in the target language, when the content is duplicated, then every reference to `A` listed in "References replaced" becomes `A'`. Given `A` has no translation in the target language, the reference is left unchanged.
- **DUP-03** Given the source content has an `<a href>` to a same-site post `P` (the URL resolves with `url_to_postid()`) or a synced pattern block `core/block` with `ref` `P`, and `P` has a translation `P'` in the target language, when the content is duplicated, then the link becomes `get_permalink( P' )` with its query string and fragment kept, and the `ref` becomes `P'`. Without a translation both are left unchanged. External URLs are never changed.
- **DUP-04** [coexistence] Given Polylang's sync options for taxonomies, custom fields and featured image are on, when a translation is created with DUP on, then each term relation and each meta row exists exactly once on the new post, with the same values as with DUP off.
- **DUP-05** Duplication only fills the new auto-draft of a new-translation request. Given any post that already exists (any status other than `auto-draft`), no code path of this module changes its title, content or excerpt.
- **DUP-06** The new post's status, slug, author, date, parent and language are never taken from the source by this module.
- **DUP-07** The action `tkpll_after_duplicate( int $source_id, int $target_id, string $lang )` fires exactly once per new-translation request, after the three fields are filled, with the target language slug. It does not fire when nothing was duplicated.
- **DUP-08** [coexistence] The module is off by default. `overlaps_pro()` is true, so it does not boot while `POLYLANG_PRO` is defined. With the module off, a new-translation request gives an empty title, content and excerpt, exactly as without the toolkit.
- **DUP-09** [security] Given a user who cannot `read_post` the source post (for example another author's private post), when that user opens a new-translation request for it, then title, content and excerpt stay empty and `tkpll_after_duplicate` does not fire.
- **DUP-10** [security] Given a request to `post-new.php` with `from_post` and `new_lang` but a missing or invalid nonce, then the module copies nothing. It relies on the Adapter result only.
- **DUP-11** Duplication works for every post type for which `pll_is_translated_post_type()` is true, including a custom post type, and does nothing for other post types. Given the source post is missing or in the trash, nothing is duplicated.
- **DUP-12** Given source content in which nothing can be replaced, the duplicated content is identical to the source byte for byte (no block re-serialization differences, no changed escaping, no added or removed whitespace).
- **DUP-13** [performance] On an admin request that is not a new-translation request, and on any front-end request, the enabled module runs no database query.

### References replaced (DUP-02, DUP-03)
| Where | What changes |
|---|---|
| `core/image` | `id` attribute, and the `wp-image-N` class in its HTML |
| `core/gallery` | inner `core/image` blocks as above; legacy `ids` attribute |
| `core/cover` | `id` |
| `core/media-text` | `mediaId`, and the `wp-image-N` class |
| `core/file`, `core/video`, `core/audio` | `id` |
| `core/block` (synced pattern) | `ref` |
| Classic content | `wp-image-N` classes, `[gallery ids="…"]` |
| Any `<a href>` | same-site post URLs, see DUP-03 |

File URLs (`src`, `href` to an upload) are not changed: a Polylang media translation shares the file of its source. Blocks nested at any depth (group, columns, …) are handled the same way.

## Fixtures
`tests/fixtures/dup/<case>.input.html` and `<case>.expected.html`. IDs are written as placeholders the test resolves: `{{media:a}}` and `{{post:p}}` are source IDs, `{{media:a:fr}}` and `{{post:p:fr}}` their French translations, `{{url:p}}` and `{{url:p:fr}}` permalinks.

| Case | Covers |
|---|---|
| `image` | DUP-02: image block, translated |
| `image-untranslated` | DUP-02: no translation, unchanged |
| `gallery` | DUP-02: gallery with one translated and one untranslated image |
| `cover-media-text` | DUP-02: cover and media-text |
| `file-video-audio` | DUP-02 |
| `nested` | DUP-02: image inside columns inside a group |
| `classic` | DUP-02: classic HTML with `wp-image-N` and `[gallery ids]` |
| `links` | DUP-03: translated link with `?a=1#top`, untranslated link, external link |
| `synced-pattern` | DUP-03: `core/block` with and without a translated `ref` |
| `untouched` | DUP-12: blocks with unicode, escaped JSON (`-`), HTML comments and a custom third-party block |

## Edge cases
- Malformed block markup or invalid block JSON: that block is copied unchanged, no PHP warning.
- An ID attribute that is not a positive integer, or points to a deleted attachment: unchanged.
- Empty source content, title or excerpt: the field stays empty; the others are still filled.
- Source and target language are the same, or the source already has a translation in the target language: whatever Polylang does; the module adds no behaviour of its own.
- Content larger than 1 MB: duplicated without a fatal error or a timeout in the test environment.

## Non-goals
- Translating text. `tkpll_after_duplicate` exists so AI add-ons can do that.
- Copying into a translation that already exists, and bulk duplication (Phase 2).
- Copying metas, taxonomies or the featured image: Polylang's sync owns those.
- Page-builder data stored in post meta (Phase 2).
- Rewriting references inside third-party blocks.
