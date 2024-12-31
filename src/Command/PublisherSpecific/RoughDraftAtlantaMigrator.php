<?php
/**
 * Southwest Regional Publishing specific commands.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use WP_CLI;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use NewspackCustomContentMigrator\Logic\ContentDiffMigrator;
use Newspack\MigrationTools\Logic\Taxonomy;
use WP_Term;

/**
 * RoughDraftAtlantaMigrator.
 */
class RoughDraftAtlantaMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * Taxonomy instance.
	 *
	 * @var Taxonomy $taxonomy Instance of the Taxonomy class.
	 */
	private $taxonomy;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->taxonomy = new Taxonomy();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator roughdraftatlanta thegavoice-merging-update-cdiff-imported-post-categories',
			self::get_command_closure( 'cmd_thegavoice_update_post_categories' ),
			[
				'shortdesc' => 'After CDiff merging of thegavoice into roughdraft atlanta, update categories to all be children of one specific category.',
				'synopsis'  => [],
			]
		);
	}

	/**
	 * Callable for `newspack-content-migrator roughdraftatlanta thegavoice-merging-update-cdiff-imported-post-categories`.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function cmd_thegavoice_update_post_categories( array $pos_args, array $assoc_args ): void {
		global $wpdb;

		// Parent category ID to move all categories under.
		$main_parent_cat_id   = 50157;
		$main_parent_cat_name = 'Georgia Voice';
		
		// Quickly validate that our main parent category ID and name match.
		$cat = get_category( $main_parent_cat_id );
		if ( ! $cat || $cat->name !== $main_parent_cat_name ) {
			WP_CLI::error( 'Category with ID ' . $main_parent_cat_id . ' does not exist or has a different name.' );
		}

		// Loop all imported posts by CDiff.
		// phpcs:disable
		// WordPress.DB.DirectDatabaseQuery.DirectQuery WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT wpm.post_id
				FROM {$wpdb->postmeta} wpm
				JOIN {$wpdb->posts} p
					ON wpm.post_id = p.ID AND p.post_type = 'post' AND p.post_status = 'publish'
				WHERE wpm.meta_key = %s",
				ContentDiffMigrator::SAVED_META_LIVE_POST_ID
			)
		);
		// phpcs:enable
		WP_CLI::line( 'Found ' . count( $post_ids ) . ' posts to update.' );
		foreach ( $post_ids as $key_post_id => $post_id ) {
			$response = $this->taxonomy->move_category_tree_under_new_top_parent( $post_id, $main_parent_cat_id );
			$result   = $response ? 'Success' : 'ERROR';
			WP_CLI::line( sprintf( '%s (%d/%d) PostID %d msg: %s', $result, $key_post_id + 1, count( $post_ids ), $post_id, wp_json_encode( $response ) ) );
		}
	}
}
