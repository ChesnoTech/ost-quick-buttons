<?php
return array(
    'id'          => 'osticket:quick-buttons',
    'version'     => '4.3.0-dev',
    'name'        => 'Quick Buttons',
    'author'      => 'ChesnoTech',
    'description' => 'Workflow buttons for the osTicket agent panel queue view. Each widget handles one help topic with per-department Start/Stop configuration driven by ticket status. Supports single-step and two-step workflow variants with configurable labels.',
    'url'         => 'https://github.com/ChesnoTech/ost-quick-buttons',
    'ost_version' => '1.18',
    'plugin'      => 'class.QuickButtonsPlugin.php:QuickButtonsPlugin',
);
