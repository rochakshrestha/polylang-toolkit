# Basic synchronization (SYNC)

Status: draft

Delta over free Polylang: free Polylang syncs all custom fields or none (`options['sync']['post_meta']`). SYNC lets the owner choose individual fields, optionally per post type, to keep in sync after the initial copy. Extends `pll_copy_post_metas`.

## Acceptance criteria
- **SYNC-01** Only the custom fields selected in settings are synced between translations; others are untouched.
- **SYNC-02** Rules can be scoped per post type.
- **SYNC-03** The settings UI shows which fields sync and from which language.
- **SYNC-04** Syncing never touches status, slug or language, and cannot recurse (a guard stops a save triggered by sync from syncing again).
- **SYNC-05** With Polylang's own custom-field sync on, no meta is written twice.
- **SYNC-06** The module is off by default.
