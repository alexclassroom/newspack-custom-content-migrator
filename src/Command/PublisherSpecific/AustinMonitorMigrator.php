<?php
/**
 * Migration tasks for Austin Monitor.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Posts;
use Newspack\MigrationTools\Logic\Taxonomy;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;

/**
 * Custom migration scripts for Austin Monitor.
 */
class AustinMonitorMigrator implements RegisterCommandInterface {
	use WpCliCommandTrait;

	// Whispers.
	const WHISPERS_POST_TYPE = 'amon_whispers';
	const WHISPERS_CATEGORY  = 'Whispers';

	// Nonprofits.
	const NONPROFITS_POST_TYPE = 'amon_nonprofit';
	const NONPROFITS_CATEGORY  = 'Nonprofits';

	// Data Graphics.
	const DATAGRAPHICS_POST_TYPE = 'amon_datagraphic';
	const DATAGRAPHICS_CATEGORY  = 'Graphics';

	// Premium Content.
	const PREMIUM_POST_TYPE = 'amon_premium';
	const PREMIUM_CATEGORY  = 'Premium Types';

	// Districts.
	const DISTRICT_TAXONOMY         = 'amon_districts';
	const DISTRICTS_PARENT_CATEGORY = 'Districts';

	// Default Featured Image.
	const FEATURED_IMAGE_META_KEY          = '_thumbnail_id';
	const ORIGINAL_FEATURED_IMAGE_META_KEY = '_nmt_original_featured_image_id';

	/**
	 * Posts logic.
	 * 
	 * @var Posts
	 */
	private Posts $posts_logic;

	/**
	 * Taxonomy logic.
	 * 
	 * @var Taxonomy
	 */
	private Taxonomy $taxonomy_logic;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->posts_logic    = new Posts();
		$this->taxonomy_logic = new Taxonomy();
	}

	/**
	 * Registers WP CLI Commands.
	 * 
	 * @return void
	 */
	public static function register_commands(): void {
		$generic_args = [];

		WP_CLI::add_command(
			'newspack-content-migrator am-migrate-whispers',
			self::get_command_closure( 'cmd_migrate_whispers' ),
			[
				...$generic_args,
				'shortdesc' => 'Migrate Whispers.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator am-rollback-whispers',
			self::get_command_closure( 'cmd_rollback_whispers' ),
			[
				...$generic_args,
				'shortdesc' => 'Rollback Whispers.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator am-migrate-nonprofits',
			self::get_command_closure( 'cmd_migrate_nonprofits' ),
			[
				...$generic_args,
				'shortdesc' => 'Migrate Nonprofits.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator am-rollback-nonprofits',
			self::get_command_closure( 'cmd_rollback_nonprofits' ),
			[
				...$generic_args,
				'shortdesc' => 'Rollback Nonprofits.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator am-migrate-datagraphics',
			self::get_command_closure( 'cmd_migrate_datagraphics' ),
			[
				...$generic_args,
				'shortdesc' => 'Migrate Datagraphics.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator am-rollback-datagraphics',
			self::get_command_closure( 'cmd_rollback_datagraphics' ),
			[
				...$generic_args,
				'shortdesc' => 'Rollback Datagraphics.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator am-migrate-premium-content',
			self::get_command_closure( 'cmd_migrate_premium_content' ),
			[
				...$generic_args,
				'shortdesc' => 'Migrate Premium Content.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator am-rollback-premium-content',
			self::get_command_closure( 'cmd_rollback_premium_content' ),
			[
				...$generic_args,
				'shortdesc' => 'Rollback Premium Content.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator am-migrate-districts',
			self::get_command_closure( 'cmd_migrate_districts' ),
			[
				...$generic_args,
				'shortdesc' => 'Migrate Districts.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator am-set-default-featured-image',
			self::get_command_closure( 'cmd_set_default_featured_image' ),
			[
				'shortdesc' => 'Sets a default featured image for all Posts.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'attachment_id',
						'description' => 'The ID of the attachment to set as the featured image.',
						'optional'    => false,
					],
				],
			]
		);
	}

	/**
	 * Migrates Whispers from CPT `amon_whispers` to Posts with Category "Whispers".
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_migrate_whispers( array $pos_args, array $assoc_args ): void {
		// Change the Source URL for the Redirection CSV.
		add_filter(
			'nmt_posts_migrator_cpt_to_posts_post_source_url',
			function ( $source_url, $post_id, $post_name ) {
				return sprintf( '/stories/whispers/%s', $post_name );
			},
			10,
			3
		);

		// Set the Primary Category to "Whispers".
		$whispers_category_id = $this->taxonomy_logic->get_or_create_category_by_name_and_parent_id( self::WHISPERS_CATEGORY );

		add_action(
			'nmt_posts_migrator_cpt_to_posts_post_migrated',
			function ( $post_id ) use ( $whispers_category_id ) {
				update_post_meta( $post_id, '_yoast_wpseo_primary_category', $whispers_category_id );
			},
			10,
			1
		);

		WP_CLI::runcommand(
			sprintf(
				'newspack-content-migrator migrate-cpt-to-posts --post_type=%s --category_name=%s',
				self::WHISPERS_POST_TYPE,
				self::WHISPERS_CATEGORY
			),
			[
				'return'     => false,
				'launch'     => false,
				'exit_error' => true,
			]
		);
	}

	/**
	 * Rollbacks "Whispers" posts to CPT Whispers.
	 * This is needed before Content Refresh to get an up-to-date list of Whispers posts.
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_rollback_whispers( array $pos_args, array $assoc_args ): void {
		WP_CLI::runcommand(
			sprintf(
				'newspack-content-migrator rollback-posts-to-cpt --category_name=%s --post_type=%s',
				self::WHISPERS_CATEGORY,
				self::WHISPERS_POST_TYPE,
			),
			[
				'return'     => false,
				'launch'     => false,
				'exit_error' => true,
			]
		);
	}

	/**
	 * Migrates NonProfits from CPT `amon_nonprofit` to Posts with Category "Nonprofits".
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_migrate_nonprofits( array $pos_args, array $assoc_args ): void {
		// Change the Source URL for the Redirection CSV.
		add_filter(
			'nmt_posts_migrator_cpt_to_posts_post_source_url',
			function ( $source_url, $post_id, $post_name ) {
				return sprintf( '/nonprofits/%s', $post_name );
			},
			10,
			3
		);

		// Set the Primary Category to "Nonprofits".
		$nonprofits_category_id = $this->taxonomy_logic->get_or_create_category_by_name_and_parent_id( self::NONPROFITS_CATEGORY );

		add_action(
			'nmt_posts_migrator_cpt_to_posts_post_migrated',
			function ( $post_id ) use ( $nonprofits_category_id ) {
				update_post_meta( $post_id, '_yoast_wpseo_primary_category', $nonprofits_category_id );
			},
			10,
			1
		);

		WP_CLI::runcommand(
			sprintf(
				'newspack-content-migrator migrate-cpt-to-posts --post_type=%s --category_name=%s',
				self::NONPROFITS_POST_TYPE,
				self::NONPROFITS_CATEGORY
			),
			[
				'return'     => false,
				'launch'     => false,
				'exit_error' => true,
			]
		);

		wp_cache_flush();
	}

	/**
	 * Rollbacks "Nonprofits" posts to CPT Nonprofits.
	 * This is needed before Content Refresh to get an up-to-date list of Nonprofits posts.
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_rollback_nonprofits( array $pos_args, array $assoc_args ): void {
		WP_CLI::runcommand(
			sprintf(
				'newspack-content-migrator rollback-posts-to-cpt --category_name=%s --post_type=%s',
				self::NONPROFITS_CATEGORY,
				self::NONPROFITS_POST_TYPE,
			),
			[
				'return'     => false,
				'launch'     => false,
				'exit_error' => true,
			]
		);
	}

	/**
	 * Migrates Data Graphics from CPT `amon_datagraphic` to Posts with Category "Graphics".
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_migrate_datagraphics( array $pos_args, array $assoc_args ): void {
		// Change the Source URL for the Redirection CSV.
		add_filter(
			'nmt_posts_migrator_cpt_to_posts_post_source_url',
			function ( $source_url, $post_id, $post_name ) {
				return sprintf( '/data-graphic/%s', $post_name );
			},
			10,
			3
		);

		// Set the Primary Category to "Data Graphics".
		$data_graphics_category_id = $this->taxonomy_logic->get_or_create_category_by_name_and_parent_id( self::DATAGRAPHICS_CATEGORY );

		add_action(
			'nmt_posts_migrator_cpt_to_posts_post_migrated',
			function ( $post_id ) use ( $data_graphics_category_id ) {
				update_post_meta( $post_id, '_yoast_wpseo_primary_category', $data_graphics_category_id );
			},
			10,
			1
		);

		WP_CLI::runcommand(
			sprintf(
				'newspack-content-migrator migrate-cpt-to-posts --post_type=%s --category_name=%s',
				self::DATAGRAPHICS_POST_TYPE,
				self::DATAGRAPHICS_CATEGORY
			),
			[
				'return'     => false,
				'launch'     => false,
				'exit_error' => true,
			]
		);

		wp_cache_flush();
	}

	/**
	 * Rollbacks "Graphics" posts to CPT Data Graphics.
	 * This is needed before Content Refresh to get an up-to-date list of Data Graphics posts.
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_rollback_datagraphics( array $pos_args, array $assoc_args ): void {
		WP_CLI::runcommand(
			sprintf(
				'newspack-content-migrator rollback-posts-to-cpt --category_name=%s --post_type=%s',
				self::DATAGRAPHICS_CATEGORY,
				self::DATAGRAPHICS_POST_TYPE,
			),
			[
				'return'     => false,
				'launch'     => false,
				'exit_error' => true,
			]
		);
	}

	/**
	 * Migrates Premium Content from CPT `amon_premium` to Posts with Category "Premium Content".
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_migrate_premium_content( array $pos_args, array $assoc_args ): void {
		// Change the Source URL for the Redirection CSV.
		add_filter(
			'nmt_posts_migrator_cpt_to_posts_post_source_url',
			function ( $source_url, $post_id, $post_name ) {
				return sprintf( '/stories/premium/%s', $post_name );
			},
			10,
			3
		);

		// Set the Primary Category to "Premium Content".
		$premium_content_category_id = $this->taxonomy_logic->get_or_create_category_by_name_and_parent_id( self::PREMIUM_CATEGORY );

		add_action(
			'nmt_posts_migrator_cpt_to_posts_post_migrated',
			function ( $post_id ) use ( $premium_content_category_id ) {
				update_post_meta( $post_id, '_yoast_wpseo_primary_category', $premium_content_category_id );
			},
			10,
			1
		);

		WP_CLI::runcommand(
			sprintf(
				'newspack-content-migrator migrate-cpt-to-posts --post_type=%s --category_name=%s',
				escapeshellarg( self::PREMIUM_POST_TYPE ),
				escapeshellarg( self::PREMIUM_CATEGORY )
			),
			[
				'return'     => false,
				'launch'     => false,
				'exit_error' => true,
			]
		);

		wp_cache_flush();
	}

	/**
	 * Rollbacks "Premium Content" posts to CPT Premium Content.
	 * This is needed before Content Refresh to get an up-to-date list of Premium Content posts.
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_rollback_premium_content( array $pos_args, array $assoc_args ): void {
		WP_CLI::runcommand(
			sprintf(
				'newspack-content-migrator rollback-posts-to-cpt --category_name=%s --post_type=%s',
				escapeshellarg( self::PREMIUM_CATEGORY ),
				escapeshellarg( self::PREMIUM_POST_TYPE )
			),
			[
				'return'     => false,
				'launch'     => false,
				'exit_error' => true,
			]
		);
	}

	/**
	 * Callable for `newspack-content-migrator am-migrate-districts` command.
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_migrate_districts( array $pos_args, array $assoc_args ): void {
		$log_name = 'austin-monitor-districts-migrator';
		$logger   = MultiLog::get_logger(
			$log_name,
			[
				CliLog::get_logger( $log_name ),
				FileLog::get_logger( $log_name ),
			] 
		);

		$this->register_districts_taxonomy();

		$logger->info( '🟢 Starting Districts taxonomy migration to Categories' );

		$districts_terms = get_terms(
			[
				'taxonomy'   => self::DISTRICT_TAXONOMY,
				'hide_empty' => false,
			] 
		);

		$logger->info( sprintf( '👉 Found %d taxonomy terms', count( $districts_terms ) ) );

		$districts_parent_category_id = $this->taxonomy_logic->get_or_create_category_by_name_and_parent_id(
			self::DISTRICTS_PARENT_CATEGORY
		);

		$logger->info( sprintf( '👉 Using Category "%s" (term_id: %d) as Districts parent Category', self::DISTRICTS_PARENT_CATEGORY, $districts_parent_category_id ) );

		foreach ( $districts_terms as $districts_term ) {
			$logger->info( sprintf( '👉 Processing District "%s"', $districts_term->name ) );

			$district_term_category_id = $this->taxonomy_logic->get_or_create_category_by_name_and_parent_id(
				$districts_term->name,
				$districts_parent_category_id
			);

			$logger->info( sprintf( '-- District Category ID is %d', $district_term_category_id ) );

			$district_posts_ids = $this->posts_logic->get_all_posts_ids_in_category( $districts_term->term_id );

			$logger->info( sprintf( '-- Found %d posts in District %s', count( $district_posts_ids ), $districts_term->name ) );

			foreach ( $district_posts_ids as $post_id ) {
				$logger->info( sprintf( '---- Adding District Category %d to Post ID %d', $district_term_category_id, $post_id ) );

				wp_set_post_categories( $post_id, [ $district_term_category_id ], true );
			}
		}

		unregister_taxonomy( self::DISTRICT_TAXONOMY );

		$logger->info( '🏁 Districts taxonomy migration to Categories completed successfully.' );

		wp_cache_flush();
	}

	/**
	 * Callable for `newspack-content-migrator am-migrate-districts` command.
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_set_default_featured_image( array $pos_args, array $assoc_args ): void {
		$default_attachment_id = $assoc_args['attachment_id'] ?? null;

		$log_name = 'austin-monitor-default-featured-image-migrator';
		$logger   = MultiLog::get_logger(
			$log_name,
			[
				CliLog::get_logger( $log_name ),
				FileLog::get_logger( $log_name ),
			] 
		);

		$logger->info( '🟢 Starting Posts Default Featured Image migration' );

		$this
			->posts_logic
			->throttled_posts_loop(
				[
					'post_type'   => 'post',
					'post_status' => 'any',
					//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query'  => [
						'relation' => 'OR',
						[
							'key'     => self::FEATURED_IMAGE_META_KEY,
							'value'   => $default_attachment_id,
							'compare' => '!=',
						],
						[
							'key'     => self::FEATURED_IMAGE_META_KEY,
							'compare' => 'NOT EXISTS',
						],      
					],
				],
				function ( $post ) use ( $default_attachment_id, $logger ) {
					$current_featured_image_id = get_post_meta( $post->ID, self::FEATURED_IMAGE_META_KEY, true );

					if ( ! empty( $current_featured_image_id ) ) {
							update_post_meta( $post->ID, self::ORIGINAL_FEATURED_IMAGE_META_KEY, $current_featured_image_id );

							wp_delete_attachment( $current_featured_image_id ); // Prevent force deletion, just send to Trash.
					}

					update_post_meta( $post->ID, self::FEATURED_IMAGE_META_KEY, $default_attachment_id );

					$logger->info( sprintf( '👉 Post ID %d: Set default featured image (ID: %d) and saved original featured image (ID: %d)', $post->ID, $default_attachment_id, $current_featured_image_id ) );
				} 
			);

		$logger->info( '🏁 Completed migration' );

		wp_cache_flush();
	}

	/**
	 * Temporary register Districts taxonomy.
	 */
	private function register_districts_taxonomy(): void {
		register_taxonomy(
			self::DISTRICT_TAXONOMY,
			[ 'post' ],
			[
				'hierarchical'      => true,
				'labels'            => [
					'name'              => _x( 'Districts', 'taxonomy general name' ),
					'singular_name'     => _x( 'District', 'taxonomy singular name' ),
					'search_items'      => __( 'Search Districts' ),
					'all_items'         => __( 'All Districts' ),
					'parent_item'       => __( 'Parent Districts' ),
					'parent_item_colon' => __( 'Parent District:' ),
					'edit_item'         => __( 'Edit District' ),
					'update_item'       => __( 'Update District' ),
					'add_new_item'      => __( 'Add New District' ),
					'new_item_name'     => __( 'New District' ),
					'menu_name'         => __( 'Districts' ),
				],
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => [ 'slug' => self::DISTRICT_TAXONOMY ],
			]
		);
	}
}
