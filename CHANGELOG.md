# Changelog

## [2.4.0] - 2026-03-24

### Added
- **Workflow Dashboard** — admin page with 4 metric cards:
  - Tickets processed per day (bar chart)
  - Average time per step (table with duration)
  - Agent leaderboard (ranked by claims)
  - Current queue snapshot (tickets by open status)
- Dashboard supports 7-day, 30-day, 90-day time ranges
- Dark mode support for dashboard
- **Automated test suite** — 53 self-contained tests (no osTicket bootstrap required):
  - choiceKey extraction, JSON parsing, config validation
  - Duration formatting, button resolution logic, undo state
- Dashboard and testing sections in README

## [2.3.0] - 2026-03-24

### Added
- **Live timer** — shows elapsed time since ticket entered current status (on Stop buttons)
- **Undo button** — floating undo bar with 60-second countdown after any action
- **Bulk action toolbar** — Start Selected / Complete Selected buttons appear when tickets are checked
- **First instance bootstrap fix** — static bootstrap ensures assets load even with 0 active instances
- **Server timestamp sync** — timer uses server time offset to avoid client clock drift

### Changed
- Ticket metadata now includes `lastupdate` and `serverNow` for timer calculation
- Execute response includes `canUndo` flag for undo bar display
- Undo stores pre-action state (status, staff, team, dept) in session with 60s expiry

## [2.2.0] - 2026-03-24

### Added
- **Confirmation dialog** before Start/Stop execution (configurable per widget)
- **Error recovery** — rollback claim if status change fails during Start
- **Double-click protection** — client-side debounce prevents duplicate requests
- **Permission-based button visibility** — hides buttons if agent lacks required permissions
- **Customizable labels and colors** — per-widget Start/Stop label and color overrides
- **Bulk action support** — execute Start/Stop on multiple selected tickets
- **Team assignment option** — "Clear Team" checkbox per department on Stop action
- **Agent permissions in API** — widgets endpoint returns canAssign, canTransfer, canRelease, canManage

### Fixed
- Transfer department no longer required (allows mid-chain steps with no transfer)
- Color validation for custom button colors

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
