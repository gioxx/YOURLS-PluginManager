<?php
/*
Plugin Name: YOURLS Advanced Plugin Manager
Plugin URI: https://github.com/gioxx/YOURLS-PluginManager
Description: Download and install plugins from GitHub repositories directly from the YOURLS admin interface.
Version: 1.2.0
Author: Gioxx
Author URI: https://gioxx.org
Text Domain: yourls-plugin-manager
Domain Path: /languages
*/

if ( !defined( 'YOURLS_ABSPATH' ) ) die();

define( 'YPM_VERSION', '1.2.0' );
define( 'YPM_GITHUB_OWNER', 'gioxx' );
define( 'YPM_GITHUB_REPO', 'YOURLS-PluginManager' );
define( 'YPM_GITHUB_REPO_URL', 'https://github.com/' . YPM_GITHUB_OWNER . '/' . YPM_GITHUB_REPO );
define( 'YPM_GITHUB_RELEASES_URL', YPM_GITHUB_REPO_URL . '/releases/latest' );

$ypm_inc = dirname(__FILE__) . '/inc/';
require_once $ypm_inc . 'helpers.php';
require_once $ypm_inc . 'github-api.php';
require_once $ypm_inc . 'repository-metadata.php';
require_once $ypm_inc . 'installer.php';
require_once $ypm_inc . 'admin-page.php';

yourls_add_action('admin_notices', 'ypm_show_self_update_notice');
yourls_add_filter('plugin_page_title_plugin_manager', 'ypm_self_update_page_title_with_badge');

yourls_add_filter('admin_view_per_page', 'ypm_filter_admin_view_per_page');
yourls_add_filter('admin_sublinks', 'ypm_sort_admin_plugin_sublinks');
