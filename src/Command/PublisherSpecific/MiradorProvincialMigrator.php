<?php
/**
 * Publisher Specific migrator for Mirador Provincial.
 * 
 * @package NewspackCustomContentMigrator\Command\PublisherSpecific
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\Guest_Contributor_Role;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Posts;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;

/**
 * Publisher Specific commands for Mirador Provincial.
 */
class MiradorProvincialMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	const POST_META_OLD_USR_ID = '_mirador_old_usr_id';
	const USER_META_OLD_USR_ID = 'old_usr_id';
	
	const POST_META_PROCESSED_KEY     = 'newspack_processed_mirador_old_usr_id';
	const POST_META_PROCESSED_YES     = 'yes';
	const POST_META_PROCESSED_FAILED  = 'failed';
	const POST_META_PROCESSED_SKIPPED = 'skipped';

	/**
	 * Logger
	 *
	 * @var MultiLog
	 */
	private $logger;

	/**
	 * Post Logic
	 *
	 * @var Posts
	 */
	private Posts $posts_logic;
	
	/**
	 * Report
	 *
	 * @var array
	 */
	private $report = array();

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->posts_logic = new Posts();
	}

	/**
	 * Commands
	 *
	 * @return void
	 */
	public static function register_commands(): void {

		WP_CLI::add_command(
			'newspack-content-migrator mirador-provincial-dupes',
			self::get_command_closure( 'cmd_dupes' ),
			[
				'shortdesc' => 'Delete duplicate posts.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator mirador-provincial-users',
			self::get_command_closure( 'cmd_users' ),
			[
				'shortdesc' => 'Set users from old ids.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'csv-file',
						'description' => 'Path to csv author/user lookup file.',
						'optional'    => false,
					],
				],
			]
		);
	}

	/**
	 * Command: newspack-content-migrator mirador-provincial-dupes
	 *
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * 
	 * @return void
	 */
	public function cmd_dupes( array $pos_args, array $assoc_args ): void {
		
		$this->set_logger( __FUNCTION__ );
		$this->logger->info( 'Running command: ' . __FUNCTION__ );

		global $wpdb;

		// Select posts grouped by old site url, having count > 1 .
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$group_results = $wpdb->get_results(
			"
				SELECT pm.meta_value, GROUP_CONCAT( p.ID ) as dupe_ids, COUNT(*) as counter
				FROM {$wpdb->postmeta} pm
				JOIN {$wpdb->posts} p on p.ID = pm.post_id and p.post_type = 'post' and p.post_status = 'publish'
				WHERE pm.meta_key = '_mirador_old_slug'
				GROUP BY pm.meta_value
				HAVING counter > 1
			"
		);

		if ( $wpdb->last_error ) {
			$this->logger->error( 'SQL error: group_results: ' . $wpdb->last_error );
			exit();
		}

		if ( empty( $group_results ) ) {
			$this->logger->info( 'No duplicates found.' );
			return;
		}

		$this->logger->info( 'Count of old site slugs with duplicates: ' . count( $group_results ) );
		$this->report_set( 'old slugs groups found', count( $group_results ) );

		foreach ( $group_results as $group_result ) {
			
			$this->logger->info( '-- Old Slug: ' . $group_result->meta_value );
			$this->report_add( 'old slugs groups processed' );

			// Get the set of posts for the old slug.
			$dupes_arr = explode( ',', $group_result->dupe_ids );
			$posts     = get_posts(
				[
					'include'       => $dupes_arr,
					'numberposts'   => -1,
					'no_found_rows' => true,
					'orderby'       => 'post_name', // Order by post name so that -2, -3 come after -1.
					'order'         => 'ASC',
				]
			);

			if ( count( $posts ) !== count( $dupes_arr ) ) {
				$this->logger->error( 'get_posts did not return same group count.' );
				return; 
			}

			// Check for duplicates.
			$is_first_post = true;
			foreach ( $posts as $post ) {
				
				$this->logger->info( 'post_id: ' . $post->ID . ' post_name: ' . $post->post_name );

				// First post in loop.
				if ( $is_first_post ) {
					$first_post_name = $post->post_name;
					$first_hash      = $this->get_post_hash_for_comparisons( $post );
					$is_first_post   = false;
					continue;
				}

				// Additional posts.

				// Check if -2, -3, etc.
				if ( ! preg_match( '/^-\d+$/', str_replace( $first_post_name, '', $post->post_name ) ) ) {
					$this->logger->warning( 'Post name did not match -2, -3, etc.' );
					$this->report_add( 'posts not matching -2, -3' );
					continue;
				}

				// Check hash.
				if ( $first_hash !== $this->get_post_hash_for_comparisons( $post ) ) {
					$this->logger->warning( 'Hash did not match.' );
					$this->report_add( 'posts not matching hash' );
					continue;
				}
				
				// Delete the post.
				wp_delete_post( $post->ID );

				$this->logger->info( 'Deleted post.' );
				$this->report_add( 'posts deleted' );

			} // foreach post.

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$this->logger->info( print_r( $this->report, true ) );

		} // foreach group of old slugs.

		$this->logger->info( 'Done.' );
	}

	/**
	 * Command: newspack-content-migrator mirador-provincial-users
	 *
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * 
	 * @return void
	 */
	public function cmd_users( array $pos_args, array $assoc_args ): void {

		$log_slug = str_replace( __NAMESPACE__ . '\\', '', __CLASS__ ) . '_' . __FUNCTION__;
		$logger   = MultiLog::get_logger(
			$log_slug . '-multi',
			[
				CliLog::get_logger( $log_slug ),
				FileLog::get_logger( $log_slug ),
			] 
		);

		// Load a CSV Lookup array.
		if ( ! file_exists( $assoc_args['csv-file'] ) ) {
			$logger->error( 'CSV file does not exist.' );
			exit();
		}
		$csv_handle = fopen( $assoc_args['csv-file'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $csv_handle ) {
			$logger->error( 'CSV file not readable.' );
			exit();
		}
		$csv_lookup = array();
		while ( $row = fgetcsv( $csv_handle ) ) {
			// Verify ID is int and name string has length.
			if ( empty( $row[0] ) || ! preg_match( '/^\d+$/', $row[0] ) || empty( $row[1] ) ) {
				$logger->error( print_r( $row, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$logger->error( 'CSV row needs to be ID, name, ...' );
				exit();
			}
			$csv_lookup[ $row[0] ] = $row[1];
		}
		fclose( $csv_handle );

		// Loop through posts with old user meta.
		$meta_query = array(
			array(
				'key'     => static::POST_META_OLD_USR_ID,
				'value'   => '',
				'compare' => '!=',
			),
			array(
				'key'     => static::POST_META_PROCESSED_KEY,
				'compare' => 'NOT EXISTS',
			),      
		);
		$this->posts_logic->throttled_posts_loop( 
			array(
				'post_type'   => 'post',
				'post_status' => array( 'publish' ),
				'fields'      => 'ids',
				'meta_query'  => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			),
			function ( $post_id ) use ( $logger, $csv_lookup ) {
				
				$logger->info( ' -- Post id: ' . $post_id );

				// Get array of old user id meta(s).
				$post_meta_array = get_post_meta( $post_id, static::POST_META_OLD_USR_ID, false );

				// NOTE: DB only contains 1 user meta even if multiple old users - need to solve if data is fixed.
				if ( 1 !== count( $post_meta_array ) ) {
					$logger->error( print_r( $post_meta_array, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					$logger->error( 'TODO: handle cases where meta row count <> 1' );
					update_post_meta( $post_id, static::POST_META_PROCESSED_KEY, static::POST_META_PROCESSED_FAILED );
					return;
				}

				// Assign old user id from first array element.
				$old_usr_id = trim( $post_meta_array[0] );

				// If post does not have an old user id, skip, nothing to fix.
				if ( empty( $old_usr_id ) ) {
					$logger->warning( 'Skipping blank post meta.' );
					update_post_meta( $post_id, static::POST_META_PROCESSED_KEY, static::POST_META_PROCESSED_SKIPPED );
					return;
				}

				// If post meta value is not integer, error.
				if ( ! preg_match( '/^\d+$/', $old_usr_id ) ) {
					$logger->error( print_r( $post_meta_array, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					$logger->error( 'Meta value is not an integer. Need to fix.' );
					update_post_meta( $post_id, static::POST_META_PROCESSED_KEY, static::POST_META_PROCESSED_FAILED );
					return;
				}

				// Check for existing user.
				$users = get_users(
					[
						'meta_key'   => static::USER_META_OLD_USR_ID,
						'meta_value' => $old_usr_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					]
				);

				// Multiple users found by meta, need to handle this case?
				if ( 1 < count( $users ) ) {
					$logger->error( print_r( $post_meta_array, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					$logger->error( print_r( $users, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					$logger->error( 'Multiple users found by user meta.  Need to fix.' );
					update_post_meta( $post_id, static::POST_META_PROCESSED_KEY, static::POST_META_PROCESSED_FAILED );
					return;
				}
				
				// WP User found via user meta.
				if ( 1 === count( $users ) ) {

					// Update the post's author.
					wp_update_post(
						array(
							'ID'          => $post_id,
							'post_author' => $users[0]->ID,
						)
					);

					update_post_meta( $post_id, static::POST_META_PROCESSED_KEY, static::POST_META_PROCESSED_YES );

					$logger->info( 'Updated post to existing user id: ' . $users[0]->ID );

					return;
				}

				// If no user in csv, error.
				if ( ! isset( $csv_lookup[ $old_usr_id ] ) ) {
					$logger->error( print_r( $post_meta_array, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					$logger->error( 'WP user not found, nor old_usr not found in csv lookup.  Do we skip this post?' );
					update_post_meta( $post_id, static::POST_META_PROCESSED_KEY, static::POST_META_PROCESSED_FAILED );
					return;
				}               

				// Insert the old usr from csv.
				$user_data = [
					'user_login'    => Guest_Contributor_Role::generate_username( $csv_lookup[ $old_usr_id ] ),
					'user_nicename' => $old_usr_id,
					'display_name'  => $csv_lookup[ $old_usr_id ],
					'role'          => Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME,
					'user_pass'     => wp_generate_password(),
					'meta_input'    => [
						static::USER_META_OLD_USR_ID => $old_usr_id,
					],
				];

				// Insert user.
				$user_id = \wp_insert_user( $user_data );
				if ( is_wp_error( $user_id ) ) {
					$logger->error( print_r( $user_data, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					$logger->error( sprintf( 'Could not create user: %s', $user_id->get_error_message() ) );
					update_post_meta( $post_id, static::POST_META_PROCESSED_KEY, static::POST_META_PROCESSED_FAILED );
					return;
				}

				$logger->info( sprintf( 'User created successfully (#%d).', $user_id ) );

				// Update the post's author.
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_author' => $user_id,
					)
				);
				
				update_post_meta( $post_id, static::POST_META_PROCESSED_KEY, static::POST_META_PROCESSED_YES );

				$logger->info( 'Updated post to new user id: ' . $user_id );
			},
			0
		);
	}

	/**
	 * Get hash of a post with meta and related taxonomies for comparisons.
	 * 
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function get_post_hash_for_comparisons( $post ) {

		global $wpdb;

		// Get post as string; remove allowed unique fields for the comparison.
		$json_post = wp_json_encode(
			array_diff_key( 
				(array) $post,
				array( 
					'ID'                => 1,
					'post_name'         => 1,
					'guid'              => 1,
					'post_modified'     => 1,
					'post_modified_gmt' => 1,
				)
			) 
		);

		// Get post meta as string; order by meta key then value for consistency.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$postmeta_results = $wpdb->get_results(
			$wpdb->prepare(
				"
					SELECT meta_key, meta_value
					FROM {$wpdb->postmeta}
					WHERE post_id = %d
					ORDER BY meta_key, meta_value
				",
				$post->ID 
			) 
		);

		if ( $wpdb->last_error ) {
			$this->logger->error( 'SQL error: postmeta_results: ' . $wpdb->last_error );
			exit();
		}

		// No post meta is ok.
		$json_postmeta = wp_json_encode( $postmeta_results );
		
		// Get term relationships as string; order by term_taxonomy_id for consistency.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$taxonomy_results = $wpdb->get_results(
			$wpdb->prepare(
				"
					SELECT term_taxonomy_id
					FROM {$wpdb->term_relationships}
					WHERE object_id = %d
					ORDER BY term_taxonomy_id
				",
				$post->ID 
			) 
		);

		if ( $wpdb->last_error ) {
			$this->logger->error( 'SQL error: taxonomy_results: ' . $wpdb->last_error );
			exit();
		}

		// No term relationships is ok.
		$json_taxonomy = wp_json_encode( $taxonomy_results );

		return hash( 'sha256', 'post-' . $json_post . '-postmeta-' . $json_postmeta . '-taxonomy-' . $json_taxonomy );
	}

	/**
	 * Report add an increment to key.
	 * 
	 * @param string $key       Report array key.
	 * @param int    $increment Integer to add.
	 * @return void
	 */
	private function report_add( $key, $increment = 1 ) {
		if ( empty( $this->report[ $key ] ) ) {
			$this->report[ $key ] = 0;
		}
		$this->report[ $key ] += $increment;
	}

	/**
	 * Report set a key to a value.
	 *
	 * @param string $key   Key for array.
	 * @param mixed  $value Value to set.
	 * @return void
	 */
	private function report_set( $key, $value ) {
		if ( empty( $this->report[ $key ] ) ) {
			$this->report[ $key ] = 0;
		}
		$this->report[ $key ] = $value;
	}

	/**
	 * Set the logger
	 *
	 * @param string $caller Calling __FUNCTION__ name.
	 * @return void
	 */
	private function set_logger( $caller ) {
		$log_slug     = str_replace( __NAMESPACE__ . '\\', '', __CLASS__ ) . '_' . $caller;
		$this->logger = MultiLog::get_logger(
			$log_slug . '-multi',
			[
				CliLog::get_logger( $log_slug ),
				FileLog::get_logger( $log_slug ),
			] 
		);
	}
}
