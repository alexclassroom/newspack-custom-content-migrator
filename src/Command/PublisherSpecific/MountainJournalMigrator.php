<?php
/**
 * Migration tasks for Mountain Journal.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Exception;
use Newspack\Guest_Contributor_Role;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Logic\SimpleLocalAvatars;
use Newspack\MigrationTools\Logic\UsersHelper;
use Newspack\MigrationTools\Util\CsvIterator;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use Simple_Local_Avatars;
use WP_CLI;
use simplehtmldom\HtmlDocument;

/**
 * Custom migration scripts for Mountain Journal.
 */
class MountainJournalMigrator implements RegisterCommandInterface {
	use WpCliCommandTrait;

    const LIVE_SITE_URL        = 'https://mountainjournal.org';
    const PLACEHOLDER_USER_URL = 'http://mountainjournal.org/content/authors/';
    const PLACEHOLDER_POST_URL = 'http://mountainjournal.org/content/articles/';
    const DEFAULT_AUTHOR_ROLE  = Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME;
    const DEFAULT_AUTHOR_LOGIN = 'Mountain Journal';

	/**
	 * CSV Iterator.
	 * 
	 * @var CsvIterator
	 */
	private CsvIterator $csv_iterator;

    /**
	 * Attachments logic.
	 * 
	 * @var Attachments
	 */
	private Attachments $attachments;

    /**
	 * Gutenberg Block Generator logic.
	 * 
	 * @var GutenbergBlockGenerator
	 */
	private GutenbergBlockGenerator $gutenberg_block_generator;

    /**
     * Simple Local Avatars logic.
     *
	 * @var SimpleLocalAvatars
	 */
	protected SimpleLocalAvatars $simple_local_avatar_logic;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->csv_iterator              = new CsvIterator();
        $this->attachments               = new Attachments();
        $this->gutenberg_block_generator = new GutenbergBlockGenerator();
        $this->simple_local_avatar_logic = new SimpleLocalAvatars();

        $this->simple_local_avatar_logic->simple_local_avatars = new Simple_Local_Avatars();
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

        WP_CLI::add_command(
			'newspack-content-migrator mj-migrate-authors',
			self::get_command_closure( 'cmd_migrate_authors' ),
			[
				'shortdesc' => 'Migrate Authors',
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

        WP_CLI::add_command(
			'newspack-content-migrator mj-migrate-posts',
			self::get_command_closure( 'cmd_migrate_posts' ),
			[
				'shortdesc' => 'Migrate Posts',
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

        WP_CLI::add_command(
			'newspack-content-migrator mj-migrate-posts-terms',
			self::get_command_closure( 'cmd_migrate_posts_terms' ),
			[
				'shortdesc' => 'Migrate Posts Terms',
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

        WP_CLI::add_command(
			'newspack-content-migrator mj-scrape-article-categories',
			self::get_command_closure( 'cmd_scrape_article_categories' ),
			[
				'shortdesc' => 'Exports the Categories and Tags for all Posts from the live site.',
				'synopsis'  => [],
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

		$file_logger = FileLog::get_logger( 'migrate-categories-and-tags' );

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

			$file_logger->info( sprintf( '👉 Processing Entry %s', wp_json_encode( $csv_row ) ) );

			if ( term_exists( $csv_row['Title'], $taxonomy_map[ $csv_row['Type'] ] ) ) {
				$file_logger->info( sprintf( '⚠️ %s %s already exists. Skipping...', $taxonomy_map[ $csv_row['Type'] ], $csv_row['Title'] ) );

				continue;
			}

			$inserted_term = wp_insert_term(
				$csv_row['Title'],
				$taxonomy_map[ $csv_row['Type'] ]
			);

			if ( is_wp_error( $inserted_term ) ) {
				$file_logger->error( sprintf( '🚫 Could not insert term. Error: %s', wp_json_encode( $inserted_term->get_error_messages() ) ) );
				continue;
			}

			$file_logger->info( sprintf( '✅ Successfully inserted term %s', $csv_row['Title'] ) );

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

		$file_logger->info( '🎉 Done' );

		wp_cache_flush();
	}

    /**
	 * Migrate Authors from a CSV file.
	 * 
	 * @uses wp newspack-content-migrator mj-migrate-authors --source-filepath="{FILEPATH}"
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_migrate_authors( array $pos_args, array $assoc_args ): void {
        add_filter( 'intermediate_image_sizes_advanced', '__return_null' );

		$source_filepath = $assoc_args['source-filepath'];

		// Prelimiary checks.
		if ( ! $source_filepath || ! file_exists( $source_filepath ) ) {
			WP_CLI::error( sprintf( 'Incorrect source filepath: %s', $source_filepath ) );
			return;
		}

		$log_slug = 'migrate-authors';
        $logger   = MultiLog::get_logger( 
			'multi-' . $log_slug,
			[
				CliLog::get_logger( $log_slug ),
				FileLog::get_logger( $log_slug ),
			]
		);

		// CSV.
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$csv = sprintf( 'migrate-authors-%s.csv', date( 'Y-m-d H-i-s' ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$csv_file_pointer = fopen( $csv, 'w' );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
		fputcsv(
			$csv_file_pointer,
			[
				'#',
				'User',
				'User ID'
			]
		);

		$total_authors = $this->csv_iterator->count_csv_file_entries( $source_filepath, ',' );

		$progress_bar = WP_CLI\Utils\make_progress_bar( '[Mountain Journal] Migrating Authors', $total_authors );

		foreach ( $this->csv_iterator->items( $source_filepath, ',' ) as $index => $csv_row ) {
			$progress_bar->tick(
				1,
				sprintf(
					'[Memory: %s] [Mountain Journal] Migrating Authors %d/%d',
					size_format( memory_get_usage( true ) ),
					$index + 1,
					$total_authors
				)
			);

			$logger->info( sprintf( '👉 Processing Entry %s', wp_json_encode( $csv_row ) ) );

            if ( empty( $csv_row['Name'] ) ) {
                $logger->info( '🚫 Missing Name. Skipping...' );

                continue;
            }

            try {
                $user = UsersHelper::create_or_get_user(
                    [
                        'user_login'  => $csv_row['Name'],
                        'role'        => static::DEFAULT_AUTHOR_ROLE,
                        'description' => $csv_row['Description'],
                    ],
                    $csv_row['Name']
                );

                // Upload Avatar
                if ( $csv_row['Image'] !== static::PLACEHOLDER_USER_URL ) {
                    $logger->info( sprintf( '👉 Processing User Avatar %s', $csv_row['Image'] ) );

                    if ( $existing_avatar_id = $this->simple_local_avatar_logic->user_has_local_avatar( $user->ID ) ) {
                        $logger->info( sprintf( '👉 User already has avatar %s', $existing_avatar_id ) );
                    } else {
                        preg_match( '~^' . self::PLACEHOLDER_USER_URL . 'ic\_(\d+)\_(.+)$~', $csv_row['Image'], $matches );
    
                        $avatar_date = date( 'Y-m-01 00:00:00', $matches[1] );
                        $filename    = $matches[2];
    
                        $avatar_id = $this
                            ->attachments
                            ->import_external_file(
                                $csv_row['Image'],
                                sprintf( '%s Avatar', $csv_row['Name'] ),
                                sprintf( '%s Avatar', $csv_row['Name'] ),
                                sprintf( '%s Avatar', $csv_row['Name'] ),
                                null,
                                0,
                                [
                                    'post_date'     => $avatar_date,
                                    'post_date_gmt' => '',
                                ],
                                $filename,
                            );
    
                        if ( $this->simple_local_avatar_logic->assign_avatar( $user->ID, $avatar_id ) ) {
                            $logger->info( sprintf( '✅ Successfully assigned Avatar ID %s', $avatar_id ) );
                        } else {
                            $logger->info( sprintf( '🚫 Could not assign Avatar with ID %s', $avatar_id ) );
                        }
                    }
                } else {
                    $logger->info( '👉 User does not have Avatar' );
                }
            } catch ( Exception $e ) {
                $logger->info( sprintf( '🚫 ERROR: Could not create user with name %s. Reason — %s', $csv_row['Name'], wp_json_encode( $e ) ) );

                continue;
            }

			$logger->info( sprintf( '✅ Successfully inserted User %s', $csv_row['Name'] ) );

			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
			fputcsv(
				$csv_file_pointer,
				[
					$index + 1,
					$csv_row['Name'],
					$user->ID
				]
			);
		}

		$progress_bar->finish();

		// Close CSV.
		fclose( $csv_file_pointer );

		$logger->info( '🎉 Done' );

		wp_cache_flush();
	}

    /**
	 * Migrate Posts from a CSV file.
     * 
     * The "Url Key" will be used as a unique identifier with a fallback to sanitize_title.
     * 
     * A unique identifier is stored for each Post consisting ot Title, Sub Title, Url Key and Date Created.
     * Neither of those is unique separately and we don't have an ID to use for that purpose.
	 * 
	 * @uses wp newspack-content-migrator mj-migrate-posts --source-filepath="{FILEPATH}"
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_migrate_posts( array $pos_args, array $assoc_args ): void {
        add_filter( 'intermediate_image_sizes_advanced', '__return_null' );

		$source_filepath = $assoc_args['source-filepath'];

		// Prelimiary checks.
		if ( ! $source_filepath || ! file_exists( $source_filepath ) ) {
			WP_CLI::error( sprintf( 'Incorrect source filepath: %s', $source_filepath ) );
			return;
		}

        $log_slug = 'migrate-posts';
        $logger   = MultiLog::get_logger( 
			'multi-' . $log_slug,
			[
				CliLog::get_logger( $log_slug ),
				FileLog::get_logger( $log_slug ),
			]
		);

		// CSV.
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$csv = sprintf( 'migrate-posts-%s.csv', date( 'Y-m-d H-i-s' ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$csv_file_pointer = fopen( $csv, 'w' );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
		fputcsv(
			$csv_file_pointer,
			[
				'#',
				'Post ID',
				'Post Title',
                'Post URL',
                'Live Site URL',
			]
		);

		$total_posts = $this->csv_iterator->count_csv_file_entries( $source_filepath, ',' );

		$progress_bar = WP_CLI\Utils\make_progress_bar( '[Mountain Journal] Migrating Posts', $total_posts );

        global $wpdb;

        $default_author = UsersHelper::create_or_get_user(
            [
                'user_login' => self::DEFAULT_AUTHOR_LOGIN,
                'role'       => static::DEFAULT_AUTHOR_ROLE,
            ],
            self::DEFAULT_AUTHOR_LOGIN
        );

		foreach ( $this->csv_iterator->items( $source_filepath, ',' ) as $index => $csv_row ) {
            $unique_identifier = md5( implode( '|', array_filter( $csv_row ) ) );

			$progress_bar->tick(
				1,
				sprintf(
					'[Memory: %s] [Mountain Journal] Migrating Posts %d/%d',
					size_format( memory_get_usage( true ) ),
					$index + 1,
					$total_posts
				)
			);

			$logger->info( sprintf( '👉 Processing Entry %s', $csv_row['Title'] ) );

            // Fetch Post by Url Key.
            $existing_post = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT `post_id`
                     FROM `{$wpdb->postmeta}`
                     WHERE `meta_key` = 'newspack_post_source_unique_identifier'
                     AND `meta_value` = %s",
                    $unique_identifier
                )
            );

            // if ( ! empty( $existing_post ) ) {
            //     $logger->info( sprintf( '⚠️ Post %s already exists. Skipping...', $csv_row['Title'] ) );

            //     continue;
            // }

            // $author_user = UsersHelper::get_user_by_unique_identifier( $csv_row['Author'] );

            // if ( empty( $author_user ) ) {
            //     $author_user = $default_author;
            // }

            $post_sections = array_filter( $csv_row, function ( $value, $key ) {
                return str_starts_with( $key, 'Section ' );
            }, ARRAY_FILTER_USE_BOTH );

            // $raw_post_content       = $this->generate_post_content( $post_sections, true );
            $converted_post_content = $this->generate_post_content( $post_sections, false );

            if ( $converted_post_content !== get_post_field( 'post_content', $existing_post ) ) {
                // Revision is stored to be able to track the block transformations made.
                wp_save_post_revision( $existing_post );

                wp_update_post( [
                    'ID'           => $existing_post,
                    'post_content' => $converted_post_content,
                ] );
                // var_dump( $existing_post, $csv_row['Title'] );

                // var_dump( $converted_post_content, get_post_field( 'post_content', $existing_post ) );
            }
            
            // echo '<pre>';
            // var_dump( $raw_post_content, str_repeat( "\n", 3 ), $converted_post_content );
            // var_dump( $converted_post_content );
            continue;

            $thumbnail_id            = $this->get_post_thumbnail_id( $csv_row );
            $open_graph_thumbnail_id = $this->get_post_open_graph_thumbnail_id( $csv_row );

            $post_meta = [
                'newspack_post_source_unique_identifier' => $unique_identifier,
                'newspack_post_subtitle'                 => $csv_row['Sub Title'],

                // Yoast.
                '_yoast_wpseo_title'    => $csv_row['Meta Title'],
                '_yoast_wpseo_metadesc' => $csv_row['Meta Description'],
                '_yoast_wpseo_focuskw'  => $csv_row['Meta Keywords'],
            ];

            if ( ! empty( $thumbnail_id ) ) {
                $post_meta['_thumbnail_id'] = $thumbnail_id;
            }

            if ( ! empty( $open_graph_thumbnail_id ) ) {
                $post_meta['_yoast_wpseo_opengraph-image-id'] = $open_graph_thumbnail_id;
                $post_meta['_yoast_wpseo_twitter-image-id']   = $open_graph_thumbnail_id;
                $post_meta['_yoast_wpseo_opengraph-image']    = wp_get_attachment_url( $open_graph_thumbnail_id );
                $post_meta['_yoast_wpseo_twitter-image']      = wp_get_attachment_url( $open_graph_thumbnail_id );
            }

            $post_id = wp_insert_post( [
                'post_title'    => $csv_row['Title'],
                'post_content'  => $raw_post_content,
                'post_author'   => $author_user->ID,
                'post_excerpt'  => strip_tags( $csv_row['Summary'] ),
                'post_status'   => $csv_row['Active'] === 'yes' ? 'publish' : 'draft',
                'post_name'     => $csv_row['Url Key'] ?? sanitize_title( $csv_row['Title'] ),
                'post_date_gmt' => '',
                'post_date'     => date( 'Y-m-d H:i:s', strtotime( $csv_row['Date Created'] ) ),
                'meta_input'    => $post_meta,
            ] );

            if ( is_wp_error( $post_id ) ) {
                $logger->error( sprintf( '🚫 Could not insert post. Error: %s', wp_json_encode( $post_id->get_error_messages() ) ) );
                
				continue;
            }

            // Revision is stored to be able to track the block transformations made.
            wp_save_post_revision( $post_id );

            wp_update_post( [
                'ID'           => $post_id,
                'post_content' => $converted_post_content,
            ] );

			$logger->info( sprintf( '✅ Successfully inserted post %s', $csv_row['Title'] ) );

			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
			fputcsv(
				$csv_file_pointer,
				[
					$index + 1,
					$post_id,
					$csv_row['Title'],
                    get_permalink( $post_id ),
                    ! empty( $csv_row['Url Key'] ) ? sprintf( 'https://mountainjournal.org/%s', $csv_row['Url Key'] ) : null
				]
			);
		}

		$progress_bar->finish();

		// Close CSV.
		fclose( $csv_file_pointer );

		$logger->info( '🎉 Done' );

		wp_cache_flush();
	}

    /**
	 * Migrate Categories and Tags to Posts from a CSV file.
	 * 
	 * @uses wp newspack-content-migrator mj-migrate-posts-terms --source-filepath="{FILEPATH}"
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_migrate_posts_terms( array $pos_args, array $assoc_args ): void {
		$source_filepath = $assoc_args['source-filepath'];

		// Prelimiary checks.
		if ( ! $source_filepath || ! file_exists( $source_filepath ) ) {
			WP_CLI::error( sprintf( 'Incorrect source filepath: %s', $source_filepath ) );
			return;
		}

        $log_slug = 'migrate-posts-terms';
        $logger   = MultiLog::get_logger( 
			'multi-' . $log_slug,
			[
				CliLog::get_logger( $log_slug ),
				FileLog::get_logger( $log_slug ),
			]
		);

		// CSV.
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$csv = sprintf( 'migrate-posts-terms-%s.csv', date( 'Y-m-d H-i-s' ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$csv_file_pointer = fopen( $csv, 'w' );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
		fputcsv(
			$csv_file_pointer,
			[
				'#',
				'Post ID',
				'Post Title',
				'Post URL',
			]
		);

		$total_categories_and_tags = $this->csv_iterator->count_csv_file_entries( $source_filepath, ',' );

		$progress_bar = WP_CLI\Utils\make_progress_bar( '[Mountain Journal] Migrating Posts Terms', $total_categories_and_tags );

		foreach ( $this->csv_iterator->items( $source_filepath, ',' ) as $index => $csv_row ) {
			$progress_bar->tick(
				1,
				sprintf(
					'[Memory: %s] [Mountain Journal] Migrating Posts Terms %d/%d',
					size_format( memory_get_usage( true ) ),
					$index + 1,
					$total_categories_and_tags
				)
			);

			$logger->info( sprintf( '👉 Processing Entry %s', wp_json_encode( $csv_row ) ) );

            if ( empty( $csv_row['Categories'] ) && empty( $csv_row['Tags'] ) ) {
                $logger->info( '👉 Skip Processing. Categories and Tags empty' );

                continue;
            }

			$posts = get_posts(
                [
                    'post_type'      => 'post',
                    'posts_per_page' => 1,
                    'title'          => $csv_row['Title'],
                    'post_status'    => 'any'
                ]
            );

            if ( empty ( $posts ) ) {
                $logger->error( sprintf( '🚫 Could not find Post with Title %s', $csv_row['Title'] ) );

                continue;
            }

            $post_id = $posts[0]->ID;

            if ( ! empty( $csv_row['Categories'] ) ) {
                $categories   = explode( ' || ', $csv_row['Categories'] );
                $categories   = array_map( fn ( $cat_name ) => get_term_by( 'name', $cat_name, 'category' ), $categories );

                foreach ( $categories as $category ) {
                    if ( ! $category ) {
                        var_dump( $csv_row['Categories'] );
                        // exit;
                    }
                }

                $category_ids = array_map( fn ( $cat_term ) => $cat_term->term_id, $categories );

                wp_set_post_terms( $post_id, $category_ids, 'category', false );

                $logger->info( sprintf( '✅ Set Post Categories: %s', wp_json_encode( $category_ids ) ) );
            }

            if ( ! empty( $csv_row['Tags'] ) ) {
                $tags     = explode( ' || ', $csv_row['Tags'] );
                $tags     = array_map( fn ( $tag_name ) => get_term_by( 'name', $tag_name, 'post_tag' ), $tags );

                foreach ( $tags as $tag ) {
                    if ( ! $tag ) {
                        var_dump( $csv_row['Tags'] );
                        exit;
                    }
                }

                $tags_ids = array_map( fn ( $cat_term ) => $cat_term->term_id, $tags );

                wp_set_post_terms( $post_id, $tags_ids, 'post_tag', true );

                $logger->info( sprintf( '✅ Set Post Tags: %s', wp_json_encode( $tags_ids ) ) );
            }

			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
			fputcsv(
				$csv_file_pointer,
				[
					$index + 1,
					$post_id,
					get_the_title( $post_id ),
					get_permalink( $post_id ),
				]
			);
		}

		$progress_bar->finish();

		// Close CSV.
		fclose( $csv_file_pointer );

		$logger->info( '🎉 Done' );

		wp_cache_flush();
	}

    /**
     * Scrape article categories.
     *
     * @param array $args Command arguments.
     * @param array $assoc_args Command associative arguments.
     */
    public function cmd_scrape_article_categories( $args, $assoc_args ) {
        $current_page = 1;
        $total_pages  = null;

        $csv_file = fopen( 'export-posts-terms.csv', 'w' );
        fputcsv( $csv_file, [ 'Page', 'Title', 'Categories', 'Tags' ] );

        while ( $current_page !== $total_pages ) {
            $url      = sprintf( '%s/stories@p=%d', self::LIVE_SITE_URL, $current_page );
            $response = wp_remote_get( $url );
            $body     = wp_remote_retrieve_body( $response );
                
            $dom = new HtmlDocument();
            $dom->load( $body );

            if ( is_null( $total_pages ) ) {
                $pagination  = $dom->find( '.articles-paging', 0 );
                $total_pages = (int) array_pop( $pagination->find( '.dot' ) )->plaintext;
            }

            $posts = $dom->find( '.articles-list-item' );

            if ( empty( $posts ) ) {
                break;
            }

            foreach ( $posts as $post ) {
                $url = $post->find( 'h3 > a', 0 )->getAttribute( 'href' );
                $title = $post->find( 'h3 > a', 0 )->plaintext;

                // Fetch Single Article Data
                $article_url  = sprintf( '%s%s', self::LIVE_SITE_URL, $url );
                $article_html = wp_remote_get( $article_url );
                $article_body = wp_remote_retrieve_body( $article_html );

                $article_dom = new HtmlDocument();
                $article_dom->load( $article_body );

                $categories = $article_dom->find( '.article-category .body-c-alt' );
                $categories = ! empty( $categories ) ? array_map( fn ( $category ) => $category->plaintext, $categories ) : [];
                $tags = $article_dom->find( '.article-tags a' );
                $tags = ! empty( $tags ) ? array_map( fn ( $tag ) => $tag->plaintext, $tags ) : [];
                
                $article = [
                    'url'        => $article_url,
                    'title'      => $title,
                    'categories' => implode( ' || ', $categories ),
                    'tags'       => implode( ' || ', $tags ),
                ];

                fputcsv( $csv_file, $article );

                WP_CLI::log( "Processing: {$title}" );
            }

            $current_page++;
        }

        fclose( $csv_file );

        WP_CLI::success( 'Articles and categories have been scraped and saved to articles.csv' );
    }

    /**
     * Get Thumbnail ID for Post.
     * 
     * @param array $csv_row
     * @return int|null
     */
    private function get_post_thumbnail_id( array $csv_row ): ?int {
        $thumbnail_url   = null;
        $thumbnail_title = '';

        if ( ! empty( $csv_row['Home Hero Image'] ) && $csv_row['Home Hero Image'] !== self::PLACEHOLDER_POST_URL ) {
            $thumbnail_url   = $csv_row['Home Hero Image'];
            $thumbnail_title = $csv_row['Home Hero Image: Title'];
        } else if ( ! empty( $csv_row['Hero Image: Hero'] ) && ( $csv_row['Hero Image: Hero'] !== self::PLACEHOLDER_POST_URL ) ) {
            $thumbnail_url   = $csv_row['Hero Image: Hero'];
            $thumbnail_title = $csv_row['Hero Image: Title'];
        } else if ( ! empty( $csv_row['List Image'] ) && ( $csv_row['List Image'] !== self::PLACEHOLDER_POST_URL ) ) {
            $thumbnail_url   = $csv_row['List Image'];
            $thumbnail_title = $csv_row['List Image: Title'];
        }

        if ( empty( $thumbnail_url ) ) {
            return null;
        }

        preg_match( '~^' . self::PLACEHOLDER_POST_URL . 'ic\_(\d+)\_(.+)$~', $thumbnail_url, $matches );

        $avatar_date = date( 'Y-m-01 00:00:00', $matches[1] );
        $filename    = $matches[2];

        $thumbnail_id = $this
            ->attachments
            ->import_external_file(
                $thumbnail_url,
                $thumbnail_title,
                $thumbnail_title,
                $thumbnail_title,
                null,
                0,
                [
                    'post_date'     => $avatar_date,
                    'post_date_gmt' => '',
                ],
                $filename,
            );

        if ( is_wp_error( $thumbnail_id ) ) {
            return null;
        } else {
            return $thumbnail_id;
        }
    }

    /**
     * Get Open Graph Thumbnail ID for Post.
     * 
     * @param array $csv_row
     * @return int|null
     */
    private function get_post_open_graph_thumbnail_id( array $csv_row ): ?int {
        $thumbnail_url = null;

        if ( ! empty( $csv_row['Facebook Image'] ) && $csv_row['Facebook Image'] !== self::PLACEHOLDER_POST_URL ) {
            $thumbnail_url = $csv_row['Facebook Image'];
        }

        if ( empty( $thumbnail_url ) ) {
            return null;
        }

        preg_match( '~^' . self::PLACEHOLDER_POST_URL . 'ic\_(\d+)\_(.+)$~', $thumbnail_url, $matches );

        $avatar_date = date( 'Y-m-01 00:00:00', $matches[1] );
        $filename    = $matches[2];

        $thumbnail_id = $this
            ->attachments
            ->import_external_file(
                $thumbnail_url,
                null,
                null,
                null,
                null,
                0,
                [
                    'post_date'     => $avatar_date,
                    'post_date_gmt' => '',
                ],
                $filename,
            );

        return is_wp_error( $thumbnail_id ) ? null : $thumbnail_id;
    }

    /**
     * Generate Post Content from Post Sections.
     * 
     * @param  array $sections
     * @param  bool  $raw
     * @return string
     */
    private function generate_post_content( array $sections, bool $raw = false ): string {
        $post_content = [];

        foreach ( $sections as $section_name => $section_content ) {
            if ( empty( $section_content ) ) {
                continue;
            }

            if ( preg_match( '~^Section\s\d\sHeader$~', $section_name ) ) {
                $post_content[] = $raw
                    ? serialize_block( $this->gutenberg_block_generator->get_heading( $section_content, 'h3' ) )
                    : $this->gutenberg_block_generator->get_heading( $section_content, 'h3' );
            } else if ( preg_match( '~^Section\s\d\sBody$~', $section_name ) ) {
                if ( $raw ) {
                    $post_content = [
                        ...$post_content,
                        $section_content
                    ];
                } else {
                    $post_content = [
                        ...$post_content,
                        ...$this->content_to_blocks( $section_content )
                    ];
                }
            } else if ( str_contains( $section_name, 'Video Url' ) ) {
                $post_content[] = $raw
                    ? serialize_block( $this->gutenberg_block_generator->get_core_embed( $section_content ) )
                    : $this->gutenberg_block_generator->get_core_embed( $section_content );
            }
        }

        if ( $raw ) {
            return implode( "\n", $post_content );
        } else {
            return implode( "\n\n", array_map( 'serialize_block', $post_content ) );
        }
    }

    private function content_to_blocks( string $content ): mixed {
        // Replace extra newlines.
        $content = str_replace( [ "\r\n", "\n" ], ' ', $content );
        $content = str_replace( '&quot;', '\"', $content );
        
        // Replace empty style attributes.
        $content = str_replace( "style=\"\"", '', $content );
        
        $html_doc = new HtmlDocument( $content );
        
        // Remove empty tags.
        foreach ( $html_doc->find( '*' ) as $tag ) {
            if ( empty( trim( $tag->innerText() ) ) ) {
                $tag->remove();
            }
        }

        // Remove <meta tags.
        $metas = $html_doc->find( 'meta' );
        foreach ( $metas as $meta ) {
            $meta->remove();
        }
        
        // Remove <br> child nodes.
        $brs = $html_doc->find( 'br' );
        foreach ( $brs as $br ) {
            $br->remove();
        }

        foreach ( $html_doc->find( '*' ) as $element ) {
            if ( empty( $element->plaintext ) && ! in_array( $element->tag, [ 'comment', 'img' ] ) && ! str_contains( $element->innerText(), '<!--{"type' ) ) {
                $element->remove();
            }
        }

        // Remove direct <div> elements.
        foreach ( $html_doc->childNodes() as $childNode ) {
            if ( $childNode->tag === 'div' ) {
                $html_doc->load(
                    str_replace(
                        $childNode->outerText(),
                        $childNode->innerText(),
                        $html_doc->save()
                    )
                );
            }
        }
        
        // Replace extra spans.
        $spans = $html_doc->find( 'span' );
        foreach ( $spans as $span ) {
            // Remove the span if docs reference is found
            if ( str_starts_with( $span->getAttribute( 'id' ), 'docs-internal-guid' ) ) {
                $html_doc->load(
                    str_replace(
                        $span->outerText(),
                        $span->innerText(),
                        $html_doc->save()
                    )
                );
                
                continue;
            }
        }
            
        // Get Inline images stores as comments.
        $comments = $html_doc->find( 'comment' );
        foreach ( $comments as $comment ) {
            if ( ! str_contains( $comment->outerText(), '{"type":"image"' ) ) {
                continue;
            }

            $img_json = json_decode( $comment->innerText(), true );

            $img_block = $this->convert_inline_comment_img_to_attachment( $img_json );

            if ( ! empty( $img_block ) ) {
                $html_doc->load(
                    str_replace(
                        $comment->outerText(),
                        serialize_block( $img_block ),
                        $html_doc->save()
                    )
                );
            }
        }

        // Find blockquotes.
        $blockquotes = $html_doc->find( 'blockquote' );
        foreach ( $blockquotes as $blockquote ) {
            // $blockquote->find( 'em', 0 ) doesn't work each time...
            $cites         = array_filter( $blockquote->children(), fn ( $childNode ) => $childNode->tag === 'em' );
            $cite          = array_shift( $cites );
            $quote_content = $blockquote->innerText();

            if ( ! empty( $cite ) ) {
                $quote_content = str_replace(
                    [ '–' . $cite->outerText(), $cite->outerText() ],
                    '',
                    $quote_content
                );
            }

            $blockquote_block = $this
                ->gutenberg_block_generator
                ->get_quote( trim( $quote_content ), trim( $cite?->innerText() ?? '' ) );

            if ( $blockquote->parent()->tag !== 'root' ) {
                // Unwrap blockquote.
                $html_doc->load( str_replace(
                    $blockquote->parent()->outerText(),
                    serialize_block( $blockquote_block ),
                    $html_doc->save()
                ) );
            } else {
                $html_doc->load( str_replace(
                    $blockquote->outerText(),
                    serialize_block( $blockquote_block ),
                    $html_doc->save()
                ) );
            }
        }

        // Find divs that need to be transformed to paragraphs.
        $divs = $html_doc->find( 'div' );
        foreach ( $divs as $div ) {
            // If blocks are inside, unwrap the div.
            $div_blocks       = parse_blocks( $div->innerText() );
            $undefined_blocks = array_filter( $div_blocks, fn ( $block ) => is_null( $block['blockName'] ) );

            if ( empty( $undefined_blocks ) ) {
                $html_doc->load( str_replace( $div->outerText(), $div->innerText(), $html_doc->save() ) );

                continue;
            }

            // If the child nodes are <strong>, <b>, <em>, <a>, <figure>, <!-- --> then we can replace <div> with <p>.
            $child_nodes_tags = array_unique( array_map( fn( $child_node ) => $child_node->tag, $div->childNodes() ) );
            $extra_nodes      = array_diff( $child_nodes_tags, [ 'strong', 'b', 'em', 'a', 'figure', 'comment' ] );

            if ( empty( $extra_nodes ) ) {
                $style_atts = array_filter( explode( ';', $div->getAttribute( 'style' ) ) );
                $classes    = array_filter( [
                    $div->getAttribute( 'class' ),
                    in_array( 'text-align: center', $style_atts ) ? 'has-text-align-center' : '',
                ] );

                // Replace Separator.
                if ( preg_match( '~^\_{2,}~', $div->innerText() ) ) {
                    $html_doc->load( str_replace(
                        $div->outerText(),
                        serialize_block( $this->gutenberg_block_generator->get_separator() ),
                        $html_doc->save()
                    ) );

                    continue;
                }

                $paragraph_block = $this
                    ->gutenberg_block_generator
                    ->get_paragraph(
                        $div->innerText(),
                        $div->getAttribute('id') ?? '',
                        '',
                        '',
                        $classes
                    );

                if ( in_array( 'text-align: center', $style_atts ) ) {
                    $paragraph_block['attrs']['align'] = 'center';
                }

                $html_doc->load( str_replace(
                    $div->outerText(),
                    serialize_block( $paragraph_block ),
                    $html_doc->save()
                ) );

                continue;
            }

            // Unwrap the div if it has many children.
            if ( count( $div->children() ) > 1 ) {
                $html_doc->load(
                    str_replace(
                        $div->outerText(),
                        $div->innerText(),
                        $html_doc->save()
                    )
                );
            // If the child is single and it is div, unwrap.
            } else if ( ( count( $div->children() ) === 1 ) && $div->children()[0]?->tag === 'div' ) {
                $html_doc->load(
                    str_replace(
                        $div->outerText(),
                        $div->innerText(),
                        $html_doc->save()
                    )
                );
            }
        }

        // Remove empty tags.
        $html_doc->load( str_replace(
            [ '<em></em>', '<em> </em>', '<em> </em>' ],
            '',
            $html_doc->save()
        ) );

        // Move <p> tags to paragraph blocks.
        $paragraphs = $html_doc->find( 'p' );
        foreach ( $paragraphs as $paragraph ) {
            if ( empty( $paragraph->plaintext ) ) {
                $paragraph->remove();
            }

            // Check if the paragraph is part of a paragraph block.
            $paragraph_block_text = sprintf( '<!-- wp:paragraph -->%s<!-- /wp:paragraph -->', $paragraph->outerText() );

            if ( str_contains( $html_doc->save(), $paragraph_block_text ) ) {
                continue;
            }

            $html_doc->load( str_replace(
                $paragraph->outerText(),
                $paragraph_block_text,
                $html_doc->save()
            ) );
        }

        $blocks = parse_blocks( $html_doc->save() );

        foreach ( $blocks as $index => $block ) {
            if ( is_null( $block['blockName'] ) ) {
                $block[ $index ] = $this
                    ->gutenberg_block_generator
                    ->get_paragraph( $block['innerHTML'] );
            }
        }

        // if ( array_filter( $blocks, fn ( $block ) => is_null( $block['blockName'] ) ) ) {
        //     forea
        //     var_dump( $html_doc->save(), implode( "\n\n", array_map( 'serialize_block', $blocks ) ) );
        //     exit;
        // }

        return $blocks;
    }

    /**
     * Convert inline comment img to inline attachment.
     */
    private function convert_inline_comment_img_to_attachment( array $img_data ): mixed {
        global $wpdb;

        $file_logger = FileLog::get_logger( 'migrate-inline-images' );

        $attachment_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT `post_id`
                 FROM `{$wpdb->postmeta}`
                 WHERE `meta_key` = 'newspack_attachment_source_id'
                 AND `meta_value` = %s",
                $img_data['ref']
            )
        );

        if ( empty( $attachment_id ) ) {
            $filepath = pathinfo( array_values( $img_data['tails'] )[0] );
            $filename = sprintf( '%s.%s', $img_data['ref'], $filepath['extension'] );

            $attachment_id = $this
                ->attachments
                ->import_external_file(
                    sprintf(
                        'https://mountainjournal.org/content/articles/ic_%s%s',
                        $img_data['ref'],
                        $img_data['tails']['full'] ?? $img_data['tails']['large'] ?? $img_data['tails'][ $img_data['imgType'] ]
                    ),
                    $img_data['title'] ?? null,
                    $img_data['title'] ?? null,
                    $img_data['title'] ?? null,
                    null,
                    0,
                    [
                        'post_date'     => date( 'Y-m-01 00:00:00', $img_data['ref'] ),
                        'post_date_gmt' => '',
                    ],
                    $filename,
                );

            if ( is_wp_error( $attachment_id ) ) {
                $file_logger->error( sprintf( '🚫 Could not insert attachment. Error: %s', wp_json_encode( $img_data ) ) );

                return null;
            } else {
                $file_logger->info( sprintf( '✅ Successfully inserted attachment: %s', wp_json_encode( $img_data ) ) );
            }

            update_post_meta( $attachment_id, 'newspack_attachment_source_id', $img_data['ref'] );
        }

        return $this
            ->gutenberg_block_generator
            ->get_image(
                get_post( $attachment_id ),
                $img_data['imgType'] === 'full' ? 'full' : 'large',
                false,
                null,
                $img_data['imgType'] ?? 'full'
            );
    }
}
