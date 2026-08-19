<?php
/**
 * Main class for Forbidden Plugins Guard — registers the hooks that enforce the blocklist.
 *
 * @package forbidden-plugins-guard
 */

class Forbidden_Plugins_Guard {

	/**
	 * @var Forbidden_Plugins_Guard|null
	 */
	private static $instance = null;

	/**
	 * @return Forbidden_Plugins_Guard
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_filter( 'upgrader_source_selection', array( $this, 'check_install' ), 10, 4 );
		add_action( 'activate_plugin', array( $this, 'check_activate' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
		add_action( 'network_admin_notices', array( $this, 'render_notices' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_badge' ), 10, 4 );
	}

	/**
	 * Enforce the blocklist at install time. Covers repo search-install, zip upload,
	 * install-by-URL, and WP-CLI — they all route through Plugin_Upgrader and this filter.
	 *
	 * @param string|WP_Error $source        Path to the extracted plugin source.
	 * @param string          $remote_source Path to the unpacked archive.
	 * @param WP_Upgrader     $upgrader      Upgrader instance.
	 * @param array           $hook_extra    Extra arguments, including 'type' => 'plugin'.
	 * @return string|WP_Error
	 */
	public function check_install( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( is_wp_error( $source ) || ! is_string( $source ) || ! is_dir( $source ) ) {
			return $source;
		}

		if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
			return $source;
		}

		$slug  = basename( untrailingslashit( $source ) );
		$name  = fpg_find_plugin_name_in_source( $source );
		$entry = fpg_lookup_blocklist_entry( $slug, $name );

		if ( null === $entry ) {
			return $source;
		}

		if ( 'block' === $entry['mode'] ) {
			return new WP_Error( 'fpg_blocked_plugin', fpg_get_block_message( $slug, $name, $entry ) );
		}

		return $source;
	}

	/**
	 * Enforce the blocklist at activation time. Covers plugins already present on disk
	 * (e.g. dropped in via SFTP) rather than installed through the WordPress UI.
	 *
	 * @param string $plugin       Plugin file relative to the plugins directory.
	 * @param bool   $network_wide Whether the plugin is being network-activated.
	 */
	public function check_activate( $plugin, $network_wide = false ) {
		$slug = fpg_plugin_file_to_slug( $plugin );
		$name = '';

		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false );
			$name        = $plugin_data['Name'];
		}

		$entry = fpg_lookup_blocklist_entry( $slug, $name );

		if ( null === $entry ) {
			return;
		}

		if ( 'block' === $entry['mode'] ) {
			deactivate_plugins( $plugin, true, $network_wide );

			wp_die(
				fpg_get_block_message( $slug, $name, $entry ),
				esc_html__( 'Plugin Activation Blocked', 'forbidden-plugins-guard' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Show warn-mode and block-ambient notices — Plugins page only.
	 */
	public function render_notices() {
		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) ) {
			return;
		}

		foreach ( fpg_get_active_warn_violations() as $violation ) {
			fpg_render_warn_notice( $violation );
		}

		$blocked_on_disk = fpg_get_installed_blocklisted_plugins( 'block', false );

		if ( ! empty( $blocked_on_disk ) ) {
			fpg_render_block_ambient_notice( $blocked_on_disk );
		}
	}

	/**
	 * Add a warn/block badge to a blocklisted plugin's row on the Plugins list page.
	 *
	 * @param array  $plugin_meta Existing row meta links.
	 * @param string $plugin_file Plugin file relative to the plugins directory.
	 * @param array  $plugin_data Plugin header data.
	 * @param string $status      Plugin status context.
	 * @return array
	 */
	public function plugin_row_badge( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		$slug  = fpg_plugin_file_to_slug( $plugin_file );
		$entry = fpg_lookup_blocklist_entry( $slug, isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : '' );

		if ( null === $entry ) {
			return $plugin_meta;
		}

		if ( 'block' === $entry['mode'] ) {
			$plugin_meta[] = '<strong style="color:#b32d2e;">' . esc_html__( 'Blocked by hosting policy', 'forbidden-plugins-guard' ) . '</strong>';
		} else {
			$plugin_meta[] = '<strong style="color:#996800;">' . esc_html__( 'Not officially supported', 'forbidden-plugins-guard' ) . '</strong>';
		}

		return $plugin_meta;
	}
}
