<?php

namespace WordPressdotorg\GlotPress\Plugin_Directory;

use GP;
use WP_CLI;

class Plugin {

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

	/**
	 *
	 * @var Sync\Translation_Sync
	 */
	public $translation_sync = null;

	/**
	 * Parent project for plugins.
	 */
	const GP_MASTER_PROJECT = 'wp-plugins';

	/**
	 * Returns always the same instance of this plugin.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof Plugin ) ) {
			self::$instance = new Plugin();
		}
		return self::$instance;
	}

	/**
	 * Instantiates a new Plugin object.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
	}

	/**
	 * Initializes the plugin.
	 */
	public function plugins_loaded() {
		add_action( 'wporg_translate_update_plugin_status', array( $this, 'update_project_status' ) );

		$cache_purger = new Cache_Purge\Cache_Purger();
		$cache_purger->register_events();

		$language_pack_build_trigger = new Language_Pack\Build_Trigger();
		$language_pack_build_trigger->register_events();

		$language_pack_build_listener = new Language_Pack\Build_Listener(
			Language_Pack\Build_Trigger::HOOK
		);
		$language_pack_build_listener->register_events();

		$this->translation_sync = new Sync\Translation_Sync();
		$this->translation_sync->register_events();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli_commands();
		}
	}

	/**
	 * Registers CLI commands if WP-CLI is loaded.
	 */
	public function register_cli_commands() {
		WP_CLI::add_command( 'wporg-translate import-plugin-translations', __NAMESPACE__ . '\CLI\Import_Plugin_Translations' );
		WP_CLI::add_command( 'wporg-translate set-plugin-project', __NAMESPACE__ . '\CLI\Set_Plugin_Project' );
		WP_CLI::add_command( 'wporg-translate delete-plugin-project', __NAMESPACE__ . '\CLI\Delete_Plugin_Project' );
		WP_CLI::add_command( 'wporg-translate sync-plugin-translations', __NAMESPACE__ . '\CLI\Sync_Plugin_Translations' );
		WP_CLI::add_command( 'wporg-translate update-plugin-project-status', __NAMESPACE__ . '\CLI\Update_Plugin_Project_Status' );
	}

	/**
	 * Returns whether a project path belongs to the plugins project.
	 *
	 * @param string $path Path of a project.
	 *
	 * @return bool True if it's a plugin, false if not.
	 */
	public static function project_is_plugin( $path ) {
		if ( empty( $path ) ) {
			return false;
		}

		$path = '/' . trim( $path, '/' ) . '/';
		if ( false === strpos( $path, '/' . self::GP_MASTER_PROJECT . '/' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Updates the status of a plugin.
	 *
	 * Gets triggered by the cron API and the hook `wporg_translate_update_plugin_status`.
	 *
	 * @param array $args Arguments from the job. Should include the slug and the status
	 *                    of the plugin.
	 * @return bool False on failure, true on success.
	 */
	public function update_project_status( $args ) {
		$project = GP::$project->by_path( self::GP_MASTER_PROJECT . '/' . $args['plugin'] . '/' );
		if ( ! $project ) {
			return false;
		}

		if ( 'active' === $args['status'] && ! $project->active ) {
			$project->save( [
				'active' => 1,
			] );
		} elseif ( 'inactive' === $args['status'] && $project->active ) {
			$project->save( [
				'active' => 0,
			] );
		}

		return true;
	}
}
