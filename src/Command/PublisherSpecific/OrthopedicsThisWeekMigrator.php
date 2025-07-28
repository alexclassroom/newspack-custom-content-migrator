<?php
/**
 * Migration tasks for Orthopedics This Week.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Exception;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\CoAuthorsPlusHelper;
use Newspack\MigrationTools\Logic\Posts;
use Newspack\MigrationTools\Util\Log\FileLog;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;
use WP_Error;
use WP_User;

/**
 * Custom migration scripts for Orthopedics This Week.
 */
class OrthopedicsThisWeekMigrator implements RegisterCommandInterface {
	use WpCliCommandTrait;

	const BREAKING_POST_TYPE       = 'breaking';
	const SECONDARY_POSTS_TAG_NAME = 'Secondary';
	const USER_EMAIL_DOMAIN        = 'ryortho.com';

	/**
	 * Posts logic.
	 * 
	 * @var Posts
	 */
	private Posts $posts_logic;

	/**
	 * Co-Authors Plus.
	 * 
	 * @var CoAuthorsPlusHelper
	 */
	private CoAuthorsPlusHelper $cap;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->posts_logic = new Posts();
		$this->cap         = new CoAuthorsPlusHelper();
	}

	/**
	 * Registers WP CLI Commands.
	 * 
	 * @return void
	 */
	public static function register_commands(): void {
		$generic_args = [];

		WP_CLI::add_command(
			'newspack-content-migrator otw-migrate-secondary-posts',
			self::get_command_closure( 'cmd_migrate_secondary_posts' ),
			[
				...$generic_args,
				'shortdesc' => 'Migrate Secondary Posts.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator otw-rollback-secondary-posts',
			self::get_command_closure( 'cmd_rollback_secondary_posts' ),
			[
				...$generic_args,
				'shortdesc' => 'Rollback Secondary Posts to CPT Breaking.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator otw-migrate-acf-authors',
			self::get_command_closure( 'cmd_migrate_acf_authors' ),
			[
				'shortdesc' => 'Migrate Authors from ACF field to Users.',
				'synopsis'  => [
					[
						'type'        => 'flag',
						'name'        => 'dry-run',
						'description' => 'Whether to run a dry run.',
						'optional'    => true,
						'repeating'   => false,
					],
				],
			]
		);
	}

	/**
	 * Rollbacks "Secondary" posts to CPT Breaking.
	 * This is needed before Content Refresh to get an up-to-date list of Secondary posts.
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_rollback_secondary_posts( array $pos_args, array $assoc_args ): void {
		$posts_ids = get_posts(
			[
				'post_type'   => 'post',
				'post_status' => 'any',
				'tag'         => self::SECONDARY_POSTS_TAG_NAME,
				'fields'      => 'ids',
				'numberposts' => -1,
			] 
		);

		// First, we need to migrate the posts to the new post type.
		global $wpdb;

		$posts_ids_placeholders = implode( ', ', array_fill( 0, count( $posts_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$wpdb->prepare(
				"UPDATE {$wpdb->posts}
				SET `post_type` = %s
				WHERE `ID` IN ($posts_ids_placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::BREAKING_POST_TYPE,
				...$posts_ids
			)
		);

		wp_cache_flush();
	}

	/**
	 * Migrate CPT Breaking to regular Posts with post_tag "Secondary".
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_migrate_secondary_posts( array $pos_args, array $assoc_args ): void {
		$file_loggger = FileLog::get_logger( 'migrate-secondary-posts' );

		// CSV.
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$csv = sprintf( 'migrate-secondary-posts-%s.csv', date( 'Y-m-d H-i-s' ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$csv_file_pointer = fopen( $csv, 'w' );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
		fputcsv(
			$csv_file_pointer,
			[
				'#',
				'Post ID',
				'Post Title',
				'URL',
			]
		);

		$post_tag = get_term_by( 'name', self::SECONDARY_POSTS_TAG_NAME, 'post_tag' );

		if ( ! $post_tag ) {
			$file_loggger->info( 'Secondary Post Tag not found. Creating term...' );

			$post_tag = wp_insert_term( self::SECONDARY_POSTS_TAG_NAME, 'post_tag' );

			if ( is_wp_error( $post_tag ) ) {
				$file_loggger->error( 'Couldn\'t create Secondary post tag. Aborting..' );
				return;
			} else {
				$post_tag = get_term_by( 'name', self::SECONDARY_POSTS_TAG_NAME, 'post_tag' );
			}
		}

		$posts_ids = $this->posts_logic->get_all_posts_ids( self::BREAKING_POST_TYPE );

		// First, we need to migrate the posts to the new post type.
		global $wpdb;

		$posts_ids_placeholders = implode( ', ', array_fill( 0, count( $posts_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts}
				SET `post_type` = 'post'
				WHERE `ID` IN ($posts_ids_placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				...$posts_ids
			)
		);

		// Second, for each post, we need to set the post_tag to Secondary.

		$progress_bar = WP_CLI\Utils\make_progress_bar( 'Migrating Secondary Posts', count( $posts_ids ) );

		foreach ( $posts_ids as $index => $post_id ) {
			$progress_bar->tick( 1, sprintf( '[Memory: %s] Migrating Secondary Posts %d/%d', size_format( memory_get_usage( true ) ), $index + 1, count( $posts_ids ) ) );

			$file_loggger->info( sprintf( 'Processing Post #%d', $post_id ) );

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

			wp_set_post_tags( $post_id, $post_tag->slug, true );
		}

		$progress_bar->finish();

		// Close CSV.
		fclose( $csv_file_pointer );

		wp_cache_flush();
	}

	/**
	 * Migrate ACF Author fields to Users.
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_migrate_acf_authors( array $pos_args, array $assoc_args ): void {
		$dry_run = isset( $assoc_args['dry-run'] ) ? true : false;

		$file_loggger = FileLog::get_logger( 'migrate-acf-authors' );

		// CSV.
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$csv = sprintf( 'migrate-acf-authors-%s.csv', date( 'Y-m-d H-i-s' ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$csv_file_pointer = fopen( $csv, 'w' );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
		fputcsv(
			$csv_file_pointer,
			[
				'#',
				'Post ID',
				'Post Title',
				'Staging URL',
				'Live URL',
			]
		);

		global $wpdb;
		$post_ids = array_values(
			array_unique(
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->get_col(
					"SELECT `post_id`
            FROM {$wpdb->postmeta}
            WHERE `meta_key` = 'article_author'"
				) 
			) 
		);

		$progress_bar = WP_CLI\Utils\make_progress_bar( 'Migrating ACF Authors', count( $post_ids ) );

		foreach ( $post_ids as $index => $post_id ) {
			$progress_bar->tick( 1, sprintf( '[Memory: %s] Migrating ACF Authors %d/%d', size_format( memory_get_usage( true ) ), $index + 1, count( $post_ids ) ) );

			$file_loggger->info( sprintf( 'Processing Post #%d', $post_id ) );

			if ( get_post_status( $post_id ) !== 'publish' ) {
				continue;
			}

			if ( get_post_meta( $post_id, 'newspack_author_converted_to_user', true ) ) {
				continue;
			}

			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
			fputcsv(
				$csv_file_pointer,
				[
					$index + 1,
					$post_id,
					get_the_title( $post_id ),
					get_permalink( $post_id ),
					str_replace( home_url(), 'https://ryortho.com', get_permalink( $post_id ) ),
				]
			);

			$article_authors = array_unique( array_filter( get_post_meta( $post_id, 'article_author' ) ) );

			if ( ! $dry_run && ! empty( $article_authors ) ) {
				$author = $this->upsert_author( $article_authors[0] );

				try {
					if ( is_wp_error( $author ) || is_null( $author ) ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_dump
						var_dump( $post_id, $author );

						continue;
					}

					if ( $this->cap->is_coauthors_active() ) {
						$this->cap->assign_authors_to_post( [ $author ], $post_id );

						// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
						update_post_meta( $post_id, 'newspack_author_converted_to_user', date( 'Y-m-d H:i:s' ) );
					}
				} catch ( Exception $e ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_dump
					var_dump( $post_id, $e->getMessage() );
				}
			}
		}

		$progress_bar->finish();

		// Close CSV.
		fclose( $csv_file_pointer );

		wp_cache_flush();
	}

	/**
	 * Inserts a new User with the given name.
	 * The email of the User should be "name@ryortho.com".
	 * 
	 * @param  string $name  The name of the Author.
	 * @return WP_User|WP_Error The ID of the User on success. Otherwise, returns a WP_Error
	 */
	private function upsert_author( $name ): WP_User|WP_Error {
		$username   = substr( str_replace( '-', '', sanitize_title( $name ) ), 0, 59 ); // Username can be max 60 chars.
		$user_email = $username . '@' . self::USER_EMAIL_DOMAIN;

		$wp_user = get_user_by( 'email', $user_email );

		if ( ! $wp_user ) {
			$wp_user = get_user_by( 'login', $username );

			if ( $wp_user ) {
				$username .= '1';
			}

			$wp_user = wp_insert_user(
				[
					'first_name'    => $name,
					'display_name'  => $name,
					'user_nicename' => substr( $username, 0, 50 ), // User Nicename can be max 50 chars.
					'user_login'    => $username,
					'user_email'    => $user_email,
					'user_pass'     => wp_generate_password(),
					'role'          => 'contributor_no_edit',
					'meta_input'    => [
						// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
						'newspack_user_imported_date' => date( 'Y-m-d H:i:s' ),
						'newspack_user_source'        => 'ACF Meta',
					],
				] 
			);

			if ( ! is_wp_error( $wp_user ) ) {
				$wp_user = get_user_by( 'ID', $wp_user );
			}
		}

		return $wp_user;
	}
}
