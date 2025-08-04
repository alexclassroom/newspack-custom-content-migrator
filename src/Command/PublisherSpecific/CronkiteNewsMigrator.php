<?php
/**
 * Importer for Bailiwick sites.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\Guest_Contributor_Role;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Hooks\MemoryCleanupHook;
use Newspack\MigrationTools\Logic\UsersHelper;
use Newspack\MigrationTools\Logic\SimpleLocalAvatars;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use NewspackCustomContentMigrator\Logic\ContentDiffMigrator;
use WP_CLI;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Bramus\Monolog\Formatter\ColoredLineFormatter;

class CronkiteNewsMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * Whether logging is enabled. Useful for testing environment -- if disabled the loggers are NullLogger instances.
	 * 
	 * @var bool Whether logging is enabled.
	 */
	private $enable_logging = true;

	/**
	 * Log outputs.
	 */
	public const LOG_OUTPUTS = [
		'CLI'          => 'cli',
		'FILE'         => 'file', 
		'CLI_AND_FILE' => 'cli_and_file',
	];

	/**
	 * CLI logger.
	 * 
	 * @var Logger|NullLogger CLI logger.
	 */
	protected $logger_cli;

	/**
	 * File logger.
	 * 
	 * @var Logger|NullLogger File logger.
	 */
	protected $logger_file;

	/**
	 * Constructor.
	 * 
	 * @param bool $enable_logging Whether to enable logging (defaults to true).
	 */
	public function __construct( bool $enable_logging = true ) {
		$this->enable_logging = $enable_logging;

		// Initialize with NullLogger if logging is disabled.
		if ( false === $this->enable_logging ) {
			$this->logger_cli  = new NullLogger();
			$this->logger_file = new NullLogger();
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator cronkite-news-validator',
			self::get_command_closure( 'cmd_validator' ),
			[
				'shortdesc' => 'Import ACF users from a CSV file.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'live-table-prefix',
						'description' => 'Live table prefix, e.g. "live_".',
						'optional'    => false,
					],
				],
			]
		);
		WP_CLI::add_command(
			'newspack-content-migrator cronkite-news-import-acf-users',
			self::get_command_closure( 'cmd_import_acf_users' ),
			[
				'shortdesc' => 'Import ACF users from a CSV file.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'live-table-prefix',
						'description' => 'Live table prefix, e.g. "live_".',
						'optional'    => false,
					],
					[
						'type'        => 'flag',
						'name'        => 'update-existing-users',
						'description' => 'Will update existing or already impported users with new data.',
						'optional'    => true,
					],
				],
			]
		);
		WP_CLI::add_command(
			'newspack-content-migrator cronkite-news-set-post-authors',
			self::get_command_closure( 'cmd_set_post_authors' ),
			[
				'shortdesc' => 'Sets coauthors to posts -- must run after import_acf_users.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'live-table-prefix',
						'description' => 'Live table prefix, e.g. "live_".',
						'optional'    => false,
					],
				],
			]
		);
	}

	/**
	 * Callback for the `cronkite-news-validator` command.
	 *
	 * @param array $pos_args   Positional arguments from WP_CLI.
	 * @param array $assoc_args Associative arguments from WP_CLI.
	 *
	 * @throws \Exception If something goes wrong.
	 */
	public function cmd_validator( array $pos_args, array $assoc_args ): void {
		global $wpdb;
		
		$live_prefix = esc_sql( $assoc_args['live-table-prefix'] );
		
		$this->init_loggers( __FUNCTION__ );

		/**
		 * Pt.1/2: List all user data objects.
		 */
		$user_reults        = $wpdb->get_col( $wpdb->prepare( 'SELECT display_name FROM %i', $live_prefix . 'users' ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
		$user_display_names = [];
		foreach ( $user_reults as $user_reult ) {
			$user_display_names[] = $user_reult;
		}
		
		$staff_results       = $wpdb->get_col( $wpdb->prepare( "select post_title from %i where post_type = 'cn_staff';", $live_prefix . 'posts' ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
		$staff_display_names = [];
		foreach ( $staff_results as $staff_result ) {
			$staff_display_names[] = $staff_result;
		}
		
		$students_display_names = [];
		$students_results       = $wpdb->get_col( $wpdb->prepare( "select post_title from %i where post_type = 'students';", $live_prefix . 'posts' ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
		foreach ( $students_results as $student_result ) {
			$students_display_names[] = $student_result;
		}

		file_put_contents( 'cmd_validator__user_display_names.txt', implode( PHP_EOL, $user_display_names ) ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.
		file_put_contents( 'cmd_validator__staff_display_names.txt', implode( PHP_EOL, $staff_display_names ) ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.
		file_put_contents( 'cmd_validator__students_display_names.txt', implode( PHP_EOL, $students_display_names ) ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.

		$overlap_users_staff    = array_intersect( $user_display_names, $staff_display_names );
		$overlap_users_students = array_intersect( $user_display_names, $students_display_names );
		$overlap_staff_students = array_intersect( $staff_display_names, $students_display_names );

		file_put_contents( 'cmd_validator__overlap_users_staff.txt', implode( PHP_EOL, $overlap_users_staff ) ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.
		file_put_contents( 'cmd_validator__overlap_users_students.txt', implode( PHP_EOL, $overlap_users_students ) ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.
		file_put_contents( 'cmd_validator__overlap_staff_students.txt', implode( PHP_EOL, $overlap_staff_students ) ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.


		/**
		 * Pt.2/2: Break down author data per post.
		 */

		$post_ids = $wpdb->get_col( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
			$wpdb->prepare(
				"select ID
				from %i
				where post_type = 'post';",
				$live_prefix . 'posts'
			)
		);

		// CSV file with data.
		$csv_separator = '§';
		$csv_file      = 'cmd_validator.csv';
		file_put_contents( // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.
			$csv_file,
			sprintf(
				'post_id' . '%s' . 'has_custom_authors' . '%s' . 'count_bylines_staff' . '%s' . 'byline_staff' . '%s' . 'count_bylines_external' . '%s' . 'bylines_external' . '%s' . 'count_post_author_meta' . '%s' . 'post_authors_meta', // phpcs:ignore -- Generic.Strings.UnnecessaryStringConcat.Found.
				$csv_separator,
				$csv_separator,
				$csv_separator,
				$csv_separator,
				$csv_separator,
				$csv_separator,
				$csv_separator,
				$csv_separator,
				$csv_separator
			) . PHP_EOL
		);

		foreach ( $post_ids as $key_post_id => $post_id ) {

			$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( '(%d)/(%d) %d', $key_post_id + 1, count( $post_ids ), $post_id ) );
			
			$bylines_staff                     = [];
			$bylines_staff_post_ids            = [];
			$bylines_external                  = [];
			$bylines_external_post_ids         = [];
			$bylines_post_author_meta          = [];
			$bylines_post_author_meta_post_ids = [];
			$not_matched_post_ids              = [];

			/**
			 * Byline_info_cn_staff meta contains one or more CPT authors.
			 */
			$byline_staff_serialized_ids = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM %i WHERE post_id = %d AND meta_key = 'byline_info_cn_staff' and meta_value <> '';", $live_prefix . 'postmeta', $post_id ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
			if ( $byline_staff_serialized_ids ) {
				$byline_staff_ids = unserialize( $byline_staff_serialized_ids ); // phpcs:ignore -- WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize.
				foreach ( $byline_staff_ids as $byline_staff_id ) {
					// Byline names keys, post_ids values.
					$byline_staff_display_name                   = $wpdb->get_var( $wpdb->prepare( 'SELECT post_title FROM %i WHERE ID = %d;', $live_prefix . 'posts', $byline_staff_id ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
					$bylines_staff[ $byline_staff_display_name ] = array_merge( 
						isset( $bylines_staff[ $byline_staff_display_name ] ) ? $bylines_staff[ $byline_staff_display_name ] : [],
						[ $post_id ]
					);

					// List of posts where this byline is used.
					if ( ! in_array( $post_id, $bylines_staff_post_ids ) ) {
						$bylines_staff_post_ids[] = $post_id;
					}
				}
			}
			
			/**
			 * Post_author meta contains a single string byline.
			 */
			$post_author_meta = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM %i WHERE post_id = %d AND meta_key = 'post_author' and meta_value <> '';", $live_prefix . 'postmeta', $post_id ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
			if ( $post_author_meta ) {
				$bylines_post_author_meta[ $post_author_meta ] = array_merge( 
					isset( $bylines_post_author_meta[ $post_author_meta ] ) ? $bylines_post_author_meta[ $post_author_meta ] : [],
					[ $post_id ]
				);

				// List of posts where this byline is used.
				if ( ! in_array( $post_id, $bylines_post_author_meta_post_ids ) ) {
					$bylines_post_author_meta_post_ids[] = $post_id;
				}
			}

			/**
			 * Byline_info_external_authors_repeater_0_external_authors meta contains a single string byline.
			 */
			$byline_external = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM %i WHERE post_id = %d AND meta_key = 'byline_info_external_authors_repeater_0_external_authors' and meta_value <> '';", $live_prefix . 'postmeta', $post_id ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
			if ( $byline_external ) {
				$bylines_external[ $byline_external ] = array_merge( 
					isset( $bylines_external[ $byline_external ] ) ? $bylines_external[ $byline_external ] : [],
					[ $post_id ]
				);

				// List of posts where this byline is used.
				if ( ! in_array( $post_id, $bylines_external_post_ids ) ) {
					$bylines_external_post_ids[] = $post_id;
				}
			}

			if ( ! $byline_staff_serialized_ids && ! $post_author_meta && ! $byline_external ) {
				$not_matched_post_ids[] = $post_id;
			}

			file_put_contents( // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.
				$csv_file, 
				sprintf(
					'%d%s%s%s%d%s%s%s%d%s%s%s%d%s%s' . PHP_EOL,
					$post_id,
					$csv_separator,
					// has_custom_authors.
					( 0 === count( $not_matched_post_ids ) ) ? 'yes' : 'no',
					$csv_separator,
					// count_bylines_staff.
					count( $bylines_staff ),
					$csv_separator,
					// byline_staff.
					implode( ' | ', array_keys( $bylines_staff ) ),
					$csv_separator,
					// count_bylines_external.
					count( $bylines_external ),
					$csv_separator,
					// bylines_external.
					implode( ' | ', array_keys( $bylines_external ) ),
					$csv_separator,
					// count_post_author_meta.
					count( $bylines_post_author_meta ),
					$csv_separator,
					// post_authors_meta.
					implode( ' | ', array_keys( $bylines_post_author_meta ) ),
					$csv_separator
				),
				FILE_APPEND
			);

		}

		$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, 'Done.' );
	}

	/**
	 * Imports ACF users from live DB to local WP_Users.
	 * 
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_import_acf_users( array $pos_args, array $assoc_args ): void {
		global $wpdb;
		
		$live_prefix           = esc_sql( $assoc_args['live-table-prefix'] );
		$update_existing_users = $assoc_args['update-existing-users'] ?? false;

		$this->init_loggers( __FUNCTION__ );
		$users_helper         = new UsersHelper();
		$simple_local_avatars = new SimpleLocalAvatars();
		
		// phpcs:disable
		// $staff_results = $wpdb->get_col( $wpdb->prepare( "select post_title from %i where post_type = 'cn_staff';", $live_prefix . 'posts' ) );
		// $staff_display_names = [];
		// foreach ( $staff_results as $staff_result ) {
		// $staff_display_names[] = $staff_result;
		// }
		
		// $students_display_names = [];
		// $students_results = $wpdb->get_col( $wpdb->prepare( "select post_title from %i where post_type = 'students';", $live_prefix . 'posts' ) );
		// foreach ( $students_results as $student_result ) {
		// $students_display_names[] = $student_result;
		// }
		// phpcs:enable
		
		// Set unique migration ID with value display_name to existing local WP_Users.
		
		$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( 'Setting WP_User UID for %d existing local WP_Users...', count( $wp_users_results ) ) );
		$wp_users_results = $wpdb->get_results( "SELECT ID, display_name FROM {$wpdb->users}", ARRAY_A ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
		foreach ( $wp_users_results as $key_wp_user_result => $wp_user_result ) {
			$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( 'Setting WP_User UID (%d)/(%d) %d', $key_wp_user_result + 1, count( $wp_users_results ), $wp_user_result['ID'] ) );
			$updated = update_user_meta( $wp_user_result['ID'], UsersHelper::UNIQUE_IDENTIFIER_META_KEY, $wp_user_result['display_name'], true );
			if ( false === $updated ) {
				$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::ERROR, sprintf( 'Failed to update user meta for user %d with key %s and value %s', $wp_user_result['ID'], UsersHelper::UNIQUE_IDENTIFIER_META_KEY, $wp_user_result['display_name'] ) );
				exit;
			}
		}

		// Import Staff from live DB to local WP_Users.
		$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( 'Importing Staff from live DB to local WP_Users...' ) );
		$staff_rows = $wpdb->get_results( $wpdb->prepare( "select * from %i where post_type = 'cn_staff';", $live_prefix . 'posts' ), ARRAY_A ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
		foreach ( $staff_rows as $key_staff_row => $staff_row ) {
			MemoryCleanupHook::cleanup( 0, $key_staff_row + 1, 20 );

			$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( "Importing Staff (%d)/(%d) '%s'", $key_staff_row + 1, count( $staff_rows ), $display_name ) );
			
			// Check if user with same display_name exists.
			$display_name  = $staff_row['post_title'];
			$existing_user = $users_helper->get_user_by_unique_identifier( $display_name );
			
			// Skip if user exists and it's not being updated.
			if ( $existing_user && ! $update_existing_users ) {
				continue;
			}
			
			/**
			 * Get userdata from CPT postmetas:
			 *  'firstname', 'lastname', 'cn_staff_title', 'cn_staff_bio', 'cn_staff_photo'.
			 */
			$post_meta_results     = $wpdb->get_results( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
				$wpdb->prepare(
					"SELECT * FROM %i
				WHERE post_id = %d 
				AND meta_key IN ('firstname', 'lastname', 'cn_staff_title', 'cn_staff_bio', 'cn_staff_photo');",
					$live_prefix . 'postmeta',
					$staff_row['ID']
				),
				ARRAY_A 
			);
			$firstname             = array_column( $post_meta_results, 'meta_value', 'meta_key' )['firstname'] ?? null;
			$lastname              = array_column( $post_meta_results, 'meta_value', 'meta_key' )['lastname'] ?? null;
			$cn_staff_title        = array_column( $post_meta_results, 'meta_value', 'meta_key' )['cn_staff_title'] ?? null;
			$cn_staff_bio          = array_column( $post_meta_results, 'meta_value', 'meta_key' )['cn_staff_bio'] ?? null;
			$cn_staff_photo_old_id = array_column( $post_meta_results, 'meta_value', 'meta_key' )['cn_staff_photo'] ?? null;

			/**
			 * Get ACF user "Contact" data -- these fields have dynamic and optional key names:
			 *  key_name: 'cn_staff_contact_0_contact_outlet', key_value: 'phone'
			 *      and the phone number is stored in a different postmeta, in meta_value where meta_key = "cn_staff_contact_{NUMBER}_social_media_handle".
			 *  key_name: 'cn_staff_contact_1_contact_outlet', key_value: 'email'
			 *      and the email is stored in a different postmeta, in meta_value where meta_key = "cn_staff_contact_{NUMBER}_social_media_handle".
			 *  key_name: 'cn_staff_contact_2_contact_outlet', key_value: 'twitter'
			 *      and the twitter handle is stored in a different postmeta, in meta_value where meta_key = "cn_staff_contact_{NUMBER}_social_media_handle".
			 *  key_name: 'cn_staff_contact_3_contact_outlet', key_value: 'linkedin'
			 *      and the linkedin handle is stored in a different postmeta, in meta_value where meta_key = "cn_staff_contact_{NUMBER}_social_media_handle".
			 *  key_name: 'cn_staff_contact_4_contact_outlet', key_value: 'facebook'
			 *      and the facebook handle is stored in a different postmeta, in meta_value where meta_key = "cn_staff_contact_{NUMBER}_social_media_handle".
			 *  key_name: 'cn_staff_contact_5_contact_outlet', key_value: 'instagram'
			 *      and the instagram handle is stored in a different postmeta, in meta_value where meta_key = "cn_staff_contact_{NUMBER}_social_media_handle".
			 */
			$contact_rows = $wpdb->get_results( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
				$wpdb->prepare(
					'SELECT * FROM %i
					WHERE post_id = %d 
					AND meta_key LIKE %s;', 
					$live_prefix . 'postmeta',
					$staff_row['ID'],
					'cn_staff_contact_%_contact_outlet'
				),
				ARRAY_A 
			);
			$phone        = null;
			$email        = null;
			$twitter      = null;
			$linkedin     = null;
			$facebook     = null;
			$instagram    = null;
			foreach ( $contact_rows as $contact_row ) {
				$contact_type = $contact_row['meta_value'];
				if ( in_array( $contact_type, [ 'phone', 'email', 'twitter', 'linkedin', 'facebook', 'instagram' ] ) ) {
					// Extract the index from the meta_key (e.g., "cn_staff_contact_1_contact_outlet" -> "1").
					preg_match( '/cn_staff_contact_(\d+)_contact_outlet/', $contact_row['meta_key'], $matches );
					if ( isset( $matches[1] ) ) {
						$index         = (int) $matches[1];
						$contact_value = $wpdb->get_var( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
							$wpdb->prepare(
								'SELECT meta_value FROM %i WHERE post_id = %d AND meta_key = %s',
								$live_prefix . 'postmeta',
								$staff_row['ID'],
								"cn_staff_contact_{$index}_social_media_handle"
							) 
						);
						
						// Assign to variable based on contact type.
						switch ( $contact_type ) {
							case 'phone':
								$phone = $contact_value;
								break;
							case 'email':
								$email = $contact_value;
								break;
							case 'twitter':
								$twitter = $contact_value;
								break;
							case 'linkedin':
								$linkedin = $contact_value;
								break;
							case 'facebook':
								$facebook = $contact_value;
								break;
							case 'instagram':
								$instagram = $contact_value;
								break;
						}
					}
				}
			}

			// Prepare all the user data.
			$user_data = [
				'display_name' => $display_name,
				'user_email'   => $email,
				'first_name'   => $firstname,
				'last_name'    => $lastname,
				'description'  => $cn_staff_bio,
				'role'         => Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME,
			];
			// Prepare all the user meta data.
			$user_meta = [
				'newspack_job_title'    => $cn_staff_title,
				'newspack_phone_number' => $phone,
				'twitter'               => $twitter,
				'linkedin'              => $linkedin,
				'facebook'              => $facebook,
				'instagram'             => $instagram,
			];

			// The user object.
			$staff_user = null;

			// Update existing user.
			if ( $existing_user ) {
				$staff_user = $existing_user;
				$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( 'User %s exists, updating user data.', $staff_row['display_name'] ) );

				$user_data_update = $user_data;
				unset( $user_data_update['display_name'] );
				$result = wp_update_user( $user_data_update );
				if ( is_wp_error( $result ) ) {
					$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::ERROR, sprintf( "ERROR Failed to update user %d, error message: '%s'. Data: %s", $existing_user->ID, $result->get_error_message(), wp_json_encode( $user_data_update ) ) );
					continue;
				}
			} else {
				// Create new user.
				try {
					$staff_user = $users_helper->create_or_get_user( $user_data, $display_name );
				} catch ( \Exception $e ) {
					$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::ERROR, sprintf( "ERROR Failed to create user %s, error message: '%s'. Data: %s", $display_name, $e->getMessage(), wp_json_encode( $user_data ) ) );
					continue;
				}
			}

			// Update user meta.
			foreach ( $user_meta as $key => $value ) {
				update_user_meta( $staff_user->ID, $key, $value );
			}

			// Assign avatar.
			if ( $cn_staff_photo_old_id ) {
				// $cn_staff_photo_new_id will either be an CDiff-migrated attachment ID (which has meta_key=ContentDiffMigrator::SAVED_META_LIVE_POST_ID and meta_value=$cn_staff_photo_old_id), or it will remain the same value (if it was directly cloned from live, and this attachment ID was not imported with CDiff and meta was not saved).
				$cn_staff_photo_new_id = $wpdb->get_var( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
					$wpdb->prepare(
						"SELECT pm.meta_value
					FROM {$wpdb->postmeta} pm
					JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					WHERE pm.post_id = %d AND pm.meta_key = %s AND p.post_type = 'attachment';",
						$cn_staff_photo_old_id,
						ContentDiffMigrator::SAVED_META_LIVE_POST_ID
					) 
				);
				if ( ! $cn_staff_photo_new_id ) {
					$cn_staff_photo_new_id = $cn_staff_photo_old_id;
				}
				
				// postmeta cn_staff_photo.
				$updated = $simple_local_avatars->assign_avatar( $staff_user->ID, $cn_staff_photo_new_id );
				if ( ! $updated ) {
					$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::ERROR, sprintf( 'ERROR Failed to assign avatar to user %d, attachment ID: %d', $staff_user->ID, $cn_staff_photo_new_id ) );
				}
			}
		}
		
		// Import Students to WP_Users.

		// Import remaining post_author meta to WP_Users.
	}

	/**
	 * Sets post authors.
	 * 
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_set_post_authors( array $pos_args, array $assoc_args ): void {
	}

	/**
	 * Log to one or both loggers based on parameters.
	 * 
	 * Basic CLI colors are:
	 *   - LogLevel::DEBUG -- no color
	 *   - LogLevel::INFO -- green
	 *   - LogLevel::WARNING -- orange
	 *   - LogLevel::ERROR -- red
	 *   and more levels and colors exist, @see \Bramus\Monolog\Formatter\ColoredLineFormatter::getColorScheme().
	 *
	 * @param string $output   Log output, allowed values self::LOG_OUTPUTS.
	 * @param string $level    \Psr\Log\LogLevel: debug, info, notice, warning, error, critical, alert, emergency.
	 * @param string $message  Log message.
	 * @param array  $context  Log context.
	 */
	private function log( string $output, string $level, string $message, array $context = [] ): void {
		// Is level "basic" -- DEBUG, INFO or NOTICE? Will not output these levels in CLI.
		$is_level_basic = in_array( $level, [ LogLevel::NOTICE, LogLevel::INFO, LogLevel::DEBUG ] );

		switch ( $output ) {
			case 'cli':
				$this->logger_cli->$level( ( $is_level_basic ? '' : $level . ': ' ) . $message, $context );
				break;
			case 'file':
				$this->logger_file->$level( $message, $context );   
				break;
			case 'cli_and_file':
				$this->logger_cli->$level( ( $is_level_basic ? '' : $level . ': ' ) . $message, $context );
				$this->logger_file->$level( $message, $context );
				break;
		}
	}

	/**
	 * Sets up the loggers.
	 *
	 * @param string|null $logger_slug        CLI and file logger slug.
	 * @param bool        $log_init_timestamp Whether to log the timestamp of the start of the loggers.
	 */
	protected function init_loggers( ?string $logger_slug = null, bool $log_init_timestamp = true ): void {
		// If logging is disabled, NullLogger have been set instances.
		if ( false === $this->enable_logging ) {
			return;
		}

		// If slug is not provided, do not initialize the loggers.
		if ( is_null( $logger_slug ) ) {
			return;
		}

		/**
		 * File logger uses no color and full timestamp.
		 */
		$formatter_file = new LineFormatter(
			'[%datetime%] %level_name%: %message% %context%' . PHP_EOL,
			'Y-m-d H:i:s.v',
			true,
			true
		);
		$logger_file    = new Logger( $logger_slug . '_file' );
		$handler_file   = new StreamHandler( $logger_slug . '.log' );
		$handler_file->setFormatter( $formatter_file );
		$logger_file->pushHandler( $handler_file );
		$this->logger_file = $logger_file;

		/**
		 * CLI logger uses color, does not output a timestamp, and does not include level_name.
		 */
		$formatter_cli = new ColoredLineFormatter(
			null,
			'%message% %context%' . PHP_EOL,
			'Y-m-d H:i:s.u',
			true,
			true
		);
		$logger_cli    = new Logger( $logger_slug . '_cli' );
		$handler_cli   = new StreamHandler( 'php://stdout' );
		$handler_cli->setFormatter( $formatter_cli );
		$logger_cli->pushHandler( $handler_cli );
		$this->logger_cli = $logger_cli;

		// If $log_init_timestamp is set, write an init logging message with a timestampto both loggers.
		if ( $log_init_timestamp ) {
			$this->log( 'cli_and_file', LogLevel::DEBUG, '[Init logging at ' . gmdate( 'Y-m-d H:i:s.u' ) . ']' );
		}
	}
}
