<?php
/**
 * Shared helper functions for Forbidden Plugins Guard.
 *
 * @package forbidden-plugins-guard
 */

/**
 * Load (and memoize) the blocklist config.
 *
 * @return array
 */
function fpg_get_blocklist() {
	static $blocklist = null;

	if ( null === $blocklist ) {
		$blocklist = require FPG_DIR . '/inc/blocklist.php';
	}

	return $blocklist;
}

/**
 * Fill in defaults for a raw blocklist entry.
 *
 * @param string $slug Plugin slug.
 * @param array  $raw  Raw entry from blocklist.php.
 * @return array
 */
function fpg_normalize_entry( $slug, $raw ) {
	return array(
		'slug'   => $slug,
		'name'   => isset( $raw['name'] ) ? $raw['name'] : '',
		'mode'   => ( isset( $raw['mode'] ) && 'block' === $raw['mode'] ) ? 'block' : 'warn',
		'reason' => isset( $raw['reason'] ) ? $raw['reason'] : '',
	);
}

/**
 * Look up a blocklist entry by slug, falling back to a case-insensitive name match.
 *
 * @param string $slug Plugin slug.
 * @param string $name Plugin display name (fallback match).
 * @return array|null Normalized entry, or null if the plugin isn't blocklisted.
 */
function fpg_lookup_blocklist_entry( $slug, $name = '' ) {
	$blocklist = fpg_get_blocklist();

	if ( $slug && isset( $blocklist[ $slug ] ) ) {
		return fpg_normalize_entry( $slug, $blocklist[ $slug ] );
	}

	if ( $name ) {
		foreach ( $blocklist as $entry_slug => $raw ) {
			if ( ! empty( $raw['name'] ) && 0 === strcasecmp( $raw['name'], $name ) ) {
				return fpg_normalize_entry( $entry_slug, $raw );
			}
		}
	}

	return null;
}

/**
 * Derive a plugin slug from its main file path, e.g. "duplicator/duplicator.php" -> "duplicator".
 *
 * @param string $plugin_file Plugin file path relative to the plugins directory.
 * @return string
 */
function fpg_plugin_file_to_slug( $plugin_file ) {
	$dir = dirname( $plugin_file );

	if ( '.' === $dir ) {
		return basename( $plugin_file, '.php' );
	}

	return $dir;
}

/**
 * Scan a freshly-extracted plugin source directory for a "Plugin Name" header.
 *
 * Used at install time, before the plugin has a final slug on disk.
 *
 * @param string $source Absolute path to the extracted plugin directory.
 * @return string Plugin display name, or an empty string if none was found.
 */
function fpg_find_plugin_name_in_source( $source ) {
	$files = glob( trailingslashit( $source ) . '*.php' );

	if ( empty( $files ) ) {
		return '';
	}

	foreach ( $files as $file ) {
		$data = get_file_data( $file, array( 'Name' => 'Plugin Name' ) );

		if ( ! empty( $data['Name'] ) ) {
			return $data['Name'];
		}
	}

	return '';
}

/**
 * Find every installed plugin that matches the blocklist, optionally filtered by mode and active state.
 *
 * @param string|null $mode   'warn', 'block', or null for either.
 * @param bool|null   $active True for active only, false for inactive only, null for either.
 * @return array Keyed by plugin file, each value has slug/name/entry/active.
 */
function fpg_get_installed_blocklisted_plugins( $mode = null, $active = null ) {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$results        = array();
	$all_plugins    = get_plugins();
	$active_plugins = (array) get_option( 'active_plugins', array() );

	if ( is_multisite() ) {
		$active_plugins = array_merge( $active_plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}

	foreach ( $all_plugins as $plugin_file => $plugin_data ) {
		$slug  = fpg_plugin_file_to_slug( $plugin_file );
		$entry = fpg_lookup_blocklist_entry( $slug, $plugin_data['Name'] );

		if ( null === $entry ) {
			continue;
		}

		if ( null !== $mode && $entry['mode'] !== $mode ) {
			continue;
		}

		$is_active = in_array( $plugin_file, $active_plugins, true );

		if ( null !== $active && $is_active !== $active ) {
			continue;
		}

		$results[ $plugin_file ] = array(
			'slug'   => $slug,
			'name'   => $plugin_data['Name'] ? $plugin_data['Name'] : $slug,
			'entry'  => $entry,
			'active' => $is_active,
		);
	}

	return $results;
}

/**
 * Active plugins currently in warn mode — the set that drives the persistent Plugins-page notice.
 *
 * @return array
 */
function fpg_get_active_warn_violations() {
	return fpg_get_installed_blocklisted_plugins( 'warn', true );
}

/**
 * Build the explanation shown when a block-mode plugin is stopped, either as a
 * WP_Error message at install time or as the wp_die() body at activation time.
 *
 * @param string $slug  Plugin slug.
 * @param string $name  Plugin display name.
 * @param array  $entry Normalized blocklist entry.
 * @return string Escaped HTML.
 */
function fpg_get_block_message( $slug, $name, $entry ) {
	$label = $name ? $name : $slug;

	return sprintf(
		/* translators: 1: plugin name, 2: reason it is blocked. */
		'<p>' . esc_html__( '"%1$s" is blocked by hosting policy and cannot be installed or activated on this site. %2$s', 'forbidden-plugins-guard' ) . '</p>',
		esc_html( $label ),
		esc_html( $entry['reason'] )
	);
}
