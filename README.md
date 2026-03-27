# ost-quick-buttons

**Workflow automation buttons for osTicket 1.18+**

One-click action buttons in the agent panel queue view. Each ticket row shows a color-coded button based on its current status — Start, Next, or Done. Supports single-step and two-step workflows within a single widget, per-department custom labels, live timers, undo, and a built-in analytics dashboard.

Built by [ChesnoTech](https://github.com/ChesnoTech).

---

## Features

### Queue View Buttons
- **4 button types** — Start (blue), Next (orange), Start Step 2 (blue), Done (green)
- **Status-driven** — buttons appear/disappear automatically based on ticket status
- **Per-department custom labels** — rename buttons to match your workflow (max 12 chars)
- **Live timer badges** — waiting time on Start buttons, elapsed time on Done buttons
- **Sticky column** on desktop, card layout on mobile

### Workflow Variants
- **Single Step** — Start + Done (claim, status change, release, transfer)
- **Two Step** — Start + Next + Start Step 2 + Done (two independent agent phases in one widget)
- No need for multiple widgets per topic — one widget handles both variants per department

### Safety & UX
- **Three confirmation modes** — None (instant), Confirm Dialog, Countdown with cancel
- **Countdown popup** — auto-executes after configurable timer (3-10s) with progress bar
- **Undo** — 60-second window to reverse any action
- **Error recovery** — rollback claim if status change fails
- **Permission-based visibility** — buttons hidden for agents lacking required permissions

### Admin Tools
- **Workflow Builder** — full-page visual editor with status flow diagrams
- **Dashboard** — KPI cards, daily throughput chart, average time per step, agent leaderboard, queue snapshot
- **Date range picker** — 7/30/90 day presets or custom From/To dates
- **Access rules** — dashboard respects osTicket department access and limited-agent mode

### Internationalization
8 languages included: English, Russian, Arabic, Spanish, French, German, Portuguese (BR), Turkish, Chinese (Simplified). Auto-activates based on each agent's osTicket language preference.

---

## How It Works

### Single Step
```
Ticket: "Parts Ready"
    → Agent clicks [▶ Start] → claimed, status → "Platform Build"

Ticket: "Platform Build"
    → Agent clicks [✓ Done] → released, status → "Ready for Packing", transferred to Packing dept
```

### Two Step (same widget, same department)
```
Step 1:
    [▶ Start]  Parts Ready → Platform Build (agent claimed)
    [→ Next]   Platform Build → Case Assembly (agent released, NO transfer)

Step 2:
    [▶ Start]  Case Assembly → Case Assembly Working (new agent claimed)
    [✓ Done]   Case Assembly Working → Ready for Packing (released + transferred)
```

Different agents handle each step. Platform builders see Start on "Parts Ready" tickets, case assemblers see Start on "Case Assembly" tickets — all in the same department.

---

## Requirements

- osTicket **1.18+**
- PHP **7.4+**
- Works with **osTicketAwesome** theme and **default osTicket** theme (auto-detected)

## Installation

```bash
cd /path/to/osticket/include/plugins/
git clone https://github.com/ChesnoTech/ost-quick-buttons.git quick-buttons
```

Then in Admin Panel: **Manage > Plugins > Add New Plugin > Quick Buttons > Active**

## Quick Start

1. **Create a widget**: Plugins > Quick Buttons > Instances > Add New Instance
2. **Instance tab**: Name it, set Status to "Enabled"
3. **Config tab**: Select a Help Topic, set confirmation mode
4. **Open Workflow Builder**: Click the button on the Config tab
5. **Enable a department**: Toggle ON, select variant (Single/Two Step)
6. **Configure statuses**: Pick Trigger → Working → Done from the flow diagram
7. **Set labels**: Custom button text per department (optional, max 12 chars)
8. **Save**: Click Save Changes

## Workflow Builder

The Workflow Builder is a full-page visual editor for configuring department workflows.

### Single Step Card
```
● Assembly                                    [ON]
Variant: Single Step

TRIGGER              WORKING              DONE
[Parts Ready] ──▶── [Platform Build] ──✓── [Ready for Packing]

Labels: [Start] [Done]
Transfer to: [Marketplace Packing]
```

### Two Step Card
```
● Assembly                                    [ON]
Variant: Two Step

STEP 1
TRIGGER              WORKING
[Parts Ready] ──▶── [Platform Build] ──⏩──
Labels: [Build] [Next]

STEP 2
STEP 2 TRIGGER       STEP 2 WORKING       FINAL DONE
[Case Assembly] ──▶── [Case Working] ──✓── [Ready for Packing]
Labels: [Case] [Ship]

Transfer to: [Marketplace Packing]
```

Features: search departments, enable/disable all, clone config, apply templates, inline validation.

## Button Types

| Button | Icon | Color | Actions | When Visible |
|--------|------|-------|---------|-------------|
| **Start** | ▶ Play | Blue #128DBE | Claim + status change | Ticket matches trigger status |
| **Next** | → Arrow | Orange #e67e22 | Release + status change (no transfer) | Two-step: ticket matches working status |
| **Start 2** | ▶ Play | Blue #2980b9 | Claim + status change | Two-step: ticket matches step 2 trigger |
| **Done** | ✓+↪ Stacked | Green #27ae60 | Release + status change + transfer | Ticket matches stop trigger |

## Confirmation Modes

| Mode | Clicks | Safety | Best For |
|------|--------|--------|----------|
| **None** | 1 | Low | Trusted agents |
| **Confirm Dialog** | 2 | High | Critical workflows |
| **Countdown** | 1 | High | High-volume (40+ tickets/day) |

The Countdown popup shows the action description, animated progress bar, and a Cancel button. Auto-executes after the configured number of seconds (3-10).

## Dashboard

Accessible from Admin Panel > Plugins > Quick Buttons > Dashboard tab, or as a standalone page.

### KPI Cards
- **Total Processed** — tickets processed in the selected period
- **Avg Per Day** — average daily throughput
- **Open Tickets** — current queue depth
- **Active Agents** — agents with actions in the period

### Charts & Tables
- **Daily Throughput** — bar chart (weekly rollup for 90-day ranges)
- **Average Time Per Step** — how long tickets spend in each status
- **Agent Leaderboard** — ranked by tickets claimed
- **Current Queue** — open tickets grouped by status

### Date Range
Preset buttons (7/30/90 days) or custom From/To date picker.

### Access Rules
- Agents see only data for their accessible departments
- Access-limited agents see "My Performance" instead of the full leaderboard
- Admins see all data

## Access Control

Buttons respect osTicket's built-in permission system:

| Action | Required Permission |
|--------|-------------------|
| Start / Start 2 (Claim) | `Ticket::PERM_ASSIGN` |
| Status Change | `canManageTickets()` |
| Transfer | `Ticket::PERM_TRANSFER` |
| Release | `Ticket::PERM_RELEASE` |

Agents only see buttons for departments they have access to (primary + extended + managed).

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/quick-buttons/widgets` | POST | Widget configs + ticket metadata for queue buttons |
| `/quick-buttons/execute` | POST | Execute action (start/partial/start2/stop) |
| `/quick-buttons/undo` | POST | Undo last action (60s window) |
| `/quick-buttons/dashboard` | GET | Dashboard data (JSON) with date range support |
| `/quick-buttons/dashboard-page` | GET | Standalone dashboard HTML page |
| `/quick-buttons/workflow-builder` | GET | Workflow Builder page |
| `/quick-buttons/workflow-builder-save` | POST | Save Workflow Builder config |
| `/quick-buttons/admin-config-data` | GET | Departments + statuses for admin UI |
| `/quick-buttons/assets/*` | GET | JS/CSS assets with ETag caching |

All endpoints are prefixed with `/scp/ajax.php`.

## File Structure

```
quick-buttons/
├── plugin.php                      # Plugin manifest
├── config.php                      # Config class + validation
├── class.QuickButtonsPlugin.php    # Bootstrap, routes, asset injection
├── class.QuickButtonsAjax.php      # All API endpoints
├── CONTRIBUTING.md                 # Git Flow branching guide
├── CHANGELOG.md                    # Version history
├── UPGRADE.md                      # Upgrade instructions
├── LICENSE                         # MIT
├── assets/
│   ├── quick-buttons.js            # Queue view: buttons, timers, countdown, undo
│   ├── quick-buttons.css           # Queue view styles (osTicketAwesome)
│   ├── quick-buttons-default.css   # Queue view styles (default theme)
│   ├── quick-buttons-admin.js      # Admin config tab enhancements
│   ├── workflow-builder.js         # Full-page Workflow Builder UI
│   ├── workflow-builder.css        # Workflow Builder styles
│   ├── workflow-dashboard.js       # Dashboard page
│   └── workflow-dashboard.css      # Dashboard styles
├── i18n/LC_MESSAGES/               # Translations
│   ├── ru/quick-buttons.mo.php     # Russian
│   ├── ar/quick-buttons.mo.php     # Arabic
│   ├── es/quick-buttons.mo.php     # Spanish
│   ├── fr/quick-buttons.mo.php     # French
│   ├── de/quick-buttons.mo.php     # German
│   ├── pt/quick-buttons.mo.php     # Portuguese (BR)
│   ├── tr/quick-buttons.mo.php     # Turkish
│   └── zh_CN/quick-buttons.mo.php  # Chinese (Simplified)
└── tests/
    └── QuickButtonsTest.php        # 53 self-contained tests
```

## Compatibility

| Component | Version |
|-----------|---------|
| osTicket | 1.18+ |
| PHP | 7.4+ |
| osTicketAwesome | Revision 3+ (auto-detected) |
| Default osTicket theme | Supported (auto-detected) |

Theme detection is automatic — the plugin checks for the `osta/` directory at runtime and serves the appropriate CSS.

## Adding a Language

Create `i18n/LC_MESSAGES/{locale}/quick-buttons.mo.php` using any existing translation as a template. The file returns a PHP array mapping English strings to translations. See `i18n/LC_MESSAGES/ru/quick-buttons.mo.php` for the full list of 90+ translatable strings.

## Support Policy

This plugin is provided **as-is**, free and open source, with **no guaranteed support**.

- Bug reports: [GitHub Issues](https://github.com/ChesnoTech/ost-quick-buttons/issues) with steps to reproduce
- Feature requests: GitHub Issues (no timeline guarantees)
- Pull requests: Welcome, review may take time
- **No email support, no SLA, no paid support plans**

**Always test in a staging environment before production.** Back up your database before installing or upgrading.

## Disclaimer

THIS SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND. The author is not responsible for any data loss, downtime, or damage caused by use of this plugin. It is your responsibility to test in your environment before production deployment.

This plugin modifies ticket status, assignment, and department via osTicket's built-in API. Actions (claim, transfer, status change) are permanent and trigger osTicket's native notifications.

## License

MIT License. See [LICENSE](LICENSE) for details.

## Author

**ChesnoTech** — [github.com/ChesnoTech](https://github.com/ChesnoTech)
