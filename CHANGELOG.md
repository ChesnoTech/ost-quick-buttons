# Changelog

All notable changes to this project will be documented in this file.
Follows [Semantic Versioning](https://semver.org/): MAJOR.MINOR.PATCH

## [4.2.0] - 2026-04-04

### Added
- **Auto-update from admin UI** — check for updates and apply them directly from the plugin admin page
- Updates tab on plugin main page with version comparison
- One-click update: downloads zip from GitHub, backs up files, extracts and replaces
- AJAX endpoints: `check-update` (GET), `apply-update` (POST) with admin-only access
- Full dark mode support for update card UI

### Changed
- Auto-update now checks `stable` branch (via `GITHUB_BRANCH` constant)
- Branching strategy: `stable`, `rc`, `beta`, `dev` (replaces `master`/`develop`)

## [4.1.1] - 2026-04-04

### Fixed
- **Timer unit order** — reversed from small-then-big to big-then-small (e.g., `6M - 45S` instead of `45S - 6M`)
- Applies to desktop elapsed, mobile elapsed, and mobile deadline counters

## [4.1.0] - 2026-03-28

### Added
- **Admin-confirmed upgrade flow** — schema migrations require admin confirmation via UI banner
- **Automatic backup** before upgrade migrations (database config + plugin files)
- **Upgrade banner** — sticky notification on admin pages when schema update is pending
- Desktop/mobile icon centering improvements
- Mobile button redesign — vertical stack layout with inline rows and larger text

### Changed
- `pre_upgrade()` returns false to prevent auto-upgrade — admin must confirm via banner

## [4.0.0] - 2026-03-27

### Added
- **Workflow Dashboard** — full-page dashboard with KPIs, charts, agent performance grid
- **Deadline countdown timers** — configurable per-instance countdown to SLA deadline
- **Desktop timer badges** — elapsed time and deadline shown on queue buttons
- **Contextual dropdown filtering** for Agent Performance reports
- **Department-to-status mapping** page for accurate agent reports
- Calculated Fields integration — displays CF values in dashboard when plugin is installed

### Changed
- Major redesign of desktop buttons with timer integration
- Dashboard moved to separate tab with dedicated CSS/JS files
- Agent Performance split to own filterable tab

## [3.2.0] - 2026-03-26

### Added
- **Two-step workflow variant** — supports partial/start2 actions for multi-stage workflows
- **Workflow Builder** — full-page card-based UI for editing per-department configuration
- Per-department variant selector (single vs twostep)
- Configurable labels for all action buttons (start, stop, partial, start2, finish)

## [3.1.0] - 2026-03-26

### Added
- **Live timer** — shows elapsed time since ticket entered current status
- **Undo button** — floating undo bar with 60-second countdown after any action
- **Bulk action toolbar** — Start Selected / Complete Selected for checked tickets
- Server timestamp sync to avoid client clock drift

## [3.0.0] - 2026-03-25

### Added
- **Server-side timer calculation** — elapsed time via MySQL TIMESTAMPDIFF
- Mobile timer badge support
- Security gate for claimed tickets

### Fixed
- Timer counts from last status change, not from lastupdate
- MySQL timestamp parsing as local time, not UTC

## [2.5.0] - 2026-03-25

### Fixed
- **Stacked icon centering** — osTicketAwesome svg.css `top: 4px` rule leaked into stacked icon overlay (`icon-share`), pushing it off-center in green Done buttons. Added `top: auto !important` to `.qa-inline-btn .qa-icon-stack i` rule.

## [2.4.0] - 2026-03-24

### Added
- **Workflow Dashboard** — admin page with 4 metric cards:
  - Tickets processed per day (bar chart)
  - Average time per step (table with duration)
  - Agent leaderboard (ranked by claims)
  - Current queue snapshot (tickets by open status)
- Dashboard supports 7-day, 30-day, 90-day time ranges
- Dark mode support for dashboard
- **Automated test suite** — 53 self-contained tests (no osTicket bootstrap required)

## [2.3.0] - 2026-03-24

### Added
- **Live timer** — shows elapsed time since ticket entered current status (on Stop buttons)
- **Undo button** — floating undo bar with 60-second countdown after any action
- **Bulk action toolbar** — Start Selected / Complete Selected buttons appear when tickets are checked
- **Server timestamp sync** — timer uses server time offset to avoid client clock drift

## [2.2.0] - 2026-03-24

### Added
- **Confirmation dialog** before Start/Stop execution (configurable per widget)
- **Error recovery** — rollback claim if status change fails during Start
- **Double-click protection** — client-side debounce prevents duplicate requests
- **Permission-based button visibility** — hides buttons if agent lacks required permissions
- **Customizable labels and colors** — per-widget Start/Stop label and color overrides
- **Bulk action support** — execute Start/Stop on multiple selected tickets
- **Team assignment option** — "Clear Team" checkbox per department on Stop action

### Fixed
- Transfer department no longer required (allows mid-chain steps with no transfer)

## [2.1.0] - 2026-03-24

### Added
- Multilanguage (i18n) support for all UI strings
- PHP `__()` translation function used for all user-facing text

## [2.0.0] - 2026-03-24

### Added
- Widget-based architecture: one widget per help topic
- Per-department configuration matrix UI
- Multi-step workflow support via widget chaining
- Status-driven Start/Stop button visibility
- Start action: auto-claim ticket + change status
- Stop action: change status + release agent + optional department transfer
- Desktop sticky column layout
- Mobile responsive card layout
- Dark mode support
- PJAX-safe asset injection
- ETag-based asset caching
