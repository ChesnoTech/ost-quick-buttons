# Changelog

## [2.1.0] - 2026-03-24

### Added
- Multilanguage (i18n) support for all UI strings
- PHP `__()` translation function used for all user-facing text
- Translated strings passed from PHP to JavaScript via API responses
- Admin matrix headers, dropdown labels, and error messages are now translatable
- Frontend button tooltips ("Start", "Done") are now translatable

## [2.0.0] - 2026-03-24

### Added
- Widget-based architecture: one widget per help topic
- Per-department configuration matrix UI
- Multi-step workflow support via widget chaining
- Auto-detect theme compatibility (osTicketAwesome + default osTicket)
- Status-driven Start/Stop button visibility
- Start action: auto-claim ticket + change status
- Stop action: change status + release agent + optional department transfer
- Desktop sticky column layout
- Mobile responsive card layout
- Dark mode support
- PJAX-safe asset injection
- ETag-based asset caching
- osTicket access role integration (PERM_ASSIGN, PERM_TRANSFER, PERM_RELEASE)

### Architecture
- `plugin.php` — Plugin metadata
- `config.php` — Help topic + JSON widget configuration with validation
- `class.QuickButtonsPlugin.php` — Bootstrap, AJAX routes, output buffer asset injection
- `class.QuickButtonsAjax.php` — REST API: widgets, execute, admin-config-data, asset serving
- `assets/quick-buttons.js` — Queue view rendering with status-based filtering
- `assets/quick-buttons.css` — osTicketAwesome theme styles (SVG icon overrides)
- `assets/quick-buttons-default.css` — Default osTicket theme styles (Font Awesome)
- `assets/quick-buttons-admin.js` — Admin config matrix builder with PJAX support
- `assets/quick-buttons-admin.css` — Admin matrix table styles with dark mode
