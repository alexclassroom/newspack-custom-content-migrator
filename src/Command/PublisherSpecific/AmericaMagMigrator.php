<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\Guest_Contributor_Role;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Posts;
use Newspack\MigrationTools\Util\FgHelper;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;
use WP_Error;

// use Newspack\MigrationTools\Logic\GuestContributorsHelper;
use NewspackCustomContentMigrator\Command\PublisherSpecific\AmericaMagMigratorTempGC as GuestContributorsHelper;


class AmericaMagMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	const META_KEY_PROFILE_POST_ID = '_np_migration_profile_post_id';

	/**
	 * Batch counts of imported nodes per type per CLI run.
	 *
	 * @var array
	 */
	private array $batch_counts;

	/**
	 * Batch max of imported nodes per type per CLI run.
	 *
	 * @var int
	 */
	private int $batch_max = 5;

	/**
	 * Custom Fields holds the related field definitions that are attached to nodes.
	 *
	 * @var array
	 */
	private array $custom_fields;

	/**
	 * FG Helper for simplification.
	 *
	 * @var FgHelper FG Helper instance.
	 */
	private FgHelper $fg_helper;

	/**
	 * Logger
	 *
	 * @var MultiLog
	 */
	private $logger;

	/**
	 * Required timezone setting.
	 *
	 * @var string
	 */
	private string $required_timezone = 'America/New_York';

	/**
	 * CLI Commands
	 *
	 * @return void
	 */
	public static function register_commands(): void {

		WP_CLI::add_command(
			'newspack-content-migrator america-mag-co-authors',
			self::get_command_closure( 'cmd_co_authors' ),
			[
				'shortdesc' => 'Set co-authors per post.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator america-mag-examine-db',
			self::get_command_closure( 'cmd_examine_db' ),
			[
				'shortdesc' => 'Examine the db tables.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator america-mag-import',
			self::get_command_closure( 'cmd_import' ),
			[
				'shortdesc' => 'America Mag Importer',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'batch-max',
						'description' => 'Max nodes to import (per type). Integer. Default: 5',
						'optional'    => true,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator america-mag-profiles',
			self::get_command_closure( 'cmd_profiles' ),
			[
				'shortdesc' => 'America Mag Profiles (to guest contributors)',
			]
		);
	}

	/**
	 * Run co-authors per post.
	 */
	public function cmd_co_authors( array $pos_args, array $assoc_args ): void {

		$this->logger_set( __FUNCTION__ );
		$this->logger->info( 'Running command: ' . __FUNCTION__ );

		// CAP Plugin is required.
		if ( ! is_plugin_active( "co-authors-plus/co-authors-plus.php" ) ) {
			$this->logger->error( 'Co-Authors Plus plugin not found. Install and activate it before using this command.' );
			exit();
		}
		
		global $coauthors_plus;

		// Loop through all posts.
		(new Posts())->throttled_posts_loop( 
			[], 
			function( $post ) use ( $coauthors_plus ) {
				
				$this->logger->info( '-- Post ID: ' . $post->ID );

				if ( $coauthors_plus->has_author_terms( $post->ID ) ) {
					$this->logger->notice( 'Authors already set.' );
					return;
				}

				// Migrated author list points to profile post type.
				$by_author = get_post_meta( $post->ID, 'by_author', true );
				if ( empty( $by_author ) ) {
					$this->logger->warning( 'Skip: No by_author value.' );
					return;
				}
				if ( ! is_array( $by_author ) ) {
					$this->logger->warning( 'Skip: by_author value is not array.' );
					return;
				}

				// The migration seemed to insert the same profile post id multiple times.
				// - keep order so "first" author is still "first" in byline.
				$by_author = array_unique( $by_author );

				// Match each profile post id to user meta id
				$co_authors = [];
				foreach( $by_author as $profile_post_id ) {

					$this->logger->info( 'Profile post id: ' . $profile_post_id );

					$users = get_users([
						'meta_key' => self::META_KEY_PROFILE_POST_ID,
   						'meta_value' => $profile_post_id,
						'fields' => 'ids',
					]);
					if ( empty( $users ) ) {
						$this->logger->warning( 'Skip: No user matched to meta.' );
						return;
					}
					if ( 1 !==  count( $users ) ) {
						$this->logger->warning( 'Skip: user match count <> 1.' );
						return;
					}

					// Get, print, and add user_id to co authors
					$user_id = reset( $users );
					$this->logger->info( 'User ID is: ' . $user_id );
					$co_authors[] = $user_id;
				}

				// Add co-authors to post
				$this->logger->info( 'co-authors (wp_users): ' . implode( ",", $co_authors ) );
				
				if ( ! $coauthors_plus->add_coauthors( $post->ID, $co_authors, false, 'id' ) ) {
					$this->logger->warning( 'Skip: unable to assign co-authors.' );
					return;
				}

			} // callback function
		); // throttled posts

		$this->logger->info( 'Done.' ); 
	}

	/**
	 * Examine the db.
	 */
	public function cmd_examine_db( array $pos_args, array $assoc_args ): void {

		$this->logger_set( __FUNCTION__ );
		$this->logger->info( 'Running command: ' . __FUNCTION__ );
		$this->logger->notice( 'Drupal tables must be in same DB as WordPress tables.' );

		// List all tables.
		global $wpdb;
		$db_tables = $wpdb->get_col( "SHOW TABLES" );
		if ( empty( $db_tables ) ) {
			$this->logger->error( 'No tables found' );
			exit();
		}

		// filter out tables.
		$filter_tables = [
			'am_legacy',
			'ban_ip',
			'batch',
			'block_content_field_revision', // revisions.
			'block_content_r__', // 'old, corrupted, limited data',
			'block_content_revision', // 'revisions',
			'cache',
			'comment', // probably not migrate.
			'contact', // contact form entries.
			'field_deleted_',
			'flag',
			'flood',
			'gdocs',
			'history',
			'node__body_', // 'backups'
			'node_access',
			'node_counter',
			'node_field_revision',
			'node_revision',
			'old_',
			'paragraph_r__',
			'paragraph_revision',
			'paragraphs_item_revision',
			'path_alias_revision',
			'queue',
			'search',
			'semaphore',
			'sequences',
			'sessions',
			'shortcut',
			'taxonomy_term_field_revision',
			'taxonomy_term_r__04be4e5c72',
			'taxonomy_term_revision',
			'watchdog',
			'webform',
			'wp_',
			'z_',
		];
		foreach( $filter_tables as $prefix ) {
			$db_tables = array_filter( $db_tables, function( $table ) use( $prefix ) {
				return ! preg_match( '/^' . $prefix . '/', $table );
			} );
			// $this->logger->info( 'Filtered ' . $prefix );
		}

		foreach( $db_tables as $table ) {
			$this->logger->info( 'Drupal table: ' . $table );
			
		}
		
	}

	/**
	 * Run the import.
	 */
	public function cmd_import( array $pos_args, array $assoc_args ): void {

		$this->logger_set( __FUNCTION__ );
		$this->logger->info( 'Running command: ' . __FUNCTION__ );

		if ( isset( $assoc_args['batch-max'] ) ) $this->batch_max = (int) $assoc_args['batch-max'];
		
		// Verify America/New_York (eastern / utc-4 timezone):
		if( wp_timezone_string() !== $this->required_timezone ) {
			// if we want to set this programatically (via wp_cli::confirm [yes/no]), the DB must match:
			//   timezone_string	America/New_York	auto
			//   gmt_offset		(null)				on|auto (?)
			// so just force this to be done by-hand in wp-admin instead.  exit if not set.
			$this->logger->error( 'WP-admin > settings > timezone must be set to: ' . $this->required_timezone );
			exit();
		}

		// Setup FG plugin's filters.
		// add_filter( 'fgd2wp_get_node_types',           [ $this, 'fgd2wp_get_node_types' ], 11, 1 );
		add_filter( 'fgd2wp_get_nodes_sql',               [ $this, 'fgd2wp_get_nodes_sql' ], 10, 6 );
		add_filter( 'fgd2wp_map_taxonomy',                [ $this, 'fgd2wp_map_taxonomy' ], 11, 3 );
		add_filter( 'fgd2wp_post_import_post',            [ $this, 'fgd2wp_post_import_post' ], 10, 5 );
		add_action( 'fgd2wp_post_register_custom_fields', [ $this, 'fgd2wp_post_register_custom_fields' ] );
		add_filter( 'fgd2wp_pre_insert_post',             [ $this, 'fgd2wp_pre_insert_post' ], 10, 2 );
		add_filter( 'fgd2wp_pre_insert_taxonomy_term',    [ $this, 'fgd2wp_pre_insert_taxonomy_term' ], 10, 3);
		add_filter( 'fgd2wp_pre_register_post_type',      [ $this, 'fgd2wp_pre_register_post_type' ], 11, 3 );

		// Premium filters. Note the extra "p" in hook name.
		// add_filter( 'fgd2wpp_get_users_sql',          [ $this, 'fgd2wpp_get_users_sql' ], 10, 2 );
		add_filter( 'fgd2wpp_post_init_premium_options', [ $this, 'fgd2wpp_post_init_premium_options' ] );
	
		// Filter DB options.
		add_filter( "option_fgd2wp_options",             [ $this, 'option_fgd2wp_options' ], 11 );
		add_filter( "default_option_fgd2wp_options",     [ $this, 'option_fgd2wp_options' ], 11 );		
		
		// Call NMT's migrator with cms type. Contructor will verify that FG plugin is installed.
		$this->fg_helper = new FgHelper( 'drupal' );

		// Other checks that constructor doesn't check.
		if ( ! defined( 'NCCM_FG_MIGRATOR_PREFIX' ) ) {
			$this->logger->error( 'NCCM_FG_MIGRATOR_PREFIX is not defined in wp-config.php' );
			exit();
		}

		// Verify the FG Drupal "Entity Reference" add-on is active.
		if ( ! is_plugin_active( "fg-drupal-to-wp-premium-entityreference-module/fg-drupal-to-wp-entityreference.php" ) ) {
			$this->logger->error( 'FG Drupal Entity Refernce Add-on plugin not found. Install and activate it before using this class.' );
			exit();
		}
		
		// Do the import.
		$this->fg_helper->import( $pos_args, $assoc_args );
	}

	/**
	 * Run command Profiles to guest contributor.
	 * 			
	 * Note: profile post's post_name is imported from Drupal (it's not just the sanitized post_title).
	 * Instead it will match to the old_url in fg redirects. We could try to use this as the 
	 * "user_nicename" (url slug) so that redirect are easier, or we can re-sanitized the wordpress
	 * way. Hmm...we're doing to have to do redirects anyway, and wp_fg_redirects has the old_urls
	 * and the P2 Guest Contributors standization project recommends to have nice urls so that CAP
	 * co-authors taxonomies match to the user better, so let's build from the pretty post_title instead of 
	 * using the old post_name from drupal.
	 * 
	 */
	public function cmd_profiles( array $pos_args, array $assoc_args ): void {

		$this->logger_set( __FUNCTION__ );
		$this->logger->info( 'Running command: ' . __FUNCTION__ );

		// Newspack Plugin is required.
		if ( ! defined( '\Newspack\Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME' ) ) {
			$this->logger->error( 'Newspack Plugin Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME not found.' );
			exit();
		}

		// Simple Local Avatars is required..
		if ( ! is_plugin_active( "simple-local-avatars/simple-local-avatars.php" ) ) {
			$this->logger->error( 'Simple Local Avatars plugin not found. Install and activate it before using this command.' );
			exit();
		}
		
		$simple_avatars = new \Simple_Local_Avatars();

		// Loop through all profile post type rows.
		(new Posts())->throttled_posts_loop( 
			[
				'post_type' => 'profile',
			], 
			function( $post ) use( $simple_avatars ) {
				
				$this->logger->info( '-- Profile Post ID: ' . $post->ID );

				// -- Check for existing user.

				$existing_check = new \WP_User_Query([
					'meta_key'   => self::META_KEY_PROFILE_POST_ID,
					'meta_value' => $post->ID
				]);

				if ( ! empty( $existing_check->get_results() ) ) {
					$this->logger->notice( 'Skip: Already migrated.' );
					return;
				}

				// -- New User.

				// todo
				WP_CLI::line( $post->post_title );
				WP_CLI::line( $post->post_name );
				// GuestContributorsHelper::create_by_display_name( $post->post_title, [], true );
				// user_nicename (see doc block) // how does this compare to wp santized post_title??
				// $userdata['description']   = $post->post_content;
				// $userdata['meta_input'] = [];
				// $userdata['meta_input'][ self::META_KEY_PROFILE_POST_ID ] = $post->ID;

				// Insert user with force since there can be multiple authors with the same display name.
				// $user_id = GuestContributorsHelper::create_by_display_name( $json_item->title, [ 'user_nicename' => str_replace( self::LIVE_AUTHOR_PATH, '', $json_item->url ) ], true );
				// if ( is_wp_error( $user_id ) ) {
				// 	$this->logger->error( sprintf( 'Failed to create Guest Contributor: %s', $user_id->get_error_message() ) );
				// 	exit();
				// }
				return;

				$this->logger->info( 'Inserted wp user id: ' . $user_id );

				// Simple Local Avatars.
				$thumbnail_id = get_post_meta( $post->ID, '_thumbnail_id', true );
				if ( is_numeric( $thumbnail_id ) && $thumbnail_id > 0 ) {
					$simple_avatars->assign_new_user_avatar( $thumbnail_id, $user_id );
					$this->logger->info( 'Avatar set to thumbnail_id: ' . $thumbnail_id ); 
				}

			} // callback function
		); // throttled posts

		$this->logger->info( 'Done.' ); 
	}

	/************************
	  BATCHING
	************************/

	/**
	 * Batch method to get key for counts.
	 *
	 * @param  string $content_type Node types: article, page, etc.
	 * @param  string $entity_type  Node, media, user, etc.
	 * @return string $batch_key    Array key. 
	 */
	private function batch_get_key( $content_type, $entity_type ) {
		$batch_key = $content_type . '---' . $entity_type;
		if ( !isset( $this->batch_counts[ $batch_key ] ) ) {
			$this->batch_counts[ $batch_key ] = 0;
		}
		return $batch_key;
	}

	/**
	 * Batch increment upon each insert.
	 */
	private function batch_increment( $content_type, $entity_type ) {
		$this->batch_counts[ $this->batch_get_key( $content_type, $entity_type ) ]++;
	}
	
	/**
	 * Batch stop when at or over max return true.
	 */
	private function batch_stop( $content_type, $entity_type ) {
		return ( $this->batch_counts[ $this->batch_get_key( $content_type, $entity_type ) ] >= $this->batch_max );
	}

	/************************************
	  FG DRUPAL HOOKS (non-premium)
	************************************/

	/**
	 * FG Drupal get nodes types.
	 * 
	 * Only use this filter to create an "allow list" for custom node types. By default, FG Drupal will
	 * migrate all node types and uses the filter fgd2wpp_post_init_premium_options to set
	 * $premium_options['nodes_to_skip'] if a certain node type needs to be skipped. It's preferred to 
	 * skip nodes using $premium_options['nodes_to_skip']. But if that is too much management,
	 * and instead you just want an "allow list", then use this filter.
	 *
	 * @param array $node_types
	 * @return array
	 */
	public function fgd2wp_get_node_types( array $node_types ): array {
		
		// @todo remove this filter completely?
		return $node_types;

		// Always allow the following core node types in this filter. To remove these core node types
		// from a migration they must be removed instead by using filter fgd2wpp_post_init_premium_options
		// and adding the core node type to $premium_options['nodes_to_skip'] .
		$allow = [ 'article', 'page', 'post', 'story' ];
		
		// Add custom node types to this list. Be advised that $premium_options['nodes_to_skip'] will take
		// precedence over this list. To remove a custom node from a migration, do not add below, but instead
		// add to filter fgd2wpp_post_init_premium_options / $premium_options['nodes_to_skip']
		$allow[] = 'profile'; // entity reference (FG plugin add-on required) | database table: node__field_by_author
		// @todo: $allow[] = 'book_review',
		// @todo: .. add more custom node types here?

		// Only return the node types that are in both $node_types and $allow.
		return array_filter( $node_types, fn( $type ) => in_array( $type, $allow ), ARRAY_FILTER_USE_KEY );
	}

	/**
	 * FG Drupal get nodes sql.
	 *
	 * Use this filter to modify the sql query for the main import loop. When FG Drupal selects nodes to import
	 * this is the SQL that it runs. The default sql will get 10 nodes in ascending node id order where the 
	 * node ids are greater than the last previously imported node id. This sql will be run over-and-over again until
	 * there are no more nodes remaining to import. 
	 * 
	 * @param [type] $sql            Default sql.
	 * @param [type] $prefix         Database table prefix.
	 * @param [type] $last_drupal_id Last imported node id. Initial value: 0
	 * @param [type] $limit          Default limit of rows to select each batch: 10
	 * @param [type] $content_type   Node content type.
	 * @param [type] $entity_type    Node, media, user, taxonomy, etc.
	 * @return void
	 */
	public function fgd2wp_get_nodes_sql( $sql, $prefix, $last_drupal_id, $limit, $content_type, $entity_type ) {
		
		// @todo - testing by profile ids:
		// if ( 'node' === $entity_type && 'profile' === $content_type ) {
		// 	$sql = str_replace( 'WHERE ', 'WHERE n.nid IN ( 234552 ) AND ', $sql );
		// }

		// When importing, it's easier to test and QA the content when importing the newest nodes
		// first. The default sql will import the lower node ids first so this means the oldest
		// articles, profiles, etc, will be imported before the newer ones. The following will
		// change the SQL to import the newest content first.
			
		// Order by nid desc to force newest content first.
		$sql = str_replace( 'ORDER BY n.nid', 'ORDER BY n.nid DESC', $sql );

		// Where ids are less than the last imported id since we're doing the newest (largest) ids first.
		// But the first time this is called, the $last_drupal_id will be 0 so don't change the sql.
		if ( $last_drupal_id > 0 ) {
			$sql = str_replace( 'AND n.nid > ', 'AND n.nid < ', $sql );
		}
		
		// Stop at batch limit.
		if ( $this->batch_stop( $content_type, $entity_type ) ) {
			$sql = str_replace( 'LIMIT ' . $limit, 'LIMIT 0', $sql );
		}
		else {
			// To make debugging and batching easier, change the limit to just 1 row.
			$sql = str_replace( 'LIMIT ' . $limit, 'LIMIT 1', $sql );
		}

		return $sql;
	}

	/**
	 * FG Drupal map drupal-to-wordpress taxonomies.
	 */
	public function fgd2wp_map_taxonomy( $wp_taxonomy, $taxonomy ) {

		// Tell FG Drupal how to migrate taxonomies.
		switch ( strtolower( $taxonomy ) ) {
			case 'channel':
				$wp_taxonomy = 'category';
				break;
			case 'sections':
				$wp_taxonomy = 'category';
				break;
			case 'topics':
				$wp_taxonomy = 'post_tag';
				break;
		}

		return $wp_taxonomy;
	}

	/**
	 * FG Drupal after a "post" (this could be article, profile, etc) is inserted.
	 */
	public function fgd2wp_post_import_post( $new_post_id, $node, $content_type, $post_type, $entity_type ) {

		// Update batch count.
		$this->batch_increment( $content_type, $entity_type );

		// Print logging.
		$this->logger->info( 'fgd2wp_post_import_post (AFTER): ' . json_encode( array( 
			$new_post_id, $node, $content_type, $post_type, $entity_type,
		) ) );
	}

	/**
	 * FG Drupal after drupal custom fields are registered.
	 * 
	 * This filter will capture the custom fields into a lookup array for later use.
	 * An example is publication_date - this is a custom field in drupal that is needed
	 * before each post is inserted ( see fgd2wp_pre_insert_post below ).
	 *
	 * Format example: $this->custom_fields['node']['article']['publication_date']
	 * 
	 */
	public function fgd2wp_post_register_custom_fields( $custom_fields ) {
		$this->custom_fields = $custom_fields;
	}

	/**
	 * FG Drupal before inserting a post. 
	 * 
	 * Use this to make adjustments to a post prior to insertion.
	 *
	 * @param  array $new_post The new post array prior to insertion.
	 * @param  array $node     The drupal node being migrated into new post.
	 * @return array            The modified $new_post array.
	 */
	public function fgd2wp_pre_insert_post( $new_post, $node ) {
	
		// Only do this for article ("post") types.
		if ( 'article' !== $node['type'] ) return $new_post;
	
		// Verify the custom field key exists.
		if ( empty( $this->custom_fields['node']['article']['publication_date'] ) ) {
			$this->logger->error( 'Missing custom field for: node > article > publication_date' );
			exit();
		}
		
		// Access the global FG Drupal Premium object (note the extra "p" in the name) to get the value.
		global $fgd2wpp;
		$pub_date_arr = $fgd2wpp->get_node_custom_field_values( $node, $this->custom_fields['node']['article']['publication_date'] );

		// Verify value.
		if ( 1 !== count( $pub_date_arr )
			|| empty( $pub_date_arr[0]['field_publication_date_value'] )
			|| false === strtotime( $pub_date_arr[0]['field_publication_date_value'])
		) {
			$this->logger->error( 'Custom post field value is not a valid datetime for: publication_date' );
			exit();
		}

		// Set new_post to use the publication date from field_publication_date_value (which is GMT).		
		$new_post['post_date'] = get_date_from_gmt( $pub_date_arr[0]['field_publication_date_value'] );
		$new_post['post_date_gmt'] = $pub_date_arr[0]['field_publication_date_value'];
	
		return $new_post;	
	}

	/**
	 * FG Drupal before inserting a taxonomy term.
	 * 
	 * FG Drupal by default will sanitize taxonomy titles into slugs by removing "-" dashes.
	 * In WordPress we'd like to keep the dashes. Example: "My Category" should have slug "my-category".
	 *
	 */
	public function fgd2wp_pre_insert_taxonomy_term( $args, $term, $wp_taxonomy ) {
		if ( isset( $args['slug'] ) ) {
			// Undo FG Drupal's taxonomy slug since it removes "-" from slugs.
			// Just let the wordpress's insert term function create the slug naturally.
			unset( $args['slug'] );
		}
		return $args;
	}

	// @todo - See CarsonNow migrator.
	public function fgd2wp_pre_register_post_type( $post_type, $node_type ) {
		
		// @todo Look at how CarsonNow migrator will convert a node type to "post"
		// and then it will add the node type as a category to the post.

		// Example: "book_review" nodes will migrate to posts with category "Book Review"

		// Map to post.
		// if ( 'book_review' === $node_type ) {
		// 	$post_type = 'post';
		// }
		
		// See also Carson now for how the category is added during inset post;
		// $new_post['post_category'][] = self::READER_CONTENT_CATEGORY_ID;

		return $post_type;
	}

	/************************************
	  FG DRUPAL HOOKS (premium)
	************************************/

	// @todo - remove?
	public function fgd2wpp_get_users_sql( string $sql ): string {
		
		// Replaced with premium option: 'only_authors' => true
		// @todo remove this filter completely?
		return $sql;

		$limit        = 10; // Possibly used to "batch" x number at a time?
		$last_user_id = (int) get_option( 'fgd2wp_last_user_id' ); // to restore the import where it left
		
		// @todo: change this to use a standardized function in NMT Drupal Migrator or NMT FG Helper.
		$prefix       = FgHelper->get_import_tables_prefix();

		$sql = "
			SELECT u.uid, u.name, u.mail, u.pass, u.created, up.user_picture_target_id AS picture
			FROM {$prefix}users_field_data u
			LEFT JOIN {$prefix}user__user_picture up ON up.entity_id = u.uid
			JOIN user__roles ur ON u.uid = ur.entity_id 
			WHERE ur.roles_target_id IN ('editor', 'web_editor')
			AND u.uid > '$last_user_id'
			AND u.status = 1
			ORDER BY u.uid
			LIMIT $limit
		";

		return $sql;
	}

	/**
	 * FG Drupal after premium options are initialized.
	 * 
	 * Use this to change premuim options in code instead of wp-admin > Tools > Import > Drupal settings.
	 *
	 */
	public function fgd2wpp_post_init_premium_options( $premium_options ) {

		// Override default values from file:
		// fg-drupal-to-wp-premium/admin/class-fg-drupal-to-wp-premium-admin.php
		// FG_Drupal_to_WordPress_Premium_Admin->set_premium_options()

		// Only import authors.
		// @todo: remove fgd2wpp_get_users_sql above.
		$premium_options['only_authors'] = true;

		// By default FG drupal will migrate all core and custom node types.
		// The core node types are 'article', 'page', 'post', 'story'
		// To skip a core or custom node type add to the following array. 
		// Note: if using fgd2wp_get_node_types it is possible to use that filter to skip
		// custom nodes (but not core nodes).  
		// to get node types with content: select distinct type from node order by type;
		// to get all node types from config:  select name from config where name like 'node.type.%' order by name;
		$premium_options['nodes_to_skip']  = [ 
			'america_special_topics',
			'app_america_today_curated_articl',
			'app_reels',
			// 'article',
			'audio_news_update',
			'audio_prayer',
			'book',
			'book_review',
			'global_module_configuration',
			'issue',
			'lectionary_date',
			'modular_page',
			'page',
			'photo_gallery',
			'podcast',
			'press_release',
			// 'profile',
			'sponsorship',
			'subscription_offer',
			'the_word',
			'video',
			'webform_page',
			'who_we_are_page',
		];

		$premium_options['skip_blocks']    = true; // sidebar widgets
		$premium_options['skip_comments']  = true;
		$premium_options['skip_menus']     = true;

		// @todo: Redirects?
		// keep default: 'skip_redirects'    => false, so that redirects are added to wp_fg_redirects
		// but possibly don't use FG to do the redirects or not? This is after the site is launched.
		$premium_options['url_redirect']   = false;

		return $premium_options;
	}

	/************************************
	  LOGGING
	************************************/

	/**
	 * Logger setup
	 *
	 * @param string $caller Calling __FUNCTION__ name.
	 * @return void
	 */
	private function logger_set( $caller ) {
		$log_slug     = str_replace( __NAMESPACE__ . '\\', '', __CLASS__ ) . '_' . $caller;
		$this->logger = MultiLog::get_logger(
			$log_slug . '-multi',
			[
				CliLog::get_logger( $log_slug ),
				FileLog::get_logger( $log_slug ),
			] 
		);
	}

	/************************************
	  WP HOOKS
	************************************/

	/**
	 * Filter the options for the FG plugin (after FgHelper).
	 * 
	 * @param  array|false $options The options array to filter or boolean false if database option doesn't exist.
	 * @return array                The filtered options.
	 */
	public function option_fgd2wp_options( array|false $options ): array {
		
		// For when options don't exist yet in the db.
		if( false === $options ) $options = [];

		// Override default values from file:
		// fg-drupal-to-wp-premium/admin/class-fg-drupal-to-wp-admin.php
		// FG_Drupal_to_WordPress_Admin->set_plugin_options();

		// Keep default: 'force_media_import' => 0 so that already downloaded images aren't fetched again from Live site.
		$options['force_media_import'] = 0;

		// @todo Should this go into Publisher specific migrator instead?
		$options['summary'] = 'in_excerpt'; // otherwise excerpt will go in top of content with <!--more--> link

		// @todo should we turn this on for images with the same filenames?
		// how are these store in drupal? in wordpress the same filename could be used if in different /year/mon/ folders...
		// but what about if the import was restarted...will images be fetched again and given unique -abc at the end?
		// import_duplicates = 1;

		return $options;
	}

}