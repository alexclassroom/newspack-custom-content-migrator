<?php
/**
 * Importer for Bailiwick sites.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use DateInterval;
use DateTime;
use Exception;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Logic\Taxonomy;
use Newspack\MigrationTools\Logic\UsersHelper;
use Newspack\MigrationTools\NMT;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use NewspackCustomContentMigrator\Logic\Concrete5Xml;
use Psr\Log\LoggerInterface;
use simplehtmldom\HtmlDocument;
use WP_CLI;
use WP_Error;

class JEPBailiwickMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * Logger for CLI output.
	 *
	 * @var LoggerInterface Logger instance.
	 */
	private LoggerInterface $cli_logger;
	/**
	 * Logger for file output.
	 *
	 * @var LoggerInterface Logger instance.
	 */
	private LoggerInterface $file_logger;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->cli_logger  = CliLog::get_logger( 'bw' );
		$this->file_logger = FileLog::get_logger( 'bw' );
		if ( ! defined( 'NP_LIVE' ) ) {
			NMT::exit_with_message( 'NP_LIVE constant is not defined. Please add it in wp-config.php with the value of the live site.' );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {

		$xml_file = [
			'type'        => 'assoc',
			'name'        => 'xml-file',
			'description' => 'Path to XML file - can also be a url',
			'optional'    => false,
		];

		$refresh = [
			'type'        => 'flag',
			'name'        => 'refresh-existing',
			'description' => 'Refresh existing articles',
			'optional'    => true,
		];

		$from_date = [
			'type'        => 'assoc',
			'name'        => 'from-date',
			'description' => 'From date in format YYYY-MM-DD. For example 2024-11-14',
			'optional'    => false,
		];
		$to_date   = [
			'type'        => 'assoc',
			'name'        => 'to-date',
			'description' => 'To date in format YYYY-MM-DD. From date in format YYYY-MM-DD. For example 2024-10-31',
			'optional'    => false,
		];

		WP_CLI::add_command(
			'newspack-content-migrator bw-download-xml',
			self::get_command_closure( 'cmd_download_xml' ),
			[
				'shortdesc' => 'Download XML files from date range.',
				'synopsis'  => [
					$from_date,
					$to_date,
					[
						'type'        => 'assoc',
						'name'        => 'base-url',
						'description' => 'Url including auth string – you should get this from the publisher',
						'optional'    => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'output-dir',
						'description' => 'Optional. Where to put the downloaded xml files – defaults to current dir', // TODO. Are these inclusive?
						'optional'    => true,
					],
					[
						'type'        => 'flag',
						'name'        => 'overwrite',
						'description' => 'Optional. Whether to overwrite existing files with the same file name when writing the xml to disk. If not set the download will append a number to the filename.',
						'optional'    => true,
					],
					[
						'type'        => 'assoc',
						'name'        => 'days-pr-file',
						'description' => 'Optional. How many days in each file downloaded. Defaults to 10.',
						'optional'    => true,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator bw-import-articles-from-xml',
			self::get_command_closure( 'cmd_import_articles_from_xml' ),
			[
				'shortdesc' => 'Import articles from an XML file.',
				'synopsis'  => [
					$xml_file,
					$refresh,
				],
			]
		);
	}

	/**
	 * Callback for the `bw-download-xml` command.
	 *
	 * Downloads XML files for a given date range.
	 *
	 * @param array $pos_args   Positional arguments from WP_CLI.
	 * @param array $assoc_args Associative arguments from WP_CLI.
	 *
	 * @throws \Exception If something goes wrong.
	 */
	public function cmd_download_xml( array $pos_args, array $assoc_args ): void {
		$from_date    = $assoc_args['from-date'];
		$to_date      = $assoc_args['to-date'];
		$base_url     = $assoc_args['base-url'];
		$output_dir   = $assoc_args['output-dir'] ?? '';
		$overwrite    = $assoc_args['overwrite'] ?? false;
		$days_pr_file = $assoc_args['days-pr-file'] ?? 10;

		if ( ! empty( $output_dir ) ) {
			if ( ! file_exists( $output_dir ) ) {
				NMT::exit_with_message( sprintf( 'Output directory for the XML file "%s" does not exist', $output_dir ) );
			}
		}

		$date_format_for_url  = 'd/m/Y';
		$short_iso8601_format = 'Y-m-d';
		foreach ( $this->get_date_range_chunks( $from_date, $to_date, $days_pr_file ) as $chunk ) {

			$url = sprintf(
				'%s&from=%s&to=%s',
				$base_url,
				$chunk['from']->format( $date_format_for_url ),
				$chunk['to']->format( $date_format_for_url )
			); // This assumes that we use '&' because the url needs auth.

			$domain   = wp_parse_url( $base_url, PHP_URL_HOST );
			$filename = sanitize_file_name( sprintf( '%s-%s-%s.xml', $domain, $chunk['from']->format( $short_iso8601_format ), $chunk['to']->format( $short_iso8601_format ) ) );
			if ( ! empty( $output_dir ) ) {
				if ( ! file_exists( $output_dir ) ) {
					NMT::exit_with_message( sprintf( 'Output directory for the XML file "%s" does not exist', $output_dir ) );
				}
				$filename = trailingslashit( $output_dir ) . $filename;
			}
			$this->cli_logger->info(
				sprintf(
					'Downloading XML for %s to %s',
					$chunk['from']->format( $short_iso8601_format ),
					$chunk['to']->format( $short_iso8601_format )
				),
				[
					'destination' => $filename,
					'url'         => $url,
				]
			);

			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get, WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- all good comes to those that wait.
			$response = wp_remote_get( $url, [ 'timeout' => 10 ] );

			if ( is_wp_error( $response ) ) {
				NMT::exit_with_message( sprintf( 'HTTP request failed fetching %s with message %s', $url, $response->get_error_message() ) );
			}

			if ( ! $overwrite ) {
				$counter   = 0;
				$file_info = pathinfo( $filename );

				// Loop until we find a unique filename.
				while ( file_exists( $filename ) ) {
					++$counter;
					$filename = $file_info['dirname'] . '/' . $file_info['filename'] . '_' . $counter . '.' . $file_info['extension'];
				}
			}

			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- we kinda need to save the file ;)
			file_put_contents( $filename, wp_remote_retrieve_body( $response ) );
		}
	}

	/**
	 * Get date range chunks.
	 *
	 * @param string $from_date The start date in format YYYY-MM-DD.
	 * @param string $to_date  The end date in format YYYY-MM-DD.
	 * @param int    $chunk_size How many days of articles to put in each file.
	 *
	 * @return array An array of date ranges.
	 * @throws \DateMalformedStringException If something went very wrong in parsing the dates.
	 */
	private function get_date_range_chunks( string $from_date, string $to_date, int $chunk_size ): array {

		$short_iso8601_format = 'Y-m-d';
		$start                = DateTime::createFromFormat( $short_iso8601_format, $from_date );
		if ( ! $start ) {
			NMT::exit_with_message( sprintf( 'Invalid start date %s', $from_date ), [ $this->cli_logger ] );
		}
		$end = DateTime::createFromFormat( $short_iso8601_format, $to_date );
		if ( ! $end ) {
			NMT::exit_with_message( sprintf( 'Invalid end date %s', $to_date ), [ $this->cli_logger ] );
		}

		// Make sure the end date is inclusive by adding one day.
		$end->modify( '+1 day' );

		$interval = new DateInterval( "P{$chunk_size}D" );

		$current_start = clone $start;
		$chunks        = [];

		// Loop until we reach the end date.
		while ( $current_start < $end ) {
			// Calculate the next end date.
			$current_end = clone $current_start;
			$current_end->add( $interval );

			// If the calculated end date exceeds the original end date, limit it.
			if ( $current_end > $end ) {
				$current_end = $end;
			}

			// Subtract one day to make the range inclusive.
			$modified_end = $current_end->modify( '-1 day' );

			// Store the current range in the result.
			$chunks[] = array(
				'from' => $current_start,
				'to'   => $modified_end,
			);

			// Move the start date to the next interval.
			$current_start = clone $current_end;
			$current_start->modify( '+1 day' ); // Start from the next day.
		}

		return array_reverse( $chunks );
	}


	/**
	 * Callback for the `bw-import-articles-from-xml` command.
	 *
	 * Imports articles from an XML file - url or local file.
	 *
	 * @param array $pos_args   Positional arguments from WP_CLI.
	 * @param array $assoc_args Associative arguments from WP_CLI.
	 *
	 * @throws Exception If things go wrong.
	 */
	public function cmd_import_articles_from_xml( array $pos_args, array $assoc_args ): void {
		$xml_file_path = $assoc_args['xml-file'];
		$refresh       = $assoc_args['refresh-existing'] ?? false;
		$xml_fetcher   = null;
		try {
			$this->cli_logger->info( 'Importing articles from XML file', [ 'xml_file' => $xml_file_path ] );
			$xml_fetcher = new Concrete5Xml( $xml_file_path );
		} catch ( Exception $o_0 ) {
			NMT::exit_with_message( $o_0->getMessage(), [ $this->cli_logger ] );
		}

		$taxonomy_helper = new Taxonomy();
		$home_url        = home_url();

		$file_logger = FileLog::get_logger( 'bw-article-import' );

		$counter = 0;
		foreach ( $xml_fetcher->get_articles() as $article ) {
			++$counter;
			if ( 0 === $counter % 10 ) {
				$this->cli_logger->info( sprintf( 'Processed %s articles', $counter ) );
			}

			$post = [
				'post_type'   => 'post',
				'post_status' => 'publish',
			];

			$url         = $article['url'];
			$path        = wp_parse_url( $article['url'], PHP_URL_PATH );
			$existing_id = $this->get_post_id_by_old_path( $path );
			if ( ! empty( $existing_id ) ) {
				if ( ! $refresh ) {
					$this->cli_logger->notice(
						'Article already imported',
						[
							'path' => $path,
							'ID'   => $existing_id,
						]
					);
					continue;
				}
				$post['ID'] = $existing_id;
			}

			$post['meta_input']['_old_path'] = $path;

			$category_name = $article['category'];
			$cat_id        = $taxonomy_helper->get_or_create_category_by_name_and_parent_id( $category_name, 0 );
			if ( ! is_wp_error( $cat_id ) ) {
				$post['post_category'] = [ $cat_id ];
			}

			$tags = explode( ',', $article['tags'] );
			if ( ! empty( $tags ) ) {
				$post['tags_input'] = $tags;
			}

			$post['post_title'] = $article['title'];
			$post['post_name']  = basename( $url );

			$post['post_author'] = $this->get_author( $article['author'] );

			$post['post_date'] = $article['datePublic'];
			$lead              = $article['lead'];
			if ( ! empty( $lead ) ) {
				$post['meta_input']['newspack_post_subtitle'] = $lead;
			}

			$post['post_content'] = $article['description'] . $article['content'];

			$post_id = wp_insert_post( $post );
			if ( is_wp_error( $post_id ) ) {
				$this->cli_logger->error( 'Failed to import article', [ 'error' => $post_id ] );
				continue;
			}

			$this->cli_logger->notice(
				'Imported article',
				[
					'post_id' => $post_id,
					'to_url'  => "$home_url/?p=$post_id",
				]
			);
			$file_logger->notice(
				'Imported article',
				[
					'post_id'  => $post_id,
					'from_url' => $url,
				]
			);

			$content = get_post_field( 'post_content', $post_id );

			// Array holds callbacks to be applied to the content.
			$replacers = [];
			if ( str_contains( $content, '<h1>' ) ) {
				$replacers[] = fn( $html_doc ) => $this->fix_h1s( $html_doc, $post_id );
			}
			if ( str_contains( $content, '<img ' ) ) {
				$replacers[] = fn( $html_doc ) => $this->get_inline_images( $html_doc, $post_id );
			}
			if ( ! empty( $replacers ) ) {
				$html_doc = new HtmlDocument( $content );
				// Run the replacers on the same HTMLDocument so we don't have to parse the content multiple times.
				foreach ( $replacers as $replacer ) {
					$replacer( $html_doc, $post_id );
				}
				$content = $html_doc->save();

				wp_update_post(
					[
						'ID'           => $post_id,
						'post_content' => $content,
					]
				);
			}

			$this->set_featured_image_on_post( $post_id, $article['image'] );
		}
	}

	/**
	 * Replace all h1 tags with h2 tags.
	 *
	 * @param HtmlDocument $html_doc The HTML document to replace in.
	 * @param int          $post_id  The post ID.
	 *
	 * @return void
	 */
	private function fix_h1s( HtmlDocument $html_doc, int $post_id ): void {
		$h1s = $html_doc->find( 'h1' );
		foreach ( $h1s as $h1 ) {
			$h1->tag = 'h2';
		}
	}

	/**
	 * Find images in HTMLDocument content and download them and replace with image blocks.
	 *
	 * @param HtmlDocument $html_doc The HTML document to replace in.
	 * @param int          $post_id  The post ID.
	 *
	 * @return void
	 */
	private function get_inline_images( HtmlDocument $html_doc, int $post_id ): void {
		$gb_blocks = new GutenbergBlockGenerator();
		$images    = $html_doc->find( 'img' );
		if ( empty( $images ) ) {
			$this->cli_logger->info( 'No inline images found in post', [ 'post_id' => $post_id ] );

			return;
		}
		// TODO. What about alt texts? I think they are in some img tags.
		foreach ( $images as $img ) {
			$src = $img?->getAttribute( 'src' );
			if ( ! $src ) {
				// Not much we can do without that.
				continue;
			}
			if ( ! str_starts_with( $src, 'http' ) ) {
				$src = NP_LIVE . $src;
			}
			$att_id = $this->get_image_from_url( $src, $post_id );
			if ( is_wp_error( $att_id ) ) {
				$this->cli_logger->error(
					'Failed to import inline image',
					[
						'post_id' => $post_id,
						'src'     => $src,
						'error'   => $att_id,
					]
				);
				continue;
			}
			FileLog::get_logger( 'bw-images' )->notice(
				'Imported inline image',
				[
					'post_id' => $post_id,
					'src'     => $src,
				]
			);

			$img->outertext = serialize_block(
				$gb_blocks->get_image(
					get_post( $att_id ),
					'full',
					false
				)
			);
		}
	}

	/**
	 * Downloads and sets the featured image on a post.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $image_url Image URL to download image from.
	 *
	 * @return void
	 */
	private function set_featured_image_on_post( int $post_id, string $image_url ): void {
		$image_url = trim( $image_url );
		if ( empty( $image_url ) ) {
			return;
		}
		$data['_old_featured_image'] = $image_url;
		if ( ! str_starts_with( $image_url, 'http' ) ) {
			$image_url = trailingslashit( NP_LIVE ) . trim( $image_url, '/' );
		}

		$attachment_id = $this->get_image_from_url( $image_url, $post_id );
		if ( ! is_wp_error( $attachment_id ) ) {
			$data['_thumbnail_id'] = $attachment_id;
			FileLog::get_logger( 'bw-images' )->notice(
				'Imported featured image',
				[
					'post_id' => $post_id,
					'image'   => $image_url,
				]
			);
		} else {
			FileLog::get_logger( 'bw-images' )->error(
				'Could not download featured image',
				[
					'post_id' => $post_id,
					'image'   => $image_url,
				]
			);
		}
		wp_update_post(
			[
				'ID'         => $post_id,
				'meta_input' => $data,
			]
		);
	}

	/**
	 * Create or get author from the name.
	 *
	 * @param string $author_name The author name.
	 *
	 * @return int The author ID or 0 if not found.
	 */
	private function get_author( string $author_name ): int {
		$default_author = 1; // TODO. There is some default author logic that we need to implement.
		if ( empty( $author_name ) ) {
			return $default_author;
		}

		try {
			$user = UsersHelper::create_or_get_user(
				[
					'user_login' => $author_name,
					'role'       => 'contributor_no_edit',
				],
				$author_name
			);

			return $user->ID;
		} catch ( Exception $e ) {
			$message = sprintf( 'Could not create user with name %s', $author_name );
			$this->cli_logger->error( $message, [ 'error' => $e ] );
			$this->file_logger->critical( $message, [ 'error' => $e ] );

			return $default_author;
		}
	}


	/**
	 * Get image from URL and return the attachment ID.
	 *
	 * @param string $url     The URL to the image.
	 * @param int    $post_id The post ID.
	 *
	 * @return int|WP_Error
	 */
	private function get_image_from_url( string $url, int $post_id ): int|WP_Error {
		if ( empty( $url ) ) {
			return new WP_Error( '', 'No image URL provided' );
		}
		// TODO. Should this be optional? The predict?
		$path = self::get_predicted_file_path( $post_id, $url );
		if ( ! file_exists( $path ) ) {
			$featured_image_id = Attachments::import_attachment_for_post( $post_id, $url );
		} else {
			$featured_image_id = Attachments::maybe_get_existing_attachment_id( $path );
			if ( empty( $featured_image_id ) ) {
				$featured_image_id = Attachments::import_attachment_for_post( $post_id, $url );
			}
		}

		return $featured_image_id;
	}

	/**
	 * Get the WP post ID by the old path.
	 *
	 * @param string $old_path The old path.
	 *
	 * @return int The post ID or 0 if not found.
	 */
	public function get_post_id_by_old_path( string $old_path ): int {
		$posts = get_posts(
			[
				'meta_key'   => '_old_path',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value' => $old_path,
			]
		);

		return $posts[0]->ID ?? 0;
	}

	/**
	 * Probably delete this if it makes no sense.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $filename The filename.
	 *
	 * @return string The path.
	 */
	public static function get_predicted_file_path( int $post_id, string $filename ): string {
		// TODO. This assumes that images are uploaded like that with the date. Are they always?
		$upload_dir = wp_upload_dir( get_post_time( 'Y/m', false, $post_id ), false );

		$sanitized_filename = sanitize_file_name( basename( $filename ) );

		return trailingslashit( $upload_dir['path'] ) . $sanitized_filename;
	}
}
