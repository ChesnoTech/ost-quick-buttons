<?php

require_once 'config.php';

class QuickButtonsPlugin extends Plugin {
    var $config_class = 'QuickButtonsConfig';

    static private $bootstrapped = false;

    function bootstrap() {
        if (self::$bootstrapped)
            return;
        self::$bootstrapped = true;

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
                url_get('^admin-config-data$', 'getAdminConfigData'),
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

        return $buffer;
    }
}
