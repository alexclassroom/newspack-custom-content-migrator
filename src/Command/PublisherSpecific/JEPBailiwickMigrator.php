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
use RuntimeException;
use Newspack\Guest_Contributor_Role;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Logic\Taxonomy;
use Newspack\MigrationTools\Logic\UsersHelper;
use Newspack\MigrationTools\Logic\Posts;
use Newspack\MigrationTools\NMT;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\PlainFileLog;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use NewspackCustomContentMigrator\Command\General\MultiBranded;
use NewspackCustomContentMigrator\Logic\Concrete5Xml;
use Psr\Log\LoggerInterface;
use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Bramus\Monolog\Formatter\ColorSchemes\DefaultScheme;
use Monolog\Level;
use simplehtmldom\HtmlDocument;
use WP_CLI;
use WP_Error;

class JEPBailiwickMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	const BRAND_NAME_BAILIWICK_JERSEY   = 'Bailiwick Express News Jersey';
	const BRAND_NAME_BAILIWICK_GUERNSEY = 'Bailiwick Express News Guernsey';

	const META_ORIGINAL_URL         = 'newspackmigration_original_url';
	const META_ORIGINAL_AUTHOR      = 'newspackmigration_original_author';
	const META_ORIGINAL_BYLINE_NODE = 'newspackmigration_original_byline_node';
	const META_DEFAULT_AUTHOR_RULE  = 'newspackmigration_default_author_rule';

	const WP_SUPPORTED_IMAGE_EXTENSIONS = [ 'png', 'jpg', 'jpeg', 'gif', 'webp', 'heic', 'heif', 'svg' ];

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
	 * Taxonomy logic.
	 *
	 * @var Taxonomy $taxonomy Taxonomy logic.
	 */
	private Taxonomy $taxonomy;
	
	/**
	 * Multibranded logic.
	 * 
	 * @var Multibranded $multibranded Multibranded logic.
	 */
	private Multibranded $multibranded;
	
	/**
	 * Gutenberg block generator.
	 * 
	 * @var GutenbergBlockGenerator $gutenberg_blocks Gutenberg block generator.
	 */
	private GutenbergBlockGenerator $gutenberg_blocks;
	
	/**
	 * Posts logic.
	 *
	 * @var Posts $posts Posts logic.
	 */
	private Posts $posts;

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Colorless CLI logger, for noiseless piping of CLI output into a file.
		$color_scheme = new DefaultScheme();
		$color_scheme->setColorizeArray( array_fill_keys( Level::VALUES, '' ) );
		$this->cli_logger = CliLog::get_logger( 'bw', new ColoredLineFormatter( $color_scheme, "%message% %context%\n", null, true ) );
		// File logger.
		$this->file_logger = FileLog::get_logger( 'bw' );
		// Logic.
		$this->taxonomy         = new Taxonomy();
		$this->multibranded     = Multibranded::get_instance();
		$this->gutenberg_blocks = new GutenbergBlockGenerator();
		$this->posts            = new Posts();
		if ( ! defined( 'NP_LIVE' ) ) {
			NMT::exit_with_message( 'NP_LIVE constant is not defined. Please add it in wp-config.php with the value of the live site.' );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {

		WP_CLI::add_command(
			'newspack-content-migrator bw-download-xml',
			self::get_command_closure( 'cmd_download_xml' ),
			[
				'shortdesc' => 'Download XML files from date range.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'from-date',
						'description' => 'From date in format YYYY-MM-DD. For example 2024-11-14',
						'optional'    => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'to-date',
						'description' => 'To date in format YYYY-MM-DD. From date in format YYYY-MM-DD. For example 2024-10-31',
						'optional'    => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'base-url',
						'description' => 'Url including auth string – you should get this from the publisher',
						'optional'    => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'output-dir',
						'description' => 'Optional. Where to put the downloaded xml files – defaults to current dir',
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
					[
						'type'        => 'assoc',
						'name'        => 'xml-file',
						'description' => 'Path to XML file - can also be a url',
						'optional'    => true,
					],
					[
						'type'        => 'assoc',
						'name'        => 'sponsors-bylines-csv-file',
						'description' => "CSV containing original article URL and the sponsor byline it should get. Expected header columns 'sponsor_byline','url'.",
						// Make it mandatory so as not to forget to use it.
						'optional'    => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'dir',
						'description' => 'Will scan all XML files in this dir.',
						'optional'    => true,
					],
					[
						'type'        => 'assoc',
						'name'        => 'header-images-bylines-csv-file',
						'description' => "CSV containing original article URL and the sponsor byline it should get. Expected header columns 'author_name','byline_image_url'.",
						// Make it mandatory so as not to forget to use it.
						'optional'    => false,
					],
					[
						'type'        => 'flag',
						'name'        => 'refresh-existing',
						'description' => 'Refresh existing articles',
						'optional'    => true,
					],
					[
						'type'        => 'assoc',
						'name'        => 'brand-name',
						'description' => "Full name of the brand to which the posts will be assigned to, e.g. --brand-name='Bailiwick Express News Jersey' . Must correspond to constants of this class BRAND_NAME_BAILIWICK_JERSEY and BRAND_NAME_BAILIWICK_GUERNSEY.",
						'optional'    => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'process-single-url',
						'description' => 'Dev helper, optional. If provided, only this single URL will be processed.',
						'optional'    => true,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator bw-list-redirects',
			self::get_command_closure( 'cmd_list_necessary_redirect_rules' ),
			[
				'shortdesc' => 'Discovers and lists all required redirect rules. WP posts will have /{category}/{slug} URL structure. This command compares all original URLs to that, and says which minimal set of redirect rules needs to be created.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'dir',
						'description' => 'Will scan all XML files in this dir.',
						'optional'    => true,
					],
					[
						'type'        => 'assoc',
						'name'        => 'xml-file',
						'description' => 'Path to a single XML file.',
						'optional'    => true,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator bw-helper-xml-syntax-check-count-articles',
			self::get_command_closure( 'cmd_helper_xml_syntax_check_count_articles' ),
			[
				'shortdesc' => 'Helper dev command. Performs an XML syntax check by running a simple counts of all articles in all the XMLs in a dir, or in a specific XML. If errors exist, they will be displayed in output.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'dir',
						'description' => 'Will scan all XML files in this dir.',
						'optional'    => true,
					],
					[
						'type'        => 'assoc',
						'name'        => 'xml-file',
						'description' => 'Path to a single XML file.',
						'optional'    => true,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator bw-helper-list-posts-with-invalid-img-srcs',
			self::get_command_closure( 'cmd_helper_list_posts_with_invalid_img_srcs' ),
			[
				'shortdesc' => 'Helper dev command. Lists which posts which have wrong <img> elements with src URLs that are not images. This was caused by Publisher sharing wrong/partial specifications on how cached and full-sized images look in their markup.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator bw-helper-list-live-downloadable-urls',
			self::get_command_closure( 'cmd_helper_list_downloadable_urls' ),
			[
				'shortdesc' => 'Helper dev command. Lists articles which contain downloadable URLs and which should be downloaded as files.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'dir',
						'description' => 'Will scan all XML files in this dir.',
						'optional'    => false,
					],
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
			$response = wp_remote_get( $url, [ 'timeout' => 40 ] );

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
			NMT::exit_with_message( sprintf( 'ERROR: Invalid start date %s', $from_date ), [ $this->cli_logger ] );
		}
		$end = DateTime::createFromFormat( $short_iso8601_format, $to_date );
		if ( ! $end ) {
			NMT::exit_with_message( sprintf( 'ERROR: Invalid end date %s', $to_date ), [ $this->cli_logger ] );
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
		$xml_file_path = $assoc_args['xml-file'] ?? null;
		$dir           = $assoc_args['dir'] ?? null;
		if ( is_null( $xml_file_path ) && is_null( $dir ) ) {
			$this->cli_logger->error( 'ERROR: Must provide either a directory or a specific XML file.' );
			return;
		}
		$sponsors_urls_bylines_csv = $assoc_args['sponsors-bylines-csv-file'] ?? null;
		$header_images_bylines_csv = $assoc_args['header-images-bylines-csv-file'] ?? null;
		$refresh                   = $assoc_args['refresh-existing'] ?? false;
		$brand_name                = $assoc_args['brand-name'];
		$process_single_url        = $assoc_args['process-single-url'] ?? null;
		$brand_id                  = $this->multibranded->get_brand_id_from_brand_name( $brand_name );
		if ( ! $brand_id ) {
			NMT::exit_with_message( 'ERROR: Brand does not exist. Check or create Multibranded plugin brands, and set this class constants BRAND_NAME_BAILIWICK_JERSEY and BRAND_NAME_BAILIWICK_GUERNSEY.', [ $this->cli_logger ] );
		}

		// Check permalink structure.
		if ( ! $this->is_permalink_structure_correct() ) {
			NMT::exit_with_message( 'ERROR: During import, permalink structure must be set to "/%category%/%postname%/". After the import it should be set back to "Post name".', [ $this->cli_logger ] );
		}

		// Initial data.
		$home_url             = home_url();
		$file_logger          = PlainFileLog::get_logger( 'bw-article-import' );
		$processed_single_url = false;
		// Get CSV data into 2D arrays.
		$sponsors_urls_to_bylines = $this->get_csv_data_to_2d_array( $sponsors_urls_bylines_csv, 'url', 'sponsor_byline' );
		if ( empty( $sponsors_urls_to_bylines ) ) {
			$this->cli_logger->error( 'ERROR: No sponsors URLs to bylines found in the CSV file.', [ 'csv_file' => $sponsors_urls_bylines_csv ] );
			return;
		}
		$header_image_urls_to_bylines = $this->get_csv_data_to_2d_array( $header_images_bylines_csv, 'byline_image_url', 'author_name' );
		if ( empty( $header_image_urls_to_bylines ) ) {
			$this->cli_logger->error( 'ERROR: No header image URLs to bylines found in the CSV file.', [ 'csv_file' => $header_images_bylines_csv ] );
			return;
		}

		// Get .xml files.
		$xml_files = [];
		if ( is_null( $dir ) ) {
			$xml_files = [ $xml_file_path ];
		} else {
			$xml_files = glob( "$dir/*.xml" );
			if ( empty( $xml_files ) ) {
				$this->cli_logger->error( 'ERROR: No XML files found in the directory.', [ 'dir' => $dir ] );
				return;
			}
		}

		foreach ( $xml_files as $xml_file_path ) {

			// Load XML file.
			$xml_fetcher = null;
			try {
				$xml_fetcher = new Concrete5Xml( $xml_file_path );
			} catch ( Exception $o_0 ) {
				NMT::exit_with_message( 'ERROR: ' . $o_0->getMessage(), [ $this->cli_logger ] );
			}

			// Log.
			if ( is_null( $process_single_url ) ) {
				$timestamp = gmdate( 'Y-m-d H:i:s' );
				$this->cli_logger->info( sprintf( '[%s] Importing articles from XML file', $timestamp ), [ 'xml_file' => $xml_file_path ] );
				$file_logger->info( sprintf( '[%s] Importing articles from XML file', $timestamp ) );
			}

			// Import articles.
			$articles    = $xml_fetcher->get_articles();
			$total_count = $xml_fetcher->get_count();
			$counter     = 0;
			foreach ( $articles as $article ) {
				++$counter;
	
				// Dev helper parameter to process only a single URL.
				if ( is_null( $process_single_url ) || rtrim( $process_single_url, '/' ) !== rtrim( $article['url'], '/' ) ) {
					continue;
				} elseif ( ! is_null( $process_single_url ) && rtrim( $process_single_url, '/' ) == rtrim( $article['url'], '/' ) ) {
					$processed_single_url = true;

					$timestamp = gmdate( 'Y-m-d H:i:s' );
					$this->cli_logger->info( sprintf( '[%s] Importing single article from XML file', $timestamp ), [ 'xml_file' => $xml_file_path ] );
					$file_logger->info( sprintf( '[%s] Importing single  article from XML file', $timestamp ) );
				}
				
				// Skip importing some articles custom marked in the sponsors-bylines-csv-file.csv file.
				if ( isset( $sponsors_urls_to_bylines[ $article['url'] ] ) && ( '<POST CAN BE DELETED>' == $sponsors_urls_to_bylines[ $article['url'] ] ) ) {
					$this->cli_logger->notice(
						'WARNING: Skipping article because it is defined in sponsors-bylines-csv-file.csv to be deleted.',
						[
							'url' => $article['url'],
						]
					);
					continue;
				}
	
				// New post data (or update if already imported).
				$post = [
					'post_type'   => 'post',
					'post_status' => 'publish',
				];
				
				// Get existing post ID if already imported.
				$original_url = $article['url'];
				$existing_id  = $this->get_post_id_by_original_url( $original_url );
				if ( ! empty( $existing_id ) ) {
					if ( ! $refresh ) {
						$this->cli_logger->notice(
							'Article already imported, skipping',
							[
								'url'     => $original_url,
								'post_id' => $existing_id,
							]
						);
						continue;
					}
	
					// This will update the existing post.
					$post['ID'] = $existing_id;
				}
	
				// Basic data.
				$post['post_title'] = $article['title'];
				$post['post_name']  = basename( $original_url );
				$post['post_date']  = $article['datePublic'];
				
				// Set article <lead> to Newspack subtitle.
				$lead = $article['lead'];
				if ( ! empty( $lead ) ) {
					$post['meta_input']['newspack_post_subtitle'] = $lead;
				}
	
				// Set content.
				$post['post_content'] = $article['description'] . $article['content'];
	
				// Get author name based on custom rules, and the rule itself (for easier QA).
				$author_arr  = $this->get_author_name_based_on_custom_rules( $article, $brand_name, $sponsors_urls_to_bylines, $header_image_urls_to_bylines );
				$author_name = $author_arr['author_name'];
				$author_rule = $author_arr['author_rule'] ?? null;
				
				// Create and set author user.
				$user_id             = $this->get_user_id( $author_name );
				$post['post_author'] = $user_id;
	
				// Set categories.
				$category_name = $article['category'];
				$cat_id        = $this->taxonomy->get_or_create_category_by_name_and_parent_id( $category_name, 0 );
				if ( ! is_wp_error( $cat_id ) ) {
					$post['post_category'] = [ $cat_id ];
				} else {
					$this->cli_logger->error(
						'ERROR: Failed to get or create category',
						[
							'error'         => $cat_id,
							'category_name' => $category_name,
						] 
					);
				}
				
				// Set tags.
				$tags = explode( ',', $article['tags'] );
				if ( ! empty( $tags ) ) {
					$post['tags_input'] = $tags;
				}
	
				// Save custom postmetas.
				$post['meta_input'][ self::META_ORIGINAL_URL ]    = $original_url;
				$post['meta_input'][ self::META_ORIGINAL_AUTHOR ] = $article['author'];
				if ( $author_rule ) {
					$post['meta_input'][ self::META_DEFAULT_AUTHOR_RULE ] = $author_rule;
				}
				if ( isset( $article['byline'] ) && ! empty( $article['byline'] ) ) {
					$post['meta_input'][ self::META_ORIGINAL_BYLINE_NODE ] = $article['byline'];
				}
	
				// Insert or update post if it already exists.
				$post_id = wp_insert_post( $post );
				if ( is_wp_error( $post_id ) ) {
					$this->cli_logger->error(
						'ERROR: Failed to import/update post',
						[
							'error'     => $post_id,
							'post_data' => $post,
						] 
					);
					continue;
				}
				// Log.
				$context = [
					'url'      => $original_url,
					'post_id'  => $post_id,
					'from_url' => $original_url,
					'to_url'   => "$home_url/?p=$post_id",
				];
				$action  = 0 !== $existing_id && $existing_id == $post_id ? 'Updated' : 'Imported';
				$this->cli_logger->info( sprintf( '%s post', $action ), $context );
				$file_logger->info( sprintf( '%s post', $action ), $context );
				
				// Custom updates to content.
				$content         = get_post_field( 'post_content', $post_id );
				$content_updated = $content;
				
				// Define content replacers -- $replacers holds callbacks to be applied to the content.
				$replacers = [];
				if ( str_contains( $content, '<h1>' ) ) {
					$replacers[] = fn( $html_doc ) => $this->fix_h1s( $html_doc, $post_id );
				}
				if ( str_contains( $content, '<img ' ) ) {
					$replacers[] = fn( $html_doc ) => $this->get_full_sized_images( $html_doc, $post_id );
				}
				if ( str_contains( $content, '<img ' ) ) {
					$replacers[] = fn( $html_doc ) => $this->get_inline_images( $html_doc, $post_id );
				}
				if ( str_contains( $content, 'bailiwickexpress.com/index.php/download_file/view' ) ) {
					$replacers[] = fn( $html_doc ) => $this->get_download_file_urls( $html_doc, $post_id );
				}
	
				// Run the replacers.
				if ( ! empty( $replacers ) ) {
					$html_doc = new HtmlDocument( $content_updated );
					// Run the replacers on the same HTMLDocument so we don't have to parse the content multiple times.
					foreach ( $replacers as $replacer ) {
						$replacer( $html_doc, $post_id );
					}
					$content_updated = $html_doc->save();
				}
	
				// Import galleries -- after replacers which replace images.
				$gallery_images = $article['gallery'] ?? null;
				if ( $gallery_images ) {
					// Download the gallery images.
					$this->cli_logger->info( sprintf( 'Downloading %d gallery images', count( $gallery_images ) ) );
					$gallery_image_att_ids = [];
					foreach ( $gallery_images as $gallery_image ) {
						$gallery_image_att_id = Attachments::import_external_file( $gallery_image, null, null, null, null, $post_id, [], '' );
						if ( is_wp_error( $gallery_image_att_id ) ) {
							$this->cli_logger->error(
								'ERROR: Failed to import gallery image.',
								[
									'error'         => $gallery_image_att_id,
									'gallery_image' => $gallery_image,
									'url'           => $original_url,
									'post_id'       => $post_id,
								] 
							);
							continue;
						}
	
						$gallery_image_att_ids[] = $gallery_image_att_id;
					}
					
					// Append gallery block to post_content.
					if ( ! empty( $gallery_image_att_ids ) ) {
						$gallery_block    = serialize_block(
							$this->gutenberg_blocks->get_jetpack_slideshow( $gallery_image_att_ids )
						);
						$content_updated .= "\n\n" . $gallery_block;
					}
				}
	
				// Update post content.
				if ( $content !== $content_updated ) {
					wp_update_post(
						[
							'ID'           => $post_id,
							'post_content' => $content_updated,
						]
					);
				}
	
				// Set featured image.
				$this->set_featured_image_on_post( $post_id, $article['image'] );
	
				// Set brand.
				$this->multibranded->set_brands_to_post( $post_id, [ $brand_id ] );

			} // End articles loop.
		} // End XML files loop.

		// If $process_single_url was used, check if it was successfully processed.
		if ( ! is_null( $process_single_url ) && true !== $processed_single_url ) {
			$this->cli_logger->error( 'ERROR: The provided --process-single-url URL was not found in the XML files.', [ 'url' => $process_single_url ] );
		}

		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$this->cli_logger->info( sprintf( '[%s] Done %s', $timestamp, $xml_file_path ) );
	}

	/**
	 * Check if the permalink structure is '/%category%/%postname%/'.
	 * 
	 * @return bool True if the permalink structure is correct, false otherwise.
	 */
	private function is_permalink_structure_correct() {
		global $wp_rewrite;
		return '/%category%/%postname%/' === $wp_rewrite->permalink_structure;
	}

	/**
	 * Takes a CSV file and produces a two dimensional array of key-value pairs from the CSV file.
	 * The CSV value to be used as array key is specified the $column_for_key, and the CSV value to be used as array value is specified the $column_for_value.
	 * 
	 * @param string $csv_filename     Path to the CSV file.
	 * @param string $column_for_key   CSV data column name to be used as array key.
	 * @param string $column_for_value CSV data column name to be used as array value.
	 * 
	 * @return array Array of key-value pairs from CSV file.
	 */
	private function get_csv_data_to_2d_array( string $csv_filename, string $column_for_key, string $column_for_value ) {
		$csv_data = [];
		
		$row    = 0;
		$handle = fopen( $csv_filename, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false !== $handle ) {
			while ( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
				// Get indexes of columns from header row.
				if ( 0 == $row ) {
					$key_index = array_search( $column_for_key, $data );
					if ( false === $key_index ) {
						NMT::exit_with_message( sprintf( 'ERROR: Failed to find column for array key `%s` in header of CSV file %s', $column_for_key, $csv_filename ), [ $this->cli_logger ] );
					}
					$value_index = array_search( $column_for_value, $data );
					if ( false === $value_index ) {
						NMT::exit_with_message( sprintf( 'ERROR: Failed to find column for array value `%s` in header of CSV file %s', $column_for_value, $csv_filename ), [ $this->cli_logger ] );
					}
				} else {
					// Combine data into key-value pairs.
					$csv_data[ $data[ $key_index ] ] = $data[ $value_index ];
				}
				++$row;
			}
			fclose( $handle );
		}

		return $csv_data;
	}

	/**
	 * Count the number of articles in all XML files in a directory.
	 *
	 * @param array $pos_args   Positional arguments from WP_CLI.
	 * @param array $assoc_args Associative arguments from WP_CLI.
	 * @return void
	 */
	public function cmd_helper_xml_syntax_check_count_articles( array $pos_args, array $assoc_args ): void {
		$dir      = $assoc_args['dir'] ?? null;
		$xml_file = $assoc_args['xml-file'] ?? null;

		if ( is_null( $dir ) && is_null( $xml_file ) ) {
			$this->cli_logger->error( 'ERROR: Must provide either a directory or a specific XML file.' );
			return;
		}

		// Get .xml files.
		if ( is_null( $dir ) ) {
			$files = [ $xml_file ];
		} else {
			$files = glob( "$dir/*.xml" );
			if ( empty( $files ) ) {
				$this->cli_logger->error( 'ERROR: No XML files found in the directory.', [ 'dir' => $dir ] );
				return;
			}
		}

		$total_count = 0;
		foreach ( $files as $file ) {
			$xml_fetcher  = new Concrete5Xml( $file );
			$count        = $xml_fetcher->get_count();
			$total_count += $count;
			$this->cli_logger->info( sprintf( 'File %s has %s articles', $file, $count ) );
		}

		$this->cli_logger->info( sprintf( 'Total articles in all XML files: %s', $total_count ) );
	}

	/**
	 * Callable for `newspack-content-migrator bw-helper-fixer-img-srcs-with-urls-not-images`.
	 * 
	 * @param array $pos_args   Positional arguments from WP_CLI.
	 * @param array $assoc_args Associative arguments from WP_CLI.
	 * @return void
	 */
	public function cmd_helper_list_posts_with_invalid_img_srcs( array $pos_args, array $assoc_args ): void {
		global $wpdb;

		$this->cli_logger->info( 'Checking all posts for <img> elements with src URLs that are not images.' );
		$this->cli_logger->info( '' );

		$file_logger = PlainFileLog::get_logger( 'bw-err-img-src-nonsupported-extensions' );
		$file_logger->info( 'post_id,src,original_url' );

		$post_ids_w_errors = [];
		$post_ids          = $this->posts->get_all_posts_ids();
		foreach ( $post_ids as $post_id ) {
			// phpcs:disable
			// WordPress.DB.DirectDatabaseQuery.DirectQuery WordPress.DB.DirectDatabaseQuery.NoCaching
			$post_content = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_content FROM $wpdb->posts WHERE ID = %d",
					$post_id
				)
			);
			// phpcs:enable

			$html_doc = new HtmlDocument( $post_content );
			$images   = $html_doc->find( 'img' );
			foreach ( $images as $image ) {
				$src        = strtolower( $image?->getAttribute( 'src' ) );
				$parsed_url = wp_parse_url( $src );
				$path       = $parsed_url['path'];
				$extension  = pathinfo( $path, PATHINFO_EXTENSION );

				// Image `src` is empty or has an unsupported extension.
				if ( empty( $src ) || ! in_array( strtolower( $extension ), self::WP_SUPPORTED_IMAGE_EXTENSIONS ) ) {
					if ( in_array( $post_id, $post_ids_w_errors ) ) {
						continue;
					}
					$post_ids_w_errors[] = $post_id;
					$original_url        = get_post_meta( $post_id, self::META_ORIGINAL_URL, true );
					$file_logger->info( sprintf( '%s,%s,%s', $post_id, $src, $original_url ) );
				}
			}
		}
	}

	/**
	 * Callable for `newspack-content-migrator bw-helper-list-live-downloadable-urls`.
	 * 
	 * @param array $pos_args   Positional arguments from WP_CLI.
	 * @param array $assoc_args Associative arguments from WP_CLI.
	 * 
	 * @return void
	 */
	public function cmd_helper_list_downloadable_urls( array $pos_args, array $assoc_args ): void {
		$dir = $assoc_args['dir'] ?? null;
	
		// Get .xml files.
		if ( is_null( $dir ) ) {
			$xml_files = [ $xml_file ];
		} else {
			$xml_files = glob( "$dir/*.xml" );
			if ( empty( $xml_files ) ) {
				$this->cli_logger->error( 'ERROR: No XML files found in the directory.', [ 'dir' => $dir ] );
				return;
			}
		}

		// Log.
		$this->cli_logger->info( 'Checking all posts with URL that can be downloaded.' );
		$this->cli_logger->info( '' );

		$download_file_urls = [];
		$downloadad_file_urls__list_of_original_article_urls_where_they_appear = [];
		$files_urls = [];
		$files_urls__list_of_original_article_urls_where_they_appear = [];

		// Go through files.
		foreach ( $xml_files as $xml_file ) {
			$xml_fetcher = null;
			try {
				$this->cli_logger->info( 'Parsing articles from XML file', [ 'xml_file' => $xml_file ] );
				$xml_fetcher = new Concrete5Xml( $xml_file );
			} catch ( Exception $o_0 ) {
				NMT::exit_with_message( 'ERROR: ' . $o_0->getMessage(), [ $this->cli_logger ] );
			}
	
			// Go through articles in a file.
			$articles = $xml_fetcher->get_articles();
			foreach ( $articles as $article ) {

				$content = $article['content'];

				/**
				 * Get all "download_file URLs" and their captions -- these contain "bailiwickexpress.com/index.php/download_file/view".
				 */
				$urls = $this->extract_download_file_urls( $content );
				if ( ! empty( $urls ) ) {
					$urls_count = count( $urls );
					
					// Additionally validate if our method has extracted all the URLs correctly.
					$pattern = '/bailiwickexpress\.com\/index\.php\/download_file\/view/';
					$count   = preg_match_all( $pattern, $content, $matches );
					if ( $count != $urls_count ) {
						WP_CLI::warning( sprintf( 'WARNING: Failed to extract all the "download_file URLs" from the content for article URL %s from XML %s . This might be OK if URLs just occur multiple times, but check the article and manually add URL to ignore in command.', esc_url_raw( $article['url'] ), esc_url( $xml_file ) ) );
						$debug = $this->extract_download_file_urls( $content );
						$url   = $article['url'];
					}

					// Get unique URLs.
					$urls_unique = [];
					foreach ( $urls as $element ) {
						if ( ! in_array( $element['url'], $urls_unique ) ) {
							$urls_unique[] = $element['url'];
						}
					}
	
					// Store.
					$download_file_urls = array_merge( $download_file_urls, $urls_unique );
					$downloadad_file_urls__list_of_original_article_urls_where_they_appear[] = $article['url'];
				}           

				/**
				 * Now get all "files URLs" -- these contain "bailiwickexpress.com/files/".
				 */
				$urls = $this->extract_files_urls( $content );
				if ( ! empty( $urls ) ) {
					$urls_count = count( $urls );
	
					// Additionally validate if our method is extracting all the URLs correctly.
					$pattern = '/bailiwickexpress\.com\/files/';
					$count   = preg_match_all( $pattern, $content, $matches );

					// These were manually checked and are OK -- ULRs simply appear multiple times in the content. It's not our method that is wrong, and these will be downloaded and replaced correctly.
					$ignore = [
						'https://www.bailiwickexpress.com/jsy/community/got-question-about-new-rubis-rd100-renewable-diesel-here-are-some-faqs',
						'https://www.bailiwickexpress.com/jsy/sport/island-games-medals-designer-guernsey-models-her-creations',
					];
					if ( $count != $urls_count && ! in_array( $article['url'], $ignore ) ) {
						WP_CLI::warning( sprintf( 'WARNING: Failed to extract all the "files URLs" from the content for article URL %s from XML %s . This might be OK if URLs just occur multiple times, but check the article and manually add URL to ignore in command.', esc_url_raw( $article['url'] ), esc_url( $xml_file ) ) );
						$debug = $this->extract_files_urls( $content );
						$url   = $article['url'];
					}
	
					// Get unique URLs.
					$urls_unique = [];
					foreach ( $urls as $element ) {
						if ( ! in_array( $element['url'], $urls_unique ) ) {
							$urls_unique[] = $element['url'];
						}
					}

					// Store.
					$files_urls = array_merge( $files_urls, $urls_unique );
					$files_urls__list_of_original_article_urls_where_they_appear[] = $article['url'];
				}
			}
		}

		WP_CLI::success( '"download_file URLs":' );
		WP_CLI::line( implode( "\n", $download_file_urls ) );
		WP_CLI::success( 'Articles with "download_file URLs":' );
		WP_CLI::line( implode( "\n", $downloadad_file_urls__list_of_original_article_urls_where_they_appear ) );
		
		WP_CLI::success( '"files URLs":' );
		WP_CLI::line( implode( "\n", $files_urls ) );
		WP_CLI::success( 'Articles with "file URLs":' );
		WP_CLI::line( implode( "\n", $files_urls__list_of_original_article_urls_where_they_appear ) );
	}

	/**
	 * Returns all URLs, even duplicates for tracking purposes, which contain:
	 *      'bailiwickexpress.com/index.php/download_file/view'  -- internally we call these "download_file URLs" to distinguish them from "files URLs".
	 *
	 * @param string $html HTML content.
	 * @return array URLs Array with subarrays containing keys for URLs and for captions. {
	 *    @type string $url      URL.
	 *    @type ?string $caption Caption. Null if not found.
	 * }
	 */
	private function extract_download_file_urls( $html ) {
		$downlod_file_urls = [];
		
		if ( false === str_contains( $html, 'bailiwickexpress.com/index.php/download_file/view' ) ) {
			return $downlod_file_urls;
		}

		/**
		 * The pattern to match URLs containing `...bailiwickexpress.com/index.php/download_file/view/...`.
		 * - https?://: Match http:// or https://.
		 * - (server\.com|[^\/]+): Match server.com or any domain.
		 * - \/index\.php\/download_file\/view\/: Match the path.
		 * - ([^\/]+(?:\/[^\/]+)*): Match the file path.
		 * - \/: Match the last slash.
		 */
		$pattern = '/https?:\/\/(bailiwickexpress\.com|[^\/]+)\/index\.php\/download_file\/view\/([^\/]+(?:\/[^\/]+)*)/';

		$dom = new \DOMDocument();
		// phpcs:disable
		// WordPress.PHP.NoSilencedErrors.Discouraged
		@$dom->loadHTML( $html ); // Suppress errors.
		// phpcs:enable
	  
		// Find elements with potential URLs.
		$elements = $dom->getElementsByTagName( '*' );
		foreach ( $elements as $element ) {
			// Check for URLs in attributes -- src or href.
			$url = $element->getAttribute( 'src' ) ?? null;
			if ( ! $url ) {
				$url = $element->getAttribute( 'href' ) ?? null;
			}

			if ( $url && preg_match( $pattern, $url, $matches ) ) {
				// Get alt or title attribute.
				$caption = $element->getAttribute( 'alt' ) ?? null;
				if ( ! $caption ) {
					$caption = $element->getAttribute( 'title' ) ?? null;
				}

				$downlod_file_urls[] = [
					'url'     => trim( $url ),
					'caption' => trim( $caption ),
				];
			}
		}
	  
		return $downlod_file_urls;
	}

	/**
	 * Returns all URLs (even duplicates, for tracking purposes) which contain:
	 *      'bailiwickexpress.com/files/' -- internally we call these "files URLs" to distinguish them from "download_file URLs".
	 * 
	 * Slightly different method than `extract_download_file_urls()`, added incrementaly
	 * as we find different kinds of URLs to extract.
	 *
	 * @param string $html HTML content.
	 * @return array URLs Array with subarrays containing keys for URLs and for captions. {
	 *    @type string $url      URL.
	 *    @type ?string $caption Caption. Null if not found.
	 * }
	 */
	private function extract_files_urls( $html ) {
		$urls = [];

		if ( false === str_contains( $html, 'bailiwickexpress.com/files/' ) ) {
			return $urls;
		}
		
		/**
		 * The pattern to match URLs containing `...bailiwickexpress.com/files/...`.
		 * - https?://: Match http:// or https://.
		 * - (bailiwickexpress\.com|[^\/]+): Match bailiwickexpress.com or any domain.
		 * - \/files\/: Match the path.
		 * - ([^\/]+(?:\/[^\/]+)*): Match the file path.
		 *      - [^\/]+: Match any character except a slash.
		 *      - (?:\/[^\/]+)*)*: Match a slash followed by any character except a slash, zero or more times.
		 *          (?:...): This is a non-capturing group. It matches the enclosed pattern but doesn't store the matched content in a separate variable.
		 * - \/: Match the last slash.
		 */
		$pattern = '/https?:\/\/(bailiwickexpress\.com|[^\/]+)\/files\/([^\/]+(?:\/[^\/]+)*)/';

		$dom = new \DOMDocument();
		// phpcs:disable
		// WordPress.PHP.NoSilencedErrors.Discouraged
		@$dom->loadHTML( $html ); // Suppress errors.
		// phpcs:enable
	  
		// Find elements with potential URLs.
		$elements = $dom->getElementsByTagName( '*' );
		foreach ( $elements as $element ) {
			// Check for URLs in attributes -- src or href.
			$url = $element->getAttribute( 'src' ) ?? null;
			if ( ! $url ) {
				$url = $element->getAttribute( 'href' ) ?? null;
			}

			if ( $url && preg_match( $pattern, $url, $matches ) ) {
				// Get alt or title attribute.
				$caption = $element->getAttribute( 'alt' ) ?? null;
				if ( ! $caption ) {
					$caption = $element->getAttribute( 'title' ) ?? null;
				}

				$urls[] = [
					'url'     => trim( $url ),
					'caption' => trim( $caption ),
				];
			}
		}
	  
		return $urls;
	}

	/**
	 * Callable for `newspack-content-migrator bw-list-redirects`.
	 *
	 * @param array $pos_args   Positional arguments from WP_CLI.
	 * @param array $assoc_args Associative arguments from WP_CLI.
	 * @return void
	 */
	public function cmd_list_necessary_redirect_rules( array $pos_args, array $assoc_args ): void {
		$dir      = $assoc_args['dir'] ?? null;
		$xml_file = $assoc_args['xml-file'] ?? null;
		if ( is_null( $dir ) && is_null( $xml_file ) ) {
			$this->cli_logger->error( 'ERROR: Must provide either a directory or a specific XML file.' );
			return;
		}

		// Get .xml files.
		if ( is_null( $dir ) ) {
			$xml_files = [ $xml_file ];
		} else {
			$xml_files = glob( "$dir/*.xml" );
			if ( empty( $xml_files ) ) {
				$this->cli_logger->error( 'ERROR: No XML files found in the directory.', [ 'dir' => $dir ] );
				return;
			}
		}

		// Create redirect rules and exceptions catalogue.
		$redirect_rules      = [];
		$redirect_exceptions = [];

		// Go through files.
		$total_count = 0;
		foreach ( $xml_files as $xml_file ) {
			$xml_fetcher = null;
			try {
				$this->cli_logger->info( 'Importing articles from XML file', [ 'xml_file' => $xml_file ] );
				$xml_fetcher = new Concrete5Xml( $xml_file );
			} catch ( Exception $o_0 ) {
				NMT::exit_with_message( 'ERROR: ' . $o_0->getMessage(), [ $this->cli_logger ] );
			}
	
			// Go through articles in a file.
			$articles    = $xml_fetcher->get_articles();
			$total_count = $xml_fetcher->get_count();
			$counter     = 0;
			foreach ( $articles as $article ) {
				++$counter;
				if ( empty( $article['url'] ) && empty( $article['title'] ) ) {
					continue;
				}
	
				// example URL 'https://www.bailiwickexpress.com/jsy/business/crestbridge-collects-weathbriefing-accolade'.
				$url = $article['url'];

				// Get the path part of the URL.
				$url_path      = str_replace( 'https://www.bailiwickexpress.com/', '', $url );
				$url_path      = rtrim( $url_path, '/' );
				$exploded_path = explode( '/', $url_path );

				// The last part of the URL is the slug 👍.
				
				// Get category slug.
				$category_slugs_specific = [
					'COVID-19 Virus Notices' => 'corona-updates',
				];
				if ( array_key_exists( $article['category'], $category_slugs_specific ) ) {
					$category_slug = $category_slugs_specific[ $article['category'] ];
				} else {
					$category_slug = str_replace( ' ', '-', strtolower( $article['category'] ) );
				}

				// Check if one before the last part is lowercase category.
				$one_before_slug_is_category = $category_slug == $exploded_path[ count( $exploded_path ) - 2 ];

				// If there's only two parts in the URL, they're {category}/{slug} so there's no need for a redirect.
				if ( 2 === count( $exploded_path ) ) {
					continue;
				}

				// Track exceptions for category slugs.
				if ( false === $one_before_slug_is_category ) {
					$redirect_exceptions[] = [
						'category' => $article['category'],
						'url'      => $url,
					];
					continue;
				}
				
				// Implode all $exploded_path except the last two {category}/{slug}.
				$imploded_path = implode( '/', array_slice( $exploded_path, 0, count( $exploded_path ) - 2 ) );

				// Track redirect rules -- array key -- with examples -- values.
				$redirect_rules[ $imploded_path ][] = [
					'category' => $article['category'],
					'url'      => $url,
				];
			}
		}

		$this->cli_logger->info( sprintf( '---' ) );

		// For further debugging pruposes, see $redirect_rules array values for the URLs covered by its key/rule.
		$this->cli_logger->info( sprintf( '%d general regex rules to create:', count( $redirect_rules ) ) ); 
		foreach ( array_keys( $redirect_rules ) as $redirect_path ) {
			$this->cli_logger->info( sprintf( "- FROM '/%s/*' TO '/*'", $redirect_path ) ); 
		}
		
		$this->cli_logger->info( sprintf( '%d specific regex rules to create:', count( $redirect_exceptions ) ) ); 
		foreach ( $redirect_exceptions as $redirect_exception ) {
			$this->cli_logger->info( sprintf( "- CATEGORY:'%s' URL:'%s'", $redirect_exception['category'], $redirect_exception['url'] ) ); 
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
	 * Some <img>s have cached (smaller sized) `src` (the `src` URL path contains '.../cache/...').
	 * Such a cached (smaller sized) <img> is wrapped in an <a> tag which contain the full sized image URL in its `href`.
	 * This `href` should be used for image sources.
	 * E.g.:
	 * ```
	 * <a href="https://www.bailiwickexpress.com/files/5117/3202/4104/kate.jpg" target="_blank">
	 *      <img src="https://www.bailiwickexpress.com/files/cache/0bce94c80a6c63f1e32ddbed148c7921_f1416263.jpg" alt="kate.jpg" width="500" height="1382" />
	 *      <br />
	 * </a>
	 * ```
	 * 
	 * This medhod replaces such <a>s with just the <img> with the correct full-sized `src` takend from the <a>'s `href`.
	 *
	 * @param HtmlDocument $html_doc The HTML document to replace in.
	 * @param int          $post_id  The parent post ID (the published post ID with the content, not the attachment object).
	 *
	 * @throws RuntimeException If a cached image URL is not fully qualified.
	 * 
	 * @return void
	 */
	private function get_full_sized_images( HtmlDocument $html_doc, int $post_id ): void {
		
		// Get all the <a> tags.
		$as = $html_doc->find( 'a' );
		if ( empty( $as ) ) {
			$this->cli_logger->info( 'No <a> tags found in post content', [ 'post_id' => $post_id ] );
			return;
		}

		foreach ( $as as $a ) {
			$a_html_debug = $a->outertext;

			/**
			 * Validate if this <a> contains an <img> with the cached (smaller sized) image.
			 * There can be one more extra child, a <br> element.
			 */
			$children = $a?->children();
			if ( empty( $children ) ) {
				// <a> tag has no children.
				continue;
			}
			if ( 'img' !== $a->children[0]?->tag ) {
				// <a> tag 1st child is not <img>.
				continue;
			}
			// There can be a second child, but it must be a <br> element.
			if ( ( 2 == count( $children ) ) && ( 'br' !== $children[1]->tag ) ) {
				// <a> tag 2nd child is not <br>.
				continue;
			}
			// There should not be more than 2 children.
			if ( count( $children ) > 2 ) {
				// <a> tag has more than 2 children.
				continue;
			}

			// Get the first child <img>.
			$img = $a->children[0];
			if ( 'img' !== $img->tag ) {
				// Not an <img> element.
				continue;
			}
			
			// Check that img's `src` contains `/cache/` in its URL path.
			$src = $img?->getAttribute( 'src' );
			if ( ! $img || ! $src || ! str_contains( $src, '/cache/' ) ) {
				// This is not a cached image.
				continue;
			}

			// Check if `src` is fully qualified, and if it's not, expand it.
			$src_expand_fully_qualified = null;
			if ( ! str_starts_with( $src, 'http' ) ) {
				$src_expand_fully_qualified = NP_LIVE . $src;
			}

			// Get `href` -- the full-sized image URL.
			$href = $a?->getAttribute( 'href' );
			if ( ! $href ) {
				// No `href` attribute.
				continue;
			}

			// Check if `href` extension is a supported image.
			$parsed_href    = wp_parse_url( $href );
			$href_path      = $parsed_href['path'];
			$href_extension = pathinfo( $href_path, PATHINFO_EXTENSION );
			if ( ! in_array( strtolower( $href_extension ), self::WP_SUPPORTED_IMAGE_EXTENSIONS ) ) {
				continue;
			}

			// Make sure `href` is fully qualified.
			if ( ! str_starts_with( $href, 'http' ) ) {
				$href = NP_LIVE . $src;
			}
			
			// Create a new <img> element. Cloning the existing $img object is an efficient way to keep all the existing attributes.
			$new_img = clone $img;
			// Set $href as the correct src.
			$new_img->setAttribute( 'src', $href );

			// Replace <a> in $html_doc with the $new_img.
			$html_doc->load( str_replace( $a->outertext, $new_img->outertext, $html_doc->save() ) );
			
			// Replace all cached image URLs with the full sized URLs in entire HTML.
			// If the cached $src URL is relative, first replace the fully qualified version, then this relative afterwards.
			if ( ! is_null( $src_expand_fully_qualified ) ) {
				$html_doc->load( str_replace( $src_expand_fully_qualified, $href, $html_doc->save() ) );
			}
			$html_doc->load( str_replace( $src, $href, $html_doc->save() ) );
		}
	}

	/**
	 * Find images in HTMLDocument content and download them and replace with image blocks.
	 *
	 * @param HtmlDocument $html_doc The HTML document to replace in.
	 * @param int          $post_id  The parent post ID (the published post ID with the content, not the attachment object).
	 *
	 * @return void
	 */
	private function get_inline_images( HtmlDocument $html_doc, int $post_id ): void {
		$images = $html_doc->find( 'img' );
		if ( empty( $images ) ) {
			$this->cli_logger->info( 'No inline images found in post', [ 'post_id' => $post_id ] );

			return;
		}
		
		foreach ( $images as $img ) {
			$src_attr = $img?->getAttribute( 'src' );
			if ( ! $src_attr ) {
				// Not much we can do without that.
				continue;
			}

			// $src_attr might be relative, so get the absolute URL.
			if ( str_starts_with( $src_attr, 'http' ) ) {
				$src = $src_attr;
			} else {
				$src = NP_LIVE . $src_attr;
			}

			// alt text.
			$alt_text = $img?->getAttribute( 'alt' ) ?: ''; // phpcs:ignore Universal.Operators.DisallowShortTernary.Found

			$att_id = $this->import_attachment_from_url( $src, $post_id, $alt_text );
			if ( is_wp_error( $att_id ) ) {
				$this->cli_logger->error(
					'ERROR: Failed to import inline image',
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

			$img_block      = serialize_block(
				$this->gutenberg_blocks->get_image(
					get_post( $att_id ),
					'full',
					false
				)
			);
			$img->outertext = $img_block;
			
			
			// Replace original URL with new URL in entire HTML; there are some "click here" links added manually for additional direct view of the images.
			$new_url = wp_get_attachment_url( $att_id );
			if ( $new_url ) {
				// Both relative and fully qualified URLs are used in the content.
				$html_doc->load( str_replace( $src_attr, $new_url, $html_doc->save() ) );
				$html_doc->load( str_replace( $src, $new_url, $html_doc->save() ) );
			}       
		}
	}

	/**
	 * Finds "download_file URLs" (see $this->extract_download_file_urls) in HTMLDocument content and download them and replace with attachment URLs.
	 *
	 * @param HtmlDocument $html_doc The HTML document to replace in.
	 * @param int          $post_id  The parent post ID (the published post ID with the content, not the attachment object).
	 *
	 * @return void
	 */
	private function get_download_file_urls( HtmlDocument $html_doc, int $post_id ): void {
		
		$html               = $html_doc->save();
		$download_file_urls = $this->extract_download_file_urls( $html );
		foreach ( $download_file_urls as $element ) {

			$download_file_url = $element['url'];
			$caption           = $element['caption'] ?: ''; // phpcs:ignore Universal.Operators.DisallowShortTernary.Found

			$att_id = $this->import_attachment_from_url( $download_file_url, $post_id );
			if ( is_wp_error( $att_id ) ) {
				$this->cli_logger->error(
					'ERROR: Failed to import downloadable URL',
					[
						'post_id' => $post_id,
						'url'     => $download_file_url,
						'error'   => $att_id,
					]
				);
				continue;
			}

			// Set caption to attachment.
			wp_update_post(
				[
					'ID'           => $att_id,
					'post_excerpt' => $caption,
				] 
			);

			// Replace the downloadable URL with the new attachment URL.
			$new_url = wp_get_attachment_url( $att_id );
			if ( $new_url ) {
				$html_doc->load( str_replace( $download_file_url, $new_url, $html_doc->save() ) );
			} else {
				// This should not happen, but better safe.
				$this->cli_logger->error(
					'ERROR: Failed to get attachment URL after importing downloadable URL',
					[
						'post_id' => $post_id,
						'url'     => $download_file_url,
					],
				);
				
				// Explicitly `continue;` for clarity.
				continue;
			}
		}
	}

	/**
	 * Downloads and sets the featured image on a post.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $image_url Image URL to download image from.
	 * @param string $alt       Alt text for the image.
	 *
	 * @return void
	 */
	private function set_featured_image_on_post( int $post_id, string $image_url, string $alt = '' ): void {
		$image_url = trim( $image_url );
		if ( empty( $image_url ) ) {
			return;
		}
		$data['_old_featured_image'] = $image_url;
		if ( ! str_starts_with( $image_url, 'http' ) ) {
			$image_url = trailingslashit( NP_LIVE ) . trim( $image_url, '/' );
		}

		$attachment_id = $this->import_attachment_from_url( $image_url, $post_id, $alt );
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
				'ERROR: Could not download featured image',
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
	 * Get author name based on custom rules.
	 *
	 * @param array  $article                      Article XML data.
	 * @param string $brand_name                   Brand name.
	 * @param array  $sponsors_urls_to_bylines     Custom sponsors URLs to bylines mapping. Keys are URLs, values are bylines.
	 * @param array  $header_image_urls_to_bylines Custom header author image URLs to bylines mapping. Keys are URLs, values are bylines.
	 *
	 * @return array An array with two keys {
	 *     string @author_name  The name of the author.
	 *     ?string @author_rule The rule for byline selection. If null, not special rule was applied and $article['author'] was used.
	 * }
	 */
	private function get_author_name_based_on_custom_rules( array $article, string $brand_name, array $sponsors_urls_to_bylines, array $header_image_urls_to_bylines ): array {
		
		/**
		 * Sponsored Content.
		 * Will also be imported as normal posts under the 'Sponsor Content' category.
		 */
		// Check if URL is in sponsors list, and assign it the custom byline.
		if ( isset( $sponsors_urls_to_bylines[ rtrim( $article['url'], '/' ) ] ) ) {
			return [
				'author_name' => $sponsors_urls_to_bylines[ $article['url'] ],
				'author_rule' => sprintf( "Custom Sponsor byline from CSV -- URL':%s' byline:'%s'", $article['url'], $sponsors_urls_to_bylines[ $article['url'] ] ),
			];
		}

		/**
		 * BEJ only.
		 */
		if ( self::BRAND_NAME_BAILIWICK_JERSEY == $brand_name ) {
			/**
			 * Petty Debts.
			 */
			if ( false !== stripos( $article['title'], 'the latest in petty debts' ) ) {
				return [
					'author_name' => 'Bailiwick Express News Team',
					'author_rule' => "'The latest in Petty Debts' in title -- byline:'Bailiwick Express News Team'",
				];
			}
			/**
			 * Petty Debts.
			 * Additional rule -- all articles uploaded by Maddy Pereira get 'Bailiwick Express News Team'.
			 */
			if ( 'Maddy Pereira' == $article['author'] ) {
				return [
					'author_name' => 'Bailiwick Express News Team',
					'author_rule' => "Maddy Pereira is author -- byline:'Bailiwick Express News Team'",
				];
			}
			/**
			 * Property Lists.
			 */
			if ( false !== stripos( $article['title'], 'the latest property sales' ) ) {
				return [
					'author_name' => 'Bailiwick Express News Team',
					'author_rule' => "'The latest property sales' in title -- byline:'Bailiwick Express News Team'",
				];
			}
		}

		/**
		 * "Jersey Heritage" author.
		 */
		// Rule 1 -- if title contains 'LOOKING BACK:'.
		if ( false !== stripos( $article['title'], 'LOOKING BACK:' ) ) {
			return [
				'author_name' => 'Jersey Heritage',
				'author_rule' => "'LOOKING BACK:' in title -- byline:'Jersey Heritage'",
			];
		}

		// Rule 2 -- if title contains 'What's your home's story?'.
		if ( false !== stripos( $article['title'], "What's your home's story?" ) ) {
			return [
				'author_name' => 'Jersey Heritage',
				'author_rule' => "'What's your home's story?' in title -- byline:'Jersey Heritage'",
			];
		}

		// Rule 3 -- if title contains 'What's your town's story?'.
		if ( false !== stripos( $article['title'], "What's your town's story?" ) ) {
			return [
				'author_name' => 'Jersey Heritage',
				'author_rule' => "'What's your town's story?' in title -- byline:'Jersey Heritage'",
			];
		}

		/**
		 * If <byline> author header image URL is present in Publisher's CSV, use that byline.
		 * 
		 * For posterity and record, before the publisher shared with us the CSV ($header_image_urls_to_bylines)
		 * containing all header byline image fully qualified URLs and their corresponding bylines,
		 * our code used to match the following image filenames to bylines:
		 *      - 'jersey_heritage.png' => 'Jersey Heritage',
		 *      - 'News-Team-By-Line.png' => 'Bailiwick Express News Team',
		 *      - 'News-Team-By-Line.png' => 'Bailiwick Express News Team'.
		 */
		$author_name = $header_image_urls_to_bylines[ $article['byline'] ] ?? null;
		if ( $author_name ) {
			return [
				'author_name' => $author_name,
				'author_rule' => sprintf( "Byline image from CSV -- URL':%s' byline:'%s'", $article['byline'], $header_image_urls_to_bylines[ $article['byline'] ] ),
			];
		}

		// Additionally confirmed that all content in Opinion category can be assigned to 'Bailiwick Express News Team'.
		if ( 'Opinion' == $article['category'] ) {
			return [
				'author_name' => 'Bailiwick Express News Team',
				'author_rule' => "Article in Opinion category -- byline:'Bailiwick Express News Team'",
			];
		}

		/**
		 * Community author.
		 */
		if ( 'Community' == $article['category'] ) {
			return [
				'author_name' => 'Bailiwick Express Community',
				'author_rule' => "Article in Community category -- byline:'Bailiwick Express Community'",
			];
		}
		
		// If author is empty.
		if ( empty( $article['author'] ) ) {
			return [
				'author_name' => 'Bailiwick Express News Team',
				'author_rule' => "Article author is empty -- byline:'Bailiwick Express News Team'",
			];
		}

		/**
		 * Manual individual fixes.
		 */
		if ( 'James.Jeune' == $article['author'] ) {
			return [
				'author_name' => 'James Jeune',
				'author_rule' => 'Manually removed dot from author name',
			];
		} elseif ( false !== strpos( $article['author'], '.' ) ) {
			// Debug, other authors contain a dot?
			$this->cli_logger->info(
				'WARNING: Author contains a dot',
				[
					'author' => $article['author'],
					'url'    => $article['url'],
				] 
			);
		}

		return [
			'author_name' => $article['author'],
			'author_rule' => 'Article author set from XML <author> node',
		];
	}

	/**
	 * Create or get author from the name.
	 *
	 * @param string $author_name The author name.
	 *
	 * @return int The author ID or 0 if not found.
	 */
	private function get_user_id( string $author_name ): int {
		// This case won't happen, but we'll leave it be since it's a good practice, and posts authored by user ID 1 == adminnewspack are easy to review and update.
		$default_author_id = 1;
		if ( empty( $author_name ) ) {
			$this->cli_logger->notice(
				'WARNING: Using default user adminnewspack.',
				[
					'url' => $article['url'],
				]
			);

			return $default_author_id;
		}

		// Initially we're setting all users as Guest Contributors, because their custom user lists will follow after the initial migration.
		$role       = Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME;
		$user_email = null;

		try {
			$user = UsersHelper::create_or_get_user(
				[
					'user_login' => $author_name,
					'role'       => $role,
				],
				$author_name
			);

			return $user->ID;
		} catch ( Exception $e ) {
			$message = sprintf( 'ERROR: Could not create user with name %s', $author_name );
			$this->cli_logger->error( $message, [ 'error' => $e ] );
			$this->file_logger->critical( $message, [ 'error' => $e ] );

			return $default_author;
		}
	}

	/**
	 * Get image or another attachment file from URL into the Media Library and return the attachment ID.
	 *
	 * @param string $url      The URL to the image/attachment.
	 * @param int    $post_id  The parent post ID (the published post ID with the content, not the attachment object).
	 * @param string $alt_text The alt text for the image/attachment.
	 *
	 * @return int|WP_Error
	 */
	private function import_attachment_from_url( string $url, int $post_id, string $alt_text = '' ): int|WP_Error {
		if ( empty( $url ) ) {
			return new WP_Error( '', 'No image/attachment URL provided' );
		}

		// Download the image and import it (will return existing attachment ID if already imported).
		$attachment_id = Attachments::import_attachment_for_post( $post_id, $url, $alt_text );

		// Save original URL as custom meta to $attachment_id.
		update_post_meta( $attachment_id, self::META_ORIGINAL_URL, $url );

		return $attachment_id;
	}

	/**
	 * Get the WP post ID by the original URL.
	 *
	 * @param string $original_url The original URL.
	 *
	 * @return int The post ID or 0 if not found.
	 */
	public function get_post_id_by_original_url( string $original_url ): int {
		$posts = get_posts(
			[
				'meta_key'   => self::META_ORIGINAL_URL,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value' => $original_url,
			]
		);

		return $posts[0]->ID ?? 0;
	}

	/**
	 * Probably delete this if it makes no sense.
	 *
	 * @param int    $post_id  The parent post ID (the published post ID with the content, not the attachment object).
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
