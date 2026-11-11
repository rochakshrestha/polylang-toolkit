# Module name (XXX)

Status: draft

<!--
How to use: copy this file to docs/specs/<module>.md, replace XXX with the module prefix and fill
every section. This file itself defines no criteria (the IDs below sit in a comment on purpose).
The owner approves the text first (an `Owner approval: <date>` line under Status). `Status: approved`
itself is set in the tests PR, because gate 5 requires every ID of an approved spec to be cited.
-->

Delta over free Polylang: one paragraph. What free Polylang already does here, and exactly what this module adds. Nothing free Polylang already does may be rebuilt.

## Polylang hooks and functions used
Every entry must already be listed in `docs/polylang-api.md`. WordPress core hooks are listed too.

| Hook or function | Why |
|---|---|
| `pll_example` | … |

## Settings
Keys under `tkpll_settings`, with type and default. "None beyond the on/off switch" is a valid answer.

## Data stored
Every option, transient and meta key the module writes (all prefixed `tkpll_` or `_tkpll_`), so uninstall can be checked. "None" is a valid answer.

## Acceptance criteria
One observable behaviour per criterion, written as Given / When / Then, with exact values where
possible. Gate tags go right after the ID: `[seo]` gate 7, `[coexistence]` gate 8, `[performance]`
gate 11, `[security]` gate 12. A tagged criterion needs a test in that group.

<!--
- **XXX-01** Given …, when …, then ….
- **XXX-02** [security] Given a user without capability …, when …, then nothing is written.
-->

Every module spec includes at least: one `[security]` criterion per write or per read of user input, one `[coexistence]` criterion (off by default; behaviour with Polylang Pro and with Polylang's own options), and one `[performance]` criterion (cost on requests where the module has nothing to do).

## Fixtures
For criteria that transform content: the list of golden cases in `tests/fixtures/<module>/`, as `<case>.input.html` and `<case>.expected.html`. The tests PR creates them from this list.

## Edge cases
Inputs that must not break anything, each with the expected result.

## Non-goals
What this module deliberately does not do in this version, so nobody builds it by accident.
