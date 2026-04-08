<?php
/*
Plugin Name: YOURLS Advanced Plugin Manager
Plugin URI: https://github.com/gioxx/YOURLS-PluginManager
Description: Download and install plugins from GitHub repositories directly from the YOURLS admin interface.
Version: 1.1.4
Author: Gioxx
Author URI: https://gioxx.org
Text Domain: yourls-plugin-manager
Domain Path: /languages
*/

if ( !defined( 'YOURLS_ABSPATH' ) ) die();

define( 'YPM_VERSION', '1.1.4' );

$ypm_inc = dirname(__FILE__) . '/inc/';
require_once $ypm_inc . 'helpers.php';
require_once $ypm_inc . 'github-api.php';
require_once $ypm_inc . 'repository-metadata.php';
require_once $ypm_inc . 'installer.php';
require_once $ypm_inc . 'admin-page.php';

yourls_add_filter('admin_view_per_page', 'ypm_filter_admin_view_per_page');
yourls_add_filter('admin_sublinks', 'ypm_sort_admin_plugin_sublinks');
