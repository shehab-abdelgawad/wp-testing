<?php
/**
 * Plugin Name: Wp-testing
 * Plugin URI: https://wordpress.org/plugins/wp-testing/
 * Description: Helps to create psychological tests.
 * Version: 0.21.19
 * Author: Alexander Ustimenko
 * Author URI: http://ustimen.co
 * License: GPLv3
 * Requires PHP: 8.3
 * Requires at least: 6.0
 * Text Domain: wp-testing
 * Domain Path: /languages
 */

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/src/bootstrap.php';

$WpTesting_Facade = new WpTesting_Facade(new WpTesting_WordPressFacade(__FILE__));

