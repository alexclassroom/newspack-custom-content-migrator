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

	private function __construct() {
		add_filter( 'fgd2wp_map_taxonomy', [ $this, 'fg_filter_map_taxonomy' ], 11, 3 );
		add_filter( 'fgd2wp_get_node_types', [ $this, 'fg_filter_get_node_types' ], 11, 1 );
		add_filter( 'fgd2wp_pre_register_post_type', [ $this, 'fg_filter_fgd2wp_pre_register_post_type' ], 11, 3 );

		add_filter( 'fgd2wpp_get_users_sql', [ $this, 'filter_fgd2wpp_get_users_sql' ], 10, 2 );
	}

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
		DrupalMigrator::run_migration( 'am_mag' );
	}

	public function add_hooks(): void {
		add_filter( 'fgd2wp_get_node_types', [ $this, 'fg_filter_get_node_types' ], 11, 1 );
	}

	public function fg_filter_map_taxonomy( $wp_taxonomy, $taxonomy ) {
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

	public function fg_filter_get_node_types( array $node_types ): array {
		$types_to_migrate = [
			'article',
			'book_review',
			// .. add more node types here.
		];

		return array_filter( $node_types, fn( $type ) => in_array( $type, $types_to_migrate ), ARRAY_FILTER_USE_KEY );
	}

	public function fg_filter_fgd2wp_pre_register_post_type( $post_type, $node_type ) {
		// Map to post.
		if ( 'book_review' === $node_type ) {
			$post_type = 'post';
		}

		return $post_type;
	}

	public function filter_fgd2wpp_get_users_sql( string $sql ): string {
		$limit        = 10; // Pretty random, but you get the idea
		$last_user_id = (int) get_option( 'fgd2wp_last_user_id' ); // to restore the import where it left
		$prefix       = DrupalHelper::get_tables_prefix();

		$sql = "
					SELECT u.uid, u.name, u.mail, u.pass, u.created, up.user_picture_target_id AS picture
					FROM {$prefix}users_field_data u
					LEFT JOIN {$prefix}user__user_picture up ON up.entity_id = u.uid
					JOIN user__roles ur ON u.uid = ur.entity_id 

				WHERE
				    ur.roles_target_id IN ('editor', 'web_editor')
				AND u.uid > '$last_user_id'
				AND u.status = 1
				ORDER BY u.uid
				LIMIT $limit
			";

		return $sql;
	}
}