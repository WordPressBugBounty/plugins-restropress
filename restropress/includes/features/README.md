# Feature modules (3.5+)

Each folder is one self-contained feature: `includes/features/<slug>/loader.php` is
loaded automatically by `RestroPress::includes()` (see `includes/class-rpress.php`).

Rules:
- Hook into core with actions and filters; register settings through
  `rpress_registered_settings` / `rpress_settings_sections_*` instead of editing
  `register-settings.php`.
- Prefix functions `rpress_` (or a class `RPRESS_<Feature>`), text domain `restropress`.
- Put assets in `assets/features/<slug>/` and enqueue them only where needed.
- Add a CLI test in `tests/<slug>-test.php` (same style as `tests/cart-pricing-test.php`).
