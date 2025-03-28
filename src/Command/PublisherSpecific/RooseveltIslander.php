<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Exception;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack_Scraper_Migrator_Util;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use Newspack_Scraper_Migrator_HTML_Parser;
use WP_CLI;

class RooseveltIslander implements RegisterCommandInterface {

	use WpCliCommandTrait;

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

	public function cmd_roosevelt_islander_content_fixes( array $args, array $assoc_args ) {
		$scraper_processor = new Newspack_Scraper_Migrator_HTML_Parser();
		$scraper_util      = new Newspack_Scraper_Migrator_Util();
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

				$maybe_updated = $this->update_content( $post, $post->guid, $scraped_urls_path, $scraper_processor, $scraper_util );

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

			$maybe_updated = $this->update_content( $post, $url, $scraper_processor, $scraper_util );

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

	private function update_content( object $post, string $url, string $scraped_urls_path, Newspack_Scraper_Migrator_HTML_Parser $scraper_processor, Newspack_Scraper_Migrator_Util $scraper_util ): ?bool {
		$scraper_processor->dom_crawler_clear();

		$url_filename = str_replace( '/', '__', $url );
		$url_filename = sanitize_file_name( $url_filename );

		if ( ! file_exists( $scraped_urls_path . $url_filename ) ) {
			$html = $scraper_util->newspack_scraper_migrator_get_raw_html( $url );
			file_put_contents( $scraped_urls_path . $url_filename, $html );
		}

		$scraper_processor->dom_crawler_add_html( file_get_contents( $scraped_urls_path . $url_filename ) );

		$content = $scraper_processor->parse_content( '', $url );

		if ( $content === $post->post_content ) {
			return null;
		}

		global $wpdb;

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

			rename( $filename, $new_filename );
		}

		require_once trailingslashit( WP_PLUGIN_DIR ) . 'newspack-scraper-migrator/configs/config-roosevelt-islander.php';

		$handle = fopen( $filename, 'w' );
		WP_CLI::log( 'URL list file created' );

		$post_urls = get_post_urls( [ 'https://rooseveltislander.blogspot.com/sitemap.xml' ] );

		global $wpdb;
		foreach ( $post_urls as $url ) {
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

			fwrite( $handle, "\"$url\"," . PHP_EOL );
		}

		fclose( $handle );
		file_put_contents( $filename, '[' . file_get_contents( $filename ) . ']' );
	}
}
