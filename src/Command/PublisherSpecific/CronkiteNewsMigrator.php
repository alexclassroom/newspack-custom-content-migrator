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
use Newspack\MigrationTools\Logic\Bylines;
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
	 * Bylines helper.
	 * 
	 * @var Bylines Bylines helper.
	 */
	private $bylines;

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

		$this->bylines = new Bylines();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator cronkite-news-validate-custom-authors',
			self::get_command_closure( 'cmd_validate_custom_authors' ),
			[
				'shortdesc' => 'Validates custom authors and bylines. Extracts display names and bylines from three different types of objects so that we can validate and see what we are working with, what needs parsing and cleanup, etc.',
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
			'newspack-content-migrator cronkite-news-validate-byline-parsing',
			self::get_command_closure( 'cmd_validate_byline_parsing' ),
			[
				'shortdesc' => 'Validates bylines parsing.',
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
			'newspack-content-migrator cronkite-news-import-users',
			self::get_command_closure( 'cmd_import_users' ),
			[
				'shortdesc' => 'This first command creates WP_Users from custom ACF authors and bylines.',
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
			'newspack-content-migrator cronkite-news-set-post-coauthors',
			self::get_command_closure( 'cmd_set_post_coauthors' ),
			[
				'shortdesc' => 'This command should be run after the users were created with cronkite-news-import-users, and it sets posts (co)authors.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'live-table-prefix',
						'description' => 'Live table prefix, e.g. "live_".',
						'optional'    => false,
					],
					[
						'type'        => 'flag',
						'name'        => 'update-existing-posts',
						'description' => 'Will update existing or already impported users with new data.',
						'optional'    => true,
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
	public function cmd_validate_custom_authors( array $pos_args, array $assoc_args ): void {
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

		$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, 'Done, see cmd_validator_* files.' );
	}

	/**
	 * Validates bylines parsing.
	 * 
	 * @param array $pos_args   Positional arguments from WP_CLI.
	 * @param array $assoc_args Associative arguments from WP_CLI.
	 */
	public function cmd_validate_byline_parsing( array $pos_args, array $assoc_args ): void {
		global $wpdb;

		$live_prefix = esc_sql( $assoc_args['live-table-prefix'] );

		$this->init_loggers( __FUNCTION__ );

		/**
		 * Staff CPTs.
		 */
		$post_ids_cn_staff = $this->get_post_ids_with_staff_authors( $live_prefix );
		foreach ( $post_ids_cn_staff as $key_post_id => $post_id ) {
			$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( '(%d)/(%d) %d', $key_post_id + 1, count( $post_ids_cn_staff ), $post_id ) );

			// Staff.
			$meta_value = $wpdb->get_var( $wpdb->prepare( "select meta_value from %i where post_id = %d and meta_key = 'byline_info_cn_staff';", $live_prefix . 'postmeta', $post_id ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
			$cpt_ids    = unserialize( $meta_value ); // phpcs:ignore -- WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize.
			if ( ! $cpt_ids ) {
				file_put_contents( 'bylines_staff_EMPTY.txt', $post_id . PHP_EOL, FILE_APPEND ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.       
				continue;
			}
			
			$cpt_ids_placeholders = implode( ',', array_fill( 0, count( $cpt_ids ), '%d' ) );
			// phpcs:disable -- Query fully prepared and sanitized.
			$display_names        = $wpdb->get_col( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
				$wpdb->prepare(
					"select post_title from %i where ID in ( $cpt_ids_placeholders );",
					$live_prefix . 'posts',
					...$cpt_ids
				) 
			);
			// phpcs:enable
			if ( ! $display_names ) {
				// ⚠️  These posts have incorrect bylines on live, and their authorship is presently not working on live.
				file_put_contents( 'bylines_staff_EMPTY.txt', $post_id . PHP_EOL, FILE_APPEND ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.        
				continue;
			}

			// Log parsed bylines.
			foreach ( $display_names as $display_name ) {
				file_put_contents( 'bylines_staff.txt', $display_name . PHP_EOL, FILE_APPEND ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.
			}
		}

		/**
		 * External authors.
		 */
		$post_ids_external = $this->get_post_ids_with_external_authors( $live_prefix );
		// Write headers to log file.
		$separator = '§';
		file_put_contents( // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.
			'bylines_external.txt',
			'count_authors' . $separator . 'post_id' . $separator . 'byline' . $separator . 'authors' . PHP_EOL,
		);
		foreach ( $post_ids_external as $key_post_id => $post_id ) {
			$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( '(%d)/(%d) %d', $key_post_id + 1, count( $post_ids_external ), $post_id ) );

			$byline = $wpdb->get_var( $wpdb->prepare( "select meta_value from %i where post_id = %d and meta_key = 'byline_info_external_authors_repeater_0_external_authors';", $live_prefix . 'postmeta', $post_id ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
			$byline = $byline ? trim( $byline ) : $byline;

			$display_names = $this->parse_external_byline( $byline );
			if ( ! $display_names ) {
				file_put_contents( 'bylines_external_EMPTY.txt', $post_id . ',' . $byline . PHP_EOL, FILE_APPEND ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.
				continue;
			}

			// Log parsed bylines.
			file_put_contents( // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.
				'bylines_external.txt',
				sprintf(
					'%d%s%d%s%s%s%s' . PHP_EOL,
					count( $display_names ),
					$separator,
					$post_id,
					$separator,
					$byline,
					$separator,
					implode( ' | ', $display_names )
				),
				FILE_APPEND
			);
		}

		/**
		 * Post_author meta.
		 */
		$post_ids_post_author = $this->get_post_ids_with_postauthor_metas( $live_prefix );
		// Write headers to log file.
		$separator = '§';
		file_put_contents( // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.
			'bylines_postauthor.txt',
			'count_authors' . $separator . 'post_id' . $separator . 'byline' . $separator . 'authors' . PHP_EOL,
		);
		foreach ( $post_ids_post_author as $key_post_id => $post_id ) {
			$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( '(%d)/(%d) %d', $key_post_id + 1, count( $post_ids_post_author ), $post_id ) );

			$byline = $wpdb->get_var( $wpdb->prepare( "select meta_value from %i where post_id = %d and meta_key = 'post_author';", $live_prefix . 'postmeta', $post_id ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
			$byline = $byline ? trim( $byline ) : $byline;
			if ( ! $byline ) {
				file_put_contents( 'bylines_postauthor_EMPTY.txt', $post_id . PHP_EOL, FILE_APPEND ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.
				continue;
			}

			$display_names = $this->parse_post_author_byline( $byline );
			if ( ! $display_names ) {
				file_put_contents( 'bylines_postauthor_EMPTY.txt', $post_id . PHP_EOL, FILE_APPEND ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.
				continue;
			}

			// Log parsed bylines.
			file_put_contents( // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents.
				'bylines_postauthor.txt',
				sprintf(
					'%d%s%d%s%s%s%s' . PHP_EOL,
					count( $display_names ),
					$separator,
					$post_id,
					$separator,
					$byline,
					$separator,
					implode( ' | ', $display_names )
				),
				FILE_APPEND
			);
		}

		$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, 'Done, see bylines_*.txt files.' );
	}

	/**
	 * Imports CPT authors and sets post (co)authors.
	 * 
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_import_users( array $pos_args, array $assoc_args ): void {
		global $wpdb;
		
		$live_prefix           = esc_sql( $assoc_args['live-table-prefix'] );
		$update_existing_users = $assoc_args['update-existing-users'] ?? false;

		$this->init_loggers( __FUNCTION__ );
		$users_helper         = new UsersHelper();
		$simple_local_avatars = new SimpleLocalAvatars();


		// Validate if there are WP_Users with duplicate display_names on local.
		// phpcs:disable -- WordPress.DB.DirectDatabaseQuery.NoCaching, WordPressVIPMinimum.DB.RestrictedSQL.DirectDBCall, WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users.
		$wp_users_duplicates = $wpdb->get_results(
			"SELECT ID, display_name FROM {$wpdb->users} WHERE display_name IN (
				SELECT display_name FROM {$wpdb->users} GROUP BY display_name HAVING COUNT(*) > 1
			)
			ORDER BY display_name, ID;;",
			ARRAY_A
		);
		// phpcs:enable
		if ( count( $wp_users_duplicates ) > 0 ) {
			$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::ERROR, 'The following WP_Users have duplicate display_names. Please merge these users before proceeding:' );
			foreach ( $wp_users_duplicates as $wp_user_duplicate ) {
				$role_string = get_user_meta( $wp_user_duplicate['ID'], 'wp_capabilities', true );
				$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( 'ID: %d, display_name: %s, capabilities: %s', $wp_user_duplicate['ID'], $wp_user_duplicate['display_name'], wp_json_encode( $role_string ) ) );
			}
			exit;
		}

		
		/**
		 * Begin by setting migration UIDs on all existing local users with value of WP_User display_name, because display_name is used as a unique identifier for all authors.
		 */
		$wp_users_results = $wpdb->get_results( "SELECT ID, display_name FROM {$wpdb->users}", ARRAY_A ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
		$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( 'Setting WP_User UID for %d existing local WP_Users...', count( $wp_users_results ) ) );
		foreach ( $wp_users_results as $key_wp_user_result => $wp_user_result ) {
			$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( 'Setting WP_User UID (%d)/(%d) %d', $key_wp_user_result + 1, count( $wp_users_results ), $wp_user_result['ID'] ) );
			update_user_meta( $wp_user_result['ID'], UsersHelper::UNIQUE_IDENTIFIER_META_KEY, $wp_user_result['display_name'], true );
		}


		// Log file.
		$log       = 'import_users.csv';
		$separator = '§';
		if ( file_exists( $log ) ) {
			unlink( $log ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink.
		}
		$log_handle = fopen( $log, 'a' ); // phpcs:ignore -- WordPress.WP.AlternativeFunctions.file_system_operations_fopen.
		/**
		 * Headers:
		 * user_id -- imported or existing user ID.
		 * status -- 'imported' or 'existing'.
		 * type -- 'cn_staff', 'students' or 'postauthor'.
		 * cpt_post_id -- if 'cn_staff' or 'students', post ID of that CPT.
		 * postauthor_post_id -- if 'postauthor', post ID of the post which uses that post_author meta.
		 * postauthor_byline -- if 'postauthor', the post_author meta byline.
		 * postauthor_parsed_display_name -- if 'postauthor', parsed display name from the post_author meta byline.
		 */
		fwrite( $log_handle, 'user_id' . $separator . 'status' . $separator . 'type' . $separator . 'cpt_post_id' . $separator . 'postauthor_post_id' . $separator . 'postauthor_byline' . $separator . 'postauthor_parsed_display_name' . PHP_EOL ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite.

		/**
		 * Import Staff and Students from live DB to local WP_Users.
		 */
		$cpts = [ 'cn_staff', 'students' ];
		// Map CPT postmeta keys.
		$cpts_metas = [
			'cn_staff' => [
				'meta_firstname' => 'firstname',
				'meta_lastname'  => 'lastname',
				'meta_bio'       => 'cn_staff_bio',
				'meta_job_title' => 'cn_staff_title',
				'meta_avatar_id' => 'cn_staff_photo',
			],
			'students' => [
				'meta_firstname' => 'firstname',
				'meta_lastname'  => 'lastname',
				'meta_bio'       => 'biography',
				'meta_job_title' => 'role',
				'meta_avatar_id' => 'student_photo',
			],
		];
		foreach ( $cpts as $cpt ) {
			$cpt_rows = $wpdb->get_results( $wpdb->prepare( "select * from %i where post_type = %s;", $live_prefix . 'posts', $cpt ), ARRAY_A ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
			foreach ( $cpt_rows as $key_cpt_row => $cpt_row ) {
				if ( $key_cpt_row + 1 > 2 ) {
					continue; }
				MemoryCleanupHook::cleanup( 0, $key_cpt_row + 1, 20 );

				$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( "[%s] (%d)/(%d) '%s'", $cpt, $key_cpt_row + 1, count( $cpt_rows ), $cpt_row['post_title'] ) );

				$display_name = $cpt_row['post_title'];
				if ( empty( $display_name ) ) {
					$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( 'Skipping [%s] -- empty display_name for post ID %d.', $cpt, $cpt_row['ID'] ) );
					continue;
				}

				// Check if user with same display_name exists.
				$existing_user = $users_helper->get_user_by_unique_identifier( $display_name );

				// Skip existing user if it's not being updated.
				if ( $existing_user && ! $update_existing_users ) {
					$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( "Skipping [%s] -- user '%s' exists.", $cpt, $display_name ) );
					continue;
				}
				
				/**
				 * Get userdata from postmetas.
				 */
				$meta_key_placeholders = implode( ',', array_fill( 0, count( $cpts_metas[ $cpt ] ), '%s' ) );
				// phpcs:disable -- Query fully prepared and sanitized.
				$post_meta_results     = $wpdb->get_results( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
					$wpdb->prepare(
						"SELECT * FROM %i
						WHERE post_id = %d 
						AND meta_key IN ( $meta_key_placeholders );",
						$live_prefix . 'postmeta',
						$cpt_row['ID'],
						...$cpts_metas[ $cpt ]
					),
					ARRAY_A 
				);
				// phpcs:enable
				$firstname     = array_column( $post_meta_results, 'meta_value', 'meta_key' )[ $cpts_metas[ $cpt ]['meta_firstname'] ] ?? null;
				$lastname      = array_column( $post_meta_results, 'meta_value', 'meta_key' )[ $cpts_metas[ $cpt ]['meta_lastname'] ] ?? null;
				$bio           = array_column( $post_meta_results, 'meta_value', 'meta_key' )[ $cpts_metas[ $cpt ]['meta_bio'] ] ?? null;
				$job_title     = array_column( $post_meta_results, 'meta_value', 'meta_key' )[ $cpts_metas[ $cpt ]['meta_job_title'] ] ?? null;
				$avatar_id_old = array_column( $post_meta_results, 'meta_value', 'meta_key' )[ $cpts_metas[ $cpt ]['meta_avatar_id'] ] ?? null;

				// Get contact data -- also postmetas, but this is a separate form in User editing screen with differently organized structure.
				$contact_data = $this->get_acf_user_contact_data( $cpt_row['ID'], $live_prefix );

				// Prepare all the user data -- for wp_update_user() or wp_insert_user().
				$user_data = [
					'display_name' => $display_name,
					'user_email'   => $contact_data['email'],
					'first_name'   => $firstname,
					'last_name'    => $lastname,
					'description'  => $bio,
					'role'         => Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME,
				];
				// Prepare all the user meta data.
				$user_meta = [
					'old_post_type'         => $cpt,
					'newspack_job_title'    => $job_title,
					'newspack_phone_number' => $contact_data['phone'],
					'twitter'               => $contact_data['twitter'],
					'linkedin'              => $contact_data['linkedin'],
					'facebook'              => $contact_data['facebook'],
					'instagram'             => $contact_data['instagram'],
				];

				// The user object.
				$user = null;

				// Update existing user (we already skipped/continued if $update_existing_users was false).
				if ( $existing_user ) {
					$user = $existing_user;

					$user_data_update       = $user_data;
					$user_data_update['ID'] = $existing_user->ID;
					unset( $user_data_update['display_name'] );
					$result = wp_update_user( $user_data_update );
					if ( is_wp_error( $result ) ) {
						$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::ERROR, sprintf( "ERROR [%s] -- failed to update existing user ID %d, error message: '%s'. User data: %s", $cpt, $existing_user->ID, $result->get_error_message(), wp_json_encode( $user_data_update ) ) );
						continue;
					}
				} else {
					// Create new user.
					try {
						$user = $users_helper->create_or_get_user( $user_data, $display_name );
					} catch ( \Exception $e ) {
						$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::ERROR, sprintf( "ERROR [%s] -- failed to create user %s, error message: '%s'. Data: %s", $cpt, $display_name, $e->getMessage(), wp_json_encode( $user_data ) ) );
						continue;
					}
				}

				// Update user meta.
				foreach ( $user_meta as $key => $value ) {
					update_user_meta( $user->ID, $key, $value );
				}

				// Set avatar.
				if ( $avatar_id_old ) {
					/**
					 * $avatar_id_new will either be a CDiff-migrated attachment ID (which has meta_key=ContentDiffMigrator::SAVED_META_LIVE_POST_ID
					 * and meta_value=$avatar_id_old), or new will remain the same value as old (if it was directly cloned from live, the attachment ID
					 * was not changed/imported with CDiff, and old-ID-meta doesn't exist).
					 */
					$avatar_id_new = $wpdb->get_var( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
						$wpdb->prepare(
							"SELECT pm.meta_value
						FROM {$wpdb->postmeta} pm
						JOIN {$wpdb->posts} p ON pm.post_id = p.ID
						WHERE pm.post_id = %d AND pm.meta_key = %s AND p.post_type = 'attachment';",
							$avatar_id_old,
							ContentDiffMigrator::SAVED_META_LIVE_POST_ID
						) 
					);
					if ( ! $avatar_id_new ) {
						$avatar_id_new = $avatar_id_old;
					}
					
					// postmeta cn_staff_photo.
					$updated = $simple_local_avatars->assign_avatar( $user->ID, $avatar_id_new );
					if ( ! $updated ) {
						$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::ERROR, sprintf( "ERROR [%s] -- failed to assign avatar to user ID '%d', attachment ID new: '%d' (attachment ID old: '%d')", $cpt, $user->ID, $avatar_id_new, $avatar_id_old ) );
						$debug = 1;
					}
				}

				// CSV log success.
				fwrite( // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite.
					$log_handle,
					sprintf(
						'%s%s' . '%s%s' . '%s%s' . '%s%s' . '%s%s' . '%s%s' . '%s', // phpcs:ignore -- Allow for readability. Generic.Strings.UnnecessaryStringConcat.Found.
						// user_id.
						$user->ID,
						$separator,
						// status.
						$existing_user ? 'existing' : 'imported',
						$separator,
						// type.
						$cpt,
						$separator,
						// cpt_post_id.
						$cpt_row['ID'],
						$separator,
						// postauthor_post_id.
						'',
						$separator,
						// postauthor_byline.
						'',
						$separator,
						// postauthor_parsed_display_name.
						''
					) . PHP_EOL 
				);

				$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( "Success [%s] -- user '%s' %s", $cpt, $display_name, $existing_user ? 'updated' : 'imported' ) );
			}
		}

		/**
		 * Import users from post_author postmetas.
		 */
		$post_ids_post_author = $this->get_post_ids_with_postauthor_metas( $live_prefix );
		foreach ( $post_ids_post_author as $key_post_id => $post_id ) {
			$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( '[post_author] (%d)/(%d) %d', $key_post_id + 1, count( $post_ids_post_author ), $post_id ) );

			// Get byline.
			$byline = $wpdb->get_var( $wpdb->prepare( "select meta_value from %i where post_id = %d and meta_key = 'post_author';", $live_prefix . 'postmeta', $post_id ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
			$byline = $byline ? trim( $byline ) : $byline;
			if ( ! $byline ) {
				$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( "Warning [post_author] -- no byline found for 'post_author' meta in post ID %d, skipping.", $post_id ) );
				continue;
			}

			// Parse byline.
			$display_names = $this->parse_post_author_byline( $byline );
			if ( ! $display_names ) {
				$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( "Warning [post_author] -- no display names found for 'post_author' meta in byline '%s', skipping post ID %d.", $byline, $post_id ) );
				continue;
			}

			// Create users.
			foreach ( $display_names as $display_name ) {

				// Check if user with same display_name exists.
				$existing_user = $users_helper->get_user_by_unique_identifier( $display_name );

				// Skip existing user if it's not being updated.
				if ( $existing_user && ! $update_existing_users ) {
					$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( "Skipping [post_author] -- user '%s' exists.", $display_name ) );
					continue;
				}

				$user_data = [
					'display_name' => $display_name,
					'role'         => Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME,
				];
				if ( $existing_user ) {
					// Update existing user.
					$user_data_update       = $user_data;
					$user_data_update['ID'] = $existing_user->ID;
					unset( $user_data_update['display_name'] );
					$result = wp_update_user( $user_data_update );
					if ( is_wp_error( $result ) ) {
						$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::ERROR, sprintf( "ERROR [post_author], failed to update existing user ID %d, error message: '%s'. User data: %s", $existing_user->ID, $result->get_error_message(), wp_json_encode( $user_data_update ) ) );
						continue;
					}
				} else {
					// Create new user.
					$user = $users_helper->create_or_get_user( $user_data, $display_name );
					if ( ! $user ) {
						$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::ERROR, sprintf( "ERROR [post_author], failed to create user '%s', error message: '%s'. Data: %s", $display_name, $e->getMessage(), wp_json_encode( $user_data ) ) );
						continue;
					}
				}

				// CSV log success.
				fwrite( // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite.
					$log_handle,
					sprintf(
						'%s%s' . '%s%s' . '%s%s' . '%s%s' . '%s%s' . '%s%s' . '%s', // phpcs:ignore -- Allow for readability. Generic.Strings.UnnecessaryStringConcat.Found.
						// user_id.
						$user->ID,
						$separator,
						// status.
						$existing_user ? 'existing' : 'imported',
						$separator,
						// type.
						'',
						$separator,
						// cpt_post_id.
						'',
						$separator,
						// postauthor_post_id.
						$post_id,
						$separator,
						// postauthor_byline.
						$byline,
						$separator,
						// postauthor_parsed_display_name.
						$display_name
					) . PHP_EOL 
				);


				$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::DEBUG, sprintf( "Success [post_author] -- user '%s' %s.", $display_name, $existing_user ? 'updated' : 'imported' ) );
			}
		}

		fclose( $log_handle );

		// Done.
		$this->log( self::LOG_OUTPUTS['CLI'], LogLevel::INFO, 'Done, see ' . $log . ' for details.' );
	}

	/**
	 * Sets post (co)authors.
	 * 
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function cmd_set_post_coauthors( array $pos_args, array $assoc_args ): void {
		global $wpdb;
		
		$live_prefix           = esc_sql( $assoc_args['live-table-prefix'] );
		$update_existing_posts = $assoc_args['update-existing-posts'] ?? false;
		
		/**
		 * Loop over all post IDs, create users from custom author objects and bylines.
		 */
		$post_ids = $wpdb->get_col( $wpdb->prepare( "select ID from %i where post_type = 'post';", $live_prefix . 'posts' ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
		MemoryCleanupHook::cleanup();

		// Get post IDs which use different types of authors.
		$post_ids_with_staff_authors    = $this->get_post_ids_with_staff_authors( $live_prefix );
		$post_ids_with_external_authors = $this->get_post_ids_with_external_authors( $live_prefix );
		$post_ids_with_post_authors     = $this->get_post_ids_with_postauthor_metas( $live_prefix );

		foreach ( $post_ids as $post_id ) {
			$has_staff_authors    = in_array( $post_id, $post_ids_with_staff_authors, true );
			$has_external_authors = in_array( $post_id, $post_ids_with_external_authors, true );
			$has_post_authors     = in_array( $post_id, $post_ids_with_post_authors, true );
			
			$authors = [];
			// Assign coauthors to posts.
			if ( $has_staff_authors ) {
				
			} elseif ( $has_external_authors ) {

				// --------------------------
					$byline = $wpdb->get_var( $wpdb->prepare( "select meta_value from %i where post_id = %d and meta_key = 'byline_info_external_authors_repeater_0_external_authors';", $live_prefix . 'postmeta', $post_id ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
					$byline        = $byline ? trim( $byline ) : $byline;
					$display_names = $this->parse_external_byline( $byline );
				// --------------------------

				
			} elseif ( $has_post_authors ) {

				// TODO
				
			} else {
				// Log the posts which don't use authors via Staff, External, or post_author postmeta.
				continue;
			}

			// Set post (co)authors.

		}
	}

	/**
	 * Parses CPT external authors byline.
	 * 
	 * @param string $byline Byline.
	 * @return array Author names.
	 */
	private function parse_external_byline( string $byline ): array {
		$author_names = $this->bylines->parse_byline(
			$byline,
			[ '&', ', and ', ',', ' and ', ' y ' ],
			[
				// Skip this ' and '.
				'Cronkite School of Journalism and Mass Communication' => [
					'Cronkite School of Journalism and Mass Communication',
				],
				// Skip this ',' in ', Jr'.
				'James Brown, Jr, Olivia Jennings and Layla Brown-Clark' => [
					'James Brown, Jr',
					'Olivia Jennings',
					'Layla Brown-Clark',
				],
				// Skip this ',' in ', Jr.'.
				'James Brown, Jr., Nathan Collins and Taylor Stevens' => [
					'James Brown, Jr.',
					'Nathan Collins',
					'Taylor Stevens',
				],
				// Skip this ',' in ', Jr.'.
				'Reagan Creamer, Tirzah Christopher, James Doyle Brown, Jr. and Caralin Nunes' => [
					'Reagan Creamer',
					'Tirzah Christopher',
					'James Doyle Brown, Jr.',
					'Caralin Nunes',
				],
				// Two exceptions with HTML for bylines.
				'<iframe width="100%" height="166" scrolling="no" frameborder="no" allow="autoplay" src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/718739407&color=%23ff5500&auto_play=false&hide_related=true&show_comments=false&show_user=false&show_reposts=false&show_teaser=false"></iframe>' => [
					'Staff',
				],
				'<a href="https://cronkitenews.azpbs.org/people/alexis-waiss/" target="_blank">Alexis Waiss</a>' => [
					'Alexis Waiss',
				],
			],
			[ 'by ' ],
		);

		return $author_names;
	}

	/**
	 * Parses post_author postmeta byline.
	 * 
	 * @param string $byline Byline.
	 * @return array Author names.
	 */
	private function parse_post_author_byline( string $byline ): array {
		$author_names = $this->bylines->parse_byline(
			$byline,
			[ '&', ', and ', ',', ' and ', ' y ' ],
			[
				'Story by Chris McCrory, Visuals by Nicole Neri' => [
					'Chris McCrory',
					'Nicole Neri',
				],
			],
			[ 'by ', 'Story and photos by ', 'Story by ' ],
			[ '| Cronkite News', ', Cronkite News', '/Cronkite News' ],
		);

		return $author_names;
	}

	/**
	 * Gets post IDs which use Staff CPT authors.
	 * 
	 * @param string $live_prefix Live table prefix.
	 * @return array Post IDs.
	 */
	private function get_post_ids_with_staff_authors( string $live_prefix ): array {
		global $wpdb;
		$post_ids_cn_staff = $wpdb->get_col( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
			$wpdb->prepare(
				"select
			distinct lpm2.post_id
			from %i lpm2
			join %i lp2 on lp2.ID = lpm2.post_id
			where lp2.post_type = 'post'
			and lpm2.meta_key = 'byline_info_cn_staff'
			and lpm2.meta_value <> ''",
				$live_prefix . 'postmeta',
				$live_prefix . 'posts'
			) 
		);

		MemoryCleanupHook::cleanup();

		return $post_ids_cn_staff;
	}

	/**
	 * Gets post IDs which use External CPT authors.
	 * 
	 * @param string $live_prefix Live table prefix.
	 * @return array Post IDs.
	 */
	private function get_post_ids_with_external_authors( string $live_prefix ): array {
		global $wpdb;
		$post_ids_external = $wpdb->get_col( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
			$wpdb->prepare(
				"select distinct lpm3.post_id
			from %i lpm3
			join %i lp3 on lp3.ID = lpm3.post_id
			where lp3.post_type = 'post'
			and lpm3.meta_key = 'byline_info_external_authors_repeater_0_external_authors'
			and lpm3.meta_value <> ''
			and lpm3.post_id not in (
				select
				distinct lpm2.post_id
				from %i lpm2
				join %i lp2 on lp2.ID = lpm2.post_id
				where lp2.post_type = 'post'
				and lpm2.meta_key = 'byline_info_cn_staff'
				and lpm2.meta_value <> ''
			);",
				$live_prefix . 'postmeta',
				$live_prefix . 'posts',
				$live_prefix . 'postmeta',
				$live_prefix . 'posts'
			) 
		);

		MemoryCleanupHook::cleanup();

		return $post_ids_external;
	}

	/**
	 * Gets post IDs which use post_author postmeta authors.
	 * 
	 * @param string $live_prefix Live table prefix.
	 * @return array Post IDs.
	 */
	private function get_post_ids_with_postauthor_metas( string $live_prefix ): array {
		global $wpdb;
		$post_ids_post_author = $wpdb->get_col( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
			$wpdb->prepare(
				"select
			distinct lpm.post_id
			from %i lpm
			join %i lp on lp.ID = lpm.post_id
			where lp.post_type = 'post'
			and lpm.meta_key = 'post_author'
			and lpm.meta_value is not null and lpm.meta_value <> ''
			and lpm.post_id not in (
				-- Exclude posts with 'byline_info_cn_staff' meta.
				select
				distinct lpm2.post_id
				from %i lpm2
				join %i lp2 on lp2.ID = lpm2.post_id
				where lp2.post_type = 'post'
				and lpm2.meta_key = 'byline_info_cn_staff'
				and lpm2.meta_value <> ''
			) and lpm.post_id not in (
				-- Exclude posts with 'byline_info_external_authors_repeater_0_external_authors' meta.
				select
				distinct lpm3.post_id
				from %i lpm3
				join %i lp3 on lp3.ID = lpm3.post_id
				where lp3.post_type = 'post'
				and lpm3.meta_key = 'byline_info_external_authors_repeater_0_external_authors'
				and lpm3.meta_value <> ''
			);",
				$live_prefix . 'postmeta',
				$live_prefix . 'posts',
				$live_prefix . 'postmeta',
				$live_prefix . 'posts',
				$live_prefix . 'postmeta',
				$live_prefix . 'posts'
			) 
		);

		MemoryCleanupHook::cleanup();

		return $post_ids_post_author;
	}

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
	 * 
	 * @param int    $cpt_post_id   The ID of the CPT post.
	 * @param string $live_prefix   The prefix of the live database.
	 * @return array The contact data {
	 *  'phone' => string,
	 *  'email' => string,
	 *  'twitter' => string,
	 *  'linkedin' => string,
	 *  'facebook' => string,
	 *  'instagram' => string,
	 * }.
	 */
	private function get_acf_user_contact_data( int $cpt_post_id, string $live_prefix ): array {
		global $wpdb;

		$contact_data = [
			'phone'     => null,
			'email'     => null,
			'twitter'   => null,
			'linkedin'  => null,
			'facebook'  => null,
			'instagram' => null,
		];

		$contact_rows = $wpdb->get_results( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
			$wpdb->prepare(
				'SELECT * FROM %i
				WHERE post_id = %d 
				AND meta_key LIKE %s;', 
				$live_prefix . 'postmeta',
				$cpt_post_id,
				'cn_staff_contact_%_contact_outlet'
			),
			ARRAY_A 
		);
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
							$cpt_post_id,
							"cn_staff_contact_{$index}_social_media_handle"
						) 
					);
					
					// Assign to variable based on contact type.
					switch ( $contact_type ) {
						case 'phone':
							$contact_data['phone'] = $contact_value;
							break;
						case 'email':
							$contact_data['email'] = $contact_value;
							break;
						case 'twitter':
							$contact_data['twitter'] = $contact_value;
							break;
						case 'linkedin':
							$contact_data['linkedin'] = $contact_value;
							break;
						case 'facebook':
							$contact_data['facebook'] = $contact_value;
							break;
						case 'instagram':
							$contact_data['instagram'] = $contact_value;
							break;
					}
				}
			}
		}

		return $contact_data;
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
