# Upgrade Guide

## Upgrading from Quick Actions v1.x to Quick Buttons v2.x

Quick Buttons v2.0 is a complete redesign. The plugin ID, folder name, class names, and configuration model have all changed. **There is no automatic migration** — you must manually recreate your button configurations as widgets.

### What Changed

| Component | v1.x (Quick Actions) | v2.x (Quick Buttons) |
|-----------|---------------------|---------------------|
| Plugin ID | `osticket:quick-actions` | `osticket:quick-buttons` |
| Folder | `plugins/quick-actions/` | `plugins/quick-buttons/` |
| Model | 1 instance = 1 button | 1 instance = 1 widget (Start/Stop pair) |
| Config | Individual fields (label, icon, color, actions) | JSON matrix (per-department status chain) |
| Buttons | Custom label, icon, color per button | Fixed Start (blue play) / Stop (green checkmark) |
| Workflows | Single action per button | Multi-step workflows via widget chaining |

### Step-by-Step Migration

#### 1. Document your current v1.x configuration

For each button instance, note:
- Help Topic
- Department
- Actions (claim, status change, transfer)
- Target status
- Transfer department

#### 2. Install Quick Buttons v2.x

```bash
cd /path/to/osticket/include/plugins/
git clone https://github.com/ChesnoTech/ost-quick-buttons.git quick-buttons
```

#### 3. Activate Quick Buttons

- Go to **Admin Panel > Manage > Plugins**
- Click **Add New Plugin** and select **Quick Buttons**
- Set status to **Active**

#### 4. Create widgets to replace your buttons

For each unique Help Topic, create **one widget instance**:

1. Click **Instances** tab > **Add New Instance**
2. Set **Name** (e.g., "Assembly Workflow") and **Status** = Enabled
3. In **Config** tab, select the **Help Topic**
4. In the department matrix, enable the relevant department(s)
5. Configure the status chain:
   - **Start Trigger** = the status your old "Start" button matched
   - **Start Target** = the status your old "Start" button set
   - **Stop Target** = the status your old "Done" button set
   - **Stop Transfer** = the department your old "Done" button transferred to

#### 5. Deactivate Quick Actions v1.x

- Go to **Admin Panel > Manage > Plugins > Quick Actions**
- Set status to **Disabled**
- Optionally delete old instances

#### 6. Remove Quick Actions v1.x files

```bash
rm -rf /path/to/osticket/include/plugins/quick-actions/
```

#### 7. Verify

- Refresh the ticket queue — Start/Stop buttons should appear
- Test the full workflow: Start → (work) → Stop → verify status and transfer

### Example Migration

**v1.x configuration:**
- Instance 1: "Accept Assembly" — Claim + Status → Platform Build
- Instance 2: "Done Assembly" — Status → Ready for Packing + Transfer to Packing + Release

**v2.x equivalent:**
- Widget "Assembly Workflow" — Help Topic: Marketplace PC Orders
  - Department: Assembly
    - Start Trigger: Collecting Parts
    - Start Target: Platform Build
    - Stop Target: Ready for Packing
    - Stop Transfer: Marketplace Packing

One widget replaces two button instances.

### Database Cleanup (Optional)

Old v1.x config entries remain in the `ost_config` table under the `plugin.XX.instance.YY` namespace (where XX is the old plugin ID). These are harmless orphaned records. To clean them up:

```sql
-- Find old Quick Actions plugin ID
SELECT id FROM ost_plugin WHERE install_path = 'plugins/quick-actions';

-- Delete old config (replace XX with the plugin ID from above)
DELETE FROM ost_config WHERE namespace LIKE 'plugin.XX.instance.%';

-- Delete old instances
DELETE FROM ost_plugin_instance WHERE plugin_id = XX;

-- Delete the plugin record
DELETE FROM ost_plugin WHERE id = XX;
```
