# Content duplication (DUP)

Status: draft

Delta over free Polylang: Polylang's "+" link opens a new translation that copies taxonomies, selected metas and featured image through its sync (`use_block_editor_for_post` priority 5000), but leaves title, content and excerpt empty. DUP fills those from the source post and swaps media and linked-post IDs for their translations. It runs before priority 5000 and never re-copies what Polylang's sync copies.

## Acceptance criteria
- **DUP-01** Opening a new translation from the "+" link (nonce `new-post-translation`) pre-fills title, content and excerpt from the source post, in the block and the classic editor.
- **DUP-02** Attachment IDs inside blocks (image, gallery, cover, media-text) are replaced by the attachment's translation in the target language when one exists, and left unchanged otherwise.
- **DUP-03** Internal post links and linked-post block attributes are mapped to their translations when one exists.
- **DUP-04** No meta or taxonomy that Polylang's own sync copies is copied a second time.
- **DUP-05** A translation that already has content is never overwritten by duplication.
- **DUP-06** Status, slug and language of the new post are never copied from the source.
- **DUP-07** The action `tkpll_after_duplicate( $source_id, $target_id, $lang )` fires once per duplication so AI translation add-ons can translate the copy.
- **DUP-08** The module is off by default, and off while Polylang Pro is active.
