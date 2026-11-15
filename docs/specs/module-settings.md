# Module settings (CORE, part 2)

Status: implemented

Owner approval: 2026-11-15. The owner also approved these changes made while writing the tests (2026-11-15):
- `Options::module_settings()` takes the module, not its id, and `HasSettings` extends `ModuleInterface` (the registry is built once per request, so an id lookup would depend on the shared plugin instance).
- The `tab` argument must match a module id exactly instead of going through `sanitize_key()`.
- The form posts `_tab`; a save survives WordPress running the sanitize callback twice on a fresh site.
- "Held back by Polylang Pro" is defined as listed in `Registry::blocked_by_pro()`.

<!-- CORE-11 and CORE-12 extend docs/specs/foundation.md. They live in their own file because
foundation.md is `implemented`, and gate 5 would fail there until their tests exist. -->

Delta over free Polylang: none. This is plugin shell, like `foundation.md`. Today the settings page only has the on/off switches, and `Options::get()` and `Options::sanitize()` keep the `modules` key only, so a module has nowhere to store its own settings, and saving the switches would wipe them. This spec adds a `HasSettings` contract and one settings tab per module that implements it. It does not touch Polylang's own settings screen (`pll_settings_modules`, `pll_settings_tabs`): our page stays under Languages → Toolkit.

## Polylang hooks and functions used
| Hook or function | Why |
|---|---|
| WordPress `register_setting()`, `settings_fields()`, `options.php` | Saving keeps going through the Settings API, which checks the `tkpll-options` nonce and `manage_options`. |
| WordPress `wp_unslash()` | Reading the `tab` query argument, which must then exactly equal the id of a registered `HasSettings` module (no case folding or stripping, so `ALPHA` or `../alpha` is not `alpha`). |

## Contract
`ToolkitForPolylang\Modules\HasSettings`, which extends `ModuleInterface`. A module with settings implements it instead of `ModuleInterface`:

| Method | Returns |
|---|---|
| `default_settings(): array` | The module's settings with their defaults. Its keys are the only keys ever stored for the module. |
| `sanitize_settings( mixed $input ): array` | Clean values for the keys of `default_settings()`, from raw form input. Never trusts `$input`'s shape. |
| `render_settings( array $values, string $name_prefix ): void` | Echoes the tab's form fields, named `<name_prefix>[<key>]`, with escaped output and a `<label>` for each field. |

These methods run on admin screens only, so they may call translation functions.

The settings form posts `tkpll_settings[_tab]`, the tab being saved (`modules` or a module id), so a save only replaces that tab's key. `_tab` is never stored. The save gives the same result when WordPress runs the sanitize callback twice, which it does on the first save of a fresh site (`update_option()`, then `add_option()` with the first result).

"Held back by Polylang Pro" means listed in `Registry::blocked_by_pro()`.

## Settings
None of its own. Module settings are stored under `tkpll_settings[<module id>]`, next to `tkpll_settings[modules]`.

## Data stored
Nothing new: everything stays inside the existing `tkpll_settings` option, so CORE-10 (uninstall) already covers it.

## Acceptance criteria
- **CORE-11** [security] Given registered modules, some of which implement `HasSettings`, when an administrator opens the settings page, then it shows a "Modules" tab (the on/off switches, the default) followed by one tab per `HasSettings` module, in registry order, as links to `admin.php?page=tkpll&tab=<id>`, with `aria-current="page"` on the active tab. A module without `HasSettings` gets no tab. A `tab` value that is not the id of such a module (missing, unknown, or not a valid id) shows the Modules tab. The tab of a module that is switched off, or held back by Polylang Pro, still opens, and says so above its fields.
- **CORE-12** [security] Given a module with `HasSettings`, when its tab is saved through `options.php`, then `tkpll_settings[<id>]` holds exactly what its `sanitize_settings()` returned, the module switches and every other module's settings are unchanged, and when the Modules tab is saved, every module's settings are unchanged. A save without the `tkpll-options` nonce, or by a user without `manage_options`, changes nothing. `Options::module_settings( $module )` returns `default_settings()` with stored values for its keys put over it, ignoring stored keys that `default_settings()` does not have.

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
