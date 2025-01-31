<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\NMT;
use Newspack\MigrationTools\Command\DrupalMigrator;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;

class AmericaMagMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

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
	 * Custom Post Fields holds the related field definitions that are attached to node articles.
	 *
	 * @var array
	 */
	private array $custom_post_fields;

	/**
	 * Migration name, used as a unique identifier.
	 *
	 * @var string
	 */
	private string $migration_name = 'am_mag';

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
			'newspack-content-migrator am-mag-import',
			self::get_command_closure( 'cmd_run_import' ),
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
	}

	/**
	 * Run the import.
	 */
	public function cmd_run_import( array $pos_args, array $assoc_args ): void {
		
		if ( isset( $assoc_args['batch-max'] ) ) $this->batch_max = (int) $assoc_args['batch-max'];

		// Verify the FG Drupal "Entity Reference" add-on is active.
		if ( ! is_plugin_active( "fg-drupal-to-wp-premium-entityreference-module/fg-drupal-to-wp-entityreference.php" ) ) {
			NMT::exit_with_message( 'FG Drupal Entity Refernce Add-on plugin not found. Install and activate it before using this class.' );
		}
		
		// Verify America/New_York (eastern / utc-4 timezone):
		if( wp_timezone_string() !== $this->required_timezone ) {
			// if we want to set this programatically (via wp_cli::confirm [yes/no]), the DB must match:
			//   timezone_string	America/New_York	auto
			//   gmt_offset		(null)				on|auto (?)
			// so just force this to be done by-hand in wp-admin instead.  exit if not set.
			WP_CLI::error( 'WP > settings > timezone must be set to: ' . $this->required_timezone, true );
		}

		// Setup FG plugin's filters.
		// add_filter( 'fgd2wp_get_node_types',                [ $this, 'fgd2wp_get_node_types' ], 11, 1 );
		add_filter( 'fgd2wp_get_nodes_sql',                    [ $this, 'fgd2wp_get_nodes_sql' ], 10, 6 );
		add_filter( 'fgd2wp_map_taxonomy',                     [ $this, 'fgd2wp_map_taxonomy' ], 11, 3 );
		add_filter( 'fgd2wp_post_import_post',                 [ $this, 'fgd2wp_post_import_post' ], 10, 5 );
		add_action( 'fgd2wp_post_register_custom_post_fields', [ $this, 'fgd2wp_post_register_custom_post_fields' ], 10, 2 );
		add_filter( 'fgd2wp_pre_insert_post',                  [ $this, 'fgd2wp_pre_insert_post' ], 10, 2 );
		add_filter( 'fgd2wp_pre_register_post_type',           [ $this, 'fgd2wp_pre_register_post_type' ], 11, 3 );

		// Premium filters. Note the extra "p" in hook name.
		add_filter( 'fgd2wpp_post_init_premium_options',       [ $this, 'fgd2wpp_post_init_premium_options' ] );

		// Call NMT's migrator using a unique migration name.
		DrupalMigrator::cmd_wrap_drupal_import( [ $this->migration_name ], [] );
	}

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

	public function fgd2wp_map_taxonomy( $wp_taxonomy, $taxonomy ) {
		switch ( strtolower( $taxonomy ) ) {
			case 'topics':
				$wp_taxonomy = 'post_tag';
				break;
			case 'sections':
				$wp_taxonomy = 'category';
				break;
		}

		return $wp_taxonomy;
	}

	public function fgd2wp_post_import_post( $new_post_id, $node, $content_type, $post_type, $entity_type ) {

		// Update batch count.
		$this->batch_increment( $content_type, $entity_type );

		WP_CLI::line( 'fgd2wp_post_import_post (AFTER): ' . json_encode( array( 
			$new_post_id, $node, $content_type, $post_type, $entity_type,
		) ) );
	}

	public function fgd2wp_post_register_custom_post_fields( $custom_fields, $post_type ) {
		// Save drupal's custom post fields to memory so we can look them up later.
		if( $post_type === 'post' ) $this->custom_post_fields = $custom_fields;
	}

	public function fgd2wp_pre_register_post_type( $post_type, $node_type ) {
		// Map to post.
		// if ( 'book_review' === $node_type ) {
		// 	$post_type = 'post';
		// }

		return $post_type;
	}

	public function fgd2wpp_post_init_premium_options( $premium_options ) {

		// had filter not existed, db option name is get_option('fgd2wpp_options') * note the extra "p" for premium

		// Premium options / Default values / FG plugin version 3.85.2
		// $this->premium_options = array(
		// 	'cpt_format'				=> 'acf',
		// 	'unicode_usernames'			=> false,
		// 	'links'						=> 'as_links',
		// 	'url_redirect'				=> true,
		// 	'skip_taxonomies'			=> false,
		// 	'skip_nodes'				=> false,
		// 	'nodes_to_skip'				=> array(),
		// 	'skip_users'				=> false,
		// 	'only_authors'				=> false,
		// 	'skip_menus'				=> false,
		// 	'skip_comments'				=> false,
		// 	'skip_blocks'				=> false,
		// 	'skip_redirects'			=> false,
		// );
	
		$premium_options['only_authors'] = true;

		// By default FG drupal will migrate all core and custom node types.
		// The core node types are 'article', 'page', 'post', 'story'
		// To skip a core or custom node type add to the following array. 
		// Note: if using fgd2wp_get_node_types it is possible to use that filter to skip
		// custom nodes (but not core nodes).  
		// to get node types with content: select distinct type from node order by type;
		// to get node types from config:  select name from config where name like 'node.type.%' order by name;
		$premium_options['nodes_to_skip']  = [ 
			'america_special_topics',
			'app_america_today_curated_articl',
			'app_reels',
			// allow: 'article',
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
			// allow: 'profile',
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
		$premium_options['skip_redirects'] = true;
		$premium_options['url_redirect']   = false;
		


		return $premium_options;
	}

	public function fgd2wp_pre_insert_post( $new_post, $node ) {
	
		if ( 'article' !== $node['type'] ) return $new_post;
		
		global $fgd2wpp;
	
		// Verify key.
		if ( empty( $this->custom_post_fields['publication_date'] ) ) {
			WP_CLI::error( 'Missing custom post field for: publication_date', true );
		}

		// Get value.
		$pub_date_arr = $fgd2wpp->get_node_custom_field_values( $node, $this->custom_post_fields['publication_date'] );

		// Verify value.
		if ( 1 !== count( $pub_date_arr )
			|| empty( $pub_date_arr[0]['field_publication_date_value'] )
			|| false === strtotime( $pub_date_arr[0]['field_publication_date_value'])
		) {
			WP_CLI::error( 'Custom post field value is not a valid datetime for: publication_date', true );
		}

		// Set new_post to use the publication date from field_publication_date_value (which is GMT).		
		$new_post['post_date'] = get_date_from_gmt( $pub_date_arr[0]['field_publication_date_value'] );
		$new_post['post_date_gmt'] = $pub_date_arr[0]['field_publication_date_value'];
	
		return $new_post;	
	}

	/*
	Replaced with premium option: 'only_authors' => true
	// add_filter( 'fgd2wpp_get_users_sql',         [ $this, 'fgd2wpp_get_users_sql' ], 10, 2 );
	public function fgd2wpp_get_users_sql( string $sql ): string {
		
		$limit        = 10; // Possibly used to "batch" x number at a time?
		$last_user_id = (int) get_option( 'fgd2wp_last_user_id' ); // to restore the import where it left
		$prefix       = \Newspack\MigrationTools\Logic\DrupalHelper::get_tables_prefix();

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
	*/
}