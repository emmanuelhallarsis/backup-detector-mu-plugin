<?php
/**
 * Admin-facing notice and settings-page renderers for Forbidden Plugins Guard.
 *
 * @package forbidden-plugins-guard
 */

/**
 * Print a single admin notice.
 *
 * @param string $type        'warn' or 'block' — controls the notice-warning / notice-error styling.
 * @param string $message     Escaped HTML message body.
 * @param bool   $dismissible Whether to add the core is-dismissible class (client-side only —
 *                             it reappears on the next page load, matching the "reappear each
 *                             admin session" requirement rather than a permanent dismissal).
 */
function fpg_render_notice( $type, $message, $dismissible = false ) {
	$classes = array( 'notice' );
	$classes[] = 'block' === $type ? 'notice-error' : 'notice-warning';

	if ( $dismissible ) {
		$classes[] = 'is-dismissible';
	}

	printf(
		'<div class="%1$s"><p>%2$s</p></div>',
		esc_attr( implode( ' ', $classes ) ),
		wp_kses_post( $message )
	);
}

/**
 * Render the persistent warn-mode notice for a single active plugin.
 *
 * @param array $violation Entry from fpg_get_installed_blocklisted_plugins().
 */
function fpg_render_warn_notice( $violation ) {
	$message = sprintf(
		/* translators: 1: plugin name, 2: reason it is unsupported. */
		esc_html__( '%1$s is not officially supported. Your backups may be deleted without prior notice. %2$s', 'forbidden-plugins-guard' ),
		'<strong>' . esc_html( $violation['name'] ) . '</strong>',
		esc_html( $violation['entry']['reason'] )
	);

	fpg_render_notice( 'warn', $message, true );
}

/**
 * Render the ambient summary notice for block-mode plugins sitting on disk but not activated.
 *
 * @param array $blocked_plugins Entries from fpg_get_installed_blocklisted_plugins().
 */
function fpg_render_block_ambient_notice( $blocked_plugins ) {
	$names = wp_list_pluck( $blocked_plugins, 'name' );

	$message = sprintf(
		/* translators: 1: number of blocked plugins found, 2: comma-separated plugin names. */
		_n(
			'%1$d blocked plugin is currently in your plugins folder but not activated: %2$s',
			'%1$d blocked plugins are currently in your plugins folder but not activated: %2$s',
			count( $blocked_plugins ),
			'forbidden-plugins-guard'
		),
		count( $blocked_plugins ),
		esc_html( implode( ', ', $names ) )
	);

	fpg_render_notice( 'block', $message, false );
}
