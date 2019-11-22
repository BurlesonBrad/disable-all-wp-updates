<?php
/**
 * Plugin Name: Disable Updates Grow Leafs
 * Plugin URI:	https://nuleafnaturals.com
 * Description: Make Leafs. Not War.
 * Author:		Thomas Griffin
 * Author URI:	https://nuleafnaturals.com
 * Version:		2.0.0
 *
 * Disable All WP Updates is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Disable All WP Updates is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Disable All WP Updates. If not, see <http://www.gnu.org/licenses/>.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 *
 * @since 1.0.0
 *
 * @package Disable_All_WP_Updates
 * @author	Thomas Griffin
 */
class Disable_All_WP_Updates {

	/**
	 * Holds the current WP version.
	 *
	 * @since 1.0.0
	 *
	 * @var bool|int
	 */
	public $version = false;

	/**
	 * Holds all the registered themes.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public $themes = array();

	/**
	 * Holds all the registered plugins.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public $plugins = array();

	/**
	 * Initializes the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @global int $wp_version The current WP version.
	 */
	public function __construct() {
		// Set WP version.
		global $wp_version;
		$this->wp_version = $wp_version;

		// Possibly define constants to prevent automatic updates.
		if ( ! defined( 'AUTOMATIC_UPDATER_DISABLED' ) ) {
			define( 'AUTOMATIC_UPDATER_DISABLED', true );
		}

		if ( ! defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			define( 'WP_AUTO_UPDATE_CORE', false );
		}

		// Remove hooks and cron checks.
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// Disable plugins from hooking into plugins_api.
		remove_all_filters( 'plugins_api' );

		// Further disable theme update checks.
		add_filter( 'pre_site_transient_update_themes', array( $this, 'pre_update_themes' ) );
		add_filter( 'site_transient_update_themes', array( $this, 'update_themes' ) );
		add_filter( 'transient_update_themes', array( $this, 'update_themes' ) );

		// Further disable plugin update checks.
		add_filter( 'pre_site_transient_update_plugins', array( $this, 'pre_update_plugins' ) );
		add_filter( 'site_transient_update_plugins', array( $this, 'update_plugins' ) );
		add_filter( 'transient_update_plugins', array( $this, 'update_plugins' ) );

		// Further disable core update checks.
		add_filter( 'pre_site_transient_update_core', array( $this, 'pre_update_core' ) );
		add_filter( 'site_transient_update_core', array( $this, 'update_core' ) );

		// Disable even other external updates related to core.
		add_filter( 'auto_update_translation', '__return_false' );
		add_filter( 'automatic_updater_disabled', '__return_true' );
		add_filter( 'allow_minor_auto_core_updates', '__return_false' );
		add_filter( 'allow_major_auto_core_updates', '__return_false' );
		add_filter( 'allow_dev_auto_core_updates', '__return_false' );
		add_filter( 'auto_update_core', '__return_false' );
		add_filter( 'wp_auto_update_core', '__return_false' );
		add_filter( 'auto_update_plugin', '__return_false' );
		add_filter( 'auto_update_theme', '__return_false' );
		add_filter( 'auto_core_update_send_email', '__return_false' );
		add_filter( 'automatic_updates_send_debug_email ', '__return_false' );
		add_filter( 'send_core_update_notification_email', '__return_false' );
		add_filter( 'automatic_updates_is_vcs_checkout', '__return_true' );

		// Remove bulk action for updating themes and plugins.
		add_filter( 'bulk_actions-plugins', array( $this, 'remove_bulk_actions' ) );
		add_filter( 'bulk_actions-themes', array( $this, 'remove_bulk_actions' ) );
		add_filter( 'bulk_actions-plugins-network', array( $this, 'remove_bulk_actions' ) );
		add_filter( 'bulk_actions-themes-network', array( $this, 'remove_bulk_actions' ) );

		// Filter outbound requests to known update hosts.
		add_filter( 'pre_http_request', array( $this, 'filter_update_requests' ), 10, 3 );
	}

	/**
	 * Remove any global update checks.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		remove_action( 'init', 'wp_version_check' );
		add_filter( 'pre_option_update_core', '__return_null' );
		remove_all_filters( 'plugins_api' );
	}

	/**
	 * Remove admin update checks and block cron checks.
	 *
	 * @since 1.0.0
	 */
	public function admin_init() {
		// Remove updates page.
		remove_submenu_page( 'index.php', 'update-core.php' );

		// Disable plugin API checks.
		remove_all_filters( 'plugins_api' );

		// Disable theme checks.
		remove_action( 'load-update-core.php', 'wp_update_themes' );
		remove_action( 'load-themes.php', 'wp_update_themes' );
		remove_action( 'load-update.php', 'wp_update_themes' );
		remove_action( 'wp_update_themes', 'wp_update_themes' );
		remove_action( 'admin_init', '_maybe_update_themes' );
		wp_clear_scheduled_hook( 'wp_update_themes' );

		// Disable plugin checks.
		remove_action( 'load-update-core.php', 'wp_update_plugins' );
		remove_action( 'load-plugins.php', 'wp_update_plugins' );
		remove_action( 'load-update.php', 'wp_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'wp_update_plugins', 'wp_update_plugins' );
		wp_clear_scheduled_hook( 'wp_update_plugins' );

		// Disable any other update/cron checks.
		remove_action( 'wp_version_check', 'wp_version_check' );
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'wp_maybe_auto_update', 'wp_maybe_auto_update' );
		remove_action( 'admin_init', 'wp_maybe_auto_update' );
		remove_action( 'admin_init', 'wp_auto_update_core' );
		wp_clear_scheduled_hook( 'wp_version_check' );
		wp_clear_scheduled_hook( 'wp_maybe_auto_update' );

		// Hide nag messages.
		remove_action( 'admin_notices', 'update_nag', 3 );
		remove_action( 'network_admin_notices', 'update_nag', 3 );
		remove_action( 'admin_notices', 'maintenance_nag' );
		remove_action( 'network_admin_notices', 'maintenance_nag' );
	}

	/**
	 * Remove theme update data from the update transient.
	 *
	 * @since 1.0.0
	 */
	public function pre_update_themes() {
		// Get all registered themes.
		if ( false === ($this->themes = get_transient( 'dawpu_themes' )) ) {
			foreach ( wp_get_themes() as $theme ) {
				$this->themes[ $theme->get_stylesheet() ] = $theme->get( 'Version' );
			}

			set_transient( 'dawpu_themes', $this->themes, DAY_IN_SECONDS );
		}

		// Return an empty object to prevent extra checks.
		return (object) array(
			'last_checked'	  => time(),
			'updates'		  => array(),
			'version_checked' => $this->wp_version,
			'checked'		  => $this->themes
		);
	}

	/**
	 * Remove theme update data from the update transient.
	 *
	 * @since 1.0.0
	 */
	public function update_themes() {
		return array();
	}

	/**
	 * Remove plugin update data from the update transient.
	 *
	 * @since 1.0.0
	 */
	public function pre_update_plugins() {
		// Get all registered plugins.
		if ( false === ($this->plugins = get_transient( 'dawpu_plugins' )) ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			foreach ( get_plugins() as $file => $plugin ) {
				$this->plugins[ $file ] = $plugin['Version'];
			}

			set_transient( 'dawpu_plugins', $this->plugins, DAY_IN_SECONDS );
		}

		// Return an empty object to prevent extra checks.
		return (object) array(
			'last_checked'	  => time(),
			'updates'		  => array(),
			'version_checked' => $this->wp_version,
			'checked'		  => $this->plugins
		);
	}

	/**
	 * Remove plugin update data from the update transient.
	 *
	 * @since 1.0.0
	 */
	public function update_plugins() {
		return array();
	}

	/**
	 * Remove core update data from the update transient.
	 *
	 * @since 1.0.0
	 */
	public function pre_update_core() {
		// Return an empty object to prevent extra checks.
		return (object) array(
			'last_checked'	  => time(),
			'updates'		  => array(),
			'version_checked' => $this->wp_version,
		);
	}

	/**
	 * Remove core update data from the update transient.
	 *
	 * @since 1.0.0
	 */
	public function update_core() {
		return array();
	}

	/**
	 * Removes update bulk actions.
	 *
	 * @since 1.0.0
	 *
	 * @param array $actions Bulk actions.
	 */
	public function remove_bulk_actions( $actions ) {
		if ( isset( $actions['update-selected'] ) ) {
			unset( $actions['update-selected'] );
		}

		if ( isset( $actions['update'] ) ) {
			unset( $actions['update'] );
		}

		if ( isset( $actions['upgrade'] ) ) {
			unset( $actions['upgrade'] );
		}

		return $actions;
	}

	/**
	 * Blocks update requests made to known update hosts.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $bool  The return value if we should filter the request.
	 * @param array $args An array of args passed to the remote request.
	 * @param string $url The URL requested.
	 * @return bool
	 */
	public function filter_update_requests( $bool, $args, $url ) {
		if ( empty( $url ) ) {
			return $bool;
		}

		$pieces = wp_parse_url( $url );
		if ( ! $pieces ) {
			return $bool;
		}

		// Add a filterable list of hosts/paths to be checked for possible blocking.
		$datasets = array(
			array(
				'host' => 'api.wordpress.org',
				'path' => 'update-check'
			),
			array(
				'host' => 'api.wordpress.org',
				'path' => 'version-check'
			),
			array(
				'host' => 'gabelivan.com'
			),
			array(
				'host' => 'license.patsatech.com'
			),
			array(
				'host' => 'wp-pdf.com'
			),
			array(
				'host' => 'my.yoast.com'
			),
			array(
				'host' => 'www.radiustheme.com'
			),
			array(
				'host' => 'api.genesistheme.com'
			)
			array(
				'host' => 'planet.wordpress.org'
			)
			array(
				'host' => 'api.freemius.com'
			)
			array(
				'host' => 'api.awesomemotive.com'
			)
			array(
				'host' => 'soliloquywp.com',
			)
			array(
				'host' => 'crmperks.com',
			)
		);
		$datasets = apply_filters( 'dawpu_filter_update_requests', $datasets );
		if ( ! $datasets ) {
			return $bool;
		}

		// Loop through the datasets to determine if we can return true
		// and prevent the request from happening.
		foreach ( $datasets as $array => $data ) {
			// Check for both host and path combined first.
			if ( ! empty( $data['host'] ) && ! empty( $data['path'] ) ) {
				if ( ! empty( $pieces['host'] ) && ! empty( $pieces['path'] ) ) {
					if ( false !== stripos( $pieces['host'], $data['host'] ) && false !== stripos( $pieces['path'], $data['path'] ) ) {
						return true;
					}
				}
			} else if ( ! empty( $data['host'] ) && ! empty( $pieces['host'] ) ) {
				if ( false !== stripos( $pieces['host'], $data['host'] ) ) {
					return true;
				}
			} else if ( ! empty( $data['path'] ) && ! empty( $pieces['path'] ) ) {
				if ( false !== stripos( $pieces['path'], $data['path'] ) ) {
					return true;
				}
			}
		}

		// Finally, return the default value.
		return $bool;
	}

}

// Initialize the plugin.
$GLOBALS['disable_all_wp_updates'] = new Disable_All_WP_Updates();
