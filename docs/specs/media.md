# Improved media (MEDIA)

Status: draft

Delta over free Polylang: with `media_support` on, free Polylang copies title, caption, description and alt text only when a media translation is created, does not propagate later edits, and does not create translations on upload (`pll_enable_duplicate_media` is not applied in free). MEDIA adds propagation of edits, optional auto-creation on upload and a bulk "create missing translations" action. Extends `pll_translate_media`.

## Acceptance criteria
- **MEDIA-01** The module does nothing while Polylang's media support is off.
- **MEDIA-02** Editing title, alt, caption or description of a media item updates its translations that still match the previous value.
- **MEDIA-03** With auto-create on, uploading a file creates translations in every language without duplicating the file on disk.
- **MEDIA-04** A bulk action creates missing media translations for the selected languages and reports a count.
- **MEDIA-05** The module is off by default.
