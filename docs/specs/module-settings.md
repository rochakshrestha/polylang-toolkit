# Module settings (CORE, part 2)

Status: draft

Owner approval: 2026-11-15. The status line changes to `approved` in the tests PR, which adds the pending tests that gate 5 requires for an approved spec.

<!-- CORE-11 and CORE-12 extend docs/specs/foundation.md. They live in their own file because
foundation.md is `implemented`, and gate 5 would fail there until their tests exist. -->

Delta over free Polylang: none. This is plugin shell, like `foundation.md`. Today the settings page only has the on/off switches, and `Options::get()` and `Options::sanitize()` keep the `modules` key only, so a module has nowhere to store its own settings, and saving the switches would wipe them. This spec adds a `HasSettings` contract and one settings tab per module that implements it. It does not touch Polylang's own settings screen (`pll_settings_modules`, `pll_settings_tabs`): our page stays under Languages → Toolkit.

## Polylang hooks and functions used
| Hook or function | Why |
|---|---|
| WordPress `register_setting()`, `settings_fields()`, `options.php` | Saving keeps going through the Settings API, which checks the `tkpll-options` nonce and `manage_options`. |
| WordPress `sanitize_key()` | Reading the `tab` query argument. |

## Contract
`ToolkitForPolylang\Modules\HasSettings`, implemented by a module next to `ModuleInterface`:

| Method | Returns |
|---|---|
| `default_settings(): array` | The module's settings with their defaults. Its keys are the only keys ever stored for the module. |
| `sanitize_settings( mixed $input ): array` | Clean values for the keys of `default_settings()`, from raw form input. Never trusts `$input`'s shape. |
| `render_settings( array $values, string $name_prefix ): void` | Echoes the tab's form fields, named `<name_prefix>[<key>]`, with escaped output and a `<label>` for each field. |

These methods run on admin screens only, so they may call translation functions.

## Settings
None of its own. Module settings are stored under `tkpll_settings[<module id>]`, next to `tkpll_settings[modules]`.

## Data stored
Nothing new: everything stays inside the existing `tkpll_settings` option, so CORE-10 (uninstall) already covers it.

## Acceptance criteria
- **CORE-11** [security] Given registered modules, some of which implement `HasSettings`, when an administrator opens the settings page, then it shows a "Modules" tab (the on/off switches, the default) followed by one tab per `HasSettings` module, in registry order, as links to `admin.php?page=tkpll&tab=<id>`, with `aria-current="page"` on the active tab. A module without `HasSettings` gets no tab. A `tab` value that is not the id of such a module (missing, unknown, or not a valid id) shows the Modules tab. The tab of a module that is switched off, or held back by Polylang Pro, still opens, and says so above its fields.
- **CORE-12** [security] Given a module with `HasSettings`, when its tab is saved through `options.php`, then `tkpll_settings[<id>]` holds exactly what its `sanitize_settings()` returned, the module switches and every other module's settings are unchanged, and when the Modules tab is saved, every module's settings are unchanged. A save without the `tkpll-options` nonce, or by a user without `manage_options`, changes nothing. `Options::module_settings( $id )` returns `default_settings()` with stored values for its keys put over it, ignoring stored keys that `default_settings()` does not have.

## Edge cases
| Input | Expected |
|---|---|
| `tkpll_settings[<id>]` is not an array (corrupted option) | `Options::module_settings()` returns the defaults; the tab renders them. |
| A module's id is `modules` | Rejected by the registry like any invalid id, so it cannot overwrite the switches. |
| A form posts keys for a module that is not registered | Dropped on save. |
| A module is removed (its plugin deactivated) | Its stored settings stay until uninstall, and saving any tab keeps them. |

## Non-goals
- No settings import or export.
- No per-language module settings.
- No REST endpoint for settings (`show_in_rest` stays false).
- No JavaScript tabs: each tab is its own page load.
