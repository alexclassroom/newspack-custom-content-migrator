<?php
/**
 * Southwest Regional Publishing specific commands.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Bramus\Monolog\Formatter\ColoredLineFormatter;

use WP_CLI;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\PlainFileLog;
use Newspack\MigrationTools\Util\Log\PlainLineFormatter;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;

/**
 * SouthwestRegionalPublishingMigrator.
 */
class SouthwestRegionalPublishingMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * CLI logger, plain, just message.
	 *
	 * @var CliLog $logger_cli CLI Logger.
	 */
	private $logger_cli_plain;
	
	/**
	 * CLI logger, plain, just level and message.
	 *
	 * @var CliLog $logger_cli CLI Logger.
	 */
	private $logger_cli_level;

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Just message.
		$this->logger_cli_plain = CliLog::get_logger( 'cli-message', new ColoredLineFormatter( null, "%message%\n", null, true ) );
		
		// Just level and message.
		$this->logger_cli_level = CliLog::get_logger( 'cli-level-message', new ColoredLineFormatter( null, "%level_name%: %message%\n", null, true ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator southwestregionalpublishing dev-helper-get-category-ids',
			self::get_command_closure( 'cmd_dev_helper_get_category_ids' ),
			[
				'shortdesc' => 'Quickly get category IDs and validate if they exist, or if multiple category names exist with different IDs.',
				'synopsis'  => [],
			]
		);
	}

	/**
	 * Add the byline to the content if there is one.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function cmd_dev_helper_get_category_ids( array $pos_args, array $assoc_args ): void {
		global $wpdb;

		$category_names = [
			'Argo-Summit',
			'Bedford Park',
			'Bridgeview',
			'Brookfield',
			'Burbank',
			'Countryside',
			'DVN business',
			'DVN Fire',
			'DVN Government',
			'DVN police',
			'DVN sports',
			'DVN township',
			'Forest View',
			'Hodgkins',
			'LaGrange',
			'Justice',
			'Lyons',
			'McCook',
			'Stickney',
			'Willow Springs',
			'Indian Head Park',
			'N-H Archer Heights',
			'N-H Chicago Lawn',
			'N-H Chicago Lawn',
			'N-H Events',
			'N-H Gage Park',
			'N-H Garfield Ridge',
			'N-H Government',
			'N-H Greater Ashburn',
			'N-H Police',
			'N-H Township',
			'N-H Sports',
			'N-H West Lawn',
			'Orland Park',
			'Palos Heights',
			'Palos Park',
			'Regional Fire',
			'Regional Police',
			'Regional Township',
			'Regional Sports',
			'Worth Township',
			'Chicago Ridge',
			'Evergreen Park',
			'Oak Lawn',
			'Hickory Hills',
			'Palos Hills',
			'Reporter Police',
			'Reporter Fire',
			'Reporter Township',
			'Reporter Government',
			'Reporter Sports',
		];

		foreach ( $category_names as $category_name ) {
			$term_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT t.term_id
					FROM $wpdb->terms t
					JOIN $wpdb->term_taxonomy tt 
					ON tt.term_id = t.term_id
					WHERE t.name = %s
					AND tt.taxonomy = 'category';",
					$category_name
				),
				ARRAY_A
			);
			if ( empty( $term_rows ) ) {
				$this->logger_cli_level->error( $category_name . ',NOT_FOUND' );
			} else {
				foreach ( $term_rows as $term_row ) {
					$this->logger_cli_plain->debug( $category_name . ',' . $term_row['term_id'] );
				}
			}
		}
	}
}
