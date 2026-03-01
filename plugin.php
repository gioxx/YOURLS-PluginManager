<?php
/*
Plugin Name: YOURLS Plugin Manager
Plugin URI: https://github.com/gioxx/YOURLS-PluginManager
Description: Download and install plugins from GitHub repositories directly from the YOURLS admin interface.
Version: 1.1.0
Author: Gioxx
Author URI: https://gioxx.org
Text Domain: yourls-plugin-manager
Domain Path: /languages
*/

if ( !defined( 'YOURLS_ABSPATH' ) ) die();

define( 'YPM_VERSION', '1.1.0' );

$ypm_inc = dirname(__FILE__) . '/inc/';
require_once $ypm_inc . 'helpers.php';
require_once $ypm_inc . 'github-api.php';
require_once $ypm_inc . 'repository-metadata.php';
require_once $ypm_inc . 'installer.php';
require_once $ypm_inc . 'admin-page.php';
require_once $ypm_inc . 'scheduler.php';
