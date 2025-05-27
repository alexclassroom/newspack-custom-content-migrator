<?php

namespace NewspackCustomContentMigrator\Command\General;

use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\CoAuthorsPlusHelper;
use Newspack\MigrationTools\Util\Log\Logger;
use WP_CLI;

class EmbarcaderoPostLaunchMigrator implements RegisterCommandInterface {
	use WpCliCommandTrait;

	const LOG_FILE                                   = 'post_launch_migrator.log';
	const EMBARCADERO_IMPORTED_BLOG_COMMENT_META_KEY = '_newspack_imported_blog_comment_id';

	/**
	 * @var DateTimeZone Embarcadero sites timezone.
	 */
	private $site_timezone;

	/**
	 * Logger instance.
	 *
	 * @var Logger.
	 */
	private $logger;

	/**
	 * CoAuthors Plus instance.
	 *
	 * @var CoAuthorsPlusHelper CoAuthors Plus instance.
	 */
	private $coauthors_plus;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->logger         = new Logger();
		$this->coauthors_plus = new CoAuthorsPlusHelper();
		$this->site_timezone  = new \DateTimeZone( 'America/Los_Angeles' );
	}

	/**
	 * Register commands.
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator embarcadero-check-posts-without-primary-category',
			self::get_command_closure( 'cmd_embarcadero_check_posts_without_primary_category' ),
			[
				'shortdesc' => 'Check posts without primary category.',
				'synopsis'  => [],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator embarcadero-delete-issue-date-categories',
			self::get_command_closure( 'cmd_embarcadero_delete_issue_date_categories' ),
			[
				'shortdesc' => 'Delete issue date categories.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'print-editions-category-id',
						'description' => 'The ID of the print editions category',
						'optional'    => false,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator embarcadero-fix-blog-comments-display-names',
			self::get_command_closure( 'cmd_embarcadero_fix_blog_comments_display_names' ),
			[
				'shortdesc' => 'Fix blog comments display names.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'blog-comments-csv',
						'description' => 'Path to the comments.csv file',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'network-display-names-csv',
						'description' => 'Path to the network_display_names.csv file',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'old-users-table',
						'description' => 'The name of the old users table',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'blog-site',
						'description' => 'The target site domain (e.g. paloaltoonline.com)',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'index-from',
						'description' => 'The index to start from',
						'optional'    => true,
						'default'     => 0,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator embarcadero-fix-blog-comments',
			self::get_command_closure( 'cmd_embarcadero_fix_blog_comments' ),
			[
				'shortdesc' => 'Fix blog comments.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'blog-topics-csv',
						'description' => 'Path to the blog_topics.csv file',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'blog-comments-csv',
						'description' => 'Path to the comments.csv file',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'blog-blogs-csv',
						'description' => 'Path to the blog_blogs.csv file',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'danville-registered-users-csv',
						'description' => 'Path to the danville_san_ramon_registrated_users.csv file',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'moutanin-view-registered-users-csv',
						'description' => 'Path to the mountain_view_voice_registrated_users.csv file',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'palo-alto-registered-users-csv',
						'description' => 'Path to the palo_alto_registrated_users.csv file',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'pleasanton-registered-users-csv',
						'description' => 'Path to the pleasanton_registrated_users.csv file',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'almanac-registered-users-csv',
						'description' => 'Path to the the_almanac_registrated_users.csv file',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'blog-site',
						'description' => 'The target site domain (e.g. paloaltoonline.com)',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'index-from',
						'description' => 'The index to start from',
						'optional'    => true,
						'default'     => 0,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator embarcadero-fix-migrated-blogs',
			self::get_command_closure( 'cmd_embarcadero_fix_migrated_blogs' ),
			[
				'shortdesc' => 'Fix migrated blogs.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'blog-topics-csv',
						'description' => 'Path to the blog_topics.csv file',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'blog-blogs-csv',
						'description' => 'Path to the blog_blogs.csv file',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'blog-site',
						'description' => 'The target site domain (e.g. paloaltoonline.com)',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'blog-category-id',
						'description' => 'The category ID of the blog',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'index-from',
						'description' => 'The index to start from',
						'optional'    => true,
						'default'     => 0,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator embarcadero-clean-duplicated-blog-posts',
			self::get_command_closure( 'cmd_embarcadero_clean_duplicated_blog_posts' ),
			[
				'shortdesc' => 'Clean duplicated blog posts.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'blog-topics-csv',
						'description' => 'Path to the blog_topics.csv file',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'blog-blogs-csv',
						'description' => 'Path to the blog_blogs.csv file',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'blog-site',
						'description' => 'The target site domain (e.g. paloaltoonline.com)',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'index-from',
						'description' => 'The index to start from',
						'optional'    => true,
						'default'     => 0,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator embarcadero-unpublish-1969-posts',
			self::get_command_closure( 'cmd_embarcadero_unpublish_1969_posts' ),
			[
				'shortdesc' => 'Unpublish 1969 posts.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'blog-site',
						'description' => 'The target site domain (e.g. paloaltoonline.com)',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'story-csv-file-path',
						'description' => 'Path to the CSV file containing the stories to import.',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'story-print-issues-csv-file-path',
						'description' => 'Path to the CSV file containing the stories\'s print issues to import.',
						'optional'    => false,
						'repeating'   => false,
					],
				],
			]
		);
	}

	/**
	 * Check posts without primary category.
	 * Callable for "newspack-content-migrator embarcadero-check-posts-without-primary-category".
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_embarcadero_check_posts_without_primary_category( $args, $assoc_args ) {
		global $wpdb;
		$log_file        = 'embarcadero_check_posts_without_primary_category.csv';
		$log_file_handle = fopen( $log_file, 'w' );
		fputcsv( $log_file_handle, [ 'post_id', 'post_title', 'post_date', 'categories', 'WP User', 'authors', 'has_one_category', 'is_blog_post', 'has_primary_category', 'primary_category_id' ] );

		// Select all the published posts without the primary category meta.
		$posts_with_primary_category_ids = $wpdb->get_col(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_yoast_wpseo_primary_category'"
		);

		$all_published_posts = $wpdb->get_results(
			"SELECT ID, post_title, post_date, post_author FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' ORDER BY post_date DESC"
		);

		foreach ( $all_published_posts as $post ) {
			$post_categories       = wp_get_post_categories( $post->ID );
			$post_categories_names = array_map(
				function ( $category ) {
					return get_category( $category )->name;
				},
				$post_categories ?? []
			);

			$post_authors = array_map(
				function ( $author ) {
					return $author->user_nicename;
				},
				$this->coauthors_plus->get_all_authors_for_post( $post->ID )
			);

			$has_primary_category = in_array( $post->ID, $posts_with_primary_category_ids );
			$category_names       = implode( '#', $post_categories_names );
			$is_blog              = str_contains( strtolower( $category_names ), 'blog' );
			$primary_category_id  = get_post_meta( $post->ID, '_yoast_wpseo_primary_category', true );
			fputcsv(
				$log_file_handle,
				[
					$post->ID,
					$post->post_title,
					$post->post_date,
					$category_names,
					$post->post_author,
					implode( '#', $post_authors ),
					1 === count( $post_categories_names ) ? 'Yes' : 'No',
					$is_blog ? 'Yes' : 'No',
					$has_primary_category ? 'Yes' : 'No',
					$primary_category_id,
				]
			);
		}

		fclose( $log_file_handle );
	}

	/**
	 * Delete issue date categories.
	 * Callable for "newspack-content-migrator embarcadero-delete-issue-date-categories".
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_embarcadero_delete_issue_date_categories( $args, $assoc_args ) {
		global $wpdb;
		$log_file        = 'embarcadero_delete_issue_date_categories.csv';
		$log_file_handle = fopen( $log_file, 'a' );
		fputcsv( $log_file_handle, [ 'year_category_id', 'year_category_name', 'date_category_id', 'date_category_name' ] );

		$print_editions_category_id = $assoc_args['print-editions-category-id'];

		$year_categories = get_categories( [ 'parent' => $print_editions_category_id ] );

		WP_CLI::line( 'Year categories: ' . count( $year_categories ) );
		foreach ( $year_categories as $year_category ) {
			// $date_categories = get_categories( [ 'parent' => $year_category->term_id ] );
			$date_categories = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT  t.term_id, t.name
			 FROM wp_terms AS t  INNER JOIN wp_term_taxonomy AS tt ON t.term_id = tt.term_id
			 WHERE tt.taxonomy IN ('category') AND tt.parent = %d
			 ORDER BY t.name ASC",
					$year_category->term_id
				)
			);

			foreach ( $date_categories as $date_category ) {
				// Make sure the date category is in the format Apr 7.
				if ( ! preg_match( '/^[A-Z][a-z]{2} \d{1,2}$/', $date_category->name ) ) {
					WP_CLI::warning( 'Probably not a date category: ' . $date_category->term_id . ' - ' . $date_category->name );
					continue;
				}

				WP_CLI::line( 'Deleting date category: ' . $date_category->term_id . ' - ' . $date_category->name );
				wp_delete_category( $date_category->term_id );

				fputcsv(
					$log_file_handle,
					[
						$year_category->term_id,
						$year_category->name,
						$date_category->term_id,
						$date_category->name,
					]
				);
			}
		}
	}
	/**
	 * Fix blog comments.
	 * Callable for "newspack-content-migrator embarcadero-fix-blog-comments-display-names".
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_embarcadero_fix_blog_comments_display_names( $args, $assoc_args ) {
		global $wpdb;

		$blog_comments_csv         = $assoc_args['blog-comments-csv'];
		$network_display_names_csv = $assoc_args['network-display-names-csv'];
		$old_users_table           = $assoc_args['old-users-table'];
		$blog_site                 = $assoc_args['blog-site'];
		$index_from                = $assoc_args['index-from'];

		$blog_comments         = $this->get_data_from_csv_or_tsv( $blog_comments_csv );
		$network_display_names = $this->get_data_from_csv_or_tsv( $network_display_names_csv );

		$log_file     = $blog_site . '_fix_blog_comments_display_names.log';
		$csv_log_file = $blog_site . '_fix_blog_comments_display_names.csv';

		$csv_log_file_handle = fopen( $csv_log_file, 'w' );
		fputcsv( $csv_log_file_handle, [ 'comment_id', 'user_id', 'current_display_name', 'network_display_name', 'old_display_name', 'same_old_name', 'same_network_name', 'same_network_as_old_name' ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$already_migrated_comments = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT meta_key, meta_value, wp_commentmeta.comment_id FROM $wpdb->commentmeta INNER JOIN $wpdb->comments ON $wpdb->commentmeta.comment_id = $wpdb->comments.comment_ID WHERE meta_key = %s AND comment_approved != 'trash'", self::EMBARCADERO_IMPORTED_BLOG_COMMENT_META_KEY ), ARRAY_A );

		$checked_user_ids = [];

		foreach ( $already_migrated_comments as $index => $already_migrated_comment ) {
			if ( $index < $index_from ) {
				continue;
			}

			// if ( 764081 != $already_migrated_comment['comment_id'] ) {
			// continue;
			// }

			$already_migrated_comment_index = array_search( $already_migrated_comment['meta_value'], array_column( $blog_comments, 'blog_comment_id' ) );

			if ( false === $already_migrated_comment_index ) {
				$this->logger->log( $log_file, 'Comment not found in already migrated comments: ' . $already_migrated_comment['comment_id'], Logger::WARNING );
				continue;
			}

			$migrated_comment = get_comment( $already_migrated_comment['comment_id'] );

			if ( '0' === $migrated_comment->user_id ) {
				continue;
			}

			if ( in_array( $migrated_comment->user_id, $checked_user_ids ) ) {
				continue;
			}

			$checked_user_ids[] = $migrated_comment->user_id;

			$comment_user = get_user_by( 'id', $migrated_comment->user_id );

			$current_display_name = $comment_user->display_name;

			// Network display names.
			$network_name_index = array_search( $comment_user->user_email, array_column( $network_display_names, 'email' ) );

			if ( false === $network_name_index ) {
				$this->logger->log( $log_file, 'Network display name not found for comment: ' . $migrated_comment->comment_ID, Logger::WARNING );
				// continue;
			}

			$network_display_name = false === $network_name_index
				? ''
				: $network_display_names[ $network_name_index ][ $blog_site ];

			// Display name from the old users table.
			$old_display_name = $wpdb->get_col( $wpdb->prepare( "SELECT display_name FROM $old_users_table WHERE ID = %d", $migrated_comment->user_id ) );

			if ( empty( $old_display_name ) ) {
				$this->logger->log( $log_file, 'Old display name not found for comment: ' . $migrated_comment->comment_ID, Logger::WARNING );
				continue;
			}

			$old_display_name = $old_display_name[0];

			if ( $old_display_name !== $current_display_name ) {
				$this->logger->log( $log_file, 'Updating display name for user ID: ' . $migrated_comment->user_id . ' from "' . $current_display_name . '" to "' . $old_display_name . '"' );

				$wpdb->update(
					$wpdb->users,
					[ 'display_name' => $old_display_name ],
					[ 'ID' => $migrated_comment->user_id ]
				);

				fputcsv(
					$csv_log_file_handle,
					[
						$migrated_comment->comment_ID,
						$migrated_comment->user_id,
						$current_display_name,
						$network_display_name,
						$old_display_name,
						$current_display_name === $old_display_name ? 'Yes' : 'No',
						$current_display_name === $network_display_name ? 'Yes' : 'No',
						$network_display_name === $old_display_name ? 'Yes' : 'No',
					]
				);
			}


			$this->logger->log( $log_file, 'Comment with index ' . $index . ' / ' . count( $already_migrated_comments ) . ' checked.' );
		}

		fclose( $csv_log_file_handle );
	}

	/**
	 * Fix blog comments.
	 * Callable for "newspack-content-migrator embarcadero-fix-blog-comments".
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_embarcadero_fix_blog_comments( $args, $assoc_args ) {
		global $wpdb;

		$blog_topics_csv         = $assoc_args['blog-topics-csv'];
		$blog_comments_csv       = $assoc_args['blog-comments-csv'];
		$blog_blogs_csv          = $assoc_args['blog-blogs-csv'];
		$danville_users_csv      = $assoc_args['danville-registered-users-csv'];
		$moutanin_view_users_csv = $assoc_args['moutanin-view-registered-users-csv'];
		$palo_alto_users_csv     = $assoc_args['palo-alto-registered-users-csv'];
		$pleasanton_users_csv    = $assoc_args['pleasanton-registered-users-csv'];
		$almanac_users_csv       = $assoc_args['almanac-registered-users-csv'];
		$blog_site               = $assoc_args['blog-site'];
		$index_from              = $assoc_args['index-from'];

		$blog_topics         = $this->get_data_from_csv_or_tsv( $blog_topics_csv );
		$blog_comments       = $this->get_data_from_csv_or_tsv( $blog_comments_csv );
		$blog_blogs          = $this->get_data_from_csv_or_tsv( $blog_blogs_csv );
		$danville_users      = $this->get_data_from_csv_or_tsv( $danville_users_csv );
		$moutanin_view_users = $this->get_data_from_csv_or_tsv( $moutanin_view_users_csv );
		$palo_alto_users     = $this->get_data_from_csv_or_tsv( $palo_alto_users_csv );
		$pleasanton_users    = $this->get_data_from_csv_or_tsv( $pleasanton_users_csv );
		$almanac_users       = $this->get_data_from_csv_or_tsv( $almanac_users_csv );

		$log_file = $blog_site . '_fix_blog_comments.log';

		// Index blog sites by domain.
		$blogs = [];
		foreach ( $blog_blogs as $blog ) {
			$blogs[ $blog['primary_site'] ][] = $blog['blog_id'];
		}

		$target_blog_ids = $blogs[ $blog_site ];

		// Get only blog topics of the current site.
		$topics = array_values(
			array_filter(
				$blog_topics,
				function ( $topic ) use ( $target_blog_ids ) {
					// Migrate only posts for this site.
					return in_array( $topic['blog_id'], $target_blog_ids );
				}
			)
		);

		$blog_users = [
			'danvillesanramon.com' => $danville_users,
			'mv-voice.com'         => $moutanin_view_users,
			'paloaltoonline.com'   => $palo_alto_users,
			'pleasantonweekly.com' => $pleasanton_users,
			'almanacnews.com'      => $almanac_users,
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$already_migrated_topics = $wpdb->get_results( "SELECT DISTINCT meta_value, post_id FROM $wpdb->postmeta WHERE meta_key IN ('topic_id', '_newspack_migrated_topic_id')", ARRAY_A );

		foreach ( $already_migrated_topics as $already_migrated_topic ) {
			if ( ! in_array( $already_migrated_topic['meta_value'], array_column( $topics, 'topic_id' ) ) ) {
				$this->logger->log( $log_file, 'Topic not found in topics: ' . $already_migrated_topic['meta_value'], Logger::WARNING );
			}
		}

		foreach ( $topics as $index => $blog_topic ) {
			if ( $index < $index_from ) {
				continue;
			}

			$already_migrated_topic_index = array_search( $blog_topic['topic_id'], array_column( $already_migrated_topics, 'meta_value' ) );

			if ( false === $already_migrated_topic_index ) {
				$this->logger->log( $log_file, 'Topic not found in already migrated topics: ' . $blog_topic['topic_id'], Logger::WARNING );
				continue;
			}

			$migrated_post = get_post( $already_migrated_topics[ $already_migrated_topic_index ]['post_id'] );

			// WP Post's Comments.
			$migrated_post_comments = $wpdb->get_results( "SELECT * FROM $wpdb->comments WHERE comment_post_ID = " . $migrated_post->ID );

			// Topic comments from the CSV.
			$csv_topic_comments = array_values(
				array_filter(
					$blog_comments,
					function ( $comment ) use ( $blog_topic ) {
						return $comment['topic_id'] === $blog_topic['topic_id'] && ! empty( $comment['comment'] );
					}
				)
			);

			$count_topic_comments = count( $csv_topic_comments );

			// Delete all migrated comments for this post.
			foreach ( $migrated_post_comments as $migrated_post_comment ) {
				wp_delete_comment( $migrated_post_comment->comment_ID );
			}

			// Migrate the comments.
			if ( ! empty( $csv_topic_comments ) ) {
				$migrated_comments_count = $this->migrate_blog_comments( $migrated_post->ID, $csv_topic_comments, $blog_users, $blog_site, $log_file );

				if ( $migrated_comments_count !== $count_topic_comments ) {
					echo 1;
				}
			}

			$this->logger->log( $log_file, 'Topic with index ' . $index . ' / ' . count( $topics ) . ' checked.' );
		}
	}

	/**
	 * Fix migrated blogs.
	 * Callable for "newspack-content-migrator embarcadero-fix-migrated-blogs".
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_embarcadero_fix_migrated_blogs( $args, $assoc_args ) {
		global $wpdb;

		$blog_topics_csv  = $assoc_args['blog-topics-csv'];
		$blog_blogs_csv   = $assoc_args['blog-blogs-csv'];
		$blog_site        = $assoc_args['blog-site'];
		$index_from       = $assoc_args['index-from'];
		$blog_category_id = $assoc_args['blog-category-id'];

		$blog_topics = $this->get_data_from_csv_or_tsv( $blog_topics_csv );
		$blog_blogs  = $this->get_data_from_csv_or_tsv( $blog_blogs_csv );

		$log_migrated_topics_csv_file = $blog_site . '_migrated_topics.csv';
		$log_file                     = $blog_site . '_fix_migrated_blogs.log';

		$migrated_topics_csv = fopen( $log_migrated_topics_csv_file, 'a' );
		// Add headers only if the file is empty.
		if ( filesize( $log_migrated_topics_csv_file ) === 0 ) {
			fputcsv( $migrated_topics_csv, [ 'original_topic_id', 'wp_post_id', 'post_title', 'primary_category_id', 'original_posted_date', 'date_from_seo_link', 'wp_date', 'fixed_date' ] );
		}

		// Index blog sites by domain.
		$blogs = [];
		foreach ( $blog_blogs as $blog ) {
			$blogs[ $blog['primary_site'] ][] = $blog['blog_id'];
		}

		$target_blog_ids = $blogs[ $blog_site ];

		// Get only blog topics of the current site.
		$topics = array_values(
			array_filter(
				$blog_topics,
				function ( $topic ) use ( $target_blog_ids ) {
					// Migrate only posts for this site.
					return in_array( $topic['blog_id'], $target_blog_ids );
				}
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$already_migrated_topics = $wpdb->get_results( "SELECT DISTINCT meta_value, post_id FROM $wpdb->postmeta WHERE meta_key IN ('topic_id', '_newspack_migrated_topic_id')", ARRAY_A );

		foreach ( $topics as $index => $blog_topic ) {
			if ( $index < $index_from ) {
				continue;
			}

			$fixed_date = '';

			$already_migrated_topic_index = array_search( $blog_topic['topic_id'], array_column( $already_migrated_topics, 'meta_value' ) );

			if ( false === $already_migrated_topic_index ) {
				$this->logger->log( $log_file, 'Topic not found in already migrated topics: ' . $blog_topic['topic_id'], Logger::WARNING );
				continue;
			}

			$migrated_post = get_post( $already_migrated_topics[ $already_migrated_topic_index ]['post_id'] );

			// The seo_link from the CSV is in the format "2007/02/06/headline".
			// We need to convert it to the format "2007-02-06".
			$seo_link_splitted = explode( '/', $blog_topic['seo_link'] );
			$csv_date          = ( 1 < count( $seo_link_splitted ) )
			? $seo_link_splitted[0] . '-' . $seo_link_splitted[1] . '-' . $seo_link_splitted[2]
			: $blog_topic['posted_date'];

			$post_date = ( new \DateTime( $migrated_post->post_date ) )->format( 'Y-m-d' );
			$post_time = ( new \DateTime( $migrated_post->post_date ) )->format( 'H:i:s' );

			if ( $post_date !== $csv_date ) {
				$this->logger->log( $log_file, 'Topic (WP ID: ' . $migrated_post->ID . ') has a different date than the CSV date: ' . $post_date . ' !== ' . $csv_date, Logger::WARNING );
				$post_date     = $csv_date . ' ' . $post_time;
				$post_date_gmt = ( new \DateTime( $post_date, $this->site_timezone ) )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
				$wpdb->update(
					$wpdb->posts,
					[
						'post_date'     => $post_date,
						'post_date_gmt' => $post_date_gmt,
					],
					[ 'ID' => $migrated_post->ID ]
				);

				$fixed_date = 'Yes';
			}

			// Set the blog name as the post's primary category.
			$topic_blog_index = array_search( $blog_topic['blog_id'], array_column( $blog_blogs, 'blog_id' ) );

			$post_category_ids     = wp_get_post_categories( $migrated_post->ID );
			$primary_category      = null;
			$primary_category_name = '';
			foreach ( $post_category_ids as $post_category_id ) {
				$category = get_category( $post_category_id );

				if ( $category->name === $blog_blogs[ $topic_blog_index ]['blog_name'] ) {
					$primary_category      = $post_category_id;
					$primary_category_name = $category->name;
				}
			}

			// Primary catgory was not set in the initial migration.
			if ( ! $primary_category ) {
				$category_id = ( false !== $topic_blog_index ) ? $this->get_or_create_category( $blog_blogs[ $topic_blog_index ]['blog_name'], $blog_category_id ) : '';

				if ( $category_id ) {
					wp_set_post_categories( $migrated_post->ID, $category_id );
					$primary_category      = $category_id;
					$primary_category_name = $blog_blogs[ $topic_blog_index ]['blog_name'];
					$this->logger->log( $log_file, 'Created category ' . $blog_blogs[ $topic_blog_index ]['blog_name'] . ' (ID: ' . $category_id . ') for the topic ' . $migrated_post->ID );
				}
			}

			if ( $primary_category ) {
				update_post_meta( $migrated_post->ID, '_yoast_wpseo_primary_category', $primary_category );
			}

			fputcsv(
				$migrated_topics_csv,
				[
					$blog_topic['topic_id'],
					$migrated_post->ID,
					$migrated_post->post_title,
					$primary_category_name,
					$blog_topic['posted_date'],
					$csv_date,
					$migrated_post->post_date,
					$fixed_date,
				]
			);

			$this->logger->log( $log_file, 'Topic with index ' . $index . '/' . count( $topics ) . ' checked.' );
		}

		fclose( $migrated_topics_csv );
	}

	/**
	 * Clean duplicated blog posts.
	 * Callable for "newspack-content-migrator embarcadero-clean-duplicated-blog-posts".
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_embarcadero_clean_duplicated_blog_posts( $args, $assoc_args ) {
		global $wpdb;

		$blog_topics_csv = $assoc_args['blog-topics-csv'];
		$blog_blogs_csv  = $assoc_args['blog-blogs-csv'];
		$blog_site       = $assoc_args['blog-site'];
		$index_from      = $assoc_args['index-from'];

		$blog_topics = $this->get_data_from_csv_or_tsv( $blog_topics_csv );
		$blog_blogs  = $this->get_data_from_csv_or_tsv( $blog_blogs_csv );

		$log_file = $blog_site . '_clean_duplicated_blog_posts.log';

		// Index blog sites by domain.
		$blogs = [];
		foreach ( $blog_blogs as $blog ) {
			$blogs[ $blog['primary_site'] ][] = $blog['blog_id'];
		}

		$target_blog_ids = $blogs[ $blog_site ];

		// Get only blog topics of the current site.
		$topics = array_values(
			array_filter(
				$blog_topics,
				function ( $topic ) use ( $target_blog_ids ) {
					// Migrate only posts for this site.
					return in_array( $topic['blog_id'], $target_blog_ids );
				}
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$already_migrated_topics = $wpdb->get_results( "SELECT meta_key, meta_value, post_id FROM $wpdb->postmeta WHERE meta_key IN ('topic_id', '_newspack_migrated_topic_id')", ARRAY_A );

		foreach ( $topics as $topic ) {
			$migrated_post_meta = array_values(
				array_filter(
					$already_migrated_topics,
					function ( $migrated_post_meta ) use ( $topic ) {
						return $migrated_post_meta['meta_value'] === $topic['topic_id'];
					}
				)
			);

			if ( count( $migrated_post_meta ) > 1 ) {
				$this->logger->log( $log_file, 'Topic ' . $topic['topic_id'] . ' has ' . count( $migrated_post_meta ) . ' migrated posts.', Logger::WARNING );
				$new_version_index = array_search( '_newspack_migrated_topic_id', array_column( $migrated_post_meta, 'meta_key' ) );

				if ( false === $new_version_index ) {
					// Migrated posts have only the topic_id meta, we'll assume the last one is the new version.
					$new_version_index = count( $migrated_post_meta ) - 1;
				}

				foreach ( $migrated_post_meta as $index => $migrated_post_meta_item ) {
					if ( $index === $new_version_index ) {
						continue;
					}

					$new_version_post_id = $migrated_post_meta[ $new_version_index ]['post_id'];
					if ( $migrated_post_meta_item['post_id'] !== $new_version_post_id ) {
						$this->logger->log( $log_file, 'Deleting post ' . $migrated_post_meta_item['post_id'] . ' (Original ID: ' . $migrated_post_meta_item['meta_value'] . ') in favor of the new version (WP ID: ' . $new_version_post_id . ')', Logger::WARNING );
						wp_delete_post( $migrated_post_meta_item['post_id'], true );
					}
				}

				$this->logger->log( $log_file, '==============================================' );
			}

			if ( empty( $migrated_post_meta ) ) {
				$this->logger->log( $log_file, 'Topic ' . $topic['topic_id'] . ' has no migrated posts.', Logger::WARNING );
				continue;
			}
		}

		// Delete migrated posts that don't belong to this site.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$already_migrated_topics = $wpdb->get_results( "SELECT meta_key, meta_value, post_id FROM $wpdb->postmeta WHERE meta_key IN ('topic_id', '_newspack_migrated_topic_id')", ARRAY_A );

		foreach ( $already_migrated_topics as $migrated_post_meta ) {
			if ( ! in_array( $migrated_post_meta['meta_value'], array_column( $topics, 'topic_id' ) ) ) {
				$this->logger->log( $log_file, 'Deleting post ' . $migrated_post_meta['post_id'] . ' (Original ID: ' . $migrated_post_meta['meta_value'] . ') because it doesn\'t belong to this site.', Logger::WARNING );
				wp_delete_post( $migrated_post_meta['post_id'], true );
			}
		}

		// Check counts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$migrated_posts = $wpdb->get_results( "SELECT DISTINCT post_id FROM $wpdb->postmeta WHERE meta_key IN ('topic_id', '_newspack_migrated_topic_id')", ARRAY_A );
		$this->logger->log( $log_file, 'Total migrated posts: ' . count( $migrated_posts ) );
		$this->logger->log( $log_file, 'Total topics: ' . count( $topics ) );
	}

	/**
	 * Unpublish 1969 posts.
	 * Callable for "newspack-content-migrator embarcadero-unpublish-1969-posts".
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_embarcadero_unpublish_1969_posts( $args, $assoc_args ) {
		global $wpdb;

		$blog_site                        = $assoc_args['blog-site'];
		$story_csv_file_path              = $assoc_args['story-csv-file-path'];
		$story_print_issues_csv_file_path = $assoc_args['story-print-issues-csv-file-path'];

		$log_deleted                = $blog_site . '_unpublish_1969_posts.log';
		$log_deleted_posts_csv_file = $blog_site . '_unpublish_1969_posts.csv';

		$print_stories = $this->get_data_from_csv_or_tsv( $story_csv_file_path );
		$print_issues  = $this->get_data_from_csv_or_tsv( $story_print_issues_csv_file_path );

		$csv = fopen( $log_deleted_posts_csv_file, 'a' );
		// Add headers only if the file is empty.
		if ( filesize( $log_deleted_posts_csv_file ) === 0 ) {
			fputcsv( $csv, [ 'original_topic_id', 'wp_post_id', 'original_id_is_0', 'original_non_existing_issue_id', 'original_seo_link' ] );
		}

		$posts = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE post_type='post' AND post_status='publish' AND post_date LIKE '1969-%'", ARRAY_A );

		foreach ( $posts as $post ) {
			wp_update_post(
				[
					'ID'          => $post['ID'],
					'post_status' => 'draft',
				]
			);

			$original_print_id = get_post_meta( $post['ID'], '_newspack_import_print_story_id', true );

			// Check the original print story.
			$original_story_index = array_search( $original_print_id, array_column( $print_stories, 'story_id' ) );

			if ( false === $original_story_index ) {
				$this->logger->log( $log_deleted, 'Original print story not found for the post ' . $post['ID'] . ' (Original ID: ' . $original_print_id . ')', Logger::WARNING );
				continue;
			}

			$original_story = $print_stories[ $original_story_index ];

			$issue_id_is_0      = '';
			$non_existing_issue = '';

			if ( '0' == $original_story['issue_number'] ) {
				$issue_id_is_0 = 'Yes';
			} else {
				// Check the original print issue.
				$original_issue_index = array_search( $original_story['issue_number'], array_column( $print_issues, 'issue_id' ) );

				if ( false === $original_issue_index ) {
					$non_existing_issue = $original_story['issue_number'];
				} else {
					$this->logger->log( $log_deleted, 'Original print issue date is ' . $original_story['issue_date'] . ' but the issue ID is ' . $original_story['issue_number'] . ' (Original ID: ' . $original_print_id . ')', Logger::WARNING );
				}
			}

			fputcsv( $csv, [ $original_print_id, $post['ID'], $issue_id_is_0, $non_existing_issue, $original_story['seo_link'] ] );
			$this->logger->log( $log_deleted, 'Unpublished post ' . $post['ID'] . ' (Original ID: ' . $original_print_id . ')' );
		}

		$this->logger->log( $log_deleted, 'Unpublished ' . count( $posts ) . ' posts.' );
		fclose( $csv );
	}

	/**
	 * Migrate blog comments.
	 *
	 * @param int    $wp_post_id Post ID.
	 * @param array  $comments Comments.
	 * @param array  $all_users All users.
	 * @param string $blog_site Blog site.
	 * @return int Migrated comments count.
	 * @param string $log_file Log file.
	 */
	private function migrate_blog_comments( $wp_post_id, $comments, $all_users, $blog_site, $log_file ) {
		$migrated_comments_count = 0;

		foreach ( $comments as $comment_index => $comment ) {
			if ( empty( $comment['comment'] ) ) {
				$this->logger->log( $log_file, sprintf( 'Skipping empty comment for the post %d/%d: %d', $comment_index + 1, count( $comments ), $comment['topic_id'] ) );
				continue;
			}

			$users = array_key_exists( $comment['site_name'], $all_users ) ? $all_users[ $comment['site_name'] ] : [];

			// Get or create subscriber user.
			$wp_user    = null;
			$user_index = array_search( $comment['user_id'], array_column( $users, 'user_id' ) );
			if ( false !== $user_index ) {
				// Get WP_User object.
				$raw_user = $users[ $user_index ];

				// Update default email.
				if ( 'blank' == $raw_user['email'] ) {
					// Using "@$blog_site" for security reasons (a valid domain not owned by us or the Publisher could emulate this email).
					$raw_user['email'] = uniqid() . "@$blog_site";
				}

				$wp_user_id = $this->get_or_create_user( $raw_user['user_name'], $raw_user['email'], 'subscriber', $raw_user['first_name'], $raw_user['last_name'], $log_file );

				if ( $wp_user_id ) {
					$wp_user = get_user_by( 'id', $wp_user_id );
				}
			}

			$comment_data = [
				'comment_post_ID'      => $wp_post_id,
				'comment_approved'     => 'no' === $comment['hide'],
				'user_id'              => $wp_user ? $wp_user->ID : '',
				'comment_author'       => $wp_user ? $wp_user->display_name : $comment['user_name'],
				'comment_author_email' => $wp_user ? $wp_user->user_email : '',
				'comment_author_url'   => $wp_user ? $wp_user->user_url : '',
				'comment_author_IP'    => $comment['ip_address'] ?? '',
				'comment_content'      => ! empty( $comment['comment_edit'] ) ? $comment['comment_edit'] : $comment['comment'],
				'comment_date'         => $this->get_post_date_from_timestamp( $comment['posted_epoch'] ),
				'comment_meta'         => [],
			];

			$comment_id = wp_insert_comment( $comment_data );

			if ( ! $comment_id ) {
				$this->logger->log( $log_file, sprintf( 'Could not create comment %s', $comment['blog_comment_id'] ), Logger::WARNING );
				continue;
			}

			++$migrated_comments_count;

			update_comment_meta( $comment_id, self::EMBARCADERO_IMPORTED_BLOG_COMMENT_META_KEY, $comment['blog_comment_id'] );

			$this->logger->log( $log_file, sprintf( 'Created comment %d with the ID %d for the post %d', $comment['blog_comment_id'], $comment_id, $wp_post_id ) );
		}

		return $migrated_comments_count;
	}

	/**
	 * Get or create a contributor.
	 *
	 * @param string $username Username of the contributor.
	 * @param string $email_address Email address of the contributor.
	 * @param string $role Role of the user.
	 * @param string $first_name First name of the user.
	 * @param string $last_name Last name of the user.
	 * @param string $log_file Log file.
	 *
	 * @return int|null WP user ID.
	 */
	private function get_or_create_user( $username, $email_address, $role, $first_name, $last_name, $log_file ) {
		if ( ! empty( $first_name ) && ! empty( $last_name ) ) {
			$full_name = $first_name . ' ' . $last_name;
		} elseif ( ! empty( $username ) ) {
			if ( str_contains( $username, '@' ) ) {
				$username_parts = explode( '@', $username );
				$full_name      = $username_parts[0];
			} else {
				$full_name = $username;
			}
		} else {
			$email_address_parts = explode( '@', $email_address );
			$full_name           = $email_address_parts[0];
		}

		// Check if user exists.
		$wp_user = get_user_by( 'email', $email_address );
		if ( $wp_user && ! empty( $email_address ) ) {
			// Set WP User display name.
			wp_update_user(
				[
					'ID'            => $wp_user->ID,
					'display_name'  => $full_name,
					'first_name'    => $first_name,
					'last_name'     => $last_name,
					'user_nicename' => sanitize_title( $full_name ),
				]
			);

			return $wp_user->ID;
		}

		// Create a WP user with the contributor role.
		$user_login = 60 < strlen( $email_address ) ? substr( $email_address, 0, 60 ) : $email_address;
		$wp_user_id = wp_insert_user(
			[
				'user_login'    => $user_login,
				'user_pass'     => wp_generate_password(),
				'user_email'    => $email_address,
				'display_name'  => $full_name,
				'first_name'    => $first_name,
				'last_name'     => $last_name,
				'role'          => $role,
				'user_nicename' => sanitize_title( $full_name ),
			]
		);
		if ( is_wp_error( $wp_user_id ) ) {
			$this->logger->log( $log_file, sprintf( 'Could not create user %s: %s', $full_name, $wp_user_id->get_error_message() ), Logger::ERROR );
		}

		return $wp_user_id;
	}

	/**
	 * Get or create a category.
	 *
	 * @param string $name Category name.
	 * @param int    $parent_id Parent category ID.
	 *
	 * @return int|null Category ID.
	 */
	private function get_or_create_category( $name, $parent_id = null ) {
		$term = get_term_by( 'name', $name, 'category' );
		if ( $term ) {
			return $term->term_id;
		}

		$args = [];

		if ( $parent_id ) {
			$args['parent'] = $parent_id;
		}

		$term = wp_insert_term( $name, 'category', $args );
		if ( is_wp_error( $term ) ) {
			$this->logger->log( self::LOG_FILE, sprintf( 'Could not create category %s: %s', $name, $term->get_error_message() ), Logger::ERROR );
			return null;
		}

		return $term['term_id'];
	}

	/**
	 * Get data from CSV file.
	 *
	 * @param string $story_csv_file_path Path to the CSV file containing the stories to import.
	 * @return array Array of data.
	 */
	private function get_data_from_csv_or_tsv( $story_csv_file_path ) {
		$data = [];

		// Reading CSV or TSV.
		$separator = ',';
		if ( '.tsv' == strtolower( substr( $story_csv_file_path, -4 ) ) ) {
			$separator = "\t";
		}

		if ( ! file_exists( $story_csv_file_path ) ) {
			$this->logger->log( self::LOG_FILE, 'File does not exist: ' . $story_csv_file_path, Logger::ERROR );
		}

		$csv_file = fopen( $story_csv_file_path, 'r' );
		if ( false === $csv_file ) {
			$this->logger->log( self::LOG_FILE, 'Could not open file: ' . $story_csv_file_path, Logger::ERROR );
		}

		$csv_row = fgetcsv( $csv_file, null, $separator );
		if ( false === $csv_row ) {
			$this->logger->log( self::LOG_FILE, 'Could not read CSV headers from file: ' . $story_csv_file_path, Logger::ERROR );
		}

		$csv_headers = array_map( 'trim', $csv_row );

		while ( ( $csv_row = fgetcsv( $csv_file, null, $separator ) ) !== false ) {
			if ( count( $csv_row ) !== count( $csv_headers ) ) {
				$this->logger->log( self::LOG_FILE, 'Could not read CSV row (' . current( $csv_row ) . ') from file: ' . $story_csv_file_path, Logger::WARNING );
				continue;
			}
			$csv_row = array_map( 'trim', $csv_row );
			$csv_row = array_combine( $csv_headers, $csv_row );

			$data[] = $csv_row;
		}

		fclose( $csv_file );

		return $data;
	}

	/**
	 * Get a date string with site timezone from a timestamp.
	 *
	 * @param int $timestamp Timestamp.
	 *
	 * @return string Date in format Y-m-d H:i:s in the site timezone.
	 */
	private function get_post_date_from_timestamp( int $timestamp ): string {
		$date = \DateTime::createFromFormat( 'U', $timestamp );
		$date->setTimezone( $this->site_timezone );

		return $date->format( 'Y-m-d H:i:s' );
	}
}
