<?php
namespace FlagshipWoocommerce\Commands;

class Console {

    public function add_commands() 
    {
        \WP_CLI::add_command('fcs settings', (new Settings_Command()));
        \WP_CLI::add_command('fcs zones', (new Zones_Command()));
    }
}