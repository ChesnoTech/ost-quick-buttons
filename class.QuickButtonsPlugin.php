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
        if (self::$bootstrapped)
            return;
        self::$bootstrapped = true;

        self::registerTranslations();

        if (!defined('STAFFINC_DIR'))
            return;

        Signal::connect('ajax.scp', array('QuickButtonsPlugin', 'registerAjaxRoutes'));
        ob_start(array('QuickButtonsPlugin', 'injectAssets'));
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
                url_get('^admin-config-data$', 'getAdminConfigData'),
                url_get('^workflow-builder$', 'serveWorkflowBuilder'),
                url_post('^workflow-builder-save$', 'saveWorkflowBuilder'),
                url_get('^assets/js$', 'serveJs'),
                url_get('^assets/css$', 'serveCss'),
                url_get('^assets/admin-js$', 'serveAdminJs'),
                url_get('^assets/admin-css$', 'serveAdminCss')
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

        $buffer = str_replace('</head>', $css . "\n" . $adminCss . "\n</head>", $buffer);
        $buffer = str_replace('</body>', $js . "\n" . $adminJs . "\n</body>", $buffer);

        // Inject "Workflow" tab into the agent dashboard page
        $buffer = self::injectDashboardTab($buffer);

        return $buffer;
    }

    /**
     * Inject a "Workflow" tab link into the built-in dashboard page.
     * Detects the dashboard by looking for the statistics tab structure.
     */
    static function injectDashboardTab($buffer) {
        // Only inject on the dashboard page (has dashboard-specific tab structure)
        if (strpos($buffer, 'dashboard.php') === false)
            return $buffer;

        // Look for the dashboard sub-navigation to add our link
        $dashUrl = ROOT_PATH . 'scp/ajax.php/quick-buttons/dashboard-page';
        $linkHtml = sprintf(
            '<li><a href="%s" class="no-pjax" target="_blank"><i class="icon-bar-chart"></i> %s</a></li>',
            $dashUrl,
            __('Workflow Dashboard')
        );

        // Inject into the sub-navigation (after "My Profile" link)
        if (strpos($buffer, 'profile.php') !== false) {
            $buffer = str_replace(
                '<a href="profile.php"',
                '<a href="profile.php"',
                $buffer
            );
            // Add to sub_nav list - inject before closing </ul> of sub_nav
            $buffer = preg_replace(
                '/(<ul[^>]*id="sub_nav"[^>]*>.*?)(class="active"[^>]*>.*?<\/a><\/li>)/s',
                '$1$2' . "\n" . $linkHtml,
                $buffer,
                1
            );
        }

        return $buffer;
    }
}

// Static bootstrap
if (defined('STAFFINC_DIR'))
    QuickButtonsPlugin::bootstrapStatic();
