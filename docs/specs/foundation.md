# Foundation (CORE)

Status: implemented

Delta over free Polylang: none. This is the plugin shell every module relies on. Boots on Polylang's `pll_init`.

## Acceptance criteria
- **CORE-01** When Polylang is not loaded or did not activate, the dependency status is `missing`, no module is registered, and an error notice explains why. No PHP error.
- **CORE-02** When the Polylang version is lower than `TKPLL_MIN_POLYLANG`, the status is `too_old`, no module boots, and an error notice names the required version.
- **CORE-03** When `POLYLANG_PRO` is defined, the status is `pro_active`: modules whose `overlaps_pro()` is true do not boot, other enabled modules do, and an info notice lists the held-back modules.
- **CORE-04** With default settings every module is off and no module's `register()` is called.
- **CORE-05** The registry ignores objects that are not modules, and modules with an invalid or duplicate id.
- **CORE-06** Settings sanitizing keeps only registered module ids and coerces values to booleans.
- **CORE-07** The `tkpll_modules` filter can add modules, and the `tkpll_booted` action fires after enabled modules boot.
- **CORE-08** The settings page is a Languages submenu (`admin.php?page=tkpll`) restricted to `manage_options`, and shows the non-affiliation disclaimer with links to official Polylang documentation and Polylang Pro.
- **CORE-09** The settings page has no serious or critical axe violations and no console errors.
- **CORE-10** Uninstalling removes every `tkpll_*` option, transient and `_tkpll_*` post/term meta, and nothing else.
