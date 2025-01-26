<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\NMT;
use Newspack\MigrationTools\Command\DrupalMigrator;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\DrupalHelper;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;

class AmericaMagMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	private string $migration_name = 'am_mag';
	private string $required_timezone = 'America/New_York';

	private array $custom_post_fields;

	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator am-mag-import',
			self::get_command_closure( 'cmd_run_import' ),
		);
	}

	/**
	 * Run the import.
	 */
	public function cmd_run_import( array $pos_args, array $assoc_args ): void {
		
		// Verify the FG Entity add-on is active.
		if ( ! is_plugin_active( "fg-drupal-to-wp-premium-entityreference-module/fg-drupal-to-wp-entityreference.php" ) ) {
			NMT::exit_with_message( 'FG Drupal Entity Refernce Add-on plugin not found. Install and activate it before using this class.' );
		}
		
		// Verify ET/-4 timezone:
		// if we want to set this programatically (via wp_cli::confirm yes/no), the DB must match:
		// timezone_string	America/New_York	auto
		// gmt_offset		(null)				on|auto (?)
		// just force this to be done by-hand in wp-admin instead.
		if( wp_timezone_string() !== $this->required_timezone ) {
			// exit on error.
			WP_CLI::error( 'WP > settings > timezone must be set to: ' . $this->required_timezone, true );
		}

		// Setup FG plugin's filters.
		add_filter( 'fgd2wp_get_node_types',                   [ $this, 'fgd2wp_get_node_types' ], 11, 1 );
		add_filter( 'fgd2wp_get_nodes_sql',                    [ $this, 'fgd2wp_get_nodes_sql' ], 10, 6 );
		add_filter( 'fgd2wp_map_taxonomy',                     [ $this, 'fgd2wp_map_taxonomy' ], 11, 3 );
		add_filter( 'fgd2wp_post_import_post',                 [ $this, 'fgd2wp_post_import_post' ], 10, 5 );
		add_action( 'fgd2wp_post_register_custom_post_fields', [ $this, 'fgd2wp_post_register_custom_post_fields' ], 10, 2 );
		add_filter( 'fgd2wp_pre_insert_post',                  [ $this, 'fgd2wp_pre_insert_post' ], 10, 2 );
		add_filter( 'fgd2wp_pre_register_post_type',           [ $this, 'fgd2wp_pre_register_post_type' ], 11, 3 );

		// Premium filters have extra "p" in name.
		add_filter( 'fgd2wpp_post_init_premium_options', [ $this, 'fgd2wpp_post_init_premium_options' ] );

		// Call NMT's migrator using a unique migration name.
		DrupalMigrator::cmd_wrap_drupal_import( [ 'am_mag' ], [] );

	}

	public function fgd2wp_get_node_types( array $node_types ): array {
		$types_to_migrate = [
			'article',
			// 'profile', // entity reference (FG plugin add-on required) | db.node__field_by_author
			// 'book_review',
			// .. add more node types here.
		];

		return array_filter( $node_types, fn( $type ) => in_array( $type, $types_to_migrate ), ARRAY_FILTER_USE_KEY );
	}

	public function fgd2wp_get_nodes_sql ( $sql, $prefix, $last_drupal_id, $limit, $content_type, $entity_type ) {
			
		if ( $content_type === 'article' ) {

			// where clause.  the first time $last_drupal_id will be (int) 0 . this will cause the MAX ID to be on the top of DESC.
			// upon each insert, $last_drupal_id will progressively get less.  so need to change to AND n.nid < '$last_drupal_id'
			// if( $last_drupal_id > 0 ) {
				// for testing don't increment so we stay on 1 import; it will stick on the last id (which is the MAX value)
				// $sql = str_replace( 'AND n.nid > ', 'AND n.nid < ', $sql );
			// }
			// order by.
			// $sql = str_replace( 'ORDER BY n.nid', 'ORDER BY n.nid DESC', $sql );
			// limit for testing.
			// $sql = str_replace( 'LIMIT ' . $limit, 'LIMIT 1', $sql );

			// hard coded
			if( $last_drupal_id == 0 ) {
				$sql = str_replace( "AND n.nid > '0'", "AND n.nid in(240610)", $sql );
			} else {
				$sql = str_replace( "AND n.nid > '" . $last_drupal_id . "'", "AND 1 = 2", $sql );
			}
		}
		else if ( $content_type === 'profile' ) {
			if( $last_drupal_id == 0 ) {
				// $sql = str_replace( "AND n.nid > '0'", "AND n.nid = 234552", $sql );
				$sql = str_replace( "AND n.nid > '0'", "AND n.nid in(241195,247417,247726,247727,247728,247729,247730)", $sql );
			} else {
				$sql = str_replace( "AND n.nid > '" . $last_drupal_id . "'", "AND 1 = 2", $sql );
			}	
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

		// temp / testing:
		$premium_options['nodes_to_skip']  = ['page'];
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
	
		// option 1: using an FG function.
		if ( empty( $this->custom_post_fields['publication_date'] ) ) {
			WP_CLI::error( 'Missing custom post field for: publication_date', true );
		}
		$result_array = $fgd2wpp->get_node_custom_field_values( $node, $this->custom_post_fields['publication_date'] );
		// end option 1.
	
		// option 2: via FG drupal query (sql).
		// $sql = sprintf(
		// 	"SELECT DISTINCT field_publication_date_value, f.delta
		// 	FROM node__field_publication_date f
		// 	WHERE f.entity_id = '%d'
		// 	AND f.langcode IN('en', 'und')
		// 	ORDER BY f.delta", 
		// 	(int) $node['nid']
		// );
		// $result_array = $fgd2wpp->drupal_query( $sql, true ); // fail on db error.
		// end option 2.
	
		// Get value:
		if ( 1 !== count( $result_array )
			|| empty( $result_array[0]['field_publication_date_value'] )
			|| false === strtotime( $result_array[0]['field_publication_date_value'])
		) {
			WP_CLI::error( 'Custom post field value is not a valid datetime for: publication_date', true );
		}
	
		// SET WORDPRESS DATE TO NEW YORK PRIOR TO IMPORT!!!!
		// below will show correct -0400 in HTML
		unset( $new_post['post_date'] ); // UNSET THIS SO IT WILL GET CREATED FROM GMT IN WP TIMEZONE (NEW YORK)
		$new_post['post_date_gmt'] = $result_array[0]['field_publication_date_value']; // this alone will cause WP to create post_date -4/Easten Time.
		// YES!!!  guid is created from post date so iF GTM is under 4 am, then GUID will be prior day - which is how DRUPAL does it!!
	
		return $new_post;	
	}

	/*
	Replaced with premium option: 'only_authors' => true
	// add_filter( 'fgd2wpp_get_users_sql',         [ $this, 'fgd2wpp_get_users_sql' ], 10, 2 );
	public function fgd2wpp_get_users_sql( string $sql ): string {
		
		$limit        = 10; // Possibly used to "batch" x number at a time?
		$last_user_id = (int) get_option( 'fgd2wp_last_user_id' ); // to restore the import where it left
		$prefix       = DrupalHelper::get_tables_prefix();

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