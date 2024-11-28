<?php
declare(ticks=1);

namespace NewspackCustomContentMigrator;

use Newspack\MigrationTools\Command\WpCliCommandInterface;
use Newspack\MigrationTools\Command\WpCliCommands;
use Newspack\MigrationTools\NMT;
use Newspack\MigrationTools\Util\Log\CliLog;
use WP_CLI;

/**
 * PluginSetup class.
 */
class PluginSetup {
	/**
	 * Register a tick callback to check the if we exceed the memory limit.
	 */
	public static function register_ticker() {
		register_tick_function(
			function() {
				$memory_usage = memory_get_usage( false );

				if ( $memory_usage > 490000000 ) { // 490 MB in bytes, since the limit on Atomic is 512 MB.
					print_r( 'Exit due to memory usage: ' . $memory_usage );
					exit( 1 );
				}
			}
		);
	}

	/**
	 * Configures all errors and warnings will be output to CLI.
	 * 
	 * @param string $level Error reporting level. 'dev' is default. 'live' will not change error reporting.
	 */
	public static function configure_error_reporting( $level = 'dev' ): void {
		if ( 'dev' === $level ) {
			// phpcs:disable -- Adds extra debugging config options for dev purposes.
			@ini_set( 'display_errors', 1 );
			@ini_set( 'display_startup_errors', 1 );
			error_reporting( E_ALL );
			// phpcs:enable

			// Enable WP_DEBUG mode.
			if ( ! defined( 'WP_DEBUG' ) ) {
				define( 'WP_DEBUG', true );
			}
			// Enable Debug logging to the /wp-content/debug.log file.
			if ( ! defined( 'WP_DEBUG_LOG' ) ) {
				define( 'WP_DEBUG_LOG', true );
			}
			// Enable display of errors and warnings.
			if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
				define( 'WP_DEBUG_DISPLAY', true );
			}
		}
	}

	/**
	 * Registers command classes.
	 *
	 * @param array $classes Array of classes implementing the RegisterCommandInterface.
	 */
	public static function register_command_classes( array $classes ): void {

		// Get the commands from implementers of the newspack_migration_tools_command_classes hook.
		foreach ( WpCliCommands::get_classes_with_cli_commands() as $command_class ) {
			if ( is_a( $command_class, WpCliCommandInterface::class, true ) ) {
				array_map( fn( $command ) => WP_CLI::add_command( ...$command ), $command_class::get_cli_commands() );
			} else {
				NMT::exit_with_message( sprintf( 'Class %s does not implement WpCliCommandInterface.', $command_class ), [ CliLog::get_logger( 'PluginSetup' ) ] );
			}
		}

		try {
			// Register the commands from the passed classes array.
			foreach ( $classes as $command_class ) {
				if ( is_a( $command_class, Command\RegisterCommandInterface::class, true ) ) {
					$command_class::register_commands();
				} else {
					CliLog::get_logger( 'register_command_classes' )->critical( sprintf( 'Registering commands for class %s.', $command_class ) );
				}
			}
		} catch ( \Exception $o_0 ) {
			NMT::exit_with_message( sprintf( 'Error registering command for class %s. Message: %s', $command_class, $o_0->getMessage() ), [ CliLog::get_logger( 'PluginSetup' ) ] );
		}

	}

	/**
	 * Registers migrators' commands.
	 *
	 * @deprecated Use register_command_classes instead (and refactor the class passed to it).
	 *
	 * @param array $migrator_classes Array of Command\InterfaceCommand classes.
	 */
	public static function register_migrators( array $migrator_classes ) {

		foreach ( $migrator_classes as $migrator_class ) {
			$migrator = $migrator_class::get_instance();
			if ( $migrator instanceof Command\InterfaceCommand ) {
				$migrator->register_commands();
			}
		}
	}

	/**
	 * Add hooks for all commands.
	 *
	 * Note that to add a hook for a specific command, you should add it in the command class in the command (not the constructor).
	 * That way it only applies to that command/publisher when run.
	 *
	 * @return void
	 */
	public static function add_hooks(): void {
		if ( ! defined( 'NCCM_DISABLE_CLI_LOG' ) || empty( 'NCCM_DISABLE_CLI_LOG' ) ) {
			add_filter( 'newspack_migration_tools_enable_cli_log', '__return_true' );
		}
		if ( ! defined( 'NCCM_DISABLE_FILE_LOG' ) || empty( 'NCCM_DISABLE_FILE_LOG' ) ) {
			add_filter( 'newspack_migration_tools_enable_file_log', '__return_true' );
		}
		if ( ! defined( 'NCCM_DISABLE_PLAIN_LOG' ) || empty( 'NCCM_DISABLE_PLAIN_LOG' ) ) {
			add_filter( 'newspack_migration_tools_enable_plain_log', '__return_true' );
		}
	}

}
