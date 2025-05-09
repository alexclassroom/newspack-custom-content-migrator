<?php

namespace NewspackCustomContentMigrator\Command\General;

use DateTime;
use DateTimeZone;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\CoAuthorsPlusHelper;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Logic\Posts as PostsLogic;
use Newspack\MigrationTools\Util\CsvIterator;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use NewspackCustomContentMigrator\Utils\CommonDataFileIterator\FileImportFactory;
use WP_CLI;
use WP_Error;
use WP_User;
use simplehtmldom\HtmlDocument;
use WP_Post;
use WP_Query;
use WP_Term;

/**
 * Custom migration scripts for Posts' and Comments' content.
 */
class EllingtonCMSMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * EllingtonCMS Post Status map.
	 * 
	 * @var array
	 */
	const POST_STATUS_MAP = [
		1 => 'publish', // Live
		2 => 'draft', // Draft
		3 => 'trash', // Deleted
		4 => 'draft', // Unreviewed
	];

	const CDN_BASE_URL = 'https://jacksonfreepress.media.clients.ellingtoncms.com/';

	const MAX_CURL_RETRIES = 10;

	/**
	 * Posts Logic.
	 * 
	 * @var PostsLogic
	 */
	private PostsLogic $posts_logic;

	/**
	 * Attachments Logic.
	 * 
	 * @var Attachments
	 */
	private Attachments $attachments;

	/**
	 * Co-Authors Plus.
	 * 
	 * @var CoAuthorsPlusHelper
	 */
	private CoAuthorsPlusHelper $cap;

	/**
	 * Gutenber Block Generator.
	 * 
	 * @var GutenbergBlockGenerator
	 */
	private GutenbergBlockGenerator $gutenberg_block_generator;

	/**
	 * Logger.
	 * 
	 * @var MultiLog
	 */
	private MultiLog $logger;

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
		$this->posts_logic               = new PostsLogic();
		$this->attachments               = new Attachments();
		$this->cap                       = new CoAuthorsPlusHelper();
		$this->gutenberg_block_generator = new GutenbergBlockGenerator();
		$this->csv_iterator              = new CsvIterator();
		$this->logger                    = MultiLog::get_logger(
			'ellingtoncms-migrator',
			[
				CliLog::get_logger( 'ellingtoncms-migrator' ),
				FileLog::get_logger( 'ellingtoncms-migrator' ),
			] 
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator ellington-cms-migrator migrate-posts',
			self::get_command_closure( 'cmd_migrate_posts' ),
			[
				'shortdesc' => 'Goes through all the Posts, and removes all occurrences of featured image from beginning of Post content.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'dir-path',
						'description' => 'Path to the directory where XML files are located.',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'source-timezone',
						'description' => 'The Timezone of the source',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'default-featured-image',
						'description' => 'The ID of the default Attachment to be used for Featured Thumbnail.',
						'optional'    => true,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'default-author',
						'description' => 'The ID of the default Author to be used for Posts.',
						'optional'    => true,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'post-tag',
						'description' => 'Apply the given Post Tag to posts.',
						'optional'    => true,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'from-index',
						'description' => 'Start from the given index.',
						'optional'    => true,
						'repeating'   => false,
					],
					[
						'type'        => 'flag',
						'name'        => 'refresh-content',
						'description' => 'Refresh the content of the posts that were already imported.',
						'optional'    => true,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator ellington-cms-migrator migrate-comments',
			self::get_command_closure( 'cmd_migrate_comments' ),
			[
				'shortdesc' => 'Migrates legacy comments from CSV.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'source-csv-path',
						'description' => 'Path to the CSV file containing the comments.',
						'optional'    => false,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator ellington-cms-migrator migrate-audio',
			self::get_command_closure( 'cmd_migrate_audio' ),
			[
				'shortdesc' => 'Migrates the Audio posts',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'csv-path',
						'description' => 'Path to the CSV file.',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'category_id',
						'description' => 'The ID of the Category that will contain all Audio Posts.',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'default-author',
						'description' => 'The ID of the default Author to be used for Audio Posts.',
						'optional'    => true,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator ellington-cms-migrator migrate-documents',
			self::get_command_closure( 'cmd_migrate_documents' ),
			[
				'shortdesc' => 'Migrates the Documents',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'csv-path',
						'description' => 'Path to the CSV file.',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'category_id',
						'description' => 'The ID of the Category that will contain all Documents Posts.',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'default-author',
						'description' => 'The ID of the default Author to be used for Posts.',
						'optional'    => true,
						'repeating'   => false,
					],
				],
			]
		);
	}

	/**
	 * Migrates posts from XML.
	 *
	 * @param  array $args
	 * @param  array $assoc_args
	 * @return void
	 */
	public function cmd_migrate_posts( $args, $assoc_args ) {
		$xml_dir_path           = $assoc_args['dir-path'];
		$source_timezone        = $assoc_args['source-timezone'];
		$refresh_content        = isset( $assoc_args['refresh-content'] ) ? true : false;
		$post_tag               = $assoc_args['post-tag'];
		$default_featured_image = $assoc_args['default-featured-image'];
		$default_author         = $assoc_args['default-author'];
		$from_index             = isset( $assoc_args['from-index'] ) ? absint( $assoc_args['from-index'] ) : 0;

		$xml_files = scandir( $xml_dir_path );
		$xml_files = array_filter( $xml_files, fn ( $filename ) => ! in_array( $filename, [ '.', '..', '.DS_Store' ] ) );

		natsort( $xml_files );

		// QA
		$qa_filename    = 'ellingtoncms-migrator-migrate-posts.csv';
		$qa_file_exists = file_exists( $qa_filename );
		$qa_file        = fopen( $qa_filename, 'a' );

		$qa_header = [
			'#',
			'Post ID',
			'Post URL',
			'Old Slug',
			'XML Source File',
		];

		if ( ! $qa_file_exists ) {
			fputcsv( $qa_file, $qa_header );
		}

		$progress_bar = WP_CLI\Utils\make_progress_bar(
			sprintf(
				'[Memory Usage: %s] Ellington CMS Migrator: Migrating Posts',
				size_format( memory_get_usage( true ) )
			),
			count( $xml_files )
		);

		$this->logger->info( sprintf( 'Found %d files', count( $xml_files ) ) );

		foreach ( array_values( $xml_files ) as $index => $xml_file ) {
			$progress_bar->tick(
				1,
				sprintf(
					'[Memory Usage: %s] Ellington CMS Migrator: Migrating Posts (%d/%d)',
					size_format( memory_get_usage( true ) ),
					$index,
					count( $xml_files )
				)
			);

			if ( ! empty( $from_index ) && $index < $from_index ) {
				continue;
			}

			$this->logger->info(
				sprintf(
					'Processing %d / %d — %s',
					$index + 1,
					count( $xml_files ),
					$xml_file
				)
			);

			$xml_contents = file_get_contents( $xml_dir_path . DIRECTORY_SEPARATOR . $xml_file );

			$post_id = $this->upsert_post(
				file_contents: $xml_contents,
				filename: $xml_file,
				source_timezone: $source_timezone,
				default_author: $default_author,
				default_featured_image: $default_featured_image,
				post_tag: $post_tag,
				refresh: $refresh_content
			);

			fputcsv(
				$qa_file,
				[
					$index + 1,
					$post_id,
					get_permalink( $post_id ),
					get_post_meta( $post_id, 'newspack_post_source_url', true ),
					$xml_file,
				] 
			);
		}

		$progress_bar->finish();

		fclose( $qa_file );

		wp_cache_flush();
		
		$this->logger->info( 'Completed! 🎉' );
	}

	/**
	 * Migrates comments from CSV.
	 *
	 * @param  array $args
	 * @param  array $assoc_args
	 * @return void
	 */
	public function cmd_migrate_comments( $args, $assoc_args ) {
		global $wpdb;

		// Input.
		$csv_file_path = $assoc_args['source-csv-path'];

		// CSV.
		$all_comments                = [ ...( new CsvIterator() )->items( $csv_file_path, ',' ) ];
		$comments_by_story_id        = array_reduce(
			$all_comments,
			function ( $carry, $item ) {
				if ( ! isset( $carry[ $item['object_pk'] ] ) ) {
					$carry[ $item['object_pk'] ] = [];
				}

				$carry[ $item['object_pk'] ][] = $item;

				return $carry;
			},
			[] 
		);
		$count_stories_with_comments = count( array_values( $comments_by_story_id ) );

		// QA.
		$qa_filename    = 'ellingtoncms-migrator-migrate-comments.csv';
		$qa_file_exists = file_exists( $qa_filename );
		$qa_file        = fopen( $qa_filename, 'a' );

		$qa_header = [
			'Post ID',
			'Story ID',
			'Comments Count',
			'Comment IDs',
			'Post URL',
			'Revision URL',
		];

		if ( ! $qa_file_exists ) {
			fputcsv( $qa_file, $qa_header );
		}

		$progress_bar = WP_CLI\Utils\make_progress_bar(
			sprintf(
				'[Memory Usage: %s] Ellington CMS Migrator: Migrating Comments',
				size_format( memory_get_usage( true ) )
			),
			$count_stories_with_comments
		);

		$index = 0;
		foreach ( $comments_by_story_id as $story_id => $story_comments ) {
			++$index;

			$progress_bar->tick(
				1,
				sprintf(
					'[Memory Usage: %s] Ellington CMS Migrator: Migrating Comments (%d/%d)',
					size_format( memory_get_usage( true ) ),
					$index,
					$count_stories_with_comments
				)
			);

			$this->logger->info(
				sprintf(
					'Processing Story #%d',
					$story_id
				)
			);

			// Reaching here means that the comments batch for the previous story
			// is ready to be inserted as a Previous Comments Block.
			$comments_block = $this->create_comments_block(
				array_map(
					function ( $comment ) {
						return [
							'ID'      => $comment['id'],
							'Comment' => $comment['comment'],
							'Author'  => $comment['user_name'],
							'Date'    => $comment['submit_date'],
						];
					},
					$story_comments
				) 
			);

			$local_post_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT `post_id`
					FROM `$wpdb->postmeta`
					WHERE `meta_key` = 'newspack_post_source_id'
					AND `meta_value` = %s",
					$story_id
				)
			);
	
			if ( ! $local_post_id ) {
				$this->logger->critical( sprintf( 'Story #%d doesn\'t exist locally.', $story_id ) );

				fputcsv(
					$qa_file,
					[
						'',
						$story_id,
						count( $story_comments ),
						implode( ', ', array_map( fn ( $comment ) => $comment['id'], $story_comments ) ),
						'',
						'',
					] 
				);

				continue;
			}

			wp_save_post_revision( $local_post_id );

			$old_content = get_post_field( 'post_content', $local_post_id );
			$new_content = $old_content . serialize_block( $comments_block );

			// Using $wpdb->update to prevent post_modified from being updated.
			// Clearing cache immediately after that to make revisions work.
			$wpdb->update(
				$wpdb->posts,
				[
					'post_content' => $new_content,
				],
				[
					'ID' => $local_post_id,
				]
			);

			clean_post_cache( $local_post_id );

			if ( $post_revision_id = wp_save_post_revision( $local_post_id ) ) {
				$wpdb->update(
					$wpdb->posts,
					[
						'post_date'     => current_time( 'mysql' ),
						'post_date_gmt' => current_time( 'mysql', 1 ),
					],
					[
						'ID' => $post_revision_id,
					]
				);
			}

			fputcsv(
				$qa_file,
				[
					$local_post_id,
					$story_id,
					count( $story_comments ),
					implode( ', ', array_map( fn ( $comment ) => $comment['id'], $story_comments ) ),
					get_permalink( $local_post_id ),
					$post_revision_id ? admin_url( sprintf( 'revision.php?revision=%d', $post_revision_id ) ) : null,
				] 
			);
		}

		$progress_bar->finish();

		fclose( $qa_file );

		wp_cache_flush();
		
		$this->logger->info( 'Completed! 🎉' );
	}

	/**
	 * Migrates audio posts.
	 *
	 * @param  array $args
	 * @param  array $assoc_args
	 * @return void
	 */
	public function cmd_migrate_audio( array $args, array $assoc_args ) {
		$csv_filepath   = (string) $assoc_args['csv-path'];
		$category_id    = (int) $assoc_args['category_id'];
		$default_author = (int) $assoc_args['default-author'];

		if ( ! file_exists( $csv_filepath ) ) {
			WP_CLI::error( sprintf( 'Provided CSV is missing — %s', $csv_filepath ) );

			return;
		}

		if ( ! category_exists( $category_id ) ) {
			WP_CLI::error( sprintf( 'Category with ID %d does not exist', $category_id ) );

			return;
		}

		$category          = get_category( $category_id );
		$csv_file_iterator = ( new FileImportFactory() )->get_file( $csv_filepath );

		// Logger.
		$log_slug = 'ellingtoncms-migrate-audio';
        $logger   = MultiLog::get_logger( 
			'multi-' . $log_slug,
			[
				CliLog::get_logger( $log_slug ),
				FileLog::get_logger( $log_slug ),
			]
		);

		// CSV.
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$csv = sprintf( 'ellingtoncms-migrate-audio-%s.csv', date( 'Y-m-d H-i-s' ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$csv_file_pointer = fopen( $csv, 'w' );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
		fputcsv(
			$csv_file_pointer,
			[
				'#',
				'Source ID',
				'Post ID',
				'Post Title',
				'Source URL',
				'Post URL',
			]
		);

		$total_posts = count( [ ...$csv_file_iterator->getIterator() ] );

		$progress_bar = WP_CLI\Utils\make_progress_bar( '[EllingtonCMS] Migrating Audio Posts', $total_posts );

		foreach ( $csv_file_iterator->getIterator() as $row_number => $row ) {
			$progress_bar->tick(
				1,
				sprintf(
					'[Memory: %s] [EllingtonCMS] Migrating Audio Posts %d/%d',
					size_format( memory_get_usage( true ) ),
					$row_number + 1,
					$total_posts
				)
			);

			$logger->info( sprintf( '⏳ Processing Row %s', wp_json_encode( $row ) ) );

			// Search for post locally.
			$local_post = $this->get_local_post( (int) $row['id'], $category );

			if ( $local_post ) {
				// TODO: Handle refresh
				$logger->notice( sprintf( '⚠️ Post exists locally with ID %d', $local_post ) );
			}

			$logger->info( 'ℹ️ Post does not exist locally' );

			$source_url = sprintf(
				'https://www.jacksonfreepress.com/audioclips/%d',
				$row['id']
			);

			if ( $local_post ) {
				$post_id = wp_insert_post( [
					'ID'            => $local_post,
					'post_type'     => 'post',
					'post_status'   => self::POST_STATUS_MAP[ $row['status'] ],
					'post_author'   => $default_author,
					'post_title'    => (string) $row['topic'],
					'post_excerpt'  => (string) $row['description'],
					'post_content'  => serialize_block( $this->gutenberg_block_generator->get_paragraph( (string) $row['description'] ) ),
					'post_date_gmt' => '',
					'post_date'     => date( 'Y-m-d H:i:s', strtotime( $row['posted_date'] ) ),
					'post_category' => [ $category->term_id ],
					'meta_input'    => [
						'newspack_featured_image_position' => 'hidden', // Default Featured Image should be hidden, by default.
						'_newspack_source_id'              => $row['id'],
						'_newspack_source_length'          => $row['length'],
						'_newspack_source_url'             => $source_url,
					]
				], true );
			} else {
				$post_id = wp_insert_post( [
					'post_type'     => 'post',
					'post_status'   => self::POST_STATUS_MAP[ $row['status'] ],
					'post_author'   => $default_author,
					'post_title'    => (string) $row['topic'],
					'post_excerpt'  => (string) $row['description'],
					'post_content'  => serialize_block( $this->gutenberg_block_generator->get_paragraph( (string) $row['description'] ) ),
					'post_date_gmt' => '',
					'post_date'     => date( 'Y-m-d H:i:s', strtotime( $row['posted_date'] ) ),
					'post_category' => [ $category->term_id ],
					'meta_input'    => [
						'newspack_featured_image_position' => 'hidden', // Default Featured Image should be hidden, by default.
						'_newspack_source_id'              => $row['id'],
						'_newspack_source_length'          => $row['length'],
						'_newspack_source_url'             => $source_url,
					]
				], true );
			}

			if ( is_wp_error( $post_id ) ) {
				$logger->critical( sprintf( '❌ Post could not be inserted. Reason: %s', wp_json_encode( $post_id->get_error_messages() ) ) );

				continue;
			}

			$post_content         = get_post_field( 'post_content', $post_id );
			$updated_post_content = parse_blocks( $post_content );

			if ( ! empty( $row['file'] ) ) {
				try {
					$logger->info( '⏳ Uploading Audio...' );
	
					$audio_id = $this->upload_file(
						$row['file'],
						[
							'post_date' => date( 'Y-m-d H:i:s', strtotime( $row['posted_date'] ) )
						],
						$post_id
					);

					$updated_post_content = [
						$this->gutenberg_block_generator->get_audio( get_post( $audio_id ) ),
						...$updated_post_content
					];
				} catch ( \Exception $e ) {
					$logger->critical( sprintf( '❌ Post Audio could not be inserted. Reason: %s', $e->getMessage() ) );
				}
			}

			$updated_post_content = serialize_blocks( $updated_post_content );

			// Update data.
			$update_data = [];

			if ( $updated_post_content !== $post_content ) {
				$update_data['post_content'] = $updated_post_content;
			}

			if ( ! empty( $update_data ) ) {
				wp_update_post( [
					'ID' => $post_id,
					...$update_data,
				] );
			}

			$logger->info( sprintf( '✅ Post upserted. Post ID: %d', $post_id ) );

			fputcsv(
				$csv_file_pointer,
				[
					$row_number,
					$row['id'],
					$post_id,
					get_the_title( $post_id ),
					$source_url,
					get_permalink( $post_id ),
				] 
			);
		}

		$progress_bar->finish();

		fclose( $csv_file_pointer );

		wp_cache_flush();
		
		$this->logger->info( 'Completed! 🎉' );
	}

	/**
	 * Migrates document posts.
	 * 
	 * The following columns are available for each CSV Row:
	 * - id — The ID of the Document on JFP
	 * - title — The Document Title
	 * - pub_date — Publication Date in YYYY-MM-DD format
	 * - slug — Document slug
	 * - document_date - Document Date in YYYY-MM-DD format
	 * - document - The path to document file relative to CDN
	 * - source — The source of the document
	 * - thumbnail — The path to thumbnail file relative to CDN
	 * - description — The description of the document
	 * - notes — Document notes
	 * - document_set_id - Document Set (ignored)
	 * - originating_site_id - Originating Site ID (ignored)
	 *
	 * @param  array $args
	 * @param  array $assoc_args
	 * @return void
	 */
	public function cmd_migrate_documents( array $args, array $assoc_args ) {
		$csv_filepath   = (string) $assoc_args['csv-path'];
		$category_id    = (int) $assoc_args['category_id'];
		$default_author = (int) $assoc_args['default-author'];

		if ( ! file_exists( $csv_filepath ) ) {
			WP_CLI::error( sprintf( 'Provided CSV is missing — %s', $csv_filepath ) );

			return;
		}

		if ( ! category_exists( $category_id ) ) {
			WP_CLI::error( sprintf( 'Category with ID %d does not exist', $category_id ) );

			return;
		}

		$category          = get_category( $category_id );
		$csv_file_iterator = ( new FileImportFactory() )->get_file( $csv_filepath );

		// Logger.
		$log_slug = 'ellingtoncms-migrate-documents';
        $logger   = MultiLog::get_logger( 
			'multi-' . $log_slug,
			[
				CliLog::get_logger( $log_slug ),
				FileLog::get_logger( $log_slug ),
			]
		);

		// CSV.
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$csv = sprintf( 'ellingtoncms-migrate-documents-%s.csv', date( 'Y-m-d H-i-s' ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$csv_file_pointer = fopen( $csv, 'w' );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
		fputcsv(
			$csv_file_pointer,
			[
				'#',
				'Source ID',
				'Post ID',
				'Post Title',
				'Source URL',
				'Post URL',
			]
		);

		$total_posts = count( [ ...$csv_file_iterator->getIterator() ] );

		$progress_bar = WP_CLI\Utils\make_progress_bar( '[EllingtonCMS] Migrating Documents', $total_posts );

		foreach ( $csv_file_iterator->getIterator() as $row_number => $row ) {
			$progress_bar->tick(
				1,
				sprintf(
					'[Memory: %s] [EllingtonCMS] Migrating Documents %d/%d',
					size_format( memory_get_usage( true ) ),
					$row_number + 1,
					$total_posts
				)
			);

			$logger->info( sprintf( '⏳ Processing Row %s', wp_json_encode( $row ) ) );

			// Search for post locally.
			$local_post = $this->get_local_post( (int) $row['id'], $category );

			if ( $local_post ) {
				$logger->notice( sprintf( '⚠️ Post exists locally with ID %d', $local_post ) );
			} else {
				$logger->info( 'ℹ️ Post does not exist locally' );
			}

			$source_url = sprintf(
				'https://www.jacksonfreepress.com/documents/%s/%s/',
				strtolower( ( DateTime::createFromFormat( 'Y-m-d', $row['pub_date'] ) )?->format( 'Y/M/d' ) ),
				$row['slug']
			);

			if ( $local_post ) {
				$post_id = wp_update_post( [
					'ID'            => $local_post,
					'post_type'     => 'post',
					'post_status'   => 'publish',
					'post_author'   => $default_author,
					'post_title'    => (string) $row['title'],
					'post_excerpt'  => (string) $row['description'],
					'post_content'  => serialize_block( $this->gutenberg_block_generator->get_paragraph( (string) $row['description'] ) ),
					'post_name'     => (string) $row['slug'],
					'post_date_gmt' => '',
					'post_date'     => sprintf( '%s 00:00:00', $row['pub_date'] ),
					'post_category' => [ $category->term_id ],
					'meta_input'    => [
						'newspack_featured_image_position' => 'hidden', // Default Featured Image should be hidden, by default.
						'_newspack_source_id'              => $row['id'],
						'_newspack_source_pub_date'        => $row['pub_date'],
						'_newspack_source_slug'            => $row['slug'],
						'_newspack_source_document_date'   => $row['document_date'],
						'_newspack_source_source'          => $row['source'],
						'_newspack_source_url'             => $source_url,
					]
				] );
			} else {
				$post_id = wp_insert_post( [
					'post_type'     => 'post',
					'post_status'   => 'publish',
					'post_author'   => $default_author,
					'post_title'    => (string) $row['title'],
					'post_excerpt'  => (string) $row['description'],
					'post_content'  => serialize_block( $this->gutenberg_block_generator->get_paragraph( (string) $row['description'] ) ),
					'post_name'     => (string) $row['slug'],
					'post_date_gmt' => '',
					'post_date'     => sprintf( '%s 00:00:00', $row['pub_date'] ),
					'post_category' => [ $category->term_id ],
					'meta_input'    => [
						'newspack_featured_image_position' => 'hidden', // Default Featured Image should be hidden, by default.
						'_newspack_source_id'              => $row['id'],
						'_newspack_source_pub_date'        => $row['pub_date'],
						'_newspack_source_slug'            => $row['slug'],
						'_newspack_source_document_date'   => $row['document_date'],
						'_newspack_source_source'          => $row['source'],
						'_newspack_source_url'             => $source_url,
					]
				] );
			}

			if ( is_wp_error( $post_id ) ) {
				$logger->critical( sprintf( '❌ Post could not be inserted. Reason: %s', wp_json_encode( $post_id->get_error_messages() ) ) );

				continue;
			}

			$document_thumbnail_id = null;
			$document_id           = null;

			$post_content         = get_post_field( 'post_content', $post_id );
			$updated_post_content = parse_blocks( $post_content );

			if ( ! empty( $row['thumbnail'] ) ) {
				try {
					$logger->info( '⏳ Uploading Thumbnail...' );

					$document_thumbnail_id = $this->upload_file(
						$row['thumbnail'],
						[
							'post_date' => sprintf( '%s 00:00:00', $row['pub_date'] )
						],
						$post_id
					);
				} catch ( \Exception $e ) {
					$logger->critical( sprintf( '❌ Post Thumbnail could not be inserted. Reason: %s', $e->getMessage() ) );
				}
			}

			if ( ! empty( $row['document'] ) ) {
				try {
					$logger->info( '⏳ Uploading Document...' );
	
					$document_id = $this->upload_file(
						$row['document'],
						[
							'post_date' => sprintf( '%s 00:00:00', $row['pub_date'] )
						],
						$post_id
					);

					if ( in_array( get_post_mime_type( $document_id ), [ 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ] ) ) {
						$updated_post_content = [
							$this->gutenberg_block_generator->get_iframe( wp_get_attachment_url( $document_id ) ),
							...$updated_post_content
						];
					} else {
						$updated_post_content = [
							$this->gutenberg_block_generator->get_image( get_post( $document_id ), 'full', false ),
							...$updated_post_content
						];
					}
				} catch ( \Exception $e ) {
					$logger->critical( sprintf( '❌ Post Document could not be inserted. Reason: %s', $e->getMessage() ) );
				}
			}

			$updated_post_content = serialize_blocks( $updated_post_content );

			// Update data.
			$update_data = [];

			if ( $updated_post_content !== $post_content ) {
				$update_data['post_content'] = $updated_post_content;
			}

			if ( ! empty( $document_thumbnail_id ) ) {
				$update_data['meta_input'] = [
					'_thumbnail_id' => $document_thumbnail_id,
				];
			}

			if ( ! empty( $update_data ) ) {
				wp_update_post( [
					'ID' => $post_id,
					...$update_data,
				] );
			}

			$logger->info( sprintf( '✅ Post inserted. Post ID: %d', $post_id ) );

			fputcsv(
				$csv_file_pointer,
				[
					$row_number,
					$row['id'],
					$post_id,
					get_the_title( $post_id ),
					$source_url,
					get_permalink( $post_id ),
				] 
			);
		}

		$progress_bar->finish();

		fclose( $csv_file_pointer );

		wp_cache_flush();
		
		$this->logger->info( 'Completed! 🎉' );
	}

	/**
	 * Upsert Post from an XML file.
	 * 
	 * @param  string      $file_contents The contents of the XML file.
	 * @param  string      $filename The name of the XML file.
	 * @param  string      $source_timezone The Timezone of the Source.
	 * @param  string|null $default_author The default author to use for the Posts.
	 * @param  string|null $default_featured_image The default featured image to use for the Posts.
	 * @param  string|null $post_tag The Post Tag to apply to the Post.
	 * @param  boolean     $refresh Whether the Post should be refreshed.
	 * @return int The ID of the created / updated Post.
	 */
	private function upsert_post(
		string $file_contents,
		string $filename,
		string $source_timezone,
		?string $default_author,
		?string $default_featured_image,
		?string $post_tag,
		bool $refresh = false
	): int {
		global $wpdb;

		preg_match( '~^story-(\d+)~', $filename, $post_source_id );

		$post_source_id = $post_source_id[1];

		$local_post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `post_id`
                FROM `$wpdb->postmeta`
                WHERE `meta_key` = 'newspack_post_source_id'
                AND `meta_value` = %s",
				$post_source_id
			)
		);

		if ( $local_post_id ) {
			$this->logger->warning( sprintf( 'Post #%d already exists', $local_post_id ) );
		}

		if ( $local_post_id && ! $refresh ) {
			$this->logger->info( 'Skipping update (Refresh not required)' );

			return absint( $local_post_id );
		}

		$post_data = $this->parse_post_xml( $file_contents );

		// Post Data Comments
		if ( ! empty( $post_data['comments'] ) ) {
			// Details
			//  — Group
			// — — Comment Text
			// — — Comment Meta
			$comment_blocks = [];

			foreach ( $post_data['comments'] as $comment_index => $comment ) {
				$comment_block = [
					$this
						->gutenberg_block_generator
						->get_paragraph( $comment['Comment'] ),
					...parse_blocks(
						sprintf(
							'<!-- wp:paragraph {"style":{"elements":{"link":{"color":{"text":"%1$s"}}},"color":{"text":"%1$s"}},"fontSize":"small"} --><p class="has-text-color has-link-color has-small-font-size" style="color:%1$s">#%2$s | Author: %3$s | Date: %4$s</p><!-- /wp:paragraph -->',
							'#bbbbbb',
							$comment['ID'],
							$comment['Author'],
							date( 'M j Y', strtotime( $comment['Date'] ) )
						)
					),
				];

				$comment_blocks[] = $this
					->gutenberg_block_generator
					->get_group_constrained( $comment_block, [ 'jfp-comment-' . $comment['ID'] ] );

				if ( $comment_index !== count( $post_data['comments'] ) - 1 ) {
					$comment_blocks[] = $this
						->gutenberg_block_generator
						->get_separator( 'is-style-wide' );
				}
			}

			$details_block_inner_content = [ '<details class="wp-block-details jfp-previous-comments"><summary>Previous Comments</summary>' ];

			foreach ( $comment_blocks as $index => $comment_block ) {
				$details_block_inner_content[] = null;

				if ( $index < ( count( $comment_blocks ) - 1 ) ) {
					$details_block_inner_content[] = '';
				}
			}

			$details_block_inner_content[] = '</details>';

			$post_data['content'] .= serialize_block(
				[
					'blockName'    => 'core/details',
					'attrs'        => [
						'className' => 'jfp-previous-comments',
					],
					'innerBlocks'  => $comment_blocks,
					'innerHTML'    => '<details class="wp-block-details jfp-previous-comments"><summary>Previous Comments</summary> </details>',
					'innerContent' => $details_block_inner_content,
				] 
			);
		}

		// Post Authors.
		$post_authors = array_values(
			array_filter(
				array_map(
					function ( $author_data ) use ( $post_source_id ) {
						$wp_user = $this->upsert_author( $author_data['first_name'], $author_data['last_name'] );

						if ( is_wp_error( $wp_user ) ) {
							$this->logger->critical( sprintf( '#' . $post_source_id . ' Couldn\'t upsert Author "%s" (%s)', implode( ', ', array_filter( $author_data ) ), $wp_user->get_error_message( 0 ) ) );

							return null;
						}

						return $wp_user;
					},
					$post_data['authors']
				) 
			) 
		);

		// Categories.
		$categories = $this->upsert_categories( $post_data['category_slugs'] );

		// Published Date
		$published_datetime     = new DateTime( $post_data['published_date'], new DateTimeZone( $source_timezone ) );
		$published_datetime_gmt = new DateTime( $post_data['published_date'], new DateTimeZone( $source_timezone ) );
		$published_datetime_gmt->setTimezone( new DateTimeZone( 'GMT' ) );

		// Insert / Update Post.
		$post_data = [
			'ID'            => $local_post_id ?? 0,
			'post_title'    => $post_data['title'],
			'post_excerpt'  => $post_data['excerpt'],
			'post_content'  => $post_data['content'],
			'post_status'   => 'publish',
			'post_author'   => ! empty( $post_authors ) ? $post_authors[0]->ID : $default_author,
			'post_date'     => $published_datetime->format( 'Y-m-d H:i:s' ),
			'post_date_gmt' => $published_datetime_gmt->format( 'Y-m-d H:i:s' ),
			'post_category' => wp_list_pluck( $categories, 'term_id' ),
			'tags_input'    => ! empty( $post_tag ) ? [ $post_tag ] : [], 
			'meta_input'    => [
				'_thumbnail_id'                    => $default_featured_image,
				'newspack_featured_image_position' => 'hidden', // Default Featured Image should be hidden, by default.
				'newspack_post_source_id'          => $post_source_id,
				'newspack_post_source_url'         => $post_data['full_slug'],
				'newspack_post_source_filename'    => $filename,
			],
		];

		if ( $local_post_id ) {
			$post_id = wp_update_post( $post_data );
		} else {
			$post_id = wp_insert_post( $post_data );
		}
		
		try {
			if ( $this->cap->is_coauthors_active() && ! empty( $post_authors ) ) {
				$this->cap->assign_authors_to_post( $post_authors, $post_id );
			}
		} catch ( \Exception $e ) {
			$this->logger->critical( '🚫 Error assigning authors to post. Post ID: ' . $post_id . ' — ' . $e->getMessage() );
		}

		// Address media in post content.
		// We do it after the post has been imported in order to associate the
		// images inside with the post ID.
		$this->transform_and_update_post_content( $post_id );

		return $post_id;
	}

	/** 
	 * Parses the given Post XML and returns an array of raw mapped values.
	 * 
	 * @param  string $xml_file_contents
	 * @return array  The mapped raw values for the Post.
	 */
	private function parse_post_xml( string $xml_file_contents ): array {
		// Replace invalid HTML tags in order to read them later.
		$xml_file_contents = str_replace( '<date.release', '<date_release', $xml_file_contents );
		$xml_file_contents = str_replace( '<body.head>', '<body_head>', $xml_file_contents );
		$xml_file_contents = str_replace( '</body.head>', '</body_head>', $xml_file_contents );
		$xml_file_contents = str_replace( '<body.content>', '<body_content>', $xml_file_contents );
		$xml_file_contents = str_replace( '</body.content>', '</body_content>', $xml_file_contents );
		$xml_file_contents = str_replace( [ '<name.given>', '</name.given>' ], [ '<name_given>', '</name_given>' ], $xml_file_contents );
		$xml_file_contents = str_replace( [ '<name.family>', '</name.family>' ], [ '<name_family>', '</name_family>' ], $xml_file_contents );

		$post_doc = new HtmlDocument( $xml_file_contents );
		
		$post_data = [
			'title'          => $post_doc->find( 'nitf > body > body_head > hedline > hl1', 0 )?->text(),
			'subtitle'       => $post_doc->find( 'nitf > body > body_head > hedline > hl2', 0 )?->text(),
			'category_slugs' => $post_doc->find( 'nitf > head > meta[name="category"]', 0 )?->getAttribute( 'content' ),
			'full_slug'      => $post_doc->find( 'nitf > head > doc-id', 0 )?->getAttribute( 'id-string' ),
			'published_date' => $post_doc->find( 'nitf > head > docdata > date_release', 0 )?->getAttribute( 'norm' ),
			'excerpt'        => $post_doc->find( 'nitf > body > body_head > abstract', 0 )?->text(),
			'content'        => str_replace(
				[ '<body_content>', '</body_content>' ],
				'',
				$post_doc->find( 'nitf > body > body_content', 0 )?->text()
			),
			'authors'        => array_map(
				function ( $author ) {
					return [
						'first_name' => $author->find( 'name_given', 0 )?->text(),
						'last_name'  => $author->find( 'name_family', 0 )?->text(),
					];
				},
				$post_doc->find( 'nitf > body > body_head > byline > person' ) ?? []
			),
		];

		// There is a div after the optional first image and at the end of the content.
		// We need to remove it.
		$post_data['content'] = $this->filter_post_content( $post_data['content'] );

		// Handle Post Comments.
		if ( strpos( $post_data['content'], 'Previous Comments' ) ) {
			// Organize comments.
			$content_parts = explode( '<h3>Previous Comments</h3>', $post_data['content'] );
			$comments      = ( new HTMLDocument( $content_parts[1] ) )->find( 'dl' );
			
			$post_data['comments'] = array_map(
				function ( $comment ) {
					$comment_data    = [];
					$comment_details = array_chunk( $comment->nodes, 2 );

					foreach ( $comment_details as $comment_detail ) {
						$comment_data[ $comment_detail[0]->text() ] = $comment_detail[1]->text();
					}

					return $comment_data;
				},
				$comments
			);

			// Cleanup comments from body
			$post_data['content'] = trim( $content_parts[0] );
		}

		return $post_data;
	}

	/**
	 * Transform posts body content.
	 * 
	 * @param  int $post_id The ID of the Post to be updated.
	 * @return void
	 */
	private function transform_and_update_post_content( $post_id ): void {
		global $wpdb;

		$post_content = get_post_field( 'post_content', $post_id );

		if ( ! str_contains( $post_content, '<media' ) ) {
			return;
		}

		$post_content_doc = new HtmlDocument( $post_content );

		$medias = $post_content_doc->find( 'media' );

		foreach ( $medias as $media ) {
			$is_post_thumbnail = $media->find( 'media-metadata[name=lead_photo]', 0 )?->getAttribute( 'value' ) == 'true';
			$image_id          = $media->find( 'media-metadata[name=id]', 0 )->getAttribute( 'value' );
			$image_src         = $media->find( 'media-reference', 0 )->getAttribute( 'source' );
			$image_credit      = $media->find( 'media-producer', 0 )->text();
			$image_caption     = $media->find( 'media-caption', 0 )->text();

			$attachment_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT `post_id`
                    FROM `$wpdb->postmeta`
                    WHERE `meta_key` = 'newspack_attachment_source_id'
                    AND `meta_value` = %s",
					$image_id
				)
			);

			if ( ! $attachment_id ) {
				$attachment_id = $this
					->attachments
					->import_external_file( $image_src, null, $image_caption, null, null, $post_id );

				update_post_meta( $attachment_id, 'newspack_attachment_source_id', $image_id );
				update_post_meta( $attachment_id, '_media_credit', $image_credit );
			}

			if ( is_wp_error( $attachment_id ) ) {
				$this->logger->critical( '🚫 Error importing attachment. Post ID: ' . $post_id . ' — ' . $attachment_id->get_error_message() );
				continue;
			}

			if ( ! $attachment_id ) {
				continue;
			}

			if ( $is_post_thumbnail ) {
				update_post_meta( $post_id, '_thumbnail_id', $attachment_id );
				update_post_meta( $post_id, 'newspack_featured_image_position', '' ); // Show featured image.

				$post_content = str_replace( $media->outerText(), '', $post_content );
			} else {
				$post_content = str_replace(
					$media->outerText(),
					sprintf(
						'[caption id="attachment_%s" align="alignleft"]%s[/caption]',
						$attachment_id,
						wp_get_attachment_image( $attachment_id, 'thumbnail' ) . $image_caption
					),
					$post_content
				);
			}
		}

		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => $post_content,
			] 
		);
	}

	/**
	 * Inserts a new User with the given first and last (optional) name.
	 * The email of the User should be "name.given+name.family+jfp@mississippifreepress.org".
	 * 
	 * @param  string      $first_name
	 * @param  string|null $last_name
	 * @return WP_User|WP_Error The ID of the User on success. Otherwise, returns a WP_Error
	 */
	private function upsert_author( $first_name, $last_name = null ): WP_User|WP_Error {
		$username   = substr(
			implode(
				'',
				array_map(
					fn ( $name_part ) => str_replace( '-', '', sanitize_title( $name_part ) ),
					array_filter( [ $first_name, $last_name ] )
				) 
			),
			0,
			60 
		); // Username can be max 60 chars.
		$user_email = $username . '+jfp@mississippifreepress.org';

		$wp_user = get_user_by( 'email', $user_email ) ?: get_user_by( 'login', $username );

		if ( ! $wp_user ) {
			$wp_user = wp_insert_user(
				[
					'first_name'    => $first_name,
					'last_name'     => $last_name,
					'display_name'  => implode( ' ', array_filter( [ $first_name, $last_name ] ) ),
					'user_nicename' => substr( $username, 0, 50 ), // User Nicename can be max 50 chars.
					'user_login'    => $username,
					'user_email'    => $user_email,
					'user_pass'     => wp_generate_password(),
					'role'          => 'author',
					'meta_input'    => [
						'newspack_user_imported_date' => date( 'Y-m-d H:i:s' ),
						'newspack_user_source'        => 'Jackson Free Press',
					],
				] 
			);

			if ( ! is_wp_error( $wp_user ) ) {
				$wp_user = get_user_by( 'ID', $wp_user );
			}
		}

		return $wp_user;
	}

	/**
	 * Upserts categories.
	 * 
	 * The categories are passed by raw value such as "/candidate|/imported|/interview".
	 * The example above contains three categories, each of them representing a Category slug.
	 * 
	 * This method takes the raw value and tries to insert a new category for it, or returns an
	 * already existing Category.
	 * 
	 * @param  string $raw_category_slugs The category slugs passed in the format from the description.
	 * @return array  An array of WP_Term instances representing Categories.
	 */
	private function upsert_categories( string $raw_category_slugs ): array {
		$category_slugs = explode( '|', $raw_category_slugs );
		$category_slugs = array_map(
			function ( $category_slug ) {
				if ( $category_slug === '/' ) {
						$category_slug = 'news';
				}

				return str_replace( '/', '', $category_slug );
			},
			$category_slugs 
		);

		$category_names = array_map( fn ( $category ) => ucwords( str_replace( '-', ' ', $category ) ), $category_slugs );

		$categories = [];

		foreach ( $category_names as $index => $category_name ) {
			if ( empty( $category_name ) ) {
				continue;
			}

			$category = get_term_by( 'slug', $category_slugs[ $index ], 'category' );

			if ( ! $category ) {
				// Category couldn't be found. Trying to insert it.
				$category = wp_insert_term( $category_name, 'category' );
			}

			if ( is_wp_error( $category ) ) {
				$this->logger->critical( '🚫 Error inserting category: ' . $category->get_error_message() . ' | ' . $category_name . ' | ' . $raw_category_slugs );
			} else {
				$categories[] = $category;
			}
		}

		return $categories;
	}

	/**
	 * Creates a "Previous Comments" from the given comments.
	 * 
	 * @param  array $comments An array of comments to create the comments block.
	 * @return array
	 */
	private function create_comments_block( array $comments = [] ): array {
		// Details
		//  — Group
		// — — Comment Text
		// — — Comment Meta
		$comment_blocks = [];

		foreach ( $comments as $comment_index => $comment ) {
			$comment_block = [
				$this
					->gutenberg_block_generator
					->get_paragraph( $comment['Comment'] ),
				...parse_blocks(
					sprintf(
						'<!-- wp:paragraph {"style":{"elements":{"link":{"color":{"text":"%1$s"}}},"color":{"text":"%1$s"}},"fontSize":"small"} --><p class="has-text-color has-link-color has-small-font-size" style="color:%1$s">#%2$s | Author: %3$s | Date: %4$s</p><!-- /wp:paragraph -->',
						'#bbbbbb',
						$comment['ID'],
						$comment['Author'],
						date( 'M j Y', strtotime( $comment['Date'] ) )
					)
				),
			];

			$comment_blocks[] = $this
				->gutenberg_block_generator
				->get_group_constrained( $comment_block, [ 'jfp-comment-' . $comment['ID'] ] );

			if ( $comment_index !== count( $comments ) - 1 ) {
				$comment_blocks[] = $this
					->gutenberg_block_generator
					->get_separator( 'is-style-wide' );
			}
		}

		$details_block_inner_content = [ '<details class="wp-block-details jfp-previous-comments"><summary>Previous Comments</summary>' ];

		foreach ( $comment_blocks as $index => $comment_block ) {
			$details_block_inner_content[] = null;

			if ( $index < ( count( $comment_blocks ) - 1 ) ) {
				$details_block_inner_content[] = '';
			}
		}

		$details_block_inner_content[] = '</details>';

		return [
			'blockName'    => 'core/details',
			'attrs'        => [
				'className' => 'jfp-previous-comments',
			],
			'innerBlocks'  => $comment_blocks,
			'innerHTML'    => '<details class="wp-block-details jfp-previous-comments"><summary>Previous Comments</summary> </details>',
			'innerContent' => $details_block_inner_content,
		];
	}

	/**
	 * Filters Post Content.
	 */
	private function filter_post_content( string $content ): string {
		$filtered_content = '';

		$post_content_doc = new HtmlDocument( $content );

		foreach ( $post_content_doc->childNodes() as $child_node ) {
			if ( $child_node->tag === 'div' ) {
				$filtered_content .= $child_node->innerText();
			} else {
				$filtered_content .= $child_node->outerText();
			}
		}

		return $filtered_content;
	}

	/**
	 * Search local Post by given ID and Category.
	 * 
	 * @param  int      $ID       The Source ID of the Post.
	 * @param  \WP_Term $category The belonging Category.
	 * @return int|null The found WP_Post ID. Otherwise, false.
	 */
	private function get_local_post( int $ID, WP_Term $category ): int|null {
		$local_posts = new WP_Query( [
			'post_type'      => 'post',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'cat'            => $category->term_id,
			'meta_query'     => [
				[
					'key'   => '_newspack_source_id',
					'value' => $ID,
				]
			]
		] );

		if ( $local_posts->have_posts() ) {
			return $local_posts->get_posts()[0];
		}

		return null;
	}

	/**
	 * Uploads a file from CDN.
	 * 
	 * @throws \Exception
	 * 
	 * @param  string   $filepath The path to file relative to EllingtonCMS CDN or a full url.
	 * @param  string   $filedata The additional file data.
	 * @param  int|null $post_ID  The ID of the related Post.
	 * @return int The Attachment ID on success. Otherwise, an exception is thrown.
	 */
	private function upload_file( string $filepath, array $filedata, int|null $post_ID ): int {
		add_filter( 'intermediate_image_sizes_advanced', '__return_null' ); // Prevent image resizing

		if ( ! str_starts_with( $filepath, 'http' ) ) {
			$filepath = untrailingslashit( self::CDN_BASE_URL ) . '/' . ltrim( $filepath, '/\\' );
		}

		$filedata = wp_parse_args( $filedata, [
			'title'       => null,
			'caption'     => null,
			'description' => null,
			'alt'         => null,
			'post_date'   => null,
			'filename'    => basename( $filepath ),
		] );

		$retry_counter = 0;

		while ( $retry_counter < self::MAX_CURL_RETRIES ) {
			$retry_counter++;

			try {
				$attachment_id = $this
					->attachments
					->import_external_file(
						$filepath,
						$filedata['title'],
						$filedata['caption'],
						$filedata['description'],
						$filedata['alt'],
						$post_ID ?? 0,
						[
							'post_date'     => $filedata['post_date'],
							'post_date_gmt' => '',
						],
						$filedata['filename'],
					);

				if ( is_wp_error( $attachment_id ) ) {
					throw new \Exception( $attachment_id->get_error_message() );
				} else {
					break;
				}
			} catch ( \Exception $e ) {
				if ( $retry_counter === self::MAX_CURL_RETRIES ) {
					throw $e;
				}
			}
		}

		update_post_meta( $attachment_id, '_newspack_attachment_source_url', $filepath );

		return $attachment_id;
	}
}
