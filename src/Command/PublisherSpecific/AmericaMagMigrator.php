<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\Command\DrupalMigrator;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\DrupalHelper;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;

class AmericaMagMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	private string $migration_name = 'am_mag';

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
		
		// Setup FG plugin's filters.
		add_filter( 'fgd2wp_get_node_types',         [ $this, 'fgd2wp_get_node_types' ], 11, 1 );
		add_filter( 'fgd2wp_map_taxonomy',           [ $this, 'fgd2wp_map_taxonomy' ], 11, 3 );
		add_filter( 'fgd2wp_pre_register_post_type', [ $this, 'fgd2wp_pre_register_post_type' ], 11, 3 );
		add_filter( 'fgd2wpp_get_users_sql',         [ $this, 'fgd2wpp_get_users_sql' ], 10, 2 );

		// global $fgd2wpp;
		// var_dump( $fgd2wpp );
		// exit();
		
		// global $drupal_db;

		// FYI: unset($wp_filter['wp_insert_post']); // Remove the "wp_insert_post" that consumes a lot of CPU and memory
		
		// Logging.
		add_action( 'fgd2wp_post_import_post', function( $new_post_id, $node, $content_type, $post_type, $entity_type ) {
			WP_CLI::line( 'fgd2wp_post_import_post (AFTER): ' . json_encode( array( 
				$new_post_id, $node, $content_type, $post_type, $entity_type,
			) ) );
		}, 10, 5 );




		// Order descending:
		add_filter( 'fgd2wp_get_nodes_sql', function( $sql, $prefix, $last_drupal_id, $limit, $content_type, $entity_type ) {
	
			// $sql = "
			// 	AND n.nid > '$last_drupal_id'
			// 	ORDER BY n.nid
			// 	LIMIT $limit
			// ";
			
			// where clause.  the first time $last_drupal_id will be (int) 0 . this will cause the MAX ID to be on the top of DESC.
			// upon each insert, $last_drupal_id will progressively get less.  so need to change to AND n.nid < '$last_drupal_id'
			if( $last_drupal_id > 0 ) {
				$sql = str_replace( 'AND n.nid > ', 'AND n.nid < ', $sql );
			}

			// order by.
			$sql = str_replace( 'ORDER BY n.nid', 'ORDER BY n.nid DESC', $sql );

			return $sql;

		}, 10, 6 );




		// Call NMT's migrator using a unique migration name.
		DrupalMigrator::cmd_wrap_drupal_import( [ 'am_mag' ], [] );

	}

	/**
	 * HOOKS
	 */

	 public function fgd2wp_get_node_types( array $node_types ): array {
		$types_to_migrate = [
			'article',
			'book_review',
			// .. add more node types here.
		];

		return array_filter( $node_types, fn( $type ) => in_array( $type, $types_to_migrate ), ARRAY_FILTER_USE_KEY );
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

	public function fgd2wp_pre_register_post_type( $post_type, $node_type ) {
		// Map to post.
		if ( 'book_review' === $node_type ) {
			$post_type = 'post';
		}

		return $post_type;
	}

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
}