<?php

namespace NewspackCustomContentMigrator\Command\General;

use Newspack\MigrationTools\Command\PostsMigrator;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Hooks\MemoryCleanupHook;
use Newspack\MigrationTools\Logic\Newsletters;
use Newspack\MigrationTools\Util\CsvWriter;
use Newspack\MigrationTools\Util\JsonIterator;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;

class NewslettersMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * @var string Newsletters.
	 */
	const NEWSLETTERS_EXPORT_FILE = 'newspack-newsletters.xml';

	/**
	 * Newsletters logic.
	 * 
	 * @var null|Newsletters
	 */
	private Newsletters $newsletters_logic;

	/**
	 * JSON iterator.
	 *
	 * @var null|JsonIterator
	 */
	private JsonIterator $json_iterator;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->json_iterator     = new JsonIterator();
		$this->newsletters_logic = new Newsletters();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command( 'newspack-content-migrator export-newsletters', self::get_command_closure( 'cmd_export_newsletters' ), [
			'shortdesc' => 'Exports Newspack Newsletters.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'output-dir',
					'description' => 'Output directory full path (no ending slash).',
					'optional'    => false,
					'repeating'   => false,
				],
			],
		] );

		WP_CLI::add_command( 'newspack-content-migrator import-newsletters', self::get_command_closure( 'cmd_import_newsletters' ), [
			'shortdesc' => 'Imports Newspack Newsletters.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'input-dir',
					'description' => 'Input directory full path (no ending slash).',
					'optional'    => false,
					'repeating'   => false,
				],
			],
		] );

		WP_CLI::add_command(
			'newspack-content-migrator export-newsletters-as-json',
			self::get_command_closure( 'cmd_export_newsletters_as_json' ),
			[
				'shortdesc' => 'Exports Newspack Newsletters as JSON files.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator import-newsletters-from-json',
			self::get_command_closure( 'cmd_import_newsletters_from_json' ),
			[
				'shortdesc' => 'Import Newspack Newsletters as JSON files.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'newsletter-layouts-json',
						'description' => 'Path to newsletter layouts JSON file.',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'newsletters-json',
						'description' => 'Path to newsletters JSON file.',
						'optional'    => false,
						'repeating'   => false,
					],
				],
			]
		);
	}

	/**
	 * Callable for export-newsletters command. Exits with code 0 on success or 1 otherwise.
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function cmd_export_newsletters( $args, $assoc_args ) {
		$output_dir = isset( $assoc_args[ 'output-dir' ] ) ? $assoc_args[ 'output-dir' ] : null;
		if ( is_null( $output_dir ) || ! is_dir( $output_dir ) ) {
			WP_CLI::error( 'Invalid output dir.' );
		}

		WP_CLI::line( sprintf( 'Exporting Newsletters...' ) );

		$result = $this->export_newsletters( $output_dir, self::NEWSLETTERS_EXPORT_FILE );
		if ( true === $result ) {
			WP_CLI::success( 'Done.' );
			exit(0);
		} else {
			WP_CLI::warning( 'Done with warnings.' );
			exit(1);
		}
	}

	/**
	 * Exports Newsletters.
	 *
	 * @param $output_dir
	 * @param $file_output_newsletters
	 *
	 * @return bool Success.
	 */
	public function export_newsletters( $output_dir, $file_output_newsletters ) {
		wp_cache_flush();

		$posts = $this->newsletters_logic->get_all_newsletters();
		if ( empty( $posts ) ) {
			WP_CLI::warning( sprintf( 'No Newsletters found.' ) );
			return false;
		}

		$post_ids = [];
		foreach ( $posts as $post ) {
			$post_ids[] = $post->ID;
		}

		return PostsMigrator::get_instance()->migrator_export_posts( $post_ids, $output_dir, $file_output_newsletters );
	}

	/**
	 * Callable for import-newsletters command.
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function cmd_import_newsletters( $args, $assoc_args ) {
		$input_dir = isset( $assoc_args[ 'input-dir' ] ) ? $assoc_args[ 'input-dir' ] : null;
		if ( is_null( $input_dir ) || ! is_dir( $input_dir ) ) {
			WP_CLI::error( 'Invalid input dir.' );
		}

		$import_file = $input_dir . '/' . self::NEWSLETTERS_EXPORT_FILE;
		if ( ! is_file( $import_file ) ) {
			WP_CLI::warning( sprintf( 'Newsletters file not found %s.', $import_file ) );
			exit(1);
		}

		WP_CLI::line( 'Importing Newsletters from ' . $import_file . ' ...' );

		$this->import_newsletterss( $import_file );

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Callable for `newspack-content-migrator export-newsletters-as-json`.
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_export_newsletters_as_json( array $pos_args, array $assoc_args ): void {
		// Export the Newsletter Layouts.
		$this->newsletters_logic->export_newsletter_layouts();

		// Export the Newsletters.
		$this->newsletters_logic->export_newsletters();

		WP_CLI::success( 'Successfully exported Newsletters!' );
		WP_CLI::log( sprintf( 'Check the CSV file with the exported newsletter layouts: %s', 'newsletter-layouts.csv' ) );
		WP_CLI::log( sprintf( 'Check the CSV file with the exported newsletters: %s', 'newsletters.csv' ) );
	}

	/**
	 * Callable for `newspack-content-migrator import-newsletters-from-json`.
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_import_newsletters_from_json( array $pos_args, array $assoc_args ): void {
		$newsletter_layouts_json_file = $assoc_args['newsletter-layouts-json'];
		$newsletters_json_file        = $assoc_args['newsletters-json'];

		$csv_header = [
			'#',
			'Status',
			'Source ID',
			'Post ID',
			'Admin URL',
		];

		$newsletter_layouts_csv_file = new CsvWriter( 'newsletter-layouts.csv' );
		$newsletter_layouts_csv_file->set_header( $csv_header );

		$newsletters_csv_file = new CsvWriter( 'newsletters.csv' );
		$newsletters_csv_file->set_header( $csv_header );

		// Import Newsletter Layouts.
		$raw_newsletter_layouts = $this->json_iterator->items( $newsletter_layouts_json_file );

		foreach ( $raw_newsletter_layouts as $index => $newsletter_layout ) {
			$newsletter_layout_id = $this->newsletters_logic->import_newsletter_layout( $newsletter_layout );

			if ( is_wp_error( $newsletter_layout_id ) ) {
				WP_CLI::error( sprintf( 'Failed to import Newsletter Layout: %s', $newsletter_layout->post->ID ) );

				$newsletter_layouts_csv_file->put( [
					$index + 1,
					'Failed',
					$newsletter_layout->post->ID,
					'',
					'',
				] );

				continue;
			}

			$newsletter_layouts_csv_file->put( [
				$index + 1,
				'Success',
				$newsletter_layout->post->ID,
				$newsletter_layout_id,
				get_edit_post_link( $newsletter_layout_id ),
			] );
		}

		wp_cache_flush();
		MemoryCleanupHook::cleanup( 3 );

		// Import Newsletters.
		$raw_newsletters = $this->json_iterator->items( $newsletters_json_file );

		foreach ( $raw_newsletters as $index => $newsletter ) {
			$newsletter_id = $this->newsletters_logic->import_newsletter( $newsletter );

			if ( is_wp_error( $newsletter_id ) ) {
				WP_CLI::error( sprintf( 'Failed to import Newsletter: %s', $newsletter->post->ID ) );

				$newsletters_csv_file->put( [
					$index + 1,
					'Failed',
					$newsletter->post->ID,
					'',
					'',
				] );

				continue;
			}

			$newsletters_csv_file->put( [
				$index + 1,
				'Success',
				$newsletter->post->ID,
				$newsletter_id,
				get_edit_post_link( $newsletter_id ),
			] );
		}

		$newsletter_layouts_csv_file->close();
		$newsletters_csv_file->close();

		WP_CLI::success( 'Successfully imported Newsletters!' );
	}

	/**
	 * Imports Newspack Newsletters.
	 *
	 * @param string $import_file XML file to import.
	 */
	private function import_newsletterss( $import_file ) {
		wp_cache_flush();

		$this->delete_all_existing_newsletters();

		register_post_type( $this->newsletters_logic::NEWSLETTER_POST_TYPE );

		PostsMigrator::get_instance()->import_posts( $import_file );
	}

	/**
	 * Deletes all existing Newsletters.
	 */
	private function delete_all_existing_newsletters() {
		wp_cache_flush();

		$posts = $this->newsletters_logic->get_all_newsletters();
		if ( empty( $posts ) ) {
			return;
		}

		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID );
		}
	}
}
