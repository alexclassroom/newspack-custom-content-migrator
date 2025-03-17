<?php
/**
 * Migration tasks for Mountain Journal.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Util\CsvIterator;
use Newspack\MigrationTools\Util\Log\FileLog;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;

/**
 * Custom migration scripts for Mountain Journal.
 */
class MountainJournalMigrator implements RegisterCommandInterface {
	use WpCliCommandTrait;

	/**
	 * CSV Iterator.
	 * 
	 * @var CsvIterator
	 */
	private CsvIterator $csv_iterator;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->csv_iterator = new CsvIterator();
	}

	/**
	 * Registers WP CLI Commands.
	 * 
	 * @return void
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator mj-migrate-categories-and-tags',
			self::get_command_closure( 'cmd_migrate_categories_and_tags' ),
			[
				'shortdesc' => 'Migrate Categories and Tags',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'source-filepath',
						'description' => 'The path to the source file.',
						'optional'    => false,
						'repeating'   => false,
					],
				],
			]
		);
	}

	/**
	 * Migrate Categories and Tags from a CSV file.
	 * 
	 * @uses wp newspack-content-migrator mj-migrate-categories-and-tags --source-filepath="{FILEPATH}"
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_migrate_categories_and_tags( array $pos_args, array $assoc_args ): void {
		$source_filepath = $assoc_args['source-filepath'];

		// Prelimiary checks.
		if ( ! $source_filepath || ! file_exists( $source_filepath ) ) {
			WP_CLI::error( sprintf( 'Incorrect source filepath: %s', $source_filepath ) );
			return;
		}

		$taxonomy_map = [
			// Type => Taxonomy.
			'Category' => 'category',
			'Tag'      => 'post_tag',
		];

		$file_loggger = FileLog::get_logger( 'migrate-categories-and-tags' );

		// CSV.
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$csv = sprintf( 'migrate-categories-and-tags-%s.csv', date( 'Y-m-d H-i-s' ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$csv_file_pointer = fopen( $csv, 'w' );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
		fputcsv(
			$csv_file_pointer,
			[
				'#',
				'Type',
				'Term ID',
				'Term Name',
			]
		);

		$total_categories_and_tags = $this->csv_iterator->count_csv_file_entries( $source_filepath, ',' );

		$progress_bar = WP_CLI\Utils\make_progress_bar( '[Mountain Journal] Migrating Categories and Tags', $total_categories_and_tags );

		foreach ( $this->csv_iterator->items( $source_filepath, ',' ) as $index => $csv_row ) {
			$progress_bar->tick(
				1,
				sprintf(
					'[Memory: %s] [Mountain Journal] Migrating Categories and Tags %d/%d',
					size_format( memory_get_usage( true ) ),
					$index + 1,
					$total_categories_and_tags
				)
			);

			$file_loggger->info( sprintf( '👉 Processing Entry %s', wp_json_encode( $csv_row ) ) );

			if ( term_exists( $csv_row['Title'], $taxonomy_map[ $csv_row['Type'] ] ) ) {
				$file_loggger->info( sprintf( '⚠️ %s %s already exists. Skipping...', $taxonomy_map[ $csv_row['Type'] ], $csv_row['Title'] ) );

				continue;
			}

			$inserted_term = wp_insert_term(
				$csv_row['Title'],
				$taxonomy_map[ $csv_row['Type'] ]
			);

			if ( is_wp_error( $inserted_term ) ) {
				$file_loggger->error( sprintf( '🚫 Could not insert term. Error: %s', wp_json_encode( $inserted_term->get_error_messages() ) ) );
				continue;
			}

			$file_loggger->info( sprintf( '✅ Successfully inserted term %s', $csv_row['Title'] ) );

			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
			fputcsv(
				$csv_file_pointer,
				[
					$index + 1,
					$csv_row['Type'],
					$inserted_term['term_id'],
					$csv_row['Title'],
				]
			);
		}

		$progress_bar->finish();

		// Close CSV.
		fclose( $csv_file_pointer );

		$file_loggger->info( '🎉 Done' );

		wp_cache_flush();
	}
}
