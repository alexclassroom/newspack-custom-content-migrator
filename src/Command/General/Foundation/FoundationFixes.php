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
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\Posts;
use Newspack\MigrationTools\Util\JsonIterator;
use Newspack\MigrationTools\Util\Log\MultiLog;
use Newspack\MigrationTools\Hooks\MemoryCleanupHook;
use WP_CLI;
use ReflectionClass;
use Exception;

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
			'newspack-content-migrator foundation-migrate-seo-meta',
			self::get_command_closure( 'cmd_migrate_seo_meta' ),
			[
				'shortdesc' => 'Migrates SEO meta for posts received from their export.',
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
					[
						'type'        => 'assoc',
						'name'        => 'oid-to-migrate',
						'description' => 'OIDs to migrate (comma separated).',
						'optional'    => true,
						'repeating'   => false,
					],
					[
						'type'        => 'flag',
						'name'        => 'is-slideshow',
						'description' => 'Is the post a slideshow.',
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

		WP_CLI::add_command(
			'newspack-content-migrator foundation-fix-featured-image',
			self::get_command_closure( 'cmd_fix_featured_image' ),
			[
				'shortdesc' => 'Fixes the featured image for posts received from their export.',
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
					[
						'type'        => 'assoc',
						'name'        => 'media-local-path',
						'description' => 'Local path to the media files (The folder usually have a `mediaserver` folder).',
						'optional'    => true,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator foundation-set-image-license-meta',
			self::get_command_closure( 'cmd_set_image_license_meta' ),
			[
				'shortdesc' => 'Sets the image license meta for posts received from their export.',
				'synopsis'  => [
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

		WP_CLI::add_command(
			'newspack-content-migrator foundation-switch-offline-posts-to-private',
			self::get_command_closure( 'cmd_switch_offline_posts_to_private' ),
			[
				'shortdesc' => 'Switches offline posts to private.',
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
			'newspack-content-migrator foundation-fix-latin-characters',
			self::get_command_closure( 'cmd_fix_latin_characters' ),
			[
				'shortdesc' => 'Fixes latin characters.',
				'synopsis'  => [],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator foundation-fix-large-image-blocks',
			self::get_command_closure( 'cmd_fix_large_image_block' ),
			[
				'shortdesc' => 'Fixes large image block.',
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
	 * Migrates SEO meta for posts received from their export.
	 * Callable for 'newspack-content-migrator foundation-migrate-seo-meta' command.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_migrate_seo_meta( array $args, array $assoc_args ): void {
		global $wpdb;

		$logger = MultiLog::get_cli_and_file_logger( __FUNCTION__ );

		$post_json_file = $assoc_args['post-json-file'];
		$start_from     = $assoc_args['start-from'] ?? 0;
		$end_at         = $assoc_args['end-at'] ?? 0;
		$is_slideshow   = $assoc_args['is-slideshow'] ?? false;
		$oid_to_migrate = isset( $assoc_args['oid-to-migrate'] ) ? explode( ',', $assoc_args['oid-to-migrate'] ) : [];

		$raw_posts = $this->json_iterator->items( $post_json_file );
		foreach ( $raw_posts as $index => $post ) {
			// Flush memory every 50 steps, with 1 seconds of sleeping time.
			MemoryCleanupHook::cleanup( 1, $index, 50 );

			if ( $index < ( $start_from - 1 ) || ( $end_at > 0 && $index >= $end_at ) ) {
				continue;
			}

			if ( ! empty( $oid_to_migrate ) && ! in_array( $post->oid, $oid_to_migrate ) ) {
				continue;
			}

			$existing_post_id = Posts::get_post_by_unique_identifier( $post->oid );

			if ( ! $existing_post_id ) {
				$logger->error( sprintf( '[%d] Post %d not migrated', $index, $post->oid ) );
				continue;
			}

			if ( $is_slideshow ) {
				if ( isset( $post->metaTitle ) && ! empty( $post->metaTitle ) ) {
					update_post_meta( $existing_post_id, '_yoast_wpseo_title', wp_strip_all_tags( $post->metaTitle ) );
				}
				if ( isset( $post->metaDescription ) && ! empty( $post->metaDescription ) ) {
					update_post_meta( $existing_post_id, '_yoast_wpseo_metadesc', wp_strip_all_tags( $post->metaDescription ) );
				}
			} else {
				if ( isset( $post->title ) && ! empty( $post->title ) ) {
					update_post_meta( $existing_post_id, '_yoast_wpseo_title', wp_strip_all_tags( $post->title ) );
				}
				if ( isset( $post->description ) && ! empty( $post->description ) ) {
					update_post_meta( $existing_post_id, '_yoast_wpseo_metadesc', wp_strip_all_tags( $post->description ) );
				}
			}

			if ( isset( $post->canonical ) && ! empty( $post->canonical ) ) {
				update_post_meta( $existing_post_id, '_yoast_wpseo_canonical', wp_strip_all_tags( $post->canonical ) );
			}

			$logger->info( sprintf( '[%d] Migrated SEO meta for post %d', $index, $existing_post_id ) );
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
	 * Switches offline posts to private.
	 * Callable for 'newspack-content-migrator foundation-switch-offline-posts-to-private' command.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_switch_offline_posts_to_private( array $args, array $assoc_args ): void {
		global $wpdb;

		$logger = MultiLog::get_cli_and_file_logger( __FUNCTION__ );

		$post_json_file = $assoc_args['post-json-file'];
		$start_from     = $assoc_args['start-from'] ?? 0;
		$end_at         = $assoc_args['end-at'] ?? 0;
		$is_slideshow   = $assoc_args['is-slideshow'] ?? false;
		$oid_to_migrate = isset( $assoc_args['oid-to-migrate'] ) ? explode( ',', $assoc_args['oid-to-migrate'] ) : [];

		$raw_posts = $this->json_iterator->items( $post_json_file );
		foreach ( $raw_posts as $index => $post ) {
			// Flush memory every 50 steps, with 1 seconds of sleeping time.
			MemoryCleanupHook::cleanup( 1, $index, 50 );

			if ( $index < ( $start_from - 1 ) || ( $end_at > 0 && $index >= $end_at ) ) {
				continue;
			}

			if ( ! empty( $oid_to_migrate ) && ! in_array( $post->oid, $oid_to_migrate ) ) {
				continue;
			}

			$existing_post_id = Posts::get_post_by_unique_identifier( $post->oid );

			if ( ! $existing_post_id ) {
				$logger->error( sprintf( '[%d] Post %d not migrated', $index, $post->oid ) );
				continue;
			}

			if ( 'Offline' === $post->status ) {
				// @phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$wpdb->posts,
					[
						'post_status' => 'private',
					],
					[ 'ID' => $existing_post_id ]
				);

				$logger->info( sprintf( '[%d] Switched post %d to private', $index, $existing_post_id ) );
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

			$all_possible_raw_images = iterator_to_array( $this->json_iterator->filtered_items( $image_json_file, 'oid', $post->imageLinks ) );

			foreach ( $post->imageLinks as $post_image_oid ) {
				$possible_raw_images = array_filter(
					$all_possible_raw_images,
					function ( $raw_image ) use ( $post_image_oid ) {
						return $raw_image->oid === $post_image_oid;
					}
				);

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

	/**
	 * Gets the posts with cropped images.
	 * Callable for 'newspack-content-migrator foundation-fix-featured-image' command.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_fix_featured_image( array $args, array $assoc_args ): void {
		$logger = MultiLog::get_cli_and_file_logger( __FUNCTION__ );

		$post_json_file   = $assoc_args['post-json-file'];
		$image_json_file  = $assoc_args['image-json-file'];
		$media_local_path = $assoc_args['media-local-path'] ?? '';
		$start_from       = $assoc_args['start-from'] ?? 0;
		$end_at           = $assoc_args['end-at'] ?? 0;

		$raw_posts = $this->json_iterator->items( $post_json_file );
		foreach ( $raw_posts as $index => $post ) {
			if ( '43797170' !== $post->oid ) {
				continue;
			}

			if ( $index < ( $start_from - 1 ) || ( $end_at > 0 && $index >= $end_at ) ) {
				continue;
			}

			$existing_post_id = Posts::get_post_by_unique_identifier( $post->oid );

			if ( ! $existing_post_id ) {
				$logger->error( sprintf( 'Post %d not migrated', $post->oid ) );
				continue;
			}

			if ( empty( $post->imageLinks ) ) {
				$logger->warning( sprintf( 'Post %d has no images', $existing_post_id ) );
				continue;
			}

			$possible_magnum_image_ids = [];

			$all_possible_raw_images = iterator_to_array( $this->json_iterator->filtered_items( $image_json_file, 'oid', $post->imageLinks ) );

			foreach ( $post->imageLinks as $post_image_oid ) {
				$possible_raw_images = array_filter(
					$all_possible_raw_images,
					function ( $raw_image ) use ( $post_image_oid ) {
						return intval( $raw_image->oid ) === $post_image_oid;
					}
				);

				if ( 1 === count( $possible_raw_images ) ) {
					$raw_image = current( $possible_raw_images );

					if ( isset( $raw_image->placements ) && in_array( 'magnum', $raw_image->placements, true ) ) {
						$possible_magnum_image_ids[] = $raw_image;
					}
				}
			}

			if ( ! empty( $possible_magnum_image_ids ) ) {
				$magnum_image  = $possible_magnum_image_ids[0];
				$attachment_id = $this->migrate_raw_attachment( $magnum_image, $existing_post_id, $media_local_path );

				if ( is_wp_error( $attachment_id ) ) {
					$logger->error( sprintf( 'Error migrating image %s for post %s from magnum: %s', $post_image_oid, $existing_post_id, $attachment_id->get_error_message() ) );
					continue;
				}

				set_post_thumbnail( $existing_post_id, $attachment_id );
				$logger->info( sprintf( 'Set featured image for post %s from magnum to %s', $existing_post_id, $attachment_id ) );
			} else {
				// set the first image as the featured image.
				$raw_first_image = $post->imageLinks[0];
				$first_image     = array_filter(
					$all_possible_raw_images,
					function ( $raw_image ) use ( $raw_first_image ) {
						return $raw_image->oid == $raw_first_image;
					}
				);

				if ( empty( $first_image ) ) {
					$logger->warning( sprintf( 'Post %d has no first image', $existing_post_id ) );
					continue;
				}

				$first_image = current( $first_image );

				$attachment_id = $this->migrate_raw_attachment( $first_image, $existing_post_id, $media_local_path );

				if ( is_wp_error( $attachment_id ) ) {
					$logger->error( sprintf( 'Error migrating image %s for post %s from first image: %s', $post_image_oid, $existing_post_id, $attachment_id->get_error_message() ) );
					continue;
				}

				set_post_thumbnail( $existing_post_id, $attachment_id );
				$logger->info( sprintf( 'Set featured image for post %s from first image to %s', $existing_post_id, $attachment_id ) );
			}
		}

		$logger->info( sprintf( '[%d] Done', $index ) );

		$logger->info( sprintf( 'Check the log file for migration details: %s', __FUNCTION__ . '.log' ) );
	}

	/**
	 * Migrate raw attachment.
	 *
	 * @param object $raw_attachment Raw attachment.
	 * @param int    $post_id   Post ID.
	 * @param string $media_local_path Local path to the media files.
	 *
	 * @return int|WP_Error Attachment ID or WP_Error.
	 */
	private function migrate_raw_attachment( object $raw_attachment, int $post_id, string $media_local_path = '' ) {
		$caption = isset( $raw_attachment->caption ) ? wp_strip_all_tags( $raw_attachment->caption ) : '';
		$alt     = isset( $raw_attachment->alt ) ? wp_strip_all_tags( $raw_attachment->alt ) : '';

		$credit_url = '';
		$credit     = '';
		if ( isset( $raw_attachment->credit ) && ! empty( $raw_attachment->credit ) ) {
			if ( str_contains( $raw_attachment->credit, 'href' ) ) {
				preg_match( '/href="([^"]+)"/', $raw_attachment->credit, $image_credit_url );
				$credit_url = $image_credit_url[1];
			}

			$credit = wp_strip_all_tags( $raw_attachment->credit );
		}

		$meta_input = [
			'meta_input' => [
				'_media_credit'     => $credit,
				'_media_credit_url' => $credit_url,
			],
		];

		$local_media_path = isset( $raw_attachment->path ) ? rtrim( $media_local_path, '/' ) . '/' . ltrim( $raw_attachment->path, '/' ) : '';
		$media_path       = is_file( $local_media_path ) ? $local_media_path : $raw_attachment->url;

		return Attachments::import_external_file( $media_path, null, $caption, null, $alt, $post_id, $meta_input, '', true, $raw_attachment->oid );
	}

	/**
	 * Sets the image license meta for posts received from their export.
	 * Callable for 'newspack-content-migrator foundation-set-image-license-meta' command.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_set_image_license_meta( array $args, array $assoc_args ): void {
		$logger = MultiLog::get_cli_and_file_logger( __FUNCTION__ );

		$image_json_file = $assoc_args['image-json-file'];
		$start_from      = $assoc_args['start-from'] ?? 0;
		$end_at          = $assoc_args['end-at'] ?? 0;

		$raw_images = $this->json_iterator->items( $image_json_file );
		foreach ( $raw_images as $index => $image ) {
			if ( $index < ( $start_from - 1 ) || ( $end_at > 0 && $index >= $end_at ) ) {
				continue;
			}

			$existing_post_id = Attachments::get_attachment_by_unique_identifier( $image->oid );

			if ( ! isset( $image->license ) || empty( $image->license ) ) {
				continue;
			}

			if ( ! $existing_post_id ) {
				$logger->error( sprintf( 'Image %d not found', $image->oid ) );
				continue;
			}

			update_post_meta( $existing_post_id, 'distribution_notes', $image->license );
			$logger->info( sprintf( 'Set distribution notes for image %d to %s', $existing_post_id, $image->license ) );
		}

		$logger->info( sprintf( 'Check the log file for migration details: %s', __FUNCTION__ . '.log' ) );
	}

	/**
	 * Fixes latin characters.
	 * Callable for 'newspack-content-migrator foundation-fix-latin-characters' command.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_fix_latin_characters( array $args, array $assoc_args ): void {
		global $wpdb;

		$logger = MultiLog::get_cli_and_file_logger( __FUNCTION__ );

		$logger->info( 'Starting Latin character detection and fixing...' );

		// Step 1: Detect problematic content.
		$logger->info( 'Step 1: Detecting content with Latin1 encoding issues...' );

		$problematic_content = $this->detect_latin1_content();

		if ( empty( $problematic_content ) ) {
			$logger->info( 'No problematic content found. All content appears to be properly encoded.' );
			return;
		}

		$logger->info( sprintf( 'Found %d items with potential Latin1 encoding issues', count( $problematic_content ) ) );

		// Step 2: Fix the problematic content.
		$logger->info( 'Step 2: Fixing Latin1 encoding issues...' );

		$fixed_count = 0;
		foreach ( $problematic_content as $index => $item ) {
			// Flush memory every 50 steps, with 1 seconds of sleeping time.
			MemoryCleanupHook::cleanup( 1, $index, 50 );

			$result = $this->fix_latin1_content( $item );
			if ( $result ) {
				++$fixed_count;
			}
		}

		$logger->info( sprintf( 'Successfully fixed %d out of %d items', $fixed_count, count( $problematic_content ) ) );
		$logger->info( 'Check the CSV file for migration details: latin1-fixes.csv' );
		$logger->info( sprintf( 'Check the log file for migration details: %s', __FUNCTION__ . '.log' ) );
	}

	/**
	 * Fixes large image block.
	 * Callable for 'newspack-content-migrator foundation-fix-large-image-blocks' command.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_fix_large_image_block( array $args, array $assoc_args ): void {
		global $wpdb;

		$logger = MultiLog::get_cli_and_file_logger( __FUNCTION__ );

		$post_json_file = $assoc_args['post-json-file'];
		$start_from     = $assoc_args['start-from'] ?? 0;
		$end_at         = $assoc_args['end-at'] ?? 0;

		$raw_posts = $this->json_iterator->items( $post_json_file );

		foreach ( $raw_posts as $index => $post ) {
			// Flush memory every 50 steps, with 1 seconds of sleeping time.
			MemoryCleanupHook::cleanup( 1, $index, 50 );

			if ( $index < ( $start_from - 1 ) || ( $end_at > 0 && $index >= $end_at ) ) {
				continue;
			}

			$existing_post_id = Posts::get_post_by_unique_identifier( $post->oid );

			if ( ! isset( $post->imageLinks ) || empty( $post->imageLinks ) ) {
				continue;
			}

			if ( ! $existing_post_id ) {
				$logger->error( sprintf( 'Post %d not found', $post->oid ) );
				continue;
			}

			$post_content = get_post_field( 'post_content', $existing_post_id );

			if ( false === strpos( $post_content, '"className":"align' ) ) {
				continue;
			}

			// Process the post content to remove className from image blocks with alignment classes.
			$updated_post_content = $this->remove_alignment_classname_from_image_blocks( $post_content );

			if ( $updated_post_content !== $post_content ) {
				$wpdb->update(
					$wpdb->posts,
					[ 'post_content' => $updated_post_content ],
					[ 'ID' => $existing_post_id ]
				);

				$logger->info( sprintf( 'Post %d is fixed', $existing_post_id ) );
			}
		}

		$logger->info( sprintf( 'Check the log file for migration details: %s', __FUNCTION__ . '.log' ) );
	}

	/**
	 * Detects content with Latin1 encoding issues.
	 *
	 * @return array Array of problematic content items.
	 */
	private function detect_latin1_content(): array {
		global $wpdb;

		$logger              = MultiLog::get_cli_and_file_logger( __FUNCTION__ );
		$problematic_content = [];

		// Check posts content.
		$logger->info( 'Checking posts content...' );
		$posts = $wpdb->get_results( "SELECT ID, post_title, post_content, post_excerpt FROM {$wpdb->posts} WHERE post_status IN ('publish', 'draft', 'private')" );

		foreach ( $posts as $post ) {
			$fields_to_check = [
				'post_title'   => $post->post_title,
				'post_content' => $post->post_content,
				'post_excerpt' => $post->post_excerpt,
			];

			foreach ( $fields_to_check as $field => $content ) {
				if ( ! empty( $content ) && $this->has_latin1_issues( $content ) ) {
					$problematic_content[] = [
						'type'      => 'post',
						'id'        => $post->ID,
						'field'     => $field,
						'content'   => $content,
						'table'     => $wpdb->posts,
						'id_column' => 'ID',
					];
				}
			}
		}

		// Check post meta.
		$logger->info( 'Checking post meta...' );
		$post_meta = $wpdb->get_results( "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value IS NOT NULL AND meta_value != ''" );

		foreach ( $post_meta as $meta ) {
			if ( $this->has_latin1_issues( $meta->meta_value ) ) {
				$problematic_content[] = [
					'type'      => 'post_meta',
					'id'        => $meta->meta_id, // Use meta_id as the primary key.
					'field'     => $meta->meta_key,
					'content'   => $meta->meta_value,
					'table'     => $wpdb->postmeta,
					'id_column' => 'meta_id',
					'post_id'   => $meta->post_id, // Store post_id for reference.
				];
			}
		}

		// Check user meta.
		$logger->info( 'Checking user meta...' );
		$user_meta = $wpdb->get_results( "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_value IS NOT NULL AND meta_value != ''" );

		foreach ( $user_meta as $meta ) {
			if ( $this->has_latin1_issues( $meta->meta_value ) ) {
				$problematic_content[] = [
					'type'      => 'user_meta',
					'id'        => $meta->user_id,
					'field'     => $meta->meta_key,
					'content'   => $meta->meta_value,
					'table'     => $wpdb->usermeta,
					'id_column' => 'user_id',
				];
			}
		}

		$logger->info( sprintf( 'Detection complete. Found %d problematic items', count( $problematic_content ) ) );
		return $problematic_content;
	}

	/**
	 * Checks if content has Latin1 encoding issues.
	 *
	 * @param string $content Content to check.
	 * @return bool True if content has Latin1 issues.
	 */
	private function has_latin1_issues( string $content ): bool {
		// Check for raw Latin1 mojibake characters stored directly in database.
		$raw_mojibake_patterns = [
			'/â€œ/', // Left double quotation mark (").
			'/â€/',  // Right double quotation mark (").
			'/â€™/',  // Right single quotation mark (').
			'/â€˜/',  // Left single quotation mark (').
			'/â€"/',  // Em dash (—).
			'/â€"/',  // En dash (–).
			'/â€¦/',  // Horizontal ellipsis (…).
		];

		foreach ( $raw_mojibake_patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				return true;
			}
		}

		// Check for common Latin1 mojibake patterns.
		$latin1_patterns = [
			'/&acirc;&#128;&#153;/', // Right single quotation mark (').
			'/&acirc;&#128;&#156;/', // Left double quotation mark (").
			'/&acirc;&#128;&#157;/', // Right double quotation mark (").
			'/&acirc;&#128;&#152;/', // Left single quotation mark (').
			'/&acirc;&#128;&#148;/', // Em dash (—).
			'/&acirc;&#128;&#147;/', // En dash (–).
			'/&acirc;&#128;&#166;/', // Horizontal ellipsis (…).
		];

		foreach ( $latin1_patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				return true;
			}
		}

		// Check for single HTML entities that are common Latin1 issues.
		$single_entity_patterns = [
			'/&#146;/',  // Right single quotation mark (').
			'/&#147;/',  // Left double quotation mark (").
			'/&#148;/',  // Right double quotation mark (").
			'/&#145;/',  // Left single quotation mark (').
			'/&#151;/',  // Em dash (—).
			'/&#150;/',  // En dash (–).
			'/&#133;/',  // Horizontal ellipsis (…).
			'/&#8217;/', // Right single quotation mark (').
			'/&#8220;/', // Left double quotation mark (").
			'/&#8221;/', // Right double quotation mark (").
			'/&#8216;/', // Left single quotation mark (').
			'/&#8212;/', // Em dash (—).
			'/&#8211;/', // En dash (–).
			'/&#8230;/', // Horizontal ellipsis (…).
		];

		foreach ( $single_entity_patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				return true;
			}
		}

		// Check for other common Latin1 characters that might be encoded incorrectly.
		// This catches patterns like &acirc;&#128;&#153; (double entities).
		if ( preg_match( '/&[a-z]+;&#[0-9]+;/', $content ) ) {
			return true;
		}

		// Check for single HTML entities with 3-digit numbers (like &#146;, &#147;, &#148;).
		// These are more likely to be Latin1 encoding issues than 1-2 digit entities.
		if ( preg_match( '/&#[1-9][0-9]{2};/', $content ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Fixes Latin1 encoding issues in content.
	 *
	 * @param array $item Item with problematic content.
	 * @return bool True if content was fixed successfully.
	 */
	private function fix_latin1_content( array $item ): bool {
		global $wpdb;

		$logger           = MultiLog::get_cli_and_file_logger( __FUNCTION__ );
		$original_content = $item['content'];
		$fixed_content    = $this->fix_latin1_string( $original_content );

		if ( $original_content === $fixed_content ) {
			$logger->warning( sprintf( 'No changes needed for %s %d field %s', $item['type'], $item['id'], $item['field'] ) );
			return false;
		}

		// Handle serialized meta data for post_meta and user_meta.
		if ( in_array( $item['type'], [ 'post_meta', 'user_meta' ], true ) ) {
			$fixed_content = $this->fix_serialized_meta_content( $original_content, $fixed_content );
		}

		// Update the content in the database.
		$update_data      = [];
		$where_conditions = [];

		// For meta tables, always update meta_value field.
		if ( in_array( $item['type'], [ 'post_meta', 'user_meta' ], true ) ) {
			$update_data = [ 'meta_value' => $fixed_content ];

			if ( 'user_meta' === $item['type'] ) {
				// For user meta, use user_id and meta_key in WHERE conditions.
				$where_conditions = [
					'user_id'  => $item['id'],
					'meta_key' => $item['field'],
				];
			} else {
				// For post meta, use meta_id in WHERE conditions (primary key).
				$where_conditions = [
					'meta_id' => $item['id'],
				];
			}
		} else {
			// For regular posts table, update the specific field.
			$update_data      = [ $item['field'] => $fixed_content ];
			$where_conditions = [ $item['id_column'] => $item['id'] ];
		}

		$result = $wpdb->update( $item['table'], $update_data, $where_conditions );

		if ( false === $result || 0 === $result ) {
			$logger->error( sprintf( 'Failed to update %s %d field %s: %s', $item['type'], $item['id'], $item['field'], $wpdb->last_error ) );
			return false;
		}

		$logger->info( sprintf( 'Successfully fixed %s %d field %s', $item['type'], $item['id'], $item['field'] ) );

		// Output detailed change information to CSV.
		$this->log_change_to_csv( $item, $original_content, $fixed_content, $where_conditions );

		return true;
	}

	/**
	 * Logs change details to a CSV file for review and analysis.
	 *
	 * @param array  $item              Item being updated.
	 * @param string $original_content  Original content.
	 * @param string $fixed_content     Fixed content.
	 * @param array  $where_conditions  WHERE conditions used for update.
	 */
	private function log_change_to_csv( array $item, string $original_content, string $fixed_content, array $where_conditions ): void {
		$csv_file = 'latin1-fixes.csv';

		// Create CSV header if file doesn't exist.
		if ( ! file_exists( $csv_file ) ) {
			$headers = [
				'Timestamp',
				'Table',
				'Type',
				'ID',
				'Field',
				'Post ID',
				'Where Conditions',
				'Original Content',
				'Fixed Content',
				'Content Length (Before)',
				'Content Length (After)',
				'Changes Made',
			];
			file_put_contents( $csv_file, implode( ',', array_map( [ $this, 'escape_csv_field' ], $headers ) ) . "\n" );
		}

		// Prepare CSV row data.
		$row_data = [
			gmdate( 'Y-m-d H:i:s' ),
			$item['table'],
			$item['type'],
			$item['id'],
			$item['field'],
			isset( $item['post_id'] ) ? $item['post_id'] : '',
			json_encode( $where_conditions ),
			$original_content,
			$fixed_content,
			strlen( $original_content ),
			strlen( $fixed_content ),
			$this->summarize_changes( $original_content, $fixed_content ),
		];

		// Append to CSV file.
		file_put_contents( $csv_file, implode( ',', array_map( [ $this, 'escape_csv_field' ], $row_data ) ) . "\n", FILE_APPEND | LOCK_EX );
	}

	/**
	 * Escapes CSV field content to prevent breaking the CSV format.
	 *
	 * @param string $field Field content to escape.
	 * @return string Escaped field content.
	 */
	private function escape_csv_field( string $field ): string {
		// Replace double quotes with two double quotes and wrap in quotes if contains comma, quote, or newline.
		$escaped = str_replace( '"', '""', $field );
		if ( strpos( $field, ',' ) !== false || strpos( $field, '"' ) !== false || strpos( $field, "\n" ) !== false || strpos( $field, "\r" ) !== false ) {
			$escaped = '"' . $escaped . '"';
		}
		return $escaped;
	}

	/**
	 * Summarizes the changes made to the content.
	 *
	 * @param string $original_content Original content.
	 * @param string $fixed_content   Fixed content.
	 * @return string Summary of changes.
	 */
	private function summarize_changes( string $original_content, string $fixed_content ): string {
		$changes = [];

		// Count Latin1 double entities replaced.
		$latin1_double_patterns = [
			'&acirc;&#128;&#153;' => 'Right single quote',
			'&acirc;&#128;&#156;' => 'Left double quote',
			'&acirc;&#128;&#157;' => 'Right double quote',
			'&acirc;&#128;&#152;' => 'Left single quote',
			'&acirc;&#128;&#148;' => 'Em dash',
			'&acirc;&#128;&#147;' => 'En dash',
			'&acirc;&#128;&#166;' => 'Ellipsis',
		];

		foreach ( $latin1_double_patterns as $pattern => $description ) {
			$count = substr_count( $original_content, $pattern );
			if ( $count > 0 ) {
				$changes[] = sprintf( '%d %s(s)', $count, $description );
			}
		}

		// Count Latin1 single entities replaced.
		$latin1_single_patterns = [
			'&#146;'  => 'Right single quote',
			'&#147;'  => 'Left double quote',
			'&#148;'  => 'Right double quote',
			'&#145;'  => 'Left single quote',
			'&#151;'  => 'Em dash',
			'&#150;'  => 'En dash',
			'&#133;'  => 'Ellipsis',
			'&#8217;' => 'Right single quote',
			'&#8220;' => 'Left double quote',
			'&#8221;' => 'Right double quote',
			'&#8216;' => 'Left single quote',
			'&#8212;' => 'Em dash',
			'&#8211;' => 'En dash',
			'&#8230;' => 'Ellipsis',
		];

		foreach ( $latin1_single_patterns as $pattern => $description ) {
			$count = substr_count( $original_content, $pattern );
			if ( $count > 0 ) {
				$changes[] = sprintf( '%d %s(s)', $count, $description );
			}
		}

		// Check for other HTML entities.
		$html_entity_count = preg_match_all( '/&[a-z]+;&#[0-9]+;/', $original_content );
		if ( $html_entity_count > 0 ) {
			$changes[] = sprintf( '%d other double HTML entities', $html_entity_count );
		}

		// Check for single HTML entities.
		$single_html_entity_count = preg_match_all( '/&#[1-9][0-9]{2};/', $original_content );
		if ( $single_html_entity_count > 0 ) {
			$changes[] = sprintf( '%d other single HTML entities', $single_html_entity_count );
		}

		// Check if content was serialized.
		if ( $this->is_serialized( $original_content ) ) {
			$changes[] = 'Serialized data processed';
		}

		return empty( $changes ) ? 'No specific changes detected' : implode( ', ', $changes );
	}

	/**
	 * Fixes Latin1 encoding issues in a string.
	 *
	 * @param string $content Content to fix.
	 * @return string Fixed content.
	 */
	private function fix_latin1_string( string $content ): string {
		// Fix raw Latin1 mojibake characters stored directly in database.
		$raw_mojibake_fixes = [
			'â€œ' => '"', // Left double quotation mark.
			'â€'  => '"', // Right double quotation mark.
			'â€™' => "'", // Right single quotation mark.
			'â€˜' => "'", // Left single quotation mark.
			'â€"' => '—', // Em dash.
			'â€"' => '–', // En dash.
			'â€¦' => '…', // Horizontal ellipsis.
		];

		// Replace raw mojibake characters first.
		$fixed_content = str_replace( array_keys( $raw_mojibake_fixes ), array_values( $raw_mojibake_fixes ), $content );

		// Common Latin1 to UTF-8 character mappings for double entities.
		$latin1_double_entities = [
			'&acirc;&#128;&#153;' => "'", // Right single quotation mark.
			'&acirc;&#128;&#156;' => '"', // Left double quotation mark.
			'&acirc;&#128;&#157;' => '"', // Right double quotation mark.
			'&acirc;&#128;&#152;' => "'", // Left single quotation mark.
			'&acirc;&#128;&#148;' => '—', // Em dash.
			'&acirc;&#128;&#147;' => '–', // En dash.
			'&acirc;&#128;&#166;' => '…', // Horizontal ellipsis.
		];

		// Common Latin1 to UTF-8 character mappings for single entities.
		$latin1_single_entities = [
			'&#146;'  => "'", // Right single quotation mark.
			'&#147;'  => '"', // Left double quotation mark.
			'&#148;'  => '"', // Right double quotation mark.
			'&#145;'  => "'", // Left single quotation mark.
			'&#151;'  => '—', // Em dash.
			'&#150;'  => '–', // En dash.
			'&#133;'  => '…', // Horizontal ellipsis.
			'&#8217;' => "'", // Right single quotation mark.
			'&#8220;' => '"', // Left double quotation mark.
			'&#8221;' => '"', // Right double quotation mark.
			'&#8216;' => "'", // Left single quotation mark.
			'&#8212;' => '—', // Em dash.
			'&#8211;' => '–', // En dash.
			'&#8230;' => '…', // Horizontal ellipsis.
		];

		// Replace double Latin1 entities with proper UTF-8 characters.
		$fixed_content = str_replace( array_keys( $latin1_double_entities ), array_values( $latin1_double_entities ), $fixed_content );

		// Replace single Latin1 entities with proper UTF-8 characters.
		$fixed_content = str_replace( array_keys( $latin1_single_entities ), array_values( $latin1_single_entities ), $fixed_content );

		// Handle other potential Latin1 issues.
		// Convert HTML entities to their proper characters.
		$fixed_content = html_entity_decode( $fixed_content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Clean up any remaining HTML tags if they're not needed.
		// This is optional and depends on your content requirements.
		// $fixed_content = wp_strip_all_tags( $fixed_content );

		return $fixed_content;
	}

	/**
	 * Handles fixing Latin1 issues in serialized meta data.
	 *
	 * @param string $original_content Original content.
	 * @param string $fixed_content Fixed content.
	 * @return string Properly fixed content.
	 */
	private function fix_serialized_meta_content( string $original_content, string $fixed_content ): string {
		// Check if the content is serialized.
		if ( ! $this->is_serialized( $original_content ) ) {
			return $fixed_content;
		}

		// Unserialize the content.
		$unserialized_data = @unserialize( $original_content );
		if ( false === $unserialized_data ) {
			// If unserialization fails, return the fixed string content.
			return $fixed_content;
		}

		// Recursively fix the unserialized data.
		$fixed_data = $this->fix_serialized_data( $unserialized_data );

		// Serialize the fixed data back.
		return serialize( $fixed_data );
	}

	/**
	 * Recursively fixes Latin1 issues in serialized data structures.
	 *
	 * @param mixed $data Data to fix.
	 * @return mixed Fixed data.
	 */
	private function fix_serialized_data( $data ) {
		if ( is_string( $data ) ) {
			return $this->fix_latin1_string( $data );
		}

		if ( is_array( $data ) ) {
			$fixed_array = [];
			foreach ( $data as $key => $value ) {
				$fixed_key                 = is_string( $key ) ? $this->fix_latin1_string( $key ) : $key;
				$fixed_array[ $fixed_key ] = $this->fix_serialized_data( $value );
			}
			return $fixed_array;
		}

		if ( is_object( $data ) ) {
			// Handle objects by converting to array, fixing, then back to object.
			$object_class = get_class( $data );
			$data_array   = (array) $data;
			$fixed_array  = $this->fix_serialized_data( $data_array );

			// Try to recreate the object if possible.
			if ( class_exists( $object_class ) ) {
				try {
					$reflection = new ReflectionClass( $object_class );
					if ( $reflection->isInstantiable() ) {
						$new_object = new $object_class();
						foreach ( $fixed_array as $property => $value ) {
							if ( property_exists( $new_object, $property ) ) {
								$reflection_property = $reflection->getProperty( $property );
								$reflection_property->setAccessible( true );
								$reflection_property->setValue( $new_object, $value );
							}
						}
						return $new_object;
					}
				} catch ( Exception $e ) {
					// If object recreation fails, return the fixed array.
					// Log the error for debugging purposes.
					// Object recreation failed, continuing with array representation.
					unset( $e ); // Suppress unused variable warning.
				}
			}

			return $fixed_array;
		}

		// Return other data types as-is.
		return $data;
	}

	/**
	 * Checks if a string is serialized.
	 *
	 * @param string $data String to check.
	 * @return bool True if serialized.
	 */
	private function is_serialized( string $data ): bool {
		// Check for serialized string pattern.
		if ( ! is_string( $data ) || empty( $data ) ) {
			return false;
		}

		// Check for serialized array or object patterns.
		if ( preg_match( '/^a:\d+:{.*}$/', $data ) || preg_match( '/^O:\d+:"[^"]+":\d+:{.*}$/', $data ) ) {
			// Additional validation: try to unserialize and check for errors.
			$unserialized = @unserialize( $data );
			return false !== $unserialized;
		}

		return false;
	}

	/**
	 * Removes className property from Gutenberg Image Block headers when they contain alignment classes.
	 *
	 * @param string $post_content The post content to process.
	 * @return string The processed post content with className removed from alignment blocks.
	 */
	private function remove_alignment_classname_from_image_blocks( string $post_content ): string {
		// First, remove className from Gutenberg Image Block headers with alignment classes.
		$pattern = '/<!-- wp:image\s*({[^}]*"(?:className":"[^"]*align[^"]*"|align":"(?:center|left|right|wide|full))[^-]*})\s*-->/';

		$post_content = preg_replace_callback(
			$pattern,
			function ( $matches ) {
				$block_attributes = $matches[1];

				// Remove the className property and its value from the JSON attributes.
				$block_attributes = preg_replace(
					'/"className":"[^"]*align[^"]*",?\s*/',
					'',
					$block_attributes
				);

				// Remove the align property and its value from the JSON attributes.
				$block_attributes = preg_replace(
					'/"align":"(?:center|left|right|wide|full)",?\s*/',
					'',
					$block_attributes
				);

				// Clean up any trailing commas that might be left.
				$block_attributes = preg_replace( '/,\s*}/', '}', $block_attributes );
				$block_attributes = preg_replace( '/{\s*,/', '{', $block_attributes );

				return '<!-- wp:image ' . $block_attributes . ' -->';
			},
			$post_content
		);

		// Then, fix duplicated alignment classes in figure tags.
		$post_content = $this->fix_duplicated_alignment_classes( $post_content );

		return $post_content;
	}

	/**
	 * Fixes duplicated alignment classes in figure tags.
	 *
	 * @param string $post_content The post content to process.
	 * @return string The processed post content with duplicated alignment classes removed.
	 */
	private function fix_duplicated_alignment_classes( string $post_content ): string {
		// Pattern to match figure tags with duplicated alignment classes.
		$pattern = '/<figure\s+class="([^"]*align(?:center|left|right)[^"]*align(?:center|left|right)[^"]*)"/';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
				$classes = $matches[1];

				// Remove duplicated alignment classes, keeping only the first occurrence.
				$classes = preg_replace(
					'/(align(?:center|left|right))(?=.*\1)/',
					'',
					$classes
				);

				// Clean up extra spaces and commas.
				$classes = preg_replace( '/\s+/', ' ', $classes );
				$classes = trim( $classes );

				return '<figure class="' . $classes . '"';
			},
			$post_content
		);
	}
}
