<?php
/**
 * Blocklist configuration for Forbidden Plugins Guard.
 *
 * Edit this file to add, remove, or change the enforcement mode for a
 * plugin. This is the single place the blocklist lives — no hook logic
 * should need to change when this list changes.
 *
 * Keys are plugin slugs (the plugin's folder name under wp-content/plugins/,
 * or its file name for a single-file plugin). 'name' is an optional
 * fallback used to match a plugin by its "Plugin Name" header when the
 * slug can't be determined yet (e.g. a zip upload, before it lands on
 * disk under its final folder name).
 *
 * @package forbidden-plugins-guard
 */

return array(
	'wp-file-manager'         => array(
		'name'   => 'WP File Manager',
		'mode'   => 'block',
		'reason' => __( 'Known security vulnerabilities, not approved.', 'forbidden-plugins-guard' ),
	),
	'duplicator'              => array(
		'name'   => 'Duplicator',
		'mode'   => 'warn',
		'reason' => __( 'Unsupported by hosting team — use at your own risk.', 'forbidden-plugins-guard' ),
	),
	'updraftplus'             => array(
		'name'   => 'UpdraftPlus',
		'mode'   => 'warn',
		'reason' => __( "Pantheon already provides automated backups; this plugin's backups are not retained during standard workflows.", 'forbidden-plugins-guard' ),
	),
	'backwpup'                => array(
		'name'   => 'BackWPup',
		'mode'   => 'warn',
		'reason' => __( "Unsupported backup plugin — its scheduled jobs may conflict with Pantheon's backup tooling.", 'forbidden-plugins-guard' ),
	),
	'all-in-one-wp-migration' => array(
		'name'   => 'All-in-One WP Migration',
		'mode'   => 'block',
		'reason' => __( "Known to conflict with Pantheon's filesystem and backup tooling.", 'forbidden-plugins-guard' ),
	),
);
