<?php
namespace FlagshipWoocommerce\Commands;

class Console {

    public function add_commands() 
    {
        \WP_CLI::add_command('fcs', (new Fcs_Command()));
    }
}