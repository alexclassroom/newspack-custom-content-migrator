<?php
/**
 * Migration tasks for Texas Tribune.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Exception;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Logic\Redirection;
use Newspack\MigrationTools\Logic\SimpleLocalAvatars;
use Newspack\MigrationTools\Logic\UsersHelper;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use NewspackCustomContentMigrator\Utils\ConsoleColor;
use CoAuthors_Plus;
use WP_CLI;
use WP_Error;
use WP_User;

/**
 * Custom migration scripts for Texas Tribune.
 */
class TexasTribuneMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * Co-Authors Plus instance.
	 *
	 * @var CoAuthors_Plus $co_authors_plus Co-Authors Plus instance.
	 */
	private CoAuthors_Plus $co_authors_plus;

	/**
	 * Class which helps generate Gutenberg Blocks.
	 *
	 * @var GutenbergBlockGenerator $block_generator Custom Gutenberg Block Generator.
	 */
	private GutenbergBlockGenerator $block_generator;

	/**
	 * Redirection instance.
	 *
	 * @var Redirection $redirection Redirection instance.
	 */
	private Redirection $redirection;

	/**
	 * Instance of SimpleLocalAvatars.
	 *
	 * @var null|SimpleLocalAvatars
	 */
	private $simple_local_avatars_logic;

	/**
	 * Cache for sponsors data.
	 *
	 * @var array<string,array{name:string,url:string}>|null
	 */
	private ?array $sponsors_data = null;

	/**
	 * Cache for author map.
	 *
	 * @var array<string,array{name:string,slug:string}>|null
	 */
	private ?array $author_map = null;

	/**
	 * Cache for tags data.
	 *
	 * @var array<int,array{name:string,slug:string}>|null
	 */
	private ?array $tags_data = [];

	/**
	 * Cache for series data.
	 *
	 * @var array<int,array{name:string,slug:string,summary:string}>|null
	 */
	private ?array $series_data = [];

	/**
	 * Counter for skipped articles.
	 *
	 * @var int
	 */
	private int $skipped = 0;

	/**
	 * Cache for legacy IDs.
	 *
	 * @var array<string,int>
	 */
	private array $legacy_ids = [];

	/**
	 * Sponsor post type name.
	 *
	 * @var string
	 */
	private const SPONSOR_POST_TYPE = 'newspack_spnsrs_cpt';

	/**
	 * Sponsor taxonomy name.
	 *
	 * @var string
	 */
	private const SPONSOR_TAXONOMY = 'newspack_spnsrs_tax';

	/**
	 * List of tags that should be treated as categories under "Topics".
	 *
	 * @var array<string>
	 */
	private const TAGS_AS_TOPICS = [
		'congress',
		'courts',
		'criminal justice',
		'demographics',
		'economy',
		'energy',
		'environment',
		'health care',
		'higher education',
		'immigration',
		'politics',
		'public education',
		'state government',
		'transportation',
	];

	/**
	 * Staff organization.
	 *
	 * @var string
	 */
	private const STAFF_ORGANIZATION = 'The Texas Tribune';

	/**
	 * Track if height adjustment script has been added.
	 *
	 * @var bool
	 */
	private bool $height_adjustment_script_added = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->block_generator            = new GutenbergBlockGenerator();
		$this->redirection                = new Redirection();
		$this->co_authors_plus            = new CoAuthors_Plus();
		$this->simple_local_avatars_logic = new SimpleLocalAvatars();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator tt-migrate-data',
			[ new self(), 'cmd_migrate_data' ],
			[
				'shortdesc' => 'Migrates Texas Tribune content from JSON files.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'json-folder',
						'description' => 'Path to the folder containing JSON files',
						'optional'    => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'sponsor-data',
						'description' => 'Path to the sponsor data JSON file',
						'optional'    => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'author-map',
						'description' => 'Path to the author map JSON file',
						'optional'    => false,
					],
					[
						'type'        => 'flag',
						'name'        => 'skip-imported',
						'description' => 'Skip articles that have already been imported',
						'optional'    => true,
					],
					[
						'type'        => 'assoc',
						'name'        => 'pdf-icon-attachment-id',
						'description' => 'Attachment ID of the PDF icon',
						'optional'    => false,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator tt-remove-uncategorized-category',
			[ new self(), 'cmd_remove_uncategorized_category' ],
			[
				'shortdesc' => 'Removes the "Uncategorized" category.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'index-from',
						'description' => 'Index from which to start removing posts',
						'optional'    => true,
					],
				],
			]
		);
	}

	/**
	 * Command to migrate Texas Tribune content.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_migrate_data( $args, $assoc_args ): void {
		$json_folder            = $assoc_args['json-folder'];
		$sponsor_data_file      = $assoc_args['sponsor-data'];
		$author_map_file        = $assoc_args['author-map'];
		$pdf_icon_attachment_id = $assoc_args['pdf-icon-attachment-id'];
		$skip_imported          = isset( $assoc_args['skip-imported'] );

		// Reset skipped counter.
		$this->skipped = 0;

		if ( ! is_dir( $json_folder ) ) {
			WP_CLI::error( sprintf( 'Directory not found: %s', $json_folder ) );
		}

		if ( ! file_exists( $sponsor_data_file ) ) {
			WP_CLI::error( sprintf( 'Sponsor data file not found: %s', $sponsor_data_file ) );
		}

		if ( ! file_exists( $author_map_file ) ) {
			WP_CLI::error( sprintf( 'Author map file not found: %s', $author_map_file ) );
		}

		// Fetch all legacy IDs.
		$this->fetch_all_legacy_ids();

		// Load sponsor data (sponsors, etc.).
		$this->load_sponsor_data( $sponsor_data_file );

		// Load author map.
		$this->load_author_map( $author_map_file );

		// Fetch all series data.
		$this->fetch_all_series_data();

		// Fetch all tags data.
		$this->fetch_all_tags_data();

		// Count total JSON files.
		$json_files  = glob( rtrim( $json_folder, '/' ) . '/*.json' );
		$total_files = count( $json_files );

		if ( ! $total_files ) {
			WP_CLI::error( sprintf( 'No JSON files found in directory: %s', $json_folder ) );
		}

		// Process JSON files.
		$processed = 0;

		// Set default domain for email.
		add_filter( 'nmt_user_email_default_domain', fn () => 'texastribune.org' );

		$total_files = count( $json_files );
		foreach ( $this->json_directory_iterator( $json_folder, $skip_imported ) as $i => $article_data ) {
			try {
				ConsoleColor::white( 'Processing article' )
				->blue( $article_data['identifier'] )
				->white( ' (' )
				->blue( $i + 1 )
				->white( '/' )
				->blue( $total_files )
				->white( ')' )
				->white( ':' )
				->bright_white( $article_data['metadata']['headline'] )
				->output();
				$this->process_article( $article_data, $pdf_icon_attachment_id );
				++$processed;
			} catch ( Exception $e ) {
				WP_CLI::warning( sprintf( 'Error processing article: %s', $e->getMessage() ) );
			}
		}

		WP_CLI::success( sprintf( 'Processed %d articles%s', $processed, $skip_imported ? sprintf( ' (skipped %d)', $this->skipped ) : '' ) );
	}

	/**
	 * Command to remove the "Uncategorized" category.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_remove_uncategorized_category( $args, $assoc_args ): void {
		$index_from = $assoc_args['index-from'] ?? 0;

		// Get all posts from the "Uncategorized" category.
		$posts = get_posts(
			[
				'category'    => 'uncategorized',
				'numberposts' => -1,
			]
		);

		foreach ( $posts as $index => $post ) {
			if ( $index < $index_from ) {
				continue;
			}

			ConsoleColor::white( 'Processing post' )
				->blue( $post->ID )
				->white( ' (' )
				->blue( $index )
				->white( '/' )
				->blue( count( $posts ) )
				->output();

			$posts_categories = wp_get_post_categories( $post->ID );

			// If the post have more than one category, remove the "Uncategorized" category.
			if ( count( $posts_categories ) > 1 ) {
				wp_remove_object_terms( $post->ID, 'uncategorized', 'category' );

				ConsoleColor::green( 'Removed "Uncategorized" category from post' )
					->bright_green( $post->ID )
					->output();
			}
		}
	}

	/**
	 * Generator function to read JSON files from a directory.
	 *
	 * @param string $directory Directory path.
	 * @param bool   $skip_imported Whether to skip already imported articles.
	 *
	 * @return \Generator
	 */
	private function json_directory_iterator( string $directory, bool $skip_imported = false ): \Generator {
		$directory = rtrim( $directory, '/' );
		$files     = glob( $directory . '/*.json' );

		if ( ! $files ) {
			WP_CLI::error( sprintf( 'No JSON files found in directory: %s', $directory ) );
			return;
		}

		foreach ( $files as $file ) {
			$handle = fopen( $file, 'r' );
			if ( ! $handle ) {
				WP_CLI::warning( sprintf( 'Could not open file: %s', $file ) );
				continue;
			}

			$buffer = '';
			while ( ! feof( $handle ) ) {
				$buffer .= fgets( $handle );

				// Attempt to decode the current buffer as a complete JSON object.
				$data = json_decode( $buffer, true );
				if ( JSON_ERROR_NONE === json_last_error() ) {
					// Skip if article is already imported.
					if ( $skip_imported && $this->get_post_id_from_legacy_id( $data['identifier'] ) ) {
						ConsoleColor::yellow( 'Skipping already imported article: ' )
							->bright_yellow( $data['identifier'] )
							->output();
						++$this->skipped;
						$buffer = '';
						continue;
					}

					yield $data;
					$buffer = '';
				}
			}

			fclose( $handle );
		}
	}

	/**
	 * Load sponsor data from JSON file.
	 *
	 * @param string $file_path Path to sponsor data JSON file.
	 */
	private function load_sponsor_data( string $file_path ): void {
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			WP_CLI::error( sprintf( 'Could not open sponsor data file: %s', $file_path ) );
		}

		$buffer = '';
		while ( ! feof( $handle ) ) {
			$buffer .= fgets( $handle );
		}
		fclose( $handle );

		$data = json_decode( $buffer, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			WP_CLI::error( sprintf( 'Invalid JSON in sponsor data file: %s', json_last_error_msg() ) );
		}

		// Store sponsor data in class properties.
		$this->sponsors_data = $data ?? null;
		// Add other sponsor data as needed.
	}

	/**
	 * Load author map from JSON file.
	 *
	 * @param string $file_path Path to author map JSON file.
	 */
	private function load_author_map( string $file_path ): void {
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			WP_CLI::error( sprintf( 'Could not open author map file: %s', $file_path ) );
		}

		$buffer = '';
		while ( ! feof( $handle ) ) {
			$buffer .= fgets( $handle );
		}
		fclose( $handle );

		$data = json_decode( $buffer, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			WP_CLI::error( sprintf( 'Invalid JSON in author map file: %s', json_last_error_msg() ) );
		}

		if ( ! isset( $data['results'] ) || ! is_array( $data['results'] ) ) {
			WP_CLI::error( 'Author map file does not contain a valid results array.' );
		}

		// Store authors indexed by their ID.
		$this->author_map = [];
		foreach ( $data['results'] as $author ) {
			if ( isset( $author['id'] ) ) {
				$this->author_map[ $author['id'] ] = $author;
			}
		}

		ConsoleColor::green( 'Loaded' )
			->bright_green( count( $this->author_map ) )
			->green( 'authors from the author map.' )
			->output();
	}

	/**
	 * Process a single article.
	 *
	 * @param array $article_data Article data from JSON.
	 * @param int   $pdf_icon_attachment_id Attachment ID of the PDF icon.
	 */
	private function process_article( array $article_data, int $pdf_icon_attachment_id ): void {
		global $wpdb;

		// Reset the height adjustment script added flag.
		$this->height_adjustment_script_added = false;

		// Create post data.
		$post_data = [
			'post_title'   => $article_data['metadata']['headline'],
			'post_status'  => $article_data['metadata']['is_published'] ? 'publish' : 'draft',
			'post_excerpt' => $article_data['metadata']['summary'] ?? '',
		];

		// Handle post name/slug.
		if ( ! empty( $article_data['metadata']['article_url'] ) ) {
			$post_data['post_name'] = basename( $article_data['metadata']['article_url'] );
		}

		// Handle post type.
		$post_type = match ( $article_data['metadata']['type'] ) {
			'article', 'sponsorcontent' => 'post',
			'flatpage' => 'page',
			'articlelink' => 'post',
			default => 'post',
		};

		if ( null === $post_type ) {
			ConsoleColor::yellow( 'Skipping articlelink: ' )
				->bright_yellow( $article_data['identifier'] )
				->output();
			return;
		}

		$post_data['post_type'] = $post_type;

		// Handle dates.
		if ( ! empty( $article_data['metadata']['date_created'] ) ) {
			$post_data['post_date']     = $article_data['metadata']['date_created'];
			$post_data['post_modified'] = $article_data['metadata']['date_created'];
		}

		if ( ! empty( $article_data['metadata']['date_published'] ) ) {
			$post_data['post_date'] = $article_data['metadata']['date_published'];
		}

		if ( ! empty( $article_data['metadata']['date_modified'] ) ) {
			$post_data['post_modified'] = $article_data['metadata']['date_modified'];
		}

		// Get or create post.
		$post_id = $this->get_or_create_post( $post_data, $article_data['identifier'] );

		if ( is_wp_error( $post_id ) ) {
			ConsoleColor::red( 'Error creating post (' )
				->bright_red( $post_id->get_error_code() )
				->red( '):' )
				->underlined_bright_red( $post_id->get_error_message() )
				->output();
			return;
		}

		// Show updated date.
		update_post_meta( $post_id, 'newspack_show_updated_date', 1 );

		// Set subhead.
		update_post_meta( $post_id, 'newspack_post_subtitle', $article_data['metadata']['summary'] );

		// Handle sponsor if present.
		if ( ! empty( $article_data['metadata']['sponsor'] ) ) {
			$sponsor_term_id = $this->get_sponsor_data( $article_data['metadata']['sponsor'] );
			if ( $sponsor_term_id ) {
				wp_set_object_terms( $post_id, [ $sponsor_term_id ], self::SPONSOR_TAXONOMY );
			}

			// Add "Paid Post" category.
			$paid_post_category = $this->get_or_create_category( 'Paid Post' );
			if ( $paid_post_category ) {
				wp_set_object_terms( $post_id, [ $paid_post_category->term_id ], 'category', true );
			}
		}

		// Handle tags.
		if ( ! empty( $article_data['metadata']['user_facing_tags'] ) ) {
			$this->handle_post_tags( $post_id, $article_data['metadata']['user_facing_tags'] );
			$this->handle_post_topics( $post_id, $article_data['metadata']['user_facing_tags'] );
		}

		// Handle private tags.
		if ( ! empty( $article_data['metadata']['tags'] ) ) {
			$this->handle_post_tags( $post_id, $article_data['metadata']['tags'], true );
		}

		// Handle series.
		if ( ! empty( $article_data['metadata']['series'] ) ) {
			$this->handle_post_series( $post_id, $article_data['metadata']['series'], $article_data['metadata']['series_kicker']['name'] ?? null );
		}

		// Handle featured image.
		$maybe_featured_image_attachment_object = null;
		if ( ! empty( $article_data['metadata']['share_image'] ) ) {
			$maybe_featured_image_attachment_object = $this->handle_image_import(
				$article_data['metadata']['share_image']['url'],
				$post_id,
				null,
				null,
				$article_data['metadata']['share_image']['photo_description']
			);

			// If there was some issue with the image download, this will be a WP_Error. In that case,
			// let's just set it to null, so that we can simplify the method signatures
			// and logic that will need this object down the line from here.
			if ( is_wp_error( $maybe_featured_image_attachment_object ) ) {
				$maybe_featured_image_attachment_object = null;
			} else {
				set_post_thumbnail( $post_id, $maybe_featured_image_attachment_object->attachment_id );
			}

			// Featured image position.
			if ( 'featured' === $article_data['metadata']['layout'] ) {
				update_post_meta( $post_id, 'newspack_featured_image_position', 'above' );
			}
		}

		// Delete all the corrections on the post.
		$post_corrections = $this->get_post_corrections( $post_id );
		foreach ( $post_corrections as $correction_id ) {
			wp_delete_post( $correction_id, true );
		}

		// Process content components.
		$content = $this->get_post_content_by_handling_components(
			$article_data['components'],
			$post_id,
			$maybe_featured_image_attachment_object,
			$pdf_icon_attachment_id
		);

		// Get first component.
		$first_component = current( parse_blocks( $content ) );
		if ( $first_component ) {
			$first_component_type = $first_component['blockName'];
			if ( in_array( $first_component_type, [ 'core/video', 'core/embed', 'core/image', 'core/gallery' ], true ) ) {
				update_post_meta( $post_id, 'newspack_featured_image_position', 'hidden' );
			}
		}

		// Update post with content and post data in case they were updated.
		wp_update_post(
			array_merge(
				[
					'ID'           => $post_id,
					'post_content' => $content,
				],
				$post_data
			)
		);

		// Handle articlelink.
		if ( 'articlelink' === $article_data['metadata']['type'] ) {
			$this->handle_articlelink( $post_id, $article_data['metadata']['headline'], $article_data['metadata']['url_override'] );
		}

		// Handle authors and contributors.
		$this->handle_authors_and_contributors( $post_id, $article_data['metadata']['authors'], $article_data['metadata']['contributors'] );

		// Handle permalink with dates different from the post date.
		$original_permalink = join( '/', $article_data['metadata']['path_array'] );

		$original_post_date = ( new \DateTime( $article_data['metadata']['date_published'] ) )->format( 'Y/m/d' );

		if ( ! str_starts_with( $original_permalink, $original_post_date ) ) {
			$relative_permalink = wp_make_link_relative( get_permalink( $post_id ) );
			$this->redirection->create_redirection_rule_in_group(
				'Different date permalink: ' . $article_data['metadata']['headline'],
				$original_permalink,
				$relative_permalink,
				'articlelink'
			);

			ConsoleColor::green( 'Created redirection group for articlelink post' )
				->bright_green( $post_id . ':' )
				->green( "$original_permalink => " )
				->green( $relative_permalink )
				->output();
		}

		// Update modification date directly in the database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->posts,
			[
				'post_modified'     => $article_data['metadata']['date_modified'] ?? $article_data['metadata']['date_published'],
				'post_modified_gmt' => get_gmt_from_date( $article_data['metadata']['date_modified'] ?? $article_data['metadata']['date_published'] ),
			],
			[ 'ID' => $post_id ]
		);

		ConsoleColor::green( sprintf( 'MIGRATED_POST,%s,%s', $article_data['metadata']['article_url'], get_permalink( $post_id ) ) )->output();
	}

	/**
	 * Get or create a post. Based on the legacy ID, we'll either get the post or create it.
	 *
	 * @param array  $post_data Post data.
	 * @param string $identifier Identifier.
	 * @return int|WP_Error The post ID or WP_Error if the post was not created.
	 */
	private function get_or_create_post( array $post_data, string $identifier ): int|WP_Error {
		// Get post by legacy ID.
		$post_id = $this->get_post_id_from_legacy_id( $identifier );

		if ( $post_id ) {
			ConsoleColor::green( 'Updating the post: ' )->bright_green( $post_id )->output();
			return $post_id;
		}

		ConsoleColor::green( 'Creating the post:' )->bright_green( $post_data['post_title'] )->output();
		$post_id = wp_insert_post( $post_data, true );

		if ( ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, '_newspack_legacy_id', $identifier );
		}

		return $post_id;
	}

	/**
	 * Handle authors and contributors.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $authors_data The authors.
	 * @param array $contributors_data The contributors.
	 */
	private function handle_authors_and_contributors( int $post_id, array $authors_data, array $contributors_data ): void {
		$authors      = [];
		$contributors = [];

		// WP Users and co-authors.
		foreach ( $authors_data as $author ) {
			$authors[] = $this->handle_user_by_role( $author, 'author' );
		}

		foreach ( $contributors_data as $contributor ) {
			$contributors[] = $this->handle_user_by_role( $contributor, 'contributor' );
		}

		// Set post author and co-authors.
		$post_author = $authors[0]['wp_user_id'] ?? null;
		if ( $post_author ) {
			wp_update_post(
				[
					'ID'          => $post_id,
					'post_author' => $post_author,
				]
			);
		}

		// Co-authors.
		$filtered_authors = array_values(
			array_filter(
				$authors,
				function ( $author ) {
					// Skip "Texas Tribune Staff" (id = 16) from the co-authors list.
					return 16 !== $author['id'];
				}
			)
		);

		$co_authors = array_merge( $filtered_authors, $contributors );

		if ( ! empty( $co_authors ) ) {
			// Co-authors.
			$maybe_coauthors_have_been_set = $this->co_authors_plus->add_coauthors(
				$post_id,
				array_column( $co_authors, 'wp_user_id' ),
				false,
				'id'
			);

			if ( ! $maybe_coauthors_have_been_set ) {
				ConsoleColor::red( 'Error setting co-authors for post: ' )
					->bright_red( $post_id )
					->output();
			}
		}

		// Custom authors byline.
		$this->set_custom_authors_byline( $post_id, $authors );
		// Custom contributors byline.
		$this->set_custom_contributors_byline( $post_id, $contributors );
	}

	/**
	 * Handle a user by role.
	 *
	 * @param array  $user_raw_data The user raw data.
	 * @param string $role The role.
	 * @return bool|array User data if the user was created, false otherwise.
	 */
	private function handle_user_by_role( array $user_raw_data, string $role ): bool|array {
		$mapped_author = $this->author_map[ $user_raw_data['id'] ] ?? null;

		$user_data = [
			'display_name' => $user_raw_data['name'],
			'role'         => $role,
		];

		// Special case for Texas Tribune Staff.
		if ( 1547 === intval( $user_raw_data['id'] ) ) {
			$user_data['user_email']    = 'multiple-staffs@example.com';
			$user_data['user_login']    = 'multiple-staffs';
			$user_data['user_nicename'] = 'texas-tribune-newsroom-fort-worth-amarillo-staffs';
		} else {
			$user_data['user_email'] = $mapped_author['email'] ?? sanitize_title( $user_raw_data['name'] ) . '@texastribune.org';
			$user_data['user_login'] = sanitize_title( $user_raw_data['name'] );
		}

		if ( $mapped_author ) {
			$user_data['meta_input'] = [
				'twitter'               => $mapped_author['twitter'],
				'facebook'              => $mapped_author['facebook'],
				'newspack_phone_number' => $mapped_author['phone'],
				'newspack_job_title'    => $mapped_author['job_title'],
				'description'           => $mapped_author['bio'],
			];
		} else {
			echo 1;
		}

		try {
			$user = UsersHelper::create_or_get_user( $user_data, $user_raw_data['id'] );
		} catch ( Exception $e ) {
			ConsoleColor::red( 'Error creating author (' )
				->bright_red( $e->getCode() )
				->red( '):' )
				->underlined_bright_red( $e->getMessage() )
				->output();
			return false;
		}

		// Handle avatar.
		if ( $mapped_author && ! empty( $mapped_author['mug'] ) ) {
			$avatar_attachment_id = Attachments::import_external_file( $mapped_author['mug'] );
			$this->simple_local_avatars_logic->assign_avatar( $user->ID, $avatar_attachment_id );
		}

		return array_merge( [ 'wp_user_id' => $user->ID ], $user_raw_data );
	}

	/**
	 * Sets a custom byline for a post based on author data.
	 *
	 * @param int   $post_id The ID of the post to set the byline for.
	 * @param array $authors Array of author objects containing id, name, affiliation, role_display.
	 * @return void
	 */
	private function set_custom_authors_byline( int $post_id, array $authors ): void {
		// Group authors by affiliation.
		$authors_by_affiliation = [];

		// Set prefix based on first author's role_display.
		$prefix = ! empty( $authors[0]['role_display'] ) ? '' : 'By';

		// Group authors by their affiliation.
		foreach ( $authors as $author ) {
			$affiliation_key = 'The Texas Tribune'; // Default affiliation for staff.

			if ( ! empty( $author['affiliation'] ) && ! empty( $author['affiliation']['name'] ) ) {
				$affiliation_key = $author['affiliation']['name'];
			}

			if ( ! isset( $authors_by_affiliation[ $affiliation_key ] ) ) {
				$authors_by_affiliation[ $affiliation_key ] = [];
			}
			$authors_by_affiliation[ $affiliation_key ][] = $author;
		}

		// Start building the byline.
		$byline_parts = [];
		if ( $prefix ) {
			$byline_parts[] = $prefix;
		}
		$first_group = true;

		foreach ( $authors_by_affiliation as $affiliation => $group_authors ) {
			// Create author links for this group.
			$author_links = [];
			foreach ( $group_authors as $author ) {
				$author_text = '';

				// Add role_display if present.
				if ( ! empty( $author['role_display'] ) ) {
					$author_text .= $author['role_display'] . ' ';
				}

				if ( ! empty( $author['wp_user_id'] ) ) {
					$author_text .= sprintf(
						'[Author id=%s]%s[/Author]',
						$author['wp_user_id'],
						$author['name']
					);
				} else {
					$author_text .= $author['name'];
				}

				$author_links[] = $author_text;
			}

			// Join authors in this group with proper grammar.
			if ( ! $first_group ) {
				$byline_parts[] = 'and';
			}
			$byline_parts[] = $this->join_with_oxford_comma( $author_links );

			// Add affiliation if it's not the default Texas Tribune staff.
			if ( 'The Texas Tribune' !== $affiliation || count( $authors_by_affiliation ) > 1 ) {
				if ( $first_group ) {
					$byline_parts[] = ',';
				}
				$byline_parts[] = $affiliation;
			}

			$first_group = false;
		}

		// Store the custom byline.
		$custom_byline = implode( ' ', $byline_parts );
		$custom_byline = str_replace( ' ,', ',', $custom_byline );
		update_post_meta( $post_id, '_newspack_byline_active', true );
		update_post_meta( $post_id, '_newspack_byline', $custom_byline );
	}

	/**
	 * Sets a custom contributors byline for a post based on contributor data.
	 *
	 * @param int   $post_id      The ID of the post to set the contributors byline for.
	 * @param array $contributors Array of contributor objects containing id, name, affiliation, and wp_user_id.
	 * @return void
	 */
	private function set_custom_contributors_byline( int $post_id, array $contributors ): void {
		if ( empty( $contributors ) ) {
			return;
		}

		// Process each contributor.
		$contributor_parts = [];
		foreach ( $contributors as $contributor ) {
			if ( ! empty( $contributor['wp_user_id'] ) ) {
				// WP user - create link.
				$contributor_parts[] = sprintf(
					'<a href="%s">%s</a>',
					get_author_posts_url( $contributor['wp_user_id'] ),
					$contributor['name']
				);
			} else {
				// External contributor or no WP user - just use name.
				$contributor_parts[] = $contributor['name'];
			}
		}

		// Join contributors with proper grammar.
		$contributors_string = $this->join_with_oxford_comma( $contributor_parts );

		// Create the complete contributors line.
		$contributors_line = sprintf( '%s contributed to this report.', $contributors_string );

		// Get current post content.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// Create contributors paragraph block.
		$contributors_block = serialize_block(
			$this->block_generator->get_paragraph( $contributors_line )
		);

		// Append contributors block to post content.
		$updated_content = $post->post_content . "\n\n" . $contributors_block;

		// Update the post.
		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => $updated_content,
			]
		);
	}

	/**
	 * Joins an array of strings with commas and 'and' for the last item.
	 *
	 * @param array $items Array of strings to join.
	 * @return string The joined string.
	 */
	private function join_with_oxford_comma( array $items ): string {
		$count = count( $items );
		if ( 0 === $count ) {
			return '';
		}
		if ( 1 === $count ) {
			return $items[0];
		}
		if ( 2 === $count ) {
			return $items[0] . ' and ' . $items[1];
		}

		$last_item = array_pop( $items );
		return implode( ', ', $items ) . ' and ' . $last_item;
	}

	/**
	 * Get sponsor data by ID.
	 *
	 * @param int $sponsor_id The sponsor ID.
	 * @return int|null The sponsor term ID or null if not found.
	 */
	private function get_sponsor_data( int $sponsor_id ): ?int {
		// Convert the integer sponsor_id to string for JSON lookup.
		$sponsor_id_str = (string) $sponsor_id;

		$sponsor_data = $this->sponsors_data[ $sponsor_id_str ] ?? null;

		if ( ! $sponsor_data ) {
			return null;
		}

		// Get or create the sponsor post.
		$sponsor_post = get_posts(
			[
				'post_type'   => self::SPONSOR_POST_TYPE,
				'meta_key'    => '_newspack_migration_sponsor_id',
				'meta_value'  => $sponsor_id,
				'post_status' => 'publish',
				'numberposts' => 1,
			]
		);

		if ( ! empty( $sponsor_post ) ) {
			$sponsor_post = $sponsor_post[0];
		} else {
			// Create the sponsor post.
			$sponsor_post_id = $this->create_sponsor_post( $sponsor_id, $sponsor_data['name'], $sponsor_data['url'] );
			if ( ! $sponsor_post_id ) {
				return null;
			}
			$sponsor_post = get_post( $sponsor_post_id );
		}

		// Get or create the term for this sponsor.
		$term = get_term_by( 'slug', $sponsor_post->post_name, self::SPONSOR_TAXONOMY );
		if ( ! $term ) {
			$term_result = wp_insert_term(
				$sponsor_post->post_title,
				self::SPONSOR_TAXONOMY,
				[
					'slug' => $sponsor_post->post_name,
				]
			);
			if ( is_wp_error( $term_result ) ) {
				return null;
			}
			$term = get_term( $term_result['term_id'], self::SPONSOR_TAXONOMY );
		}

		return $term ? $term->term_id : null;
	}

	/**
	 * Create a sponsor post.
	 *
	 * @param int    $sponsor_id Original sponsor ID.
	 * @param string $name Sponsor name.
	 * @param string $url Sponsor URL.
	 * @return int|null The created sponsor post ID or null on failure.
	 */
	private function create_sponsor_post( int $sponsor_id, string $name, string $url ): ?int {
		$post_data = [
			'post_title'  => $name,
			'post_status' => 'publish',
			'post_type'   => self::SPONSOR_POST_TYPE,
			'meta_input'  => [
				'_newspack_migration_sponsor_id'           => $sponsor_id,
				'newspack_sponsor_url'                     => $url,
				'newspack_sponsor_sponsorship_scope'       => 'native',
				'newspack_sponsor_underwriter_style'       => 'simple',
				'newspack_sponsor_underwriter_placement'   => 'inherit',
				'newspack_sponsor_native_category_display' => 'inherit',
				'newspack_sponsor_native_byline_display'   => 'inherit',
			],
		];

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			ConsoleColor::red( 'Error creating sponsor post (' )
				->bright_red( $name )
				->red( '):' )
				->underlined_bright_red( is_wp_error( $post_id ) ? $post_id->get_error_message() : 'Unknown error' )
				->output();
			return null;
		}

		return $post_id;
	}

	/**
	 * Fetch all tags data from the Texas Tribune API and store in cache.
	 *
	 * @return void
	 */
	private function fetch_all_tags_data(): void {
		if ( ! empty( $this->tags_data ) ) {
			return;
		}

		$this->tags_data = [];
		$next_url        = 'https://www.texastribune.org/api/v2/tags/?limit=100';

		while ( $next_url ) {
			$response = wp_remote_get( $next_url );

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				ConsoleColor::red( 'Error fetching tags data from ' )
					->bright_red( $next_url )
					->red( ':' )
					->underlined_bright_red( is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response ) )
					->output();
				break;
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( ! is_array( $data ) || ! isset( $data['results'] ) ) {
				ConsoleColor::red( 'Invalid response from tags API.' )->output();
				break;
			}

			// Store each tag in our cache, indexed by ID.
			foreach ( $data['results'] as $tag ) {
				if ( isset( $tag['id'] ) && isset( $tag['name'] ) && isset( $tag['type'] ) ) {
					// Get parent tag name based on type.
					$parent_name = 'subject' === strtolower( $tag['type'] ) ? 'Topic' : ucfirst( strtolower( $tag['type'] ) );

					$this->tags_data[ $tag['id'] ] = [
						'name'        => $tag['name'],
						'parent_name' => $parent_name,
					];
				}
			}

			// Get the next page URL, if any.
			$next_url = $data['next'] ?? null;
		}

		ConsoleColor::green( 'Fetched' )
			->bright_green( count( $this->tags_data ) )
			->green( 'tags from the API.' )
			->output();
	}

	/**
	 * Get tag data from the Texas Tribune API.
	 *
	 * @param int $tag_id The tag ID to fetch.
	 * @return array{name:string,slug:string,parent_name:string}|null Tag data or null on failure.
	 */
	private function get_tag_data( int $tag_id ): ?array {
		// Return from cache if available.
		return $this->tags_data[ $tag_id ] ?? null;
	}

	/**
	 * Fetch all series data from the Texas Tribune API and store in cache.
	 *
	 * @return void
	 */
	private function fetch_all_series_data(): void {
		if ( ! empty( $this->series_data ) ) {
			return;
		}

		$this->series_data = [];
		$next_url          = 'https://www.texastribune.org/api/v2/series/?limit=100';

		while ( $next_url ) {
			$response = wp_remote_get( $next_url );

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				ConsoleColor::red( 'Error fetching series data from ' )
					->bright_red( $next_url )
					->red( ':' )
					->underlined_bright_red( is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response ) )
					->output();
				break;
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( ! is_array( $data ) || ! isset( $data['results'] ) ) {
				ConsoleColor::red( 'Invalid response from series API.' )->output();
				break;
			}

			// Store each series in our cache, indexed by ID.
			foreach ( $data['results'] as $series ) {
				if ( isset( $series['id'] ) ) {
					$this->series_data[ $series['id'] ] = [
						'name'    => $series['name'],
						'slug'    => $series['slug'],
						'summary' => $series['summary'] ?? '',
					];
				}
			}

			// Get the next page URL, if any.
			$next_url = $data['next'] ?? null;
		}

		ConsoleColor::green( 'Fetched' )
			->bright_green( count( $this->series_data ) )
			->green( 'series from the API.' )
			->output();
	}

	/**
	 * Get series data from the Texas Tribune API.
	 *
	 * @param int $series_id The series ID to fetch.
	 * @return array{name:string,slug:string,summary:string}|null Series data or null on failure.
	 */
	private function get_series_data( int $series_id ): ?array {
		// Return from cache if available.
		return $this->series_data[ $series_id ] ?? null;
	}

	/**
	 * Handle tags for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $tag_ids Array of tag IDs.
	 * @param bool  $is_private Whether the tags are private.
	 * @return void
	 */
	private function handle_post_tags( int $post_id, array $tag_ids, bool $is_private = false ): void {
		$tag_ids_to_set = [];

		foreach ( $tag_ids as $tag_id ) {
			$tag_data = $this->get_tag_data( $tag_id );
			if ( null !== $tag_data ) {
				// Skip if this tag should be treated as a topic category.
				if ( in_array( $tag_data['name'], self::TAGS_AS_TOPICS, true ) ) {
					continue;
				}

				// Handle regular tags.
				$parent_term = get_term_by( 'name', $tag_data['parent_name'], 'post_tag' );
				if ( ! $parent_term ) {
					$parent_result = wp_insert_term( ucfirst( $tag_data['parent_name'] ), 'post_tag' );

					if ( ! is_wp_error( $parent_result ) ) {
						$parent_term = get_term( $parent_result['term_id'], 'post_tag' );
					}
				}

				// Create child tag if parent exists.
				if ( $parent_term && ! is_wp_error( $parent_term ) ) {
					$child_term = get_term_by( 'name', $tag_data['name'], 'post_tag' );
					if ( ! $child_term ) {
						$child_result = wp_insert_term(
							$tag_data['name'],
							'post_tag',
							[ 'parent' => $parent_term->term_id ]
						);

						if ( ! is_wp_error( $child_result ) ) {
							if ( $is_private ) {
								// Set tag meta.
								update_term_meta( $child_result['term_id'], 'np_private_tag', true );
							}

							$tag_ids_to_set[] = $child_result['term_id'];
							continue;
						}
					} elseif ( $child_term->parent !== $parent_term->term_id ) {
						// Update parent if it's different.
						wp_update_term(
							$child_term->term_id,
							'post_tag',
							[
								'parent' => $parent_term->term_id,
							]
						);
					}

					if ( $child_term && ! is_wp_error( $child_term ) ) {
						$tag_ids_to_set[] = $child_term->term_id;
					}
				} else {
					ConsoleColor::red( 'Error creating tag (' )
						->bright_red( $tag_data['name'] )
						->red( '):' )
						->underlined_bright_red( 'Parent term not found' )
						->output();
				}
			}
		}

		// Set regular tags.
		if ( ! empty( $tag_ids_to_set ) ) {
			wp_set_post_tags( $post_id, $tag_ids_to_set, false );
		}
	}

	/**
	 * Handle topic categories for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $tag_ids Array of tag IDs.
	 * @return void
	 */
	private function handle_post_topics( int $post_id, array $tag_ids ): void {
		$category_ids_to_set = [];

		// Get or create parent "Topics" category.
		$topics_parent_term = $this->get_or_create_category( 'Topics' );
		if ( ! $topics_parent_term ) {
			ConsoleColor::red( 'Error creating parent Topics category' )->output();
			return;
		}

		foreach ( $tag_ids as $tag_id ) {
			$tag_data = $this->get_tag_data( $tag_id );
			if ( null !== $tag_data && in_array( strtolower( $tag_data['name'] ), self::TAGS_AS_TOPICS, true ) ) {
				// Create or get the topic category.
				$topic_term = $this->get_or_create_category(
					$tag_data['name'],
					'',
					$topics_parent_term->term_id
				);

				if ( $topic_term ) {
					$category_ids_to_set[] = $topic_term->term_id;
				}
			}
		}

		// Set topic categories (append to existing categories).
		if ( ! empty( $category_ids_to_set ) ) {
			wp_set_post_categories( $post_id, $category_ids_to_set );
		}
	}

	/**
	 * Handle series for a post.
	 *
	 * @param int         $post_id Post ID.
	 * @param array       $series_ids Array of series IDs.
	 * @param string|null $series_kicker Optional. Series kicker.
	 * @return void
	 */
	private function handle_post_series( int $post_id, array $series_ids, ?string $series_kicker = null ): void {
		// Get or create parent "Series" category.
		$parent_term = $this->get_or_create_category( 'Series' );
		if ( ! $parent_term ) {
			ConsoleColor::red( 'Error creating parent Series category' )->output();
			return;
		}

		$category_ids = [];
		foreach ( $series_ids as $series_id ) {
			$series_data = $this->get_series_data( $series_id );
			if ( null !== $series_data ) {
				// Create or get the series category.
				$series_term = $this->get_or_create_category(
					$series_data['name'],
					$series_data['slug'],
					$parent_term->term_id,
					$series_data['summary']
				);

				if ( $series_term ) {
					$category_ids[] = $series_term->term_id;
				}
			}
		}

		if ( ! empty( $category_ids ) ) {
			wp_set_post_categories( $post_id, $category_ids, true );
		}

		// Handle primary category.
		if ( $series_kicker ) {
			$primary_category = $this->get_or_create_category( $series_kicker );
			if ( $primary_category ) {
				update_post_meta( $post_id, '_yoast_wpseo_primary_category', $primary_category->term_id );
			}
		} else {
			// If no series_kicker, set the first category as primary.
			update_post_meta( $post_id, '_yoast_wpseo_primary_category', $category_ids[0] );
		}
	}

	/**
	 * Get or create a category term, optionally setting a parent and description.
	 *
	 * @param string      $name        The category name.
	 * @param string      $slug        Optional. The category slug. If not provided, will be generated from name.
	 * @param int|null    $parent_id   Optional. The parent term ID.
	 * @param string|null $description Optional. The category description.
	 *
	 * @return \WP_Term|null The term object if successful, null otherwise.
	 */
	private function get_or_create_category( string $name, string $slug = '', ?int $parent_id = null, ?string $description = null ): ?\WP_Term {
		// First try to get by slug if provided.
		if ( ! empty( $slug ) ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				// Update parent if different.
				if ( null !== $parent_id && $term->parent !== $parent_id ) {
					wp_update_term(
						$term->term_id,
						'category',
						[
							'parent' => $parent_id,
						]
					);
					$term = get_term( $term->term_id, 'category' );
				}
				return $term;
			}
		}

		// Then try by name.
		$term = get_term_by( 'name', $name, 'category' );
		if ( $term && ! is_wp_error( $term ) ) {
			// Update parent if different.
			if ( null !== $parent_id && $term->parent !== $parent_id ) {
				wp_update_term(
					$term->term_id,
					'category',
					[
						'parent' => $parent_id,
					]
				);
				$term = get_term( $term->term_id, 'category' );
			}
			return $term;
		}

		// Create new term if not found.
		$args = [ 'parent' => $parent_id ];
		if ( ! empty( $slug ) ) {
			$args['slug'] = $slug;
		}
		if ( ! empty( $description ) ) {
			$args['description'] = $description;
		}

		$result = wp_insert_term( $name, 'category', $args );
		if ( is_wp_error( $result ) ) {
			return null;
		}

		return get_term( $result['term_id'], 'category' );
	}

	/**
	 * Return fully formed HTML from the supplied components.
	 *
	 * @param array                                $components Components to process.
	 * @param int                                  $post_id Post ID.
	 * @param TexasTribuneAttachmentMetadataObject $featured_image_object Object representing a featured image.
	 * @param int                                  $pdf_icon_attachment_id Attachment ID of the PDF icon.
	 *
	 * @return string
	 */
	private function get_post_content_by_handling_components( array $components, int $post_id, ?TexasTribuneAttachmentMetadataObject $featured_image_object, ?int $pdf_icon_attachment_id ): string {
		$content = '';

		$previous = null;
		foreach ( $components as $key => $component ) {
			$next     = $components[ $key + 1 ] ?? null;
			$content .= $this->handle_component( $component, $post_id, $previous, $next, $featured_image_object, $pdf_icon_attachment_id );
			$previous = $component;
		}

		return $content;
	}

	/**
	 * This function handles specific components and returns the HTML.
	 *
	 * @param array                                     $component Component to process.
	 * @param int                                       $post_id Post ID.
	 * @param array|null                                $previous_sibling Previous sibling component.
	 * @param array|null                                $next_sibling Next sibling component.
	 * @param TexasTribuneAttachmentMetadataObject|null $featured_image The featured image set for the post.
	 *
	 * @return string
	 */
	private function handle_component( array $component, int $post_id, ?array $previous_sibling, ?array $next_sibling, ?TexasTribuneAttachmentMetadataObject $featured_image, ?int $pdf_icon_attachment_id ): string {
		switch ( strtolower( $component['role'] ) ) {
			case 'header':
				return '';
			case 'container':
			case 'text container':
			case 'thumbnail container':
			case 'thumbnail text container':
			case 'thumbnail entry':
			case 'sections':
			case 'sections container':
				return $this->get_post_content_by_handling_components( $component['components'], $post_id, $featured_image, $pdf_icon_attachment_id );
			case 'sections entry container':
				return $this->handle_sections_entry_container( $component, $post_id, $pdf_icon_attachment_id );
			case 'text':
				if ( isset( $component['text'] ) && '* * *' === $component['text'] ) {
					return serialize_block( $this->block_generator->get_separator( 'is-stile-dots' ) );
				}

				return $this->handle_text_component( $component );
			case 'context snippet':
				return $this->handle_context_snippet_component( $component );
			case 'heading':
				return $this->handle_heading_component( $component );
			case 'archival raw elem':
				return $this->handle_archival_element_component( $component );
			case 'raw elem':
				return $this->handle_raw_element_compontent( $component );
			case 'important corrections container':
				foreach ( $component['components'] as $sub_component ) {
					$this->handle_correction_component( $sub_component, $post_id, true );
				}

				return '';
			case 'normal corrections container':
				foreach ( $component['components'] as $sub_component ) {
					$this->handle_correction_component( $sub_component, $post_id );
				}

				return '';
			case 'pullquote':
				return $this->handle_pullquote_component( $component );
			case 'list':
				return $this->handle_list_component( $component );
			case 'table of contents':
				return $this->handle_table_of_contents_component( $component );
			case 'related link':
				return $this->handle_related_link_component( $component );
			case 'photo':
				return $this->handle_photo_component( $component, $post_id, $featured_image );
			case 'mosaic':
				return $this->handle_mosaic_component( $component, $post_id );
			case 'thumbnail':
				$thumbnail_block = '';
				foreach ( $component['components'] as $sub_component ) {
					$modded_component             = $sub_component;
					$modded_component['location'] = $component['location'];
					$thumbnail_block             .= $this->handle_photo_component(
						$modded_component,
						$post_id,
						$featured_image
					);
				}

				return $thumbnail_block;
			case 'legacy data graphic':
			case 'data graphic':
				return $this->handle_data_graphic_component( $component, $next_sibling, $post_id );
			case 'video':
				return $this->handle_video_component( $component, $next_sibling, $post_id );
			case 'iframe':
				return $this->handle_iframe_component( $component );
			case 'tweet':
				return serialize_block( $this->block_generator->get_twitter( $component['url'] ) );
			case 'caption':
				if ( $previous_sibling ) {
					if ( 'photo' === $previous_sibling['role'] && $component['text'] === $previous_sibling['caption'] ) {
						return '';
					}

					$omit_caption_if_previous_sibling_matched_role = [
						'audio',
						'data graphic',
						'video',
					];

					if ( in_array( $previous_sibling['role'], $omit_caption_if_previous_sibling_matched_role, true ) ) {
						return '';
					}
				}

				return serialize_block( $this->handle_caption_component( $component ) );
			case 'audio':
				return $this->handle_audio_component( $component );
			case 'document link':
				return $this->handle_document_link_component( $component, $pdf_icon_attachment_id );
			case 'series list':
				return $this->handle_series_list_component( $component );
			case 'series snippet':
				return $this->handle_series_snippet_component( $component );
			case 'cta membership':
				return $this->handle_cta_membership_component( $component, $post_id );
			case 'simple cta':
				return $component['text'] ?? '';
			case 'faq container':
				return $this->handle_faq_container_component( $component, $post_id );
			case 'newsletter signup':
				return $this->handle_newsletter_signup_component( $component );
			case 'divider':
				return serialize_block(
					$this->block_generator->get_separator( 'is-style-wide' )
				);
			case 'pulitzer':
				return $this->handle_pulitzer_component( $component, $post_id );
			default:
				ConsoleColor::yellow( 'Skipped Component' )->underlined_yellow( $component['role'] )->output();
				return '';
		}
	}

	/**
	 * Handles sections entry container components and returns HTML.
	 *
	 * @param array $component Component to process.
	 * @param int   $post_id Post ID.
	 * @param int   $pdf_icon_attachment_id Attachment ID of the PDF icon.
	 *
	 * @return string
	 */
	private function handle_sections_entry_container( array $component, int $post_id, ?int $pdf_icon_attachment_id ): string {
		$sections_entry      = '';
		$timestamp           = '';
		$copy_link           = '';
		$component_permalink = get_permalink( $post_id ) . '#' . $component['identifier'];

		if ( isset( $component['show_copy_link'] ) && $component['show_copy_link'] ) {
			$copy_link = '<a href="#' . $component['identifier'] . '"><span class="dashicons dashicons-admin-links" onclick="navigator.clipboard.writeText(\'' . $component_permalink . '\');"></span></a>';
		}

		foreach ( $component['components'] as $sub_component ) {
			switch ( $sub_component['role'] ) {
				case 'sections entry heading':
					$sections_entry .= serialize_block( $this->block_generator->get_heading( $sub_component['text'], 'h2', $component['identifier'] ) );
					break;
				case 'sections entry content':
					$sections_entry .= $this->get_post_content_by_handling_components( $sub_component['components'], $post_id, null, $pdf_icon_attachment_id );
					break;
				case 'sections entry timestamp':
					$timestamp = '<time datetime="' . $sub_component['timestamp'] . '">' . $sub_component['text'] . '</time>';
					break;
				default:
					ConsoleColor::bright_magenta( 'Unhandled Section Entry Object' )
								->underlined_bright_magenta( $sub_component['role'] )
								->output();
			}
		}

		return $timestamp . $copy_link . $sections_entry;
	}

	/**
	 * Handles text components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return string
	 */
	private function handle_text_component( array $component ): string {
		return serialize_block( $this->block_generator->get_paragraph( $component['text'] ) );
	}

	/**
	 * Handles context snippet components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return string
	 */
	private function handle_context_snippet_component( array $component ): string {
		$title                = '';
		$emphasized_title     = '';
		$has_emphasized_title = false;

		if ( isset( $component['title'] ) && $component['title'] ) {
			$title = $component['title'];
		}

		if ( isset( $component['emphasized_title'] ) && $component['emphasized_title'] ) {
			$emphasized_title     = ( empty( $title ) ? '' : ' ' ) . $component['emphasized_title'];
			$has_emphasized_title = true;
		}

		if ( ! empty( $title ) || ! empty( $emphasized_title ) ) {
			$heading = serialize_block(
				$this->block_generator->get_heading(
					'<span style="text-transform:uppercase;' . ( $has_emphasized_title ? 'font-weight:500;' : '' ) . '">' . $title . '</span>'
					. $emphasized_title
				)
			);

			$emphasized_paragraph = serialize_block(
				$this->block_generator->get_paragraph(
					'<em>' . $component['description'] . '</em>'
				)
			);

			return $heading . $emphasized_paragraph;
		}

		return '';
	}

	/**
	 * Handles heading components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return string
	 */
	private function handle_heading_component( array $component ): string {
		return serialize_block(
			$this->block_generator->get_heading(
				$component['text'],
				'h' . $component['level'],
				$component['identifier'] ?? '',
			)
		);
	}

	/**
	 * Handles archival element components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return string
	 */
	private function handle_archival_element_component( array $component ): string {
		$allowed_html = wp_kses_allowed_html( 'post' );
		unset( $allowed_html['script'] );
		return wp_kses( $component['content'], $allowed_html, wp_allowed_protocols() );
	}

	/**
	 * Handles raw element components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return string
	 */
	private function handle_raw_element_compontent( array $component ): string {
		$raw_element = $component['content'];

		if ( isset( $component['css content'] ) ) {
			$raw_element .= $component['css content'];
		}

		// Format and prepend JavaScript to HTML content.
		if ( ! empty( $component['js content'] ) ) {
			// Clean up the script content.
			$js_content = str_replace( '    ', "\n", $component['js content'] );

			// More precise formatting with regex.
			$js_content = preg_replace( '/}\s+/', "}\n", $js_content );
			$js_content = preg_replace( '/{\s+/', "{\n", $js_content );
			$js_content = preg_replace( '/;\s+/', ";\n", $js_content );
			$js_content = preg_replace( '/const\s+/', "\nconst ", $js_content );
			$js_content = preg_replace( '/\/\/\s+/', "\n// ", $js_content );

			// Add proper indentation.
			$js_content = preg_replace( '/\n/', "\n    ", $js_content );

			// Clean up any extra spaces that might have been created.
			$js_content = preg_replace( '/\s+\n/', "\n", $js_content );

			// Prepend the formatted JavaScript to the HTML content.
			$raw_element = $raw_element . $js_content;
		}

		return serialize_block( $this->block_generator->get_html( $raw_element ) );
	}

	/**
	 * Handles correction components and updates post metadata.
	 *
	 * @param array $component Component to process.
	 * @param int   $post_id Post ID.
	 * @param bool  $important Whether the correction is important.
	 *
	 * @return void
	 */
	private function handle_correction_component( array $component, int $post_id, bool $important = false ): void {
		// Format the timestamp to WordPress format (Y-m-d H:i:s).
		$timestamp      = $component['timestamp'];
		$date_time      = new \DateTime( $timestamp );
		$formatted_date = $date_time->format( 'Y-m-d H:i:s' );

		// Create the correction post.
		$correction_data = [
			'post_type'    => 'newspack_correction',
			'post_status'  => 'publish',
			'post_title'   => sprintf( 'Correction for %s', get_the_title( $post_id ) ),
			'post_content' => $component['text'],
			'post_date'    => $formatted_date,
		];

		$correction_id = wp_insert_post( $correction_data );

		if ( is_wp_error( $correction_id ) ) {
			ConsoleColor::red( 'Error creating correction (' )
				->bright_red( $correction_id->get_error_code() )
				->red( '):' )
				->underlined_bright_red( $correction_id->get_error_message() )
				->output();
			return;
		}

		// Link the correction to the post.
		update_post_meta( $correction_id, 'newspack_correction-post-id', $post_id );

		// Set the correction type and priority.
		update_post_meta( $correction_id, 'newspack_corrections_type', $component['type'] );
		update_post_meta( $correction_id, 'newspack_corrections_priority', $important ? 'high' : 'low' );

		// Set correction settings on the post.
		update_post_meta( $post_id, 'newspack_corrections_active', true );
	}

	/**
	 * Get all the corrections on a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array<int> Array of correction IDs.
	 */
	private function get_post_corrections( int $post_id ): array {
		return get_posts(
			[
				'post_status'    => 'all',
				'post_type'      => 'newspack_correction',
				'meta_key'       => 'newspack_correction-post-id',
				'meta_value'     => $post_id, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);
	}

	/**
	 * Handles pullquote components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return string
	 */
	private function handle_pullquote_component( array $component ): string {
		return serialize_block(
			$this->block_generator->get_quote(
				$component['text'],
				$component['attribution']
			)
		);
	}

	/**
	 * Handles list components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return string
	 */
	private function handle_list_component( array $component ): string {
		$array_list = $this->get_list_array( $component['text'] );

		if ( empty( $array_list['elements'] ) ) {
			return '';
		}

		return serialize_block(
			$this->block_generator->get_list(
				$array_list['elements'],
				$array_list['ordered']
			)
		);
	}

	/**
	 * Get an array representing list items from an HTML list.
	 *
	 * @param string $html_list HTML list.
	 *
	 * @return array
	 */
	private function get_list_array( string $html_list ): array {
		$ordered_list = false;

		if ( str_starts_with( $html_list, '<ol>' ) ) {
			$ordered_list = true;
		}

		$matches = [];
		// regex to extract <li>'s and the contents in between from string.
		$pattern = '/<li>(.*?)<\/li>/';
		preg_match_all( $pattern, $html_list, $matches );

		return [
			'ordered'  => $ordered_list,
			'elements' => array_map(
				fn( $list_item ) => wp_kses_post( trim( $list_item ) ),
				$matches[1]
			),
		];
	}

	/**
	 * Handles table of contents components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return string
	 */
	private function handle_table_of_contents_component( array $component ): string {
		$blocks        = [];
		$heading_block = [];
		$list_block    = [];

		foreach ( $component['components'] as $sub_component ) {
			switch ( $sub_component['role'] ) {
				case 'table of contents heading':
					$heading_block = $this->block_generator->get_heading(
						$sub_component['text']
					);
					break;
				case 'table of contents list':
					$list_array = $this->get_list_array( $sub_component['text'] );

					if ( empty( $list_array['elements'] ) ) {
						break;
					}

					$list_block = $this->block_generator->get_list( $list_array['elements'], $list_array['ordered'] );
					break;
			}
		}

		if ( ! empty( $heading_block ) ) {
			$blocks[] = $heading_block;
		}

		if ( ! empty( $list_block ) ) {
			$blocks[] = $list_block;
		}

		return serialize_block(
			$this->block_generator->get_group_constrained(
				$blocks,
				[
					'is-style-border',
				]
			)
		);
	}

	/**
	 * Handles related link components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return string
	 */
	private function handle_related_link_component( array $component ): string {
		$post_id = $this->get_post_id_from_legacy_id( $component['article_id'] );

		if ( null !== $post_id ) {
			return serialize_block(
				$this->block_generator->get_homepage_articles_for_specific_posts(
					[ $post_id ],
					[
						'postsToShow'   => 1,
						'showAuthor'    => false,
						'showCaption'   => true,
						'sectionHeader' => isset( $component['kicker'] ) ? $component['kicker'] : 'Related Story',
					]
				)
			);
		}

		$paragraph_block = $this->block_generator->get_paragraph(
			'<a href="' . $component['article_url'] . '">' . $component['article_headline'] . '</a>',
		);

		$paragraph_block['attrs'] = array_merge(
			$paragraph_block['attrs'],
			[
				'original-related-story-id' => $component['article_id'],
			]
		);

		return serialize_block( $paragraph_block );
	}

	/**
	 * Handles photo components and returns HTML.
	 *
	 * @param array                                     $component Component to process.
	 * @param int                                       $post_id Post ID.
	 * @param TexasTribuneAttachmentMetadataObject|null $featured_image The featured image set for the post.
	 *
	 * @return string
	 */
	private function handle_photo_component( array $component, int $post_id, ?TexasTribuneAttachmentMetadataObject $featured_image ): string {
		// If the (photo) component has already been imported as a featured image, we don't want to import it again.
		if ( null !== $featured_image ) {
			if ( ! array_key_exists( 'url', $component ) ) {
				ConsoleColor::bright_yellow( 'No URL found for photo component, for post' )
					->underlined_bright_yellow( $post_id )
					->output();
				return '';
			}

			$photo_attachment_object = new TexasTribuneAttachmentMetadataObject( $component['url'] );

			// Not sure if we'd need to do any additional tests to determine if the photo has already been imported as a featured image.
			$decoded_download_url = $photo_attachment_object->decoded_download_url === $featured_image->decoded_download_url;

			if ( $decoded_download_url ) {
				$attachment = get_post( $featured_image->attachment_id );

				if ( isset( $component['caption'] ) && $attachment->post_excerpt !== $component['caption'] ) {
					$attachment_post = array(
						'ID'           => $featured_image->attachment_id,
						'post_excerpt' => $component['caption'],
					);

					if ( isset( $component['file_description'] ) ) {
						$attachment_post['post_content'] = $component['file_description'];
					} elseif ( isset( $component['photo_description'] ) ) {
						$attachment_post['post_content'] = $component['photo_description'];
					}

					wp_update_post( $attachment_post );
				}

				return '';
			}
		}

		$maybe_attachment_id = $this->handle_image_import_and_import_metadata( $component, $post_id );

		if ( is_wp_error( $maybe_attachment_id ) ) {
			return '';
		}

		$image_size = 'full';
		$alignment  = 'center';

		if ( isset( $component['location'] ) ) {
			switch ( strtolower( $component['location'] ) ) {
				case 'narrow':
					$image_size = 'large';
					$alignment  = 'center';
					break;
				case 'left':
					$alignment = 'left';
					break;
				case 'right':
					$alignment = 'right';
					break;
				case 'giant':
					$alignment = 'full';
					break;
				case 'full':
					$image_size = 'large';
					$alignment  = 'wide';
					break;
			}
		}

		return serialize_block( $this->block_generator->get_image( get_post( $maybe_attachment_id ), $image_size, true, null, $alignment ) );
	}

	/**
	 * Handles mosaic components and returns HTML.
	 *
	 * @param array $component Component to process.
	 * @param int   $post_id Post ID.
	 *
	 * @return string
	 */
	private function handle_mosaic_component( array $component, int $post_id ): string {
		$attachment_ids = [];
		foreach ( $component['items'] as $item ) {
			$maybe_attachment_id = $this->handle_image_import_and_import_metadata( $item, $post_id );

			if ( is_wp_error( $maybe_attachment_id ) ) {
				return '';
			}

			$attachment_ids[] = $maybe_attachment_id;
		}

		return serialize_block( $this->block_generator->get_jetpack_tiled_gallery( $attachment_ids, 'attachment' ) );
	}

	/**
	 * Handles data graphic components and returns HTML.
	 *
	 * @param array      $component Component to process.
	 * @param array|null $next_sibling Next sibling component.
	 * @param int        $post_id Post ID.
	 *
	 * @return string
	 */
	private function handle_data_graphic_component( array $component, ?array $next_sibling, int $post_id ): string {
		return $this->handle_iframe_component( $component );
	}

	/**
	 * Handles video components and returns HTML.
	 *
	 * @param array      $component Component to process.
	 * @param array|null $next_sibling Next sibling component.
	 * @param int        $post_id Post ID.
	 *
	 * @return string
	 */
	private function handle_video_component( array $component, ?array $next_sibling, int $post_id ): string {
		$caption_block = [];

		if ( isset( $component['caption'] ) && ! empty( $component['caption'] ) ) {
			$caption_block = $this->handle_caption_component(
				[
					'text' => $component['caption'],
				]
			);
		} elseif ( $next_sibling ) {
			if ( 'caption' === $next_sibling['role'] ) {
				$caption_block = $this->handle_caption_component( $next_sibling );
			}
		}

		switch ( $component['player_type'] ) {
			case 'youtube':
				$youtube_block = $this->block_generator->get_youtube(
					isset( $component['external_id'] ) ?
						$component['external_id'] :
						$component['url']
				);

				if ( ! empty( $caption_block ) ) {
					return serialize_block(
						$this->block_generator->get_group_constrained(
							[
								$youtube_block,
								$caption_block,
							]
						)
					);
				}

				return serialize_block( $youtube_block );
			case 'livestream':
				return $this->handle_iframe_component( $component );
			default:
				ConsoleColor::bright_magenta( 'Different video type needs attention!' )->output();

				$maybe_attachment_id = Attachments::import_external_file(
					$component['url'],
					null,
					null,
					null,
					null,
					$post_id
				);

				if ( is_wp_error( $maybe_attachment_id ) ) {
					ConsoleColor::red( 'Error creating attachment (' )
						->bright_red( $maybe_attachment_id->get_error_code() )
						->red( '):' )
						->underlined_bright_red( $maybe_attachment_id->get_error_message() )
						->output();

					return '';
				}

				$video_block = $this->block_generator->get_video( get_post( $maybe_attachment_id ) );

				if ( ! empty( $caption_block ) ) {
					return serialize_block(
						$this->block_generator->get_group_constrained(
							[
								$video_block,
								$caption_block,
							]
						)
					);
				}

				return serialize_block( $video_block );
		}
	}

	/**
	 * Handles iframe components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return string
	 */
	private function handle_iframe_component( array $component ): string {
		$width  = isset( $component['width'] ) ? $component['width'] : null;
		$height = isset( $component['height'] ) ? $component['height'] : null;

		$iframe_block = $this->block_generator->get_iframe( $component['url'], intval( $width ), intval( $height ) );

		// Only add the script if it hasn't been added yet.
		if ( ! $this->height_adjustment_script_added ) {
			$script_block = $this->block_generator->get_html(
				'<script>
    window.addEventListener("message", function(event) {
        // Ensure message contains height data
        if (event.data.height) {
            document.querySelectorAll("iframe").forEach(iframe => {
                if (iframe.contentWindow === event.source) {
                    // Remove height from the iframe\'s parent element.
                    if (iframe.parentElement) {
                        iframe.parentElement.style.height = "auto";
                    }

                    // Set the new height dynamically
                    iframe.style.height = event.data.height + "px";
                }
            });
        }
    });
</script>'
			);

			$this->height_adjustment_script_added = true;

			return serialize_block(
				$this->block_generator->get_group_constrained(
					[
						$script_block,
						$iframe_block,
					]
				)
			);
		}

		return serialize_block( $iframe_block );
	}

	/**
	 * Handles caption components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return array
	 */
	private function handle_caption_component( array $component ): array {
		$caption = isset( $component['text'] ) ? $component['text'] : ( isset( $component['caption'] ) ? $component['caption'] : '' );

		return $this->block_generator->get_paragraph(
			$caption,
			'',
			'',
			'',
			[
				'wp-caption-text',
			]
		);
	}

	/**
	 * Get post ID from legacy ID.
	 *
	 * @param string $legacy_id Legacy post ID.
	 * @return int|null Post ID or null if not found.
	 */
	private function get_post_id_from_legacy_id( string $legacy_id ): ?int {
		return $this->legacy_ids[ $legacy_id ] ?? null;
	}

	/**
	 * Handles audio components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return string
	 */
	private function handle_audio_component( array $component ): string {
		$audio_url        = $component['url'];
		$caption          = $component['caption'] ?? '';
		$file_description = $component['file_description'] ?? '';

		return serialize_block( $this->block_generator->get_audio( $audio_url, $caption, $file_description, true ) );
	}

	/**
	 * Handles document link components and returns HTML.
	 *
	 * @param array $component Component to process.
	 * @param int   $pdf_icon_attachment_id Attachment ID of the PDF icon.
	 *
	 * @return string
	 */
	private function handle_document_link_component( array $component, int $pdf_icon_attachment_id ): string {
		switch ( $component['file_type'] ) {
			case 'application/pdf':
				$attachment_id = Attachments::import_external_file( $component['file_url'] );

				if ( is_wp_error( $attachment_id ) ) {
					ConsoleColor::red( 'Error creating attachment (' )
						->bright_red( $attachment_id->get_error_code() )
						->red( '):' )
						->underlined_bright_red( $attachment_id->get_error_message() )
						->output();

					$paragraph_block = $this->block_generator->get_paragraph( $component['file_url'] );

					$paragraph_block['attrs'] = array_merge(
						$paragraph_block['attrs'],
						[
							'original-file-url'  => $component['file_url'],
							'original-file-type' => $component['file_type'],
							'original-caption'   => $component['caption'] ?? '',
						]
					);

					return serialize_block( $paragraph_block );
				}

				$caption_text = isset( $component['text'] ) ? $component['text'] : ( isset( $component['caption'] ) ? $component['caption'] : '' );

				return serialize_block(
					$this->block_generator->get_group_constrained(
						[
							$this->block_generator->get_heading( 'Reference', 'h4', '', 'small', 'bold' ),
							$this->block_generator->get_row(
								[
									$this->block_generator->get_image( get_post( $pdf_icon_attachment_id ), 'full', false, null, null, null, 24 ),
									$this->block_generator->get_paragraph( '<a href="' . $component['file_url'] . '">' . $caption_text . ' <span>Download</span></a>' ),
								],
								'horizontal',
								'left',
								'',
								'nowrap'
							),
						],
						[ 'newspack-document-download-container' ]
					)
				);
			default:
				ConsoleColor::bright_magenta( 'Different file type needs attention!' )->output();
				return '';
		}
	}

	/**
	 * Handles series list components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return string
	 */
	private function handle_series_list_component( array $component ): string {
		// TODO this needs to be updated after we've imported categories/tags.
		$series_data = $this->get_series_data( $component['series_id'] );

		// Get category.
		// Get or create parent "Series" category.
		$parent_term = $this->get_or_create_category( 'Series' );
		if ( ! $parent_term ) {
			return '';
		}

		// Get or create the series category.
		$series_term = $this->get_or_create_category(
			$series_data['name'],
			$series_data['slug'],
			$parent_term->term_id,
			$series_data['summary']
		);

		if ( ! $series_term ) {
			return '';
		}

		$url  = isset( $component['series_url'] ) ? get_site_url( null, $component['series_url'] ) : '';
		$text = isset( $component['series_name'] ) ? $component['series_name'] : $url;

		$inner_blocks = [
			$this->block_generator->get_heading(
				'<span style="text-transform:uppercase">Latest from the series</span>'
				. '<br>'
				. '<a href="' . $url . '" >' . $text . '</a>'
			),
			$this->block_generator->get_homepage_articles_for_category(
				[ $series_term->term_id ],
				[
					'postsToShow'   => 3,
					'mediaPosition' => 'left',
					'typeScale'     => 3,
					'imageScale'    => 2,
				]
			),
		];

		return serialize_block( $this->block_generator->get_group_constrained( $inner_blocks ), [ 'newspack-latest-from-the-series-container' ] );
	}

	/**
	 * Handles series snippet components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return string
	 */
	private function handle_series_snippet_component( array $component ): string {
		$more_in_series_link = '<a href="' . get_site_url( null, $component['series_url'] ) . '">More in this series</a>';
		$paragraph_block     = $this->block_generator->get_paragraph(
			strip_tags( $component['text'], [ 'a' ] ) . $more_in_series_link
		);

		// Get or create the series category.
		$series_data = $this->get_series_data( $component['series_id'] );
		if ( null !== $series_data ) {
			// Get or create parent "Series" category.
			$parent_term = $this->get_or_create_category( 'Series' );
			if ( $parent_term ) {
				// Get or create the series category.
				$series_term = $this->get_or_create_category(
					$series_data['name'],
					$series_data['slug'],
					$parent_term->term_id,
					$series_data['summary']
				);

				// Update the more_in_series_link to use the proper category URL.
				if ( $series_term ) {
					$more_in_series_link = '<a href="' . get_term_link( $series_term ) . '">More in this series</a>';
					$paragraph_block     = $this->block_generator->get_paragraph(
						strip_tags( $component['text'], [ 'a' ] ) . $more_in_series_link
					);
				}
			}
		}

		if ( ! isset( $component['show_logo'] ) || ! $component['show_logo'] ) {
			return serialize_block( $paragraph_block );
		}

		$logo       = Attachments::import_external_file( $component['logo_url'] );
		$logo_block = $this->block_generator->get_image( get_post( $logo ), 'full', false, null, 'center' );

		return serialize_block(
			$this->block_generator->get_group_constrained(
				[ $logo_block, $paragraph_block ]
			)
		);
	}

	/**
	 * Handles CTA membership components and updates post metadata.
	 *
	 * @param array $component Component to process.
	 * @param int   $post_id Post ID.
	 *
	 * @return string
	 */
	private function handle_cta_membership_component( array $component, int $post_id ): string {
		update_post_meta( $post_id, 'cta_membership_message', $component['message'] );
		update_post_meta( $post_id, 'cta_membership_campaign_id', $component['campaign_id'] );

		return '';
	}

	/**
	 * Handles FAQ container components and returns HTML.
	 *
	 * @param array $component Component to process.
	 * @param int   $post_id Post ID.
	 *
	 * @return string
	 */
	private function handle_faq_container_component( array $component, int $post_id ): string {
		$content = serialize_block(
			$this->block_generator->get_heading(
				$component['title'],
				'h3',
				$component['cta_url']
			)
		);

		// Handle the FAQ entries.
		foreach ( $component['components'] as $sub_component ) {
			if ( 'faq entry' === $sub_component['role'] ) {
				$content .= $this->handle_faq_entry_component( $sub_component );
			}
		}

		// Handle the sponsor.
		if ( isset( $component['sponsor_name'] ) && ! empty( $component['sponsor_name'] ) ) {
			$heading = $this->block_generator->get_heading( 'Sponsored by', 'h3', '', 'small', 'regular', 'center' );

			$sponsor_content = null;
			if ( isset( $component['sponsor_logo'] ) && ! empty( $component['sponsor_logo'] ) ) {
				// Import the sponsor logo.
				$maybe_logo_object = $this->handle_image_import( $component['sponsor_logo'], $post_id );

				if ( ! is_wp_error( $maybe_logo_object ) ) {
					// Create image block with link.
					$sponsor_content = $this->block_generator->get_image(
						get_post( $maybe_logo_object->attachment_id ),
						'medium',
						false,
						null,
						'none',
						esc_url( $component['sponsor_url'] )
					);
				} else {
					// Fallback to text if logo import fails.
					$sponsor_content = $this->block_generator->get_paragraph(
						'<a href="' . esc_url( $component['sponsor_url'] ) . '">' . esc_html( $component['sponsor_name'] ) . '</a>'
					);
				}
			} else {
				// No logo, use text link.
				$sponsor_content = $this->block_generator->get_paragraph(
					'<a href="' . esc_url( $component['sponsor_url'] ) . '">' . esc_html( $component['sponsor_name'] ) . '</a>'
				);
			}

			$content .= serialize_block(
				$this->block_generator->get_row(
					[ $heading, $sponsor_content ],
					'horizontal',
					'center'
				)
			);
		}

		return $content;
	}

	/**
	 * Handles FAQ entry components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return string
	 */
	private function handle_faq_entry_component( array $component ): string {
		return serialize_block(
			$this->block_generator->get_details(
				$component['question'],
				[
					$this->block_generator->get_quote(
						$component['answer']
					),
				]
			)
		);
	}

	/**
	 * Handles newsletter signup components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return string
	 */
	private function handle_newsletter_signup_component( array $component ): string {
		$paragraph_block = $this->block_generator->get_paragraph( 'NEWSLETTER SIGNUP HERE' );

		$paragraph_block['attrs'] = array_merge(
			$paragraph_block['attrs'],
			[
				'newsletter-id' => $component['newsletter'] ?? '',
				'slug'          => $component['slug'] ?? '',
				'name'          => $component['name'] ?? '',
				'description'   => $component['description'] ?? '',
			]
		);

		return serialize_block( $paragraph_block );
	}

	/**
	 * Handles pulitzer components and returns HTML.
	 *
	 * @param array $component Component to process.
	 *
	 * @return string
	 */
	private function handle_pulitzer_component( array $component, int $post_id ): string {
		$text_block = $this->block_generator->get_paragraph( $component['text'] );

		$image_block = '';

		$maybe_image_object = $this->handle_image_import( $component['logo'], $post_id );

		if ( is_wp_error( $maybe_image_object ) ) {
			ConsoleColor::red( 'Error getting pulitzer logo (' )
				->bright_red( $maybe_image_object->get_error_code() )
				->red( '):' )
				->underlined_bright_red( $maybe_image_object->get_error_message() )
				->output();
		} else {
			$image_url   = wp_get_attachment_image_url( $maybe_image_object->attachment_id );
			$image_block = $this->block_generator->get_image( get_post( $maybe_image_object->attachment_id ), 'full', false, null, 'center', $image_url );
		}

		$group_block = $this->block_generator->get_group_constrained(
			[ $image_block, $text_block ],
			[
				'alignright',
				'has-light-gray-background-color',
				'has-background',
			],
			[
				'align'           => 'right',
				'backgroundColor' => 'light-gray',
			]
		);

		$group_block['attrs']['layout'] = [
			'type'           => 'flex',
			'orientation'    => 'vertical',
			'justifyContent' => 'center',
			'flexWrap'       => 'wrap',
		];

		return serialize_block( $group_block );
	}

	/**
	 * Handles importing an image and its metadata.
	 *
	 * @param array $component Component to process.
	 * @param int   $post_id Post ID.
	 *
	 * @return int|WP_Error
	 */
	private function handle_image_import_and_import_metadata( array $component, int $post_id ): int|WP_Error {
		$description = '';
		if ( isset( $component['file_description'] ) && $component['file_description'] ) {
			$description = $component['file_description'];
		} elseif ( isset( $component['photo_description'] ) && $component['photo_description'] ) {
			$description = $component['photo_description'];
		}

		$maybe_attachment_object = $this->handle_image_import(
			$component['url'],
			$post_id,
			null,
			$component['caption'] ?? null,
			$description
		);

		if ( is_wp_error( $maybe_attachment_object ) ) {
			return $maybe_attachment_object;
		}

		$attachment_post = array(
			'ID'           => $maybe_attachment_object->attachment_id,
			'post_excerpt' => $component['caption'] ?? '',
			'post_content' => $component['file_description'] ?? $component['photo_description'] ?? '',
			'post_parent'  => $post_id,
		);

		$updated = wp_update_post( $attachment_post );

		if ( is_wp_error( $updated ) ) {
			ConsoleColor::bright_yellow( 'Error saving some data sources for photos.' )
				->underlined_white( $updated->get_error_code() )
				->white( $updated->get_error_message() )
				->output();
		}

		return $maybe_attachment_object->attachment_id;
	}

	/**
	 * This function handles the low-level task of URL sanitation, image download, and attachment data creation.
	 *
	 * @param string      $image_url The url of the image to be downloaded.
	 * @param int|null    $post_id The post ID.
	 * @param string|null $title The title of the image.
	 * @param string|null $caption The caption for the image.
	 * @param string|null $description The description of the image.
	 * @param string|null $alt Alt text to display for the image.
	 *
	 * @return TexasTribuneAttachmentMetadataObject|WP_Error
	 */
	private function handle_image_import( string $image_url, ?int $post_id = null, ?string $title = null, ?string $caption = null, ?string $description = null, ?string $alt = null ): TexasTribuneAttachmentMetadataObject|WP_Error {
		$image_object = new TexasTribuneAttachmentMetadataObject( $image_url );

		$maybe_attachment_id = Attachments::import_external_file( $image_object->download_url, $title, $caption, $description, $alt, $post_id );

		if ( is_wp_error( $maybe_attachment_id ) ) {
			ConsoleColor::bright_magenta( "Error Importing Image $image_url." )
				->magenta( '(' )
				->white( $maybe_attachment_id->get_error_code() )
				->magenta( ')' )
				->underlined_magenta( $maybe_attachment_id->get_error_message() )
				->output();

			return $maybe_attachment_id;
		}

		$image_object->attachment_id = $maybe_attachment_id;

		return $image_object;
	}

	/**
	 * Fetch all legacy IDs from the database.
	 *
	 * @return void
	 */
	private function fetch_all_legacy_ids(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'_newspack_legacy_id'
			),
			ARRAY_A
		);

		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				$this->legacy_ids[ $result['meta_value'] ] = (int) $result['post_id'];
			}
		}

		ConsoleColor::green( 'Found' )
			->bright_green( count( $this->legacy_ids ) )
			->green( 'previously imported articles.' )
			->output();
	}

	/**
	 * Handle articlelink posts.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $post_title The post title.
	 * @param string $url_override The URL override.
	 */
	private function handle_articlelink( int $post_id, string $post_title, string $url_override ): void {
		// TODO: Check if both URLs are the same.
		$post_url_relative = $this->get_post_url_relative( $post_id );
		$this->redirection->create_redirection_rule_in_group(
			$post_title,
			$post_url_relative,
			$url_override,
			'articlelink'
		);

		ConsoleColor::green( 'Created redirection group for articlelink post' )
			->bright_green( $post_id . ':' )
			->green( "$post_url_relative => " )
			->green( $url_override )
			->output();
	}

	/**
	 * Get the relative URL of a post.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return string
	 */
	private function get_post_url_relative( int $post_id ): string {
		return str_replace( home_url(), '', get_permalink( $post_id ) );
	}
}
