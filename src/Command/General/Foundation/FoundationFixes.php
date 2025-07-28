<?php
/**
 * Foundation data migration commands for migrating exported content from Foundation.
 *
 * @phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\General\Foundation;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use Newspack\MigrationTools\Logic\GuestContributorsHelper;
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\Taxonomy;
use Newspack\MigrationTools\Logic\SimpleLocalAvatars;
use Newspack\MigrationTools\Logic\UsersHelper;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Logic\Posts;
use Newspack\MigrationTools\Util\JsonIterator;
use Newspack\MigrationTools\Util\Log\MultiLog;
use Newspack\MigrationTools\Util\CsvWriter;
use Newspack\MigrationTools\Util\CustomRedirectGenerator;
use WP_CLI;

class FoundationFixes implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * JSON iterator.
	 *
	 * @var null|SJsonIterator
	 */
	private JsonIterator $json_iterator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->json_iterator = new JsonIterator();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator foundation-fix-slideshow-tags',
			self::get_command_closure( 'cmd_fix_slideshow_tags' ),
			[
				'shortdesc' => 'Fixes the slideshow tags for slideshows received from their export.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'slideshow-json-file',
						'description' => 'Path to the JSON file containing the slideshows (e.g. `Slideshow.json`).',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'start-from',
						'description' => 'Start from the post with the given index.',
						'optional'    => true,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'end-at',
						'description' => 'End at the post with the given index.',
						'optional'    => true,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator foundation-migrate-features-as-tags',
			self::get_command_closure( 'cmd_migrate_features_as_tags' ),
			[
				'shortdesc' => 'Migrates features as tags for features received from their export.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'post-json-file',
						'description' => 'Path to the JSON file containing the posts (e.g. `Post.json`).',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'start-from',
						'description' => 'Start from the post with the given index.',
						'optional'    => true,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'end-at',
						'description' => 'End at the post with the given index.',
						'optional'    => true,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator foundation-fix-post-wide-templates',
			self::get_command_closure( 'cmd_fix_post_wide_templates' ),
			[
				'shortdesc' => 'Fixes the post wide templates for posts received from their export.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'post-json-file',
						'description' => 'Path to the JSON file containing the posts (e.g. `Post.json`).',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'start-from',
						'description' => 'Start from the post with the given index.',
						'optional'    => true,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'end-at',
						'description' => 'End at the post with the given index.',
						'optional'    => true,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator foundation-get-posts-with-cropped-images',
			self::get_command_closure( 'cmd_get_posts_with_cropped_images' ),
			[
				'shortdesc' => 'Gets the posts with cropped images.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'post-json-file',
						'description' => 'Path to the JSON file containing the posts (e.g. `Post.json`).',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'image-json-file',
						'description' => 'Path to the JSON file containing the images (e.g. `Image.json`).',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'start-from',
						'description' => 'Start from the post with the given index.',
						'optional'    => true,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'end-at',
						'description' => 'End at the post with the given index.',
						'optional'    => true,
						'repeating'   => false,
					],
				],
			]
		);
	}

	/**
	 * Fixes the slideshow tags for slideshows received from their export.
	 * Callable for 'newspack-content-migrator foundation-fix-slideshow-tags' command.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_fix_slideshow_tags( array $args, array $assoc_args ): void {
		global $wpdb;

		$logger = MultiLog::get_cli_and_file_logger( __FUNCTION__ );

		$slideshow_json_file = $assoc_args['slideshow-json-file'];
		$start_from          = $assoc_args['start-from'] ?? 0;
		$end_at              = $assoc_args['end-at'] ?? 0;

		$raw_slideshows = $this->json_iterator->items( $slideshow_json_file );
		foreach ( $raw_slideshows as $index => $slideshow ) {
			if ( $index < ( $start_from - 1 ) || ( $end_at > 0 && $index >= $end_at ) ) {
				continue;
			}

			$existing_slideshow_id = Posts::get_post_by_unique_identifier( $slideshow->oid );

			if ( ! $existing_slideshow_id ) {
				$logger->error( sprintf( '[%d] Slideshow %d not migrated', $index, $slideshow->oid ) );
				continue;
			}

			// Set post tags.
			$tags   = $slideshow->tags ?? [];
			$tags[] = 'Slideshow Gallery';

			if ( ! empty( $tags ) ) {
				wp_set_post_tags( $existing_slideshow_id, $tags );

				$logger->info( sprintf( '[%d] Set tags %s to slideshow %d', $index, implode( ', ', $tags ), $existing_slideshow_id ) );
			}
		}

		$logger->info( sprintf( 'Check the log file for migration details: %s', __FUNCTION__ . '.log' ) );
	}

	/**
	 * Migrates features as tags for features received from their export.
	 * Callable for 'newspack-content-migrator foundation-migrate-features-as-tags' command.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_migrate_features_as_tags( array $args, array $assoc_args ): void {
		global $wpdb;

		$logger = MultiLog::get_cli_and_file_logger( __FUNCTION__ );

		$post_json_file = $assoc_args['post-json-file'];
		$start_from     = $assoc_args['start-from'] ?? 0;
		$end_at         = $assoc_args['end-at'] ?? 0;

		$raw_posts = $this->json_iterator->items( $post_json_file );
		foreach ( $raw_posts as $index => $post ) {
			if ( $index < ( $start_from - 1 ) || ( $end_at > 0 && $index >= $end_at ) ) {
				continue;
			}

			$existing_post_id = Posts::get_post_by_unique_identifier( $post->oid );

			if ( ! $existing_post_id ) {
				$logger->error( sprintf( '[%d] Post %d not migrated', $index, $post->oid ) );
				continue;
			}

			// Add posts features as tags.
			$feature_tags = $post->features ?? [];

			if ( ! empty( $feature_tags ) ) {
				wp_set_post_tags( $existing_post_id, $feature_tags, true );

				$logger->info( sprintf( '[%d] Added featurestags "%s" to post %d', $index, implode( ', ', $feature_tags ), $existing_post_id ) );
			}

			// Add posts special placement as tags.
			$special_placement_tags = $post->specialPlacement ?? [];

			if ( ! empty( $special_placement_tags ) ) {
				wp_set_post_tags( $existing_post_id, $special_placement_tags, true );

				$logger->info( sprintf( '[%d] Added special placement tags "%s" to post %d', $index, implode( ', ', $special_placement_tags ), $existing_post_id ) );
			}
		}

		$logger->info( sprintf( 'Check the log file for migration details: %s', __FUNCTION__ . '.log' ) );
	}

	/**
	 * Fixes the post wide templates for posts received from their export.
	 * Callable for 'newspack-content-migrator foundation-fix-post-wide-templates' command.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_fix_post_wide_templates( array $args, array $assoc_args ): void {
		$logger = MultiLog::get_cli_and_file_logger( __FUNCTION__ );

		$post_json_file = $assoc_args['post-json-file'];
		$start_from     = $assoc_args['start-from'] ?? 0;
		$end_at         = $assoc_args['end-at'] ?? 0;

		$raw_posts = $this->json_iterator->items( $post_json_file );
		foreach ( $raw_posts as $index => $post ) {
			if ( $index < ( $start_from - 1 ) || ( $end_at > 0 && $index >= $end_at ) ) {
				continue;
			}

			$existing_post_id = Posts::get_post_by_unique_identifier( $post->oid );

			if ( ! $existing_post_id ) {
				$logger->error( sprintf( 'Post %d not migrated', $post->oid ) );
				continue;
			}

			if ( in_array( $post->layout, FoundationMigrator::WIDE_LAYOUTS_LIST, true ) ) {
				update_post_meta( $existing_post_id, '_wp_page_template', 'single-feature.php' );
				update_post_meta( $existing_post_id, 'newspack_featured_image_position', 'above' );

				$logger->info( sprintf( '[%d] Post %d has post wide layout %s', $index, $existing_post_id, $post->layout ) );
			}
		}

		$logger->info( sprintf( 'Check the log file for migration details: %s', __FUNCTION__ . '.log' ) );
	}

	/**
	 * Gets the posts with cropped images.
	 * Callable for 'newspack-content-migrator foundation-get-posts-with-cropped-images' command.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_get_posts_with_cropped_images( array $args, array $assoc_args ): void {
		$logger = MultiLog::get_cli_and_file_logger( __FUNCTION__ );

		$post_json_file                = $assoc_args['post-json-file'];
		$image_json_file               = $assoc_args['image-json-file'];
		$start_from                    = $assoc_args['start-from'] ?? 0;
		$end_at                        = $assoc_args['end-at'] ?? 0;
		$post_oids_with_cropped_images = [];

		$raw_posts = $this->json_iterator->items( $post_json_file );
		foreach ( $raw_posts as $index => $post ) {
			if ( $index < ( $start_from - 1 ) || ( $end_at > 0 && $index >= $end_at ) ) {
				continue;
			}

			$existing_post_id = Posts::get_post_by_unique_identifier( $post->oid );

			if ( ! $existing_post_id ) {
				$logger->error( sprintf( 'Post %d not migrated', $post->oid ) );
				continue;
			}

			foreach ( $post->imageLinks as $post_image_oid ) {
				$possible_raw_images = iterator_to_array( $this->json_iterator->filtered_items( $image_json_file, 'oid', $post_image_oid ) );

				$raw_image = null;

				if ( 1 === count( $possible_raw_images ) ) {
					$raw_image = $possible_raw_images[0];

					if ( isset( $raw_image->cropCoords ) && ! empty( $raw_image->cropCoords ) ) {
						if ( ! in_array( $existing_post_id, $post_oids_with_cropped_images, true ) ) {
							$post_oids_with_cropped_images[] = $existing_post_id;
							$logger->info( sprintf( 'Post %d has cropped image %s', $existing_post_id, $post_image_oid ) );
						}
					}
				}
			}
		}

		$logger->info( sprintf( 'Post OIDs with cropped images: %s', implode( ', ', $post_oids_with_cropped_images ) ) );

		$logger->info( sprintf( 'Check the log file for migration details: %s', __FUNCTION__ . '.log' ) );
	}
}
