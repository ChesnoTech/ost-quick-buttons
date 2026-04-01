<?php
/**
 * Quick Buttons Plugin - Main Class
 *
 * @author  ChesnoTech
 * @version 3.2.0
 */

require_once 'config.php';

class QuickButtonsPlugin extends Plugin {
    var $config_class = 'QuickButtonsConfig';

    static private $bootstrapped = false;

    function bootstrap() {
        self::bootstrapStatic();
    }

    /**
     * Run migrations when plugin version changes.
     * Called automatically by osTicket on version mismatch.
     */
    function pre_upgrade(&$errors) {
        self::runMigrations();
        return true;
    }

    /**
     * Run migrations once per version upgrade.
     * Uses a config key to track the last migrated version.
     */
    private static function runMigrationsOnce() {
        $currentVersion = '4.1.0';
        $ns = 'plugin.quick-buttons.meta';
        $res = db_query(sprintf(
            "SELECT value FROM %s WHERE namespace = '%s' AND `key` = 'migrated_version'",
            CONFIG_TABLE, $ns));
        $row = $res ? db_fetch_row($res) : null;
        $migrated = $row ? $row[0] : '0';
        if (version_compare($migrated, $currentVersion, '>='))
            return;
        self::runMigrations();
        // Update or insert the migrated version flag
        if ($migrated === '0') {
            db_query(sprintf(
                "INSERT INTO %s (namespace, `key`, value) VALUES ('%s', 'migrated_version', '%s')",
                CONFIG_TABLE, $ns, $currentVersion));
        } else {
            db_query(sprintf(
                "UPDATE %s SET value = '%s' WHERE namespace = '%s' AND `key` = 'migrated_version'",
                CONFIG_TABLE, $currentVersion, $ns));
        }
    }

    /**
     * Database migrations for version upgrades.
     * Safe to run multiple times — each migration checks before acting.
     */
    static function runMigrations() {
        // v4.1.0: Enable show_deadline on all existing instances that don't have it
        self::migrate_410_showDeadline();
        // v4.1.0: Update stop icon from stacked to emoji checkmark
        self::migrate_410_stopIcon();
    }

    /**
     * v4.1.0: Add show_deadline=1 to all widget instances missing it.
     */
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

    /**
     * v4.1.0: Update button_icon from stacked icon-check+icon-share to icon-ok-sign.
     */
    private static function migrate_410_stopIcon() {
        db_query("UPDATE " . CONFIG_TABLE . " SET value = '{\"icon-ok-sign\":\"OK Sign (Bold Checkmark)\"}'
            WHERE `key` = 'button_icon'
              AND value LIKE '%icon-check+icon-share%'
              AND namespace LIKE 'plugin.%.instance.%'");
    }

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
        // Run migrations once if needed — check a flag in config
        self::runMigrationsOnce();

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

        // Only inject admin assets on plugin admin pages
        $isAdminPage = (strpos($_SERVER['REQUEST_URI'] ?? '', 'plugins.php') !== false);
        $headInject = $css;
        $bodyInject = $js;
        if ($isAdminPage) {
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
