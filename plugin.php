<?php
return array(
    'id'          => 'osticket:quick-buttons',
    'version'     => '2.3.0',
    'name'        => 'Quick Buttons',
    'author'      => 'ChesnoTech',
    'description' => 'Widget-based workflow buttons for the osTicket agent panel queue view. Each widget handles one help topic with per-department Start/Stop button configuration driven by ticket status. Supports multi-step workflows with chained widgets.',
    'url'         => 'https://github.com/ChesnoTech/ost-quick-buttons',
    'ost_version' => '1.18',
    'plugin'      => 'class.QuickButtonsPlugin.php:QuickButtonsPlugin',
);
