<?php
/**
 * Logger class for handling commands' logging
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Utils;

use Monolog\Level;
use Newspack\MigrationTools\NMT;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Psr\Log\LogLevel;
use UnhandledMatchError;

/**
 * Class for handling commands' logging.
 *
 * @deprecated Use the NMT Log classes instead.
 */
class Logger {

	const WARNING = 'warning';
	const LINE    = 'line';
	const SUCCESS = 'success';
	const ERROR   = 'error';

	/**
	 * Simple file logging.
	 *
	 * @deprecated Use the NMT Log classes instead.
	 *
	 * @param string $message       Log message.
	 * @param string $level         Whether to output the message to the CLI. Default to `line` CLI level.
	 * @param bool   $exit_on_error Whether to exit on error.
	 *
	 * @param string $file          File name or path.
	 *
	 * @return void
	 */
	public function log( string $file, $message, string $level = LogLevel::INFO, bool $exit_on_error = false ): void {
		$filename = pathinfo( $file, PATHINFO_FILENAME ); // File name without extension.
		$file_logger = FileLog::get_logger(
			$filename,
			basename( $file ) // File name.
		);
		$cli_logger = CliLog::get_logger( $filename );

		try {
			$log_level = Level::fromName( $level );
		} catch ( UnhandledMatchError $e ) {
			$log_level = Level::fromName(Level::Info);
		}

		if ( $exit_on_error ) {
			NMT::exit_with_message( $message, [ $cli_logger, $file_logger ] );
		}
		$file_logger->log( $log_level->getName(), $message );
		$cli_logger->log( $log_level->getName(), $message );
	}

}
