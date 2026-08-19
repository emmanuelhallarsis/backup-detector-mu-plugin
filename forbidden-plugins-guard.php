<?php
/**
 * Plugin Name: Forbidden Plugins Guard (Backup Detector)
 * Description: Blocks or warns on install/activation of plugins that conflict with Pantheon's hosting policies or backup tooling.
 * Version: 1.0.0
 * Author: Pantheon Systems
 * Author URI: https://pantheon.io/
 *
 * @package forbidden-plugins-guard
 */

if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
	return;
}

define( 'FPG_DIR', __DIR__ . '/forbidden-plugins-guard' );

require_once FPG_DIR . '/inc/functions.php';
require_once FPG_DIR . '/inc/admin-notices.php';
require_once FPG_DIR . '/inc/class-forbidden-plugins-guard.php';

Forbidden_Plugins_Guard::instance();
