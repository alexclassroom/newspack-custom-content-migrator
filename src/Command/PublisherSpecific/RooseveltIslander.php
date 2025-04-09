<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack_Scraper_Migrator_Util;
use NewspackContentConverter\ContentPatcher\Patchers\BlockDecodePatcher;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use NewspackCustomContentMigrator\Utils\ConsoleColor;
use NewspackCustomContentMigrator\Utils\ConsoleTable;
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

		WP_CLI::add_command(
			'newspack-content-migrator roosevelt-islander-fix-block-encoded-content',
			self::get_command_closure( 'cmd_roosevelt_islander_fix_block_encoded_content' ),
			[
				'shortdesc' => 'Fix block encoded content in the Roosevelt Islander blog',
				'synopsis'  => [],
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
	}

	/**
	 * This function will update posts with block-encoded content that was created as a result of the NCC conversion process.
	 *
	 * @return void
	 */
	public function cmd_roosevelt_islander_fix_block_encoded_content() {
		global $wpdb;

		$affected_posts_query = $wpdb->prepare(
			"SELECT 
    			YEAR(post_date) as post_year, 
    			COUNT(*) as counter 
			FROM $wpdb->posts 
			WHERE ID IN (
				SELECT 
				    id 
				FROM $wpdb->posts 
				WHERE post_status = 'publish' 
				  AND post_type = 'post' 
				  AND post_content LIKE %s
				) 
			GROUP BY YEAR(post_date);",
			'%' . $wpdb->esc_like( 'BLOCK-ENCODED' ) . '%'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$summary_of_affected_posts = $wpdb->get_results( $affected_posts_query );

		ConsoleTable::output_data( $summary_of_affected_posts );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$affected_posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
    				ID, 
    				post_content,
    				YEAR(post_date) as post_year 
				FROM $wpdb->posts 
				WHERE post_status = 'publish' 
				  AND post_type = 'post' 
				  AND post_content LIKE %s 
				ORDER BY post_year DESC, ID DESC",
				'%' . $wpdb->esc_like( 'BLOCK-ENCODED' ) . '%'
			)
		);

		$decoder = new BlockDecodePatcher();

		ConsoleColor::white( 'Total # of Affected Posts' )->bright_yellow( count( $affected_posts ) )->output();
		foreach ( $affected_posts as $post ) {
			ConsoleColor::white( 'Post ID' )->bright_yellow( $post->ID )->white( 'Year' )->bright_yellow( $post->post_year )->output();

			preg_match_all( '/(?:<!-- wp:preformatted -->\s*)?<pre .+>(?:\s*)?(\[BLOCK-ENCODED:.+\])(?:\s*)?<\/pre>(?:\s*)?(?:<!-- \/wp:preformatted -->)?/', $post->post_content, $matches, PREG_SET_ORDER );
			if ( empty( $matches ) ) {
				ConsoleColor::magenta( 'No encoded blocks found' )->output();
				continue;
			}

			$post_content = $post->post_content;
			foreach ( $matches as $match ) {
				// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
				// 0 => '<!-- wp:preformatted --><pre>[BLOCK-ENCODED:...]</pre><!-- /wp:preformatted -->'
				// 1 => [BLOCK-ENCODED:...]

				$decoded_content = $decoder->patch_blocks_contents( $match[1], $post->post_content, $post->ID );
				if ( ! str_contains( $decoded_content, '<!-- wp:' ) ) {
					$decoded_content = "<!-- wp:html -->\n{$decoded_content}\n<!-- /wp:html -->";
				}
				$post_content = str_replace( $match[0], $decoded_content, $post_content );
			}

			if ( $post_content === $post->post_content ) {
				ConsoleColor::magenta( 'Content was not correctly decoded' )->output();
				continue;
			}

			wp_save_post_revision( $post->ID );

			$maybe_updated = wp_update_post(
				[
					'ID'           => $post->ID,
					'post_content' => $post_content,
				]
			);

			if ( is_wp_error( $maybe_updated ) ) {
				ConsoleColor::red( 'POST NOT UPDATED' )->output();
				continue;
			}

			ConsoleColor::green( 'Updated' )->output();
		}
	}
}
