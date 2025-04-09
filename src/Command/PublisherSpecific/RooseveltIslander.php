<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Exception;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack_Scraper_Migrator_Util;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use Newspack_Scraper_Migrator_HTML_Parser;
use WP_CLI;
use WP_Filesystem_Base;

/**
 * Roosevelt Islander-specific migrator and content fixer.
 */
class RooseveltIslander implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * Registers commands.
	 *
	 * @inheritDoc
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator roosevelt-islander-content-fixes',
			self::get_command_closure( 'cmd_roosevelt_islander_content_fixes' ),
			[
				'shortdesc' => 'Re-run the content logic for all posts so that past erroneous formatting can be fixed.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'post-ids',
						'description' => 'A list of post IDs to fix. If not provided, all posts will be processed.',
						'optional'    => true,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator roosevelt-islander-create-scrape-urls',
			self::get_command_closure( 'cmd_roosevelt_islander_create_scrape_urls' ),
			[
				'shortdesc' => 'Create a list of URLs to scrape from the Roosevelt Islander blog.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'url-list-filename',
						'description' => 'The full path of the file containing the URLs that must be scraped.',
						'optional'    => false,
						'default'     => 'roosevelt-islander-scrape-urls.json',
					],
				],
			]
		);
	}

	/**
	 * This command will cycle through all posts and re-run the scraping logic that was used initially to scrape
	 * posts from the Roosevelt Islander blog. This was necessary because the scraping logic was being worked
	 * on up until the last minute, and applying the changes retroactively was not feasible.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function cmd_roosevelt_islander_content_fixes( array $args, array $assoc_args ) {
		$scraper_processor = new Newspack_Scraper_Migrator_HTML_Parser();
		$scraper_util      = new Newspack_Scraper_Migrator_Util();

		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		/* @var $wp_filesystem WP_Filesystem_Base */
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		require_once trailingslashit( WP_PLUGIN_DIR ) . 'newspack-scraper-migrator/configs/config-roosevelt-islander.php';

		$scraped_urls_path = WP_CONTENT_DIR . '/plugins/newspack-scraper-migrator/scraped_urls/';

		if ( isset( $assoc_args['post-ids'] ) ) {
			$post_ids = array_map( 'intval', explode( ',', $assoc_args['post-ids'] ) );

			foreach ( $post_ids as $post_id ) {
				$post = get_post( $post_id );

				if ( ! $post ) {
					WP_CLI::warning(
						sprintf(
							'Post ID: %d - No post found.',
							$post_id
						)
					);
					continue;
				}

				WP_CLI::log(
					sprintf(
						'Processing post ID: %d - %s',
						$post_id,
						$post->guid
					)
				);

				$maybe_updated = $this->update_content( $post, $post->guid, $scraped_urls_path, $scraper_processor, $scraper_util, $wp_filesystem );

				if ( null === $maybe_updated ) {
					WP_CLI::log( 'NO UPDATE NEEDED' );
					update_post_meta( $post->ID, '_newspack_skip_content_fix', true );
					continue;
				}

				if ( false === $maybe_updated ) {
					WP_CLI::warning(
						sprintf(
							'Failed to update post ID: %d',
							$post->ID
						)
					);
					continue;
				}

				WP_CLI::success( 'Content updated successfully' );
				update_post_meta( $post->ID, '_newspack_skip_content_fix', true );
			}
			return;
		}

		$post_urls = get_post_urls( [ 'https://rooseveltislander.blogspot.com/sitemap.xml' ] );

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$skippable_posts = $wpdb->get_results(
			"SELECT meta_value, post_id FROM $wpdb->postmeta WHERE meta_key = '_newspack_skip_content_fix'",
			OBJECT_K
		);

		foreach ( $post_urls as $url ) {
			if ( isset( $skippable_posts[ $url ] ) ) {
				WP_CLI::log(
					sprintf(
						'SKIPPING URL: %s',
						$url
					)
				);
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$post = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $wpdb->posts WHERE guid = %s",
					$url
				)
			);

			if ( ! $post ) {
				WP_CLI::warning(
					sprintf(
						'URL: %s - No post found.',
						$url
					)
				);
				continue;
			}

			WP_CLI::success(
				sprintf(
					'URL: %s Post ID: %d',
					$url,
					$post->ID
				)
			);

			$maybe_updated = $this->update_content( $post, $url, $scraped_urls_path, $scraper_processor, $scraper_util, $wp_filesystem );

			if ( null === $maybe_updated ) {
				WP_CLI::log( 'NO UPDATE NEEDED' );
				update_post_meta( $post->ID, '_newspack_skip_content_fix', true );
				continue;
			}

			if ( false === $maybe_updated ) {
				WP_CLI::warning(
					sprintf(
						'Failed to update post ID: %d',
						$post->ID
					)
				);
				continue;
			}

			WP_CLI::success( 'Content updated successfully' );
			update_post_meta( $post->ID, '_newspack_skip_content_fix', true );
		}
	}

	/**
	 * This function handles updating the content of a post using the latest scraping logic.
	 *
	 * @param object                                $post The post object to update.
	 * @param string                                $url The URL of the post.
	 * @param string                                $scraped_urls_path The path to the scraped URLs directory.
	 * @param Newspack_Scraper_Migrator_HTML_Parser $scraper_processor The HTML parser for scraping.
	 * @param Newspack_Scraper_Migrator_Util        $scraper_util The utility class for scraping.
	 * @param WP_Filesystem_Base                    $wp_filesystem The WordPress filesystem object.
	 *
	 * @return bool|null
	 */
	private function update_content( object $post, string $url, string $scraped_urls_path, Newspack_Scraper_Migrator_HTML_Parser $scraper_processor, Newspack_Scraper_Migrator_Util $scraper_util, WP_Filesystem_Base $wp_filesystem ): ?bool {
		$scraper_processor->dom_crawler_clear();

		$url_filename = str_replace( '/', '__', $url );
		$url_filename = sanitize_file_name( $url_filename );

		if ( ! file_exists( $scraped_urls_path . $url_filename ) ) {
			$html = $scraper_util->newspack_scraper_migrator_get_raw_html( $url );
			$wp_filesystem->put_contents( $scraped_urls_path . $url_filename, $html );
		}

		$scraper_processor->dom_crawler_add_html( $wp_filesystem->get_contents( $scraped_urls_path . $url_filename ) );

		$content = $scraper_processor->parse_content( '', $url );

		if ( $content === $post->post_content ) {
			return null;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->update(
			$wpdb->posts,
			[
				'post_content' => $content,
			],
			[
				'ID' => $post->ID,
			]
		);
	}

	public function cmd_roosevelt_islander_create_scrape_urls( array $args, array $assoc_args ) {
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		/* @var $wp_filesystem WP_Filesystem_Base */
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$filename = $assoc_args['url-list-filename'];

		if ( file_exists( $filename ) ) {
			$file_name    = pathinfo( $filename, PATHINFO_FILENAME );
			$extension    = pathinfo( $filename, PATHINFO_EXTENSION );
			$new_filename = $file_name . '-bak-' . gmdate( 'Ymd-His' ) . '.' . $extension;

			WP_CLI::warning(
				sprintf(
					'File %s already exists. Moving to %s',
					$filename,
					$new_filename
				)
			);

			$wp_filesystem->move( $filename, $new_filename );
		}

		require_once trailingslashit( WP_PLUGIN_DIR ) . 'newspack-scraper-migrator/configs/config-roosevelt-islander.php';

		if ( ! $wp_filesystem->exists( $filename ) && $wp_filesystem->touch( $filename ) ) {
			WP_CLI::log( 'URL list file created' );
		}

		if ( ! $wp_filesystem->exists( $filename ) ) {
			WP_CLI::error( 'Failed to create URL list file' );
		}

		$post_urls = get_post_urls( [ 'https://rooseveltislander.blogspot.com/sitemap.xml' ] );

		global $wpdb;
		foreach ( $post_urls as $url ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$post_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE guid = %s",
					$url
				)
			);

			if ( $post_id ) {
				WP_CLI::log(
					sprintf(
						'URL: %s - 👍.',
						$url
					)
				);
				continue;
			}

			WP_CLI::log(
				sprintf(
					'URL: %s - 🌎.',
					$url
				)
			);

			$wp_filesystem->put_contents( $filename, "\"$url\"," . PHP_EOL, FILE_APPEND );
		}

		$wp_filesystem->put_contents( $filename, '[' . $wp_filesystem->get_contents( $filename ) . ']' );
		file_put_contents( $filename, '[' . file_get_contents( $filename ) . ']' );
	}
}
