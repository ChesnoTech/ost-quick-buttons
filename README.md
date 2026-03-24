# ost-quick-buttons

**Widget-based workflow buttons for osTicket 1.18+**

One-click Start/Stop buttons in the agent panel queue view, driven by ticket status. Supports multi-step workflows with chained widgets — from simple single-step handoffs to complex multi-department assembly lines.

Built by [ChesnoTech](https://github.com/ChesnoTech).

---

## Features

- **Start/Stop buttons** on each ticket row in the queue view
- **Status-driven visibility** — buttons appear/disappear based on ticket status
- **Multi-step workflows** — chain multiple widgets for N-step processes
- **Per-department configuration** — enable/disable buttons per department with a visual matrix UI
- **One widget per help topic** — different workflows for different ticket types
- **Auto-claim on Start** — assigns the ticket to the clicking agent
- **Auto-release + transfer on Stop** — releases agent and moves ticket to the next department
- **osTicket access role integration** — respects department access and permissions
- **Desktop + Mobile responsive** — sticky column on desktop, card layout on mobile
- **Dark mode support**
- **Theme auto-detection** — works with both osTicketAwesome and default osTicket theme

## How It Works

Each plugin instance is a **widget** tied to one help topic. The widget defines Start/Stop behavior per department:

```
Ticket in "Collecting Parts" status
    → Agent clicks [▶ Start] → ticket claimed, status changes to "Platform Build"

Ticket in "Platform Build" status
    → Agent clicks [✓ Done] → agent released, status changes to "Ready for Packing",
      ticket transferred to Packing department
```

### Multi-Step Workflows

Chain multiple widgets on the same help topic for multi-step processes:

```
Widget 1 (Platform Build):
  [▶ Start] Collecting Parts → Platform Build  [✓ Done] → Case Assembly

Widget 2 (Case Assembly):
  [▶ Start] Case Assembly → Case Assembly (Working)  [✓ Done] → Ready for Packing → Transfer
```

Different agents can handle each step. Platform builders pick up "Collecting Parts" tickets, case assemblers pick up "Case Assembly" tickets — all in the same department, driven by status.

## Requirements

- osTicket **1.18+**
- PHP **7.4+**
- Works with **osTicketAwesome** theme (SVG icons) and **default osTicket** theme (Font Awesome)

## Installation

1. Download or clone this repository into your osTicket plugins directory:
   ```
   cd /path/to/osticket/include/plugins/
   git clone https://github.com/ChesnoTech/ost-quick-buttons.git quick-buttons
   ```

2. In osTicket Admin Panel, go to **Manage > Plugins**

3. Click **Add New Plugin** and select **Quick Buttons**

4. Set the plugin status to **Active**

5. Click the **Instances** tab and **Add New Instance** to create your first widget

## Configuration

### Creating a Widget

1. Go to **Admin Panel > Manage > Plugins > Quick Buttons**
2. Click **Instances** tab > **Add New Instance**
3. **Instance tab**: Set name and status to "Enabled"
4. **Config tab**:
   - Select a **Help Topic** (one widget per topic)
   - The **department matrix** appears with all departments
   - Check **Enabled** for each department that should show buttons
   - Configure the status chain for each enabled department:

| Field | Description |
|-------|-------------|
| **Start: Trigger Status** | Ticket status that makes the Start button visible |
| **Start: Target Status** | Status set after clicking Start (also triggers the Stop button) |
| **Stop: Target Status** | Status set after clicking Stop |
| **Stop: Transfer To** | Department to transfer the ticket to (leave empty for no transfer) |

### Button Behavior

| Button | Icon | Color | Actions |
|--------|------|-------|---------|
| **Start** | ▶ Play | Blue (#128DBE) | Claim ticket + Change status |
| **Stop** | ✓ Done | Green (#27ae60) | Change status + Release agent + Transfer (optional) |

### Multi-Step Setup

To create a multi-step workflow, create multiple widget instances for the same help topic:

1. **Widget 1** — handles step 1 (e.g., Platform Build)
   - Stop target = step 2's trigger status
   - Stop transfer = empty (stays in same department)

2. **Widget 2** — handles step 2 (e.g., Case Assembly)
   - Start trigger = Widget 1's stop target
   - Stop transfer = next department

The widgets chain automatically through matching statuses.

## Access Control

- Buttons are shown based on the agent's **department access** (`getDepts()` — primary + extended + managed departments)
- Actions are verified server-side against osTicket permissions:
  - **Start (Claim)**: requires `Ticket::PERM_ASSIGN`
  - **Status Change**: requires `canManageTickets()`
  - **Transfer**: requires `Ticket::PERM_TRANSFER`
  - **Release**: requires `Ticket::PERM_RELEASE`

## File Structure

```
quick-buttons/
├── plugin.php                    # Plugin metadata
├── config.php                    # QuickButtonsConfig — topic + JSON widget config
├── class.QuickButtonsPlugin.php  # Bootstrap, AJAX routes, asset injection
├── class.QuickButtonsAjax.php    # API endpoints: widgets, execute, admin-config-data
└── assets/
    ├── quick-buttons.js          # Queue view button rendering + click handlers
    ├── quick-buttons.css         # Styles for osTicketAwesome theme (SVG icons)
    ├── quick-buttons-default.css # Styles for default osTicket theme (Font Awesome)
    ├── quick-buttons-admin.js    # Admin config matrix UI builder
    └── quick-buttons-admin.css   # Admin matrix table styles
```

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/scp/ajax.php/quick-buttons/widgets` | POST | Get widget configs + ticket metadata |
| `/scp/ajax.php/quick-buttons/execute` | POST | Execute Start/Stop action on tickets |
| `/scp/ajax.php/quick-buttons/admin-config-data` | GET | Get departments + statuses for admin UI |
| `/scp/ajax.php/quick-buttons/assets/*` | GET | Serve JS/CSS assets with ETag caching |

## Compatibility

| Component | Version |
|-----------|---------|
| osTicket | 1.18+ |
| PHP | 7.4+ |
| osTicketAwesome | Revision 3+ (auto-detected) |
| Default osTicket theme | Supported (auto-detected) |

Theme detection is automatic — the plugin checks for the `osta/` directory at runtime and serves the appropriate CSS.

## License

MIT License. See [LICENSE](LICENSE) for details.

## Author

**ChesnoTech** — [github.com/ChesnoTech](https://github.com/ChesnoTech)

*Product Visionary. Systems Architect. Founder.*
