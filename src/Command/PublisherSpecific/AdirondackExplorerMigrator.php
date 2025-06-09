<?php
/**
 * Migration tasks for Adirondack Explorer.
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
 * Custom migration scripts for Orthopedics This Week.
 */
class AdirondackExplorerMigrator implements RegisterCommandInterface {
	use WpCliCommandTrait;

	const ARTICLE_TYPE_TAXONOMY = 'spark-article-type';
    const PRIMARY_CATEGORY_META_KEY = '_yoast_wpseo_primary_category';

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
			'newspack-content-migrator ae-migrate-article-types',
			self::get_command_closure( 'cmd_migrate_article_types' ),
			[
				...$generic_args,
				'shortdesc' => 'Migrate Article Types.',
			]
		);
	}

    /**
     * Callable for `newspack-content-migrator ae-migrate-article-types` command.
     * 
     * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
     */
    public function cmd_migrate_article_types( array $pos_args, array $assoc_args ): void {
        $log_name = 'adirondack-explorer-article-types-migrator';
        $logger = MultiLog::get_logger(
			$log_name,
			[
				CliLog::get_logger( $log_name ),
				FileLog::get_logger( $log_name ),
			] 
		);

        $this->register_article_types_taxonomy();

        $logger->info( '🟢 Starting Article Types taxonomy migration to Categories' );

        $article_types_terms = get_terms( [
            'taxonomy'   => self::ARTICLE_TYPE_TAXONOMY,
            'hide_empty' => false,
        ] );

        $logger->info( sprintf( '👉 Found %d taxonomy terms', count( $article_types_terms ) ) );

        foreach ( $article_types_terms as $article_types_term ) {
            $logger->info( sprintf( '👉 Processing Article Type "%s"', $article_types_term->name ) );

            $article_type_term_category_id = $this->taxonomy_logic->get_or_create_category_by_name_and_parent_id(
                $article_types_term->name
            );

            wp_update_category( [
                'cat_ID'            => $article_type_term_category_id,
                'category_nicename' => $article_types_term->slug,
            ] ); // handle proper article type slug

            $logger->info( sprintf( '-- Article Type Category ID is %d', $article_type_term_category_id ) );

            $article_types_post_ids = $this->posts_logic->get_all_posts_ids_in_category( $article_types_term->term_id );

            $logger->info( sprintf( '-- Found %d posts in Article Type %s', count( $article_types_post_ids ), $article_types_term->name ) );

            foreach ( $article_types_post_ids as $post_id ) {
                $logger->info( sprintf( '---- Adding Article Type Category %d to Post ID %d', $article_type_term_category_id, $post_id ) );

                wp_set_post_categories( $post_id, [ $article_type_term_category_id ], true );

                update_post_meta( $post_id, self::PRIMARY_CATEGORY_META_KEY, $article_type_term_category_id );
            }
        }

        unregister_taxonomy( self::ARTICLE_TYPE_TAXONOMY );

        $logger->info( '🏁 Article Types taxonomy migration to Categories completed successfully.' );

        wp_cache_flush();
    }

    /**
     * Temporary register Article Types taxonomy.
     */
    private function register_article_types_taxonomy(): void {
        register_taxonomy(
            self::ARTICLE_TYPE_TAXONOMY,
            [ 'post' ],
            [
                'hierarchical'      => true,
                'labels'            => [
                    'name'              => _x( 'Article Types', 'taxonomy general name' ),
                    'singular_name'     => _x( 'Article Type', 'taxonomy singular name' ),
                    'search_items'      => __( 'Search Article Types' ),
                    'all_items'         => __( 'All Article Types' ),
                    'parent_item'       => __( 'Parent Article Types' ),
                    'parent_item_colon' => __( 'Parent Article Type:' ),
                    'edit_item'         => __( 'Edit Article Type' ),
                    'update_item'       => __( 'Update Article Type' ),
                    'add_new_item'      => __( 'Add New Article Type' ),
                    'new_item_name'     => __( 'New Article Type' ),
                    'menu_name'         => __( 'Article Types' ),
                ],
                'show_ui'           => true,
                'show_admin_column' => true,
                'query_var'         => true,
                'rewrite'           => [ 'slug' => 'article-type' ],
            ]
        );
    }
}
