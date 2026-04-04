<?php
/**
 * Quick Buttons Plugin - Main Class
 *
 * @author  ChesnoTech
 * @version 5.0.0-dev
 */

require_once 'config.php';

class QuickButtonsPlugin extends Plugin {
    var $config_class = 'QuickButtonsConfig';

    const CURRENT_SCHEMA = '5.0.0';
    const GITHUB_REPO = 'ChesnoTech/ost-quick-buttons';
    const GITHUB_BRANCH = 'stable';

    static private $bootstrapped = false;

    function bootstrap() {
        self::bootstrapStatic();
    }

    /**
     * Prevent osTicket's auto-upgrade from running without confirmation.
     * We handle upgrades manually via the admin UI.
     */
    function pre_upgrade(&$errors) {
        // Don't auto-upgrade — let admin confirm via the UI banner
        return false;
    }

    // ================================================================
    //  Upgrade detection & admin banner
    // ================================================================

    /**
     * Check if a database upgrade is pending.
     * Compares migrated_version in DB against CURRENT_SCHEMA.
     */
    static function isUpgradePending() {
        $ns = 'plugin.quick-buttons.meta';
        $res = db_query(sprintf(
            "SELECT value FROM %s WHERE namespace = '%s' AND `key` = 'migrated_version'",
            CONFIG_TABLE, $ns));
        $row = $res ? db_fetch_row($res) : null;
        $migrated = $row ? $row[0] : '0';
        return version_compare($migrated, self::CURRENT_SCHEMA, '<');
    }

    /**
     * Get the currently migrated version from DB.
     */
    static function getMigratedVersion() {
        $ns = 'plugin.quick-buttons.meta';
        $res = db_query(sprintf(
            "SELECT value FROM %s WHERE namespace = '%s' AND `key` = 'migrated_version'",
            CONFIG_TABLE, $ns));
        $row = $res ? db_fetch_row($res) : null;
        return $row ? $row[0] : '0';
    }

    /**
     * Inject an upgrade banner into admin pages when upgrade is pending.
     */
    static function injectUpgradeBanner(&$buffer) {
        if (!self::isUpgradePending())
            return;

        $from = self::getMigratedVersion();
        $to = self::CURRENT_SCHEMA;
        $csrfToken = '';
        if (preg_match('/name="__CSRFToken__"[^>]*value="([^"]+)"/', $buffer, $m))
            $csrfToken = $m[1];

        $banner = '
<div id="qa-upgrade-banner" style="
    position: sticky;
    top: 0;
    z-index: 99999;
    background: linear-gradient(135deg, #ff9800, #f57c00);
    color: #fff;
    padding: 14px 24px;
    margin: 0;
    border-radius: 0;
    font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    gap: 16px;
    min-height: 36px;
    box-sizing: border-box;
">
    <span style="font-size: 24px; flex-shrink:0;">&#9888;</span>
    <div style="flex:1; min-width:0;">
        <strong>Quick Buttons — Database Update Required</strong><br>
        <span style="opacity:0.9;font-size:13px;">
            Schema version <strong>' . htmlspecialchars($from ?: 'none') . '</strong>
            &rarr; <strong>' . htmlspecialchars($to) . '</strong>.
            A backup will be created automatically before upgrading.
        </span>
    </div>
    <button id="qa-upgrade-btn" onclick="QAUpgrade.run()" style="
        background: #fff;
        color: #e65100;
        border: none;
        padding: 10px 24px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        white-space: nowrap;
        flex-shrink: 0;
        box-shadow: 0 1px 4px rgba(0,0,0,0.2);
    ">&#x2B06; Upgrade Now</button>
</div>
<script>
var QAUpgrade = {
    run: function() {
        var btn = document.getElementById("qa-upgrade-btn");
        if (!confirm("This will:\\n\\n1. Backup database config\\n2. Backup plugin files\\n3. Run schema migrations\\n\\nProceed with upgrade?"))
            return;
        btn.disabled = true;
        btn.textContent = "Upgrading...";
        btn.style.opacity = "0.7";
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "ajax.php/quick-buttons/upgrade", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.setRequestHeader("X-CSRFToken", "' . $csrfToken . '");
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        var banner = document.getElementById("qa-upgrade-banner");
                        banner.style.background = "linear-gradient(135deg, #4caf50, #388e3c)";
                        banner.innerHTML = \'<span style="font-size:28px;">&#x2705;</span>\' +
                            \'<div style="flex:1;"><strong>Upgrade Complete!</strong><br>\' +
                            \'<span style="opacity:0.9;font-size:13px;">Schema updated to v\' + res.version +
                            \'. Backups saved to <code>backups/</code> directory.</span></div>\';
                    } else {
                        btn.textContent = "Retry Upgrade";
                        btn.disabled = false;
                        btn.style.opacity = "1";
                        alert("Upgrade failed: " + (res.error || "Unknown error"));
                    }
                } catch(e) {
                    btn.textContent = "Retry Upgrade";
                    btn.disabled = false;
                    btn.style.opacity = "1";
                    alert("Upgrade failed: Invalid response");
                }
            } else {
                btn.textContent = "Retry Upgrade";
                btn.disabled = false;
                btn.style.opacity = "1";
                alert("Upgrade failed: HTTP " + xhr.status);
            }
        };
        xhr.onerror = function() {
            btn.textContent = "Retry Upgrade";
            btn.disabled = false;
            btn.style.opacity = "1";
            alert("Network error during upgrade");
        };
        xhr.send("__CSRFToken__=' . urlencode($csrfToken) . '");
    }
};
</script>';

        // Inject right after <body> so it sits above all page content
        $pos = strpos($buffer, '<body');
        if ($pos !== false) {
            $insertPos = strpos($buffer, '>', $pos);
            if ($insertPos !== false)
                $buffer = substr_replace($buffer, '>' . $banner, $insertPos, 1);
        }
    }

    // ================================================================
    //  Upgrade execution (called via AJAX)
    // ================================================================

    /**
     * Execute the full upgrade: backup + migrate + set version flag.
     * Returns array with success/error status.
     */
    static function executeUpgrade() {
        if (!self::isUpgradePending())
            return array('success' => true, 'version' => self::CURRENT_SCHEMA, 'msg' => 'Already up to date');

        $fromVersion = self::getMigratedVersion();
        $toVersion = self::CURRENT_SCHEMA;
        $ns = 'plugin.quick-buttons.meta';

        // Step 1: Create backups
        $dbOk = self::backupDatabase($fromVersion, $toVersion);
        $filesOk = self::backupFiles($fromVersion, $toVersion);

        if (!$dbOk || !$filesOk)
            return array('success' => false, 'error' => 'Backup failed. Check backups/ directory permissions.');

        // Step 2: Run migrations
        self::runMigrations();

        // Step 3: Set version flag
        if ($fromVersion === '0') {
            db_query(sprintf(
                "INSERT INTO %s (namespace, `key`, value) VALUES ('%s', 'migrated_version', '%s')",
                CONFIG_TABLE, $ns, $toVersion));
        } else {
            db_query(sprintf(
                "UPDATE %s SET value = '%s' WHERE namespace = '%s' AND `key` = 'migrated_version'",
                CONFIG_TABLE, $toVersion, $ns));
        }

        return array('success' => true, 'version' => $toVersion);
    }

    // ================================================================
    //  Backups
    // ================================================================

    /**
     * Backup all plugin-related config rows to a SQL file.
     * Returns true on success.
     */
    private static function backupDatabase($fromVersion, $toVersion) {
        $backupDir = dirname(__FILE__) . '/backups';
        if (!is_dir($backupDir))
            @mkdir($backupDir, 0755, true);

        $timestamp = date('Ymd_His');
        $file = $backupDir . "/db_backup_{$fromVersion}_to_{$toVersion}_{$timestamp}.sql";

        $rows = array();
        $res = db_query("SELECT * FROM " . CONFIG_TABLE
            . " WHERE namespace LIKE 'plugin.%.instance.%'"
            . " OR namespace LIKE 'plugin.quick-buttons.%'"
            . " ORDER BY namespace, `key`");
        if ($res) {
            while ($row = db_fetch_array($res)) {
                $vals = array(
                    db_input($row['namespace']),
                    db_input($row['key']),
                    db_input($row['value']),
                );
                $rows[] = sprintf("(%s, %s, %s)", $vals[0], $vals[1], $vals[2]);
            }
        }

        if ($rows) {
            $sql = "-- Quick Buttons plugin DB backup\n"
                 . "-- Date: " . date('Y-m-d H:i:s') . "\n"
                 . "-- Upgrade: {$fromVersion} -> {$toVersion}\n"
                 . "-- Restore: Run this SQL to revert config changes\n\n"
                 . "-- Delete current plugin configs\n"
                 . "DELETE FROM " . CONFIG_TABLE . " WHERE namespace LIKE 'plugin.%.instance.%'"
                 . " OR namespace LIKE 'plugin.quick-buttons.%';\n\n"
                 . "-- Re-insert original values\n"
                 . "INSERT INTO " . CONFIG_TABLE . " (namespace, `key`, value) VALUES\n"
                 . implode(",\n", $rows) . ";\n";
            return @file_put_contents($file, $sql) !== false;
        }
        return true; // No rows to back up is still success
    }

    /**
     * Backup plugin PHP/JS/CSS files to a timestamped zip or directory.
     * Returns true on success.
     */
    private static function backupFiles($fromVersion, $toVersion) {
        $backupDir = dirname(__FILE__) . '/backups';
        if (!is_dir($backupDir))
            @mkdir($backupDir, 0755, true);

        $timestamp = date('Ymd_His');
        $pluginDir = dirname(__FILE__);
        $filesToBackup = array(
            'plugin.php', 'config.php',
            'class.QuickButtonsPlugin.php', 'class.QuickButtonsAjax.php',
            'assets/quick-buttons.js', 'assets/quick-buttons.css',
        );

        // Try zip first
        if (class_exists('ZipArchive')) {
            $zipFile = $backupDir . "/files_backup_{$fromVersion}_to_{$toVersion}_{$timestamp}.zip";
            $zip = new \ZipArchive();
            if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                foreach ($filesToBackup as $f) {
                    $fullPath = $pluginDir . '/' . $f;
                    if (file_exists($fullPath))
                        $zip->addFile($fullPath, $f);
                }
                $zip->close();
                return file_exists($zipFile);
            }
        }

        // Fallback: copy files
        $copyDir = $backupDir . "/files_{$fromVersion}_to_{$toVersion}_{$timestamp}";
        @mkdir($copyDir, 0755, true);
        @mkdir($copyDir . '/assets', 0755, true);
        $ok = true;
        foreach ($filesToBackup as $f) {
            $src = $pluginDir . '/' . $f;
            if (file_exists($src))
                $ok = $ok && @copy($src, $copyDir . '/' . $f);
        }
        return $ok;
    }

    // ================================================================
    //  Auto-Update from GitHub
    // ================================================================

    /**
     * Fetch remote plugin.php from GitHub and compare versions.
     */
    static function checkForUpdate() {
        $localVersion = self::CURRENT_SCHEMA;
        $url = 'https://raw.githubusercontent.com/' . self::GITHUB_REPO . '/' . self::GITHUB_BRANCH . '/plugin.php';

        $content = self::httpGet($url);
        if (!$content)
            return array('error' => 'Cannot reach GitHub. Check server internet connectivity.');

        if (preg_match("/'version'\s*=>\s*'([^']+)'/", $content, $m)) {
            $remoteVersion = $m[1];
            return array(
                'current'   => $localVersion,
                'latest'    => $remoteVersion,
                'available' => version_compare($remoteVersion, $localVersion, '>'),
            );
        }
        return array('error' => 'Cannot parse remote version');
    }

    /**
     * Download latest zip from GitHub, backup current files, replace, and run upgrade.
     */
    static function applyUpdate() {
        $check = self::checkForUpdate();
        if (isset($check['error']))
            return array('success' => false, 'error' => $check['error']);
        if (empty($check['available']))
            return array('success' => false, 'error' => 'Already up to date');

        $latestVersion = $check['latest'];
        $pluginDir = dirname(__FILE__);

        // 1. Backup current files
        $backupOk = self::backupFiles(self::CURRENT_SCHEMA, $latestVersion);
        if (!$backupOk)
            return array('success' => false, 'error' => 'File backup failed. Check backups/ directory permissions.');

        // 2. Download zip from GitHub
        $zipUrl = 'https://github.com/' . self::GITHUB_REPO . '/archive/refs/heads/' . self::GITHUB_BRANCH . '.zip';
        $zipContent = self::httpGet($zipUrl);
        if (!$zipContent)
            return array('success' => false, 'error' => 'Failed to download update from GitHub.');

        $tmpFile = tempnam(sys_get_temp_dir(), 'qb_update_');
        file_put_contents($tmpFile, $zipContent);

        // 3. Extract zip
        if (!class_exists('ZipArchive'))
            return array('success' => false, 'error' => 'ZipArchive PHP extension required.');

        $zip = new \ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            @unlink($tmpFile);
            return array('success' => false, 'error' => 'Cannot open downloaded zip.');
        }

        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qb_update_' . uniqid();
        @mkdir($tmpDir, 0755, true);
        $zip->extractTo($tmpDir);
        $zip->close();
        @unlink($tmpFile);

        // 4. Find extracted directory (GitHub adds repo-branch prefix)
        $dirs = glob($tmpDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        if (!$dirs) {
            self::recursiveDelete($tmpDir);
            return array('success' => false, 'error' => 'Invalid archive structure.');
        }
        $sourceDir = $dirs[0];

        // 5. Copy new files over current plugin directory
        $copyOk = self::recursiveCopy($sourceDir, $pluginDir);
        self::recursiveDelete($tmpDir);

        if (!$copyOk)
            return array('success' => false, 'error' => 'Failed to copy updated files. Check directory permissions.');

        return array('success' => true, 'version' => $latestVersion);
    }

    /**
     * HTTP GET with cURL fallback.
     */
    private static function httpGet($url) {
        // Try file_get_contents first
        $ctx = @stream_context_create(array('http' => array(
            'timeout'       => 15,
            'follow_location' => 1,
            'user_agent'    => 'osTicket-QuickButtons/' . self::CURRENT_SCHEMA,
        )));
        $content = @file_get_contents($url, false, $ctx);
        if ($content)
            return $content;

        // Fallback: cURL
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'osTicket-QuickButtons/' . self::CURRENT_SCHEMA);
            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($content && $httpCode >= 200 && $httpCode < 400)
                return $content;
        }

        return null;
    }

    /**
     * Recursively copy directory contents.
     */
    private static function recursiveCopy($src, $dst) {
        $dir = opendir($src);
        if (!$dir) return false;
        if (!is_dir($dst))
            @mkdir($dst, 0755, true);
        $ok = true;
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..' || $file === '.git' || $file === 'backups')
                continue;
            $srcPath = $src . DIRECTORY_SEPARATOR . $file;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $file;
            if (is_dir($srcPath)) {
                $ok = $ok && self::recursiveCopy($srcPath, $dstPath);
            } else {
                $ok = $ok && @copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
        return $ok;
    }

    /**
     * Recursively delete a directory.
     */
    private static function recursiveDelete($dir) {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path))
                self::recursiveDelete($path);
            else
                @unlink($path);
        }
        @rmdir($dir);
    }

    // ================================================================
    //  Migrations
    // ================================================================

    /**
     * Database migrations for version upgrades.
     * Safe to run multiple times — each migration checks before acting.
     */
    static function runMigrations() {
        // v4.1.0: Enable show_deadline on all existing instances that don't have it
        self::migrate_410_showDeadline();
        // v4.1.0: Update stop icon from stacked to emoji checkmark
        self::migrate_410_stopIcon();
        // v5.0.0: Convert flat variant configs to dynamic steps arrays
        self::migrate_500_dynamicSteps();
    }

    private static function migrate_410_showDeadline() {
        $res = db_query("SELECT DISTINCT c1.namespace
            FROM " . CONFIG_TABLE . " c1
            WHERE c1.namespace LIKE 'plugin.%.instance.%'
              AND c1.namespace NOT IN (
                  SELECT c2.namespace FROM " . CONFIG_TABLE . " c2
                  WHERE c2.`key` = 'show_deadline'
              )
            GROUP BY c1.namespace");
        if ($res) {
            while ($row = db_fetch_row($res)) {
                db_query(sprintf(
                    "INSERT INTO %s (namespace, `key`, value) VALUES (%s, 'show_deadline', '1')",
                    CONFIG_TABLE,
                    db_input($row[0])
                ));
            }
        }
    }

    private static function migrate_410_stopIcon() {
        db_query("UPDATE " . CONFIG_TABLE . " SET value = '{\"icon-ok-sign\":\"OK Sign (Bold Checkmark)\"}'
            WHERE `key` = 'button_icon'
              AND value LIKE '%icon-check+icon-share%'
              AND namespace LIKE 'plugin.%.instance.%'");
    }

    /**
     * v5.0.0: Convert flat single/twostep variant configs to dynamic steps arrays.
     * Reads each instance's widget_config, converts legacy dept configs, writes back.
     */
    private static function migrate_500_dynamicSteps() {
        $res = db_query("SELECT id, namespace, `key`, value FROM " . CONFIG_TABLE
            . " WHERE `key` = 'widget_config'"
            . "   AND namespace LIKE 'plugin.%.instance.%'");
        if (!$res) return;

        while ($row = db_fetch_array($res)) {
            $raw = strip_tags($row['value'] ?: '');
            $data = @json_decode($raw, true);
            if (!is_array($data) || empty($data['departments'])) continue;

            $changed = false;
            foreach ($data['departments'] as $deptId => &$deptCfg) {
                if (!empty($deptCfg['schema_version']) && (int)$deptCfg['schema_version'] >= 2)
                    continue; // Already migrated
                if (isset($deptCfg['steps']))
                    continue; // Already has steps

                $deptCfg = self::normalizeDeptConfig($deptCfg);
                $changed = true;
            }
            unset($deptCfg);

            if ($changed) {
                $json = json_encode($data, JSON_UNESCAPED_UNICODE);
                db_query(sprintf(
                    "UPDATE %s SET value = %s WHERE id = %d",
                    CONFIG_TABLE, db_input($json), (int)$row['id']
                ));
            }
        }
    }

    /**
     * Convert a legacy flat department config to the v5.0 dynamic steps format.
     * Safe to call on already-converted configs (returns as-is).
     *
     * @param array $deptCfg  Department config (from widget_config.departments[id])
     * @return array  Normalized config with 'steps' array and 'schema_version' = 2
     */
    static function normalizeDeptConfig($deptCfg) {
        // Already in v5 format
        if (!empty($deptCfg['schema_version']) && (int)$deptCfg['schema_version'] >= 2)
            return $deptCfg;
        if (isset($deptCfg['steps']) && is_array($deptCfg['steps']))
            return $deptCfg;

        $variant = $deptCfg['variant'] ?? 'single';
        $steps = array();

        if ($variant === 'twostep') {
            // 4-step: start -> partial -> start2 -> finish
            $steps[] = array(
                'trigger_status' => (string)($deptCfg['start_trigger_status'] ?? ''),
                'target_status'  => (string)($deptCfg['start_target_status'] ?? ''),
                'behavior'       => 'claim',
                'transfer_dept'  => '',
                'clear_team'     => false,
                'label'          => $deptCfg['start_label'] ?? '',
                'icon'           => '',
                'color'          => '',
            );
            $steps[] = array(
                'trigger_status' => (string)($deptCfg['start_target_status'] ?? ''),
                'target_status'  => (string)($deptCfg['step2_trigger_status'] ?? ''),
                'behavior'       => 'release',
                'transfer_dept'  => '',
                'clear_team'     => false,
                'label'          => $deptCfg['partial_label'] ?? '',
                'icon'           => '',
                'color'          => '',
            );
            $steps[] = array(
                'trigger_status' => (string)($deptCfg['step2_trigger_status'] ?? ''),
                'target_status'  => (string)($deptCfg['step2_target_status'] ?? ''),
                'behavior'       => 'claim',
                'transfer_dept'  => '',
                'clear_team'     => false,
                'label'          => $deptCfg['start2_label'] ?? '',
                'icon'           => '',
                'color'          => '',
            );
            $steps[] = array(
                'trigger_status' => (string)($deptCfg['step2_target_status'] ?? ''),
                'target_status'  => (string)($deptCfg['step2_stop_target_status'] ?? ''),
                'behavior'       => 'release',
                'transfer_dept'  => (string)($deptCfg['stop_transfer_dept'] ?? ''),
                'clear_team'     => !empty($deptCfg['step2_clear_team']),
                'label'          => $deptCfg['finish_label'] ?? '',
                'icon'           => '',
                'color'          => '',
            );
        } else {
            // 2-step: start -> stop
            $steps[] = array(
                'trigger_status' => (string)($deptCfg['start_trigger_status'] ?? ''),
                'target_status'  => (string)($deptCfg['start_target_status'] ?? ''),
                'behavior'       => 'claim',
                'transfer_dept'  => '',
                'clear_team'     => false,
                'label'          => $deptCfg['start_label'] ?? '',
                'icon'           => '',
                'color'          => '',
            );
            $steps[] = array(
                'trigger_status' => (string)($deptCfg['start_target_status'] ?? ''),
                'target_status'  => (string)($deptCfg['stop_target_status'] ?? ''),
                'behavior'       => 'release',
                'transfer_dept'  => (string)($deptCfg['stop_transfer_dept'] ?? ''),
                'clear_team'     => !empty($deptCfg['clear_team']),
                'label'          => $deptCfg['stop_label'] ?? '',
                'icon'           => '',
                'color'          => '',
            );
        }

        return array(
            'enabled'        => !empty($deptCfg['enabled']),
            'schema_version' => 2,
            'steps'          => $steps,
        );
    }

    // ================================================================
    //  Standard plugin hooks
    // ================================================================

    static function registerTranslations() {
        if (method_exists('Plugin', 'translate')) {
            list($__, $_N) = Plugin::translate('quick-buttons');
        }
    }

    static function bootstrapStatic() {
        if (self::$bootstrapped)
            return;
        self::$bootstrapped = true;

        self::registerTranslations();

        if (!defined('STAFFINC_DIR'))
            return;

        Signal::connect('ajax.scp', array('QuickButtonsPlugin', 'registerAjaxRoutes'));
        ob_start(array('QuickButtonsPlugin', 'injectAssets'));
    }

    static function registerAjaxRoutes($dispatcher) {
        $dir = INCLUDE_DIR . 'plugins/quick-buttons/';
        $dispatcher->append(
            url('^/quick-buttons/', patterns(
                $dir . 'class.QuickButtonsAjax.php:QuickButtonsAjax',
                url('^widgets$', 'getWidgets'),
                url_post('^execute$', 'execute'),
                url_post('^undo$', 'undo'),
                url_post('^upgrade$', 'executeUpgradeAjax'),
                url_get('^check-update$', 'checkForUpdate'),
                url_post('^apply-update$', 'applyUpdate'),
                url_get('^dashboard$', 'dashboard'),
                url_get('^dashboard-page$', 'serveDashboardPage'),
                url_get('^agent-perf-page$', 'serveAgentPerfPage'),
                url_get('^workflow-builder$', 'serveWorkflowBuilder'),
                url_post('^workflow-builder-save$', 'saveWorkflowBuilder'),
                url_get('^assets/js$', 'serveJs'),
                url_get('^assets/css$', 'serveCss'),
                url_get('^assets/admin-js$', 'serveAdminJs'),
                url_get('^assets/admin-css$', 'serveAdminCss'),
                url_get('^dept-status-map$', 'getDeptStatusMap'),
                url_post('^dept-status-map-save$', 'saveDeptStatusMap')
            ))
        );
    }

    static function injectAssets($buffer) {
        if (!empty($_SERVER['HTTP_X_PJAX']))
            return $buffer;

        if (strpos($buffer, '</head>') === false
                || strpos($buffer, '</body>') === false)
            return $buffer;

        // Inject upgrade banner on admin pages if upgrade is pending
        $isAdminPage = (strpos($_SERVER['REQUEST_URI'] ?? '', 'admin.php') !== false
            || strpos($_SERVER['REQUEST_URI'] ?? '', 'plugins.php') !== false);
        if ($isAdminPage)
            self::injectUpgradeBanner($buffer);

        $base = ROOT_PATH . 'scp/ajax.php/quick-buttons/assets';
        $dir = dirname(__FILE__) . '/assets/';
        $v = max(
            @filemtime($dir . 'quick-buttons.js'),
            @filemtime($dir . 'quick-buttons.css'),
            @filemtime($dir . 'quick-buttons-default.css')
        ) ?: time();

        $css = sprintf(
            '<link rel="stylesheet" type="text/css" href="%s/css?v=%s">',
            $base, $v);
        $js = sprintf(
            '<script type="text/javascript" src="%s/js?v=%s"></script>',
            $base, $v);
        $adminCss = sprintf(
            '<link rel="stylesheet" type="text/css" href="%s/admin-css?v=%s">',
            $base, $v);
        $adminJs = sprintf(
            '<script type="text/javascript" src="%s/admin-js?v=%s"></script>',
            $base, $v);

        $isPluginPage = (strpos($_SERVER['REQUEST_URI'] ?? '', 'plugins.php') !== false);
        $headInject = $css;
        $bodyInject = $js;
        if ($isPluginPage) {
            $headInject .= "\n" . $adminCss;
            $bodyInject .= "\n" . $adminJs;
        }

        $buffer = str_replace('</head>', $headInject . "\n</head>", $buffer);
        $buffer = str_replace('</body>', $bodyInject . "\n</body>", $buffer);

        return $buffer;
    }
}

// Static bootstrap
if (defined('STAFFINC_DIR'))
    QuickButtonsPlugin::bootstrapStatic();
