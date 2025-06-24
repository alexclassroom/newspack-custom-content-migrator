<?php
/**
 * Importer for New Haven Independent .
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\Guest_Contributor_Role;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Taxonomy;
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\UsersHelper;
use Newspack\MigrationTools\Logic\CoAuthorsPlusHelper;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
use Bramus\Monolog\Formatter\ColorSchemes\DefaultScheme;
use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Level;
use Simple_Local_Avatars;
use WP_CLI;
use WP_Error;
use wpdb;

class NewHavenIndependentMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * Site ID for New Haven Independent.
	 */
	public const SITE_ID_NEW_HAVEN_INDEPENDENT = 1;

	/**
	 * Connecticut timezone for NHI (Eastern Time, handles DST automatically).
	 */
	public const NHI_TIMEZONE = 'America/New_York';
	
	/**
	 * CDN assets hostname.
	 */
	public const CDN_ASSET_HOSTNAME = 'd2f1dfnoetc03v.cloudfront.net';
	
	/**
	 * S3 hostname.
	 */
	public const S3_HOSTNAME = 'ojp-content.s3.us-east-2.amazonaws.com';

	/**
	 * All possible entry types in the prod DB ("fieldPreparsedEntryType").
	 */
	public const CRAFT_ENTRY_TYPES = [
		'typeLegalNotices',
		'typeSingleLink',
		'typeRegularArticle',
		'typeArchivalArticle',
		'typeFeaturedArticle',
	];

	/**
	 * Entry statuses to post statuses.
	 *
	 * @var array
	 */
	public const CRAFT_ENTRY_STATUSES_TO_WP_POST_STATUSES = [
		'disabled' => 'draft',
		'expired'  => 'draft',
		'live'     => 'publish',
	];

	/**
	 * Comment status values in the prod db.
	 */
	public const CRAFT_COMMENT_STATUSES = [
		'approved',
		'trashed',
		'spam',
	];

	/**
	 * If defined and not empty/null, the section categories will be created under this parent category, otherwise sections will be created as top-level categories.
	 * - field "sectionId"
	 *      - primary site structure (e.g., "Main News," "Obituaries," "Legal Notices")
	 *      - only one sectionId per entry
	 * 
	 * If defined and not empty/null, the section categories will be created under this parent category, otherwise sections will be created as top-level categories.
	 *  - "fieldSections"
	 */
	public const SECTION_PARENT_CATEGORY_NAME = 'Sections';
	
	/**
	 * If defined and not empty/null, the neighborhoods categories will be created under this parent category, otherwise neighborhoods will be created as top-level categories.
	 */
	public const NEIGHBORHOODS_PARENT_CATEGORY_NAME = 'Neighborhoods';

	/**
	 * If defined and not empty/null, the features categories will be created under this parent category, otherwise features will be created as top-level categories.
	 */
	public const FEATURES_PARENT_CATEGORY_NAME = 'Features';

	/**
	 * Will also add a category for entry type, to keep things more visible in migration.
	 * If this is defined, will add entry type category as this parent's category.
	 */
	public const CRAFT_ENTRY_TYPE_CATEGORY_NAME = 'Entry Type';

	/**
	 * Field mappings for different content types.
	 */
	public const FIELD_MAPPINGS = [
		'main_content' => [
			'text_content'            => 'field_blockText_itemContent',
			'image_content'           => 'field_blockImage_itemContent',
			'image_position'          => 'field_blockImage_itemPosition',
			'image_width'             => 'field_blockImage_itemWidth',
			'external_image_heading'  => 'field_blockExternalImage_itemHeading',
			'external_image_content'  => 'field_blockExternalImage_itemContent',
			'external_image_position' => 'field_blockExternalImage_itemPosition',
			'external_image_width'    => 'field_blockExternalImage_itemWidth',
			'external_image_url'      => 'field_blockExternalImage_itemURL',
			'raw_html_content'        => 'field_blockRawHTML_itemContent',
			'video_embed'             => 'field_blockVideo_itemVideoEmbed',
			'video_width'             => 'field_blockVideo_itemWidth',
			'video_position'          => 'field_blockVideo_itemPosition',
			'video_content'           => 'field_blockVideo_itemContent',
			'heading_type'            => 'field_blockHeading_itemType',
			'heading_content'         => 'field_blockHeading_itemHeading',
			'poll_position'           => 'field_blockPoll_itemPosition',
			'separator_visible'       => 'field_blockSeparator_itemIsVisible',
			'quote_content'           => 'field_blockQuote_itemContent',
			'quote_heading'           => 'field_blockQuote_itemHeading',
			'graphic_position'        => 'field_blockGraphic_itemPosition',
			'graphic_width'           => 'field_blockGraphic_itemWidth',
			'graphic_custom_width'    => 'field_blockGraphic_itemCustomWidth',
		],
		'lede'         => [
			'text_content'            => 'field_blockText_itemContent',
			'image_content'           => 'field_blockImage_itemContent',
			'image_position'          => 'field_blockImage_itemPosition',
			'image_width'             => 'field_blockImage_itemWidth',
			'external_image_heading'  => 'field_blockExternalImage_itemHeading',
			'external_image_content'  => 'field_blockExternalImage_itemContent',
			'external_image_position' => 'field_blockExternalImage_itemPosition',
			'external_image_width'    => 'field_blockExternalImage_itemWidth',
			'external_image_url'      => 'field_blockExternalImage_itemURL',
			'raw_html_content'        => 'field_blockRawHTML_itemContent',
			'video_embed'             => 'field_blockVideo_itemVideoEmbed',
			'video_width'             => 'field_blockVideo_itemWidth',
			'video_position'          => 'field_blockVideo_itemPosition',
			'video_content'           => 'field_blockVideo_itemContent',
			'heading_type'            => 'field_blockHeading_itemType_epxdfidq',
			'heading_content'         => 'field_blockHeading_itemHeading_rabumset',
			'poll_position'           => 'field_blockPoll_itemPosition',
			'separator_visible'       => 'field_blockSeparator_itemIsVisible',
			'quote_content'           => 'field_blockQuote_itemContent',
			'quote_heading'           => 'field_blockQuote_itemHeading',
			'graphic_position'        => 'field_blockGraphic_itemPosition',
			'graphic_width'           => 'field_blockGraphic_itemWidth',
			'graphic_custom_width'    => 'field_blockGraphic_itemCustomWidth',
		],
	];

	/**
	 * Dry run flag.
	 *
	 * @var bool $dry_run The dry run flag.
	 */
	private $dry_run = false;

	/**
	 * Logger
	 *
	 * @var MultiLog
	 */
	private $logger;

	/**
	 * Taxonomy logic.
	 *
	 * @var Taxonomy $taxonomy_logic The taxonomy logic.
	 */
	private $taxonomy;

	/**
	 * Attachments logic.
	 *
	 * @var Attachments $attachments The attachments logic.
	 */
	private $attachments;

	/**
	 * Users helper.
	 *
	 * @var UsersHelper $users The users helper.
	 */
	private $users;
	
	/**
	 * CoAuthorsPlusHelper.
	 *
	 * @var CoAuthorsPlusHelper $coauthors The coauthors helper.
	 */
	private $coauthors;

	/**
	 * Gutenberg blocks helper.
	 *
	 * @var GutenbergBlockGenerator $gutenberg_blocks The gutenberg blocks helper.
	 */
	private $gutenberg_blocks;
	
	/**
	 * Simple_Local_Avatars.
	 *
	 * @var Simple_Local_Avatars $simple_local_avatars The simple local avatars helper.
	 */
	private $simple_local_avatars;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->taxonomy             = new Taxonomy();
		$this->attachments          = new Attachments();
		$this->users                = new UsersHelper();
		$this->coauthors            = new CoAuthorsPlusHelper();
		$this->simple_local_avatars = new Simple_Local_Avatars();
		$this->gutenberg_blocks     = new GutenbergBlockGenerator();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator newhavenindependent test',
			self::get_command_closure( 'cmd_test' ),
			[
				'synopsis' => [
					[
						'type'     => 'flag',
						'name'     => 'dry-run',
						'optional' => true,
					],
					[
						'type'     => 'assoc',
						'name'     => 'json-expanded-entries-folder',
						'optional' => false,
					],
					[
						'type'     => 'assoc',
						'name'     => 'json-expanded-users',
						'optional' => false,
					],
					[
						'type'     => 'assoc',
						'name'     => 'json-expanded-categories-news-sections',
						'optional' => false,
					],
					[
						'description' => 'If not all craft-db-* params are provided, will use the global $wpdb to locate tables in the local schema.',
						'type'        => 'assoc',
						'name'        => 'craft-db-name',
						'optional'    => true,
					],
					[
						'description' => 'If not all craft-db-* params are provided, will use the global $wpdb to locate tables in the local schema.',
						'type'        => 'assoc',
						'name'        => 'craft-db-user',
						'optional'    => true,
					],
					[
						'description' => 'If not all craft-db-* params are provided, will use the global $wpdb to locate tables in the local schema.',
						'type'        => 'assoc',
						'name'        => 'craft-db-pass',
						'optional'    => true,
					],
					[
						'description' => 'If not all craft-db-* params are provided, will use the global $wpdb to locate tables in the local schema.',
						'type'        => 'assoc',
						'name'        => 'craft-db-host',
						'optional'    => true,
					],
					[
						'description' => 'If not all craft-db-* params are provided, will use the global $wpdb to locate tables in the local schema.',
						'type'        => 'assoc',
						'name'        => 'craft-db-port',
						'optional'    => true,
					],

				],
			]
		);
		WP_CLI::add_command(
			'newspack-content-migrator newhavenindependent import',
			self::get_command_closure( 'cmd_import' ),
			[
				'synopsis' => [
					[
						'type'     => 'flag',
						'name'     => 'dry-run',
						'optional' => true,
					],
					[
						'type'     => 'assoc',
						'name'     => 'json-expanded-entries-folder',
						'optional' => false,
					],
					[
						'type'     => 'assoc',
						'name'     => 'json-expanded-users',
						'optional' => false,
					],
					[
						'type'     => 'assoc',
						'name'     => 'json-expanded-categories-news-sections',
						'optional' => false,
					],
					[
						'description' => 'If not all craft-db-* params are provided, will use the global $wpdb to locate tables in the local schema.',
						'type'        => 'assoc',
						'name'        => 'craft-db-name',
						'optional'    => true,
					],
					[
						'description' => 'If not all craft-db-* params are provided, will use the global $wpdb to locate tables in the local schema.',
						'type'        => 'assoc',
						'name'        => 'craft-db-user',
						'optional'    => true,
					],
					[
						'description' => 'If not all craft-db-* params are provided, will use the global $wpdb to locate tables in the local schema.',
						'type'        => 'assoc',
						'name'        => 'craft-db-pass',
						'optional'    => true,
					],
					[
						'description' => 'If not all craft-db-* params are provided, will use the global $wpdb to locate tables in the local schema.',
						'type'        => 'assoc',
						'name'        => 'craft-db-host',
						'optional'    => true,
					],
					[
						'description' => 'If not all craft-db-* params are provided, will use the global $wpdb to locate tables in the local schema.',
						'type'        => 'assoc',
						'name'        => 'craft-db-port',
						'optional'    => true,
					],

				],
			]
		);
	}

	/**
	 * Get the Craft database connection.
	 * If not all params are provided, returns the global $wpdb.
	 * This lets you connect to Craft tables in a separate schema, or in the local schema.
	 *
	 * @param ?string $db_name The database name.
	 * @param ?string $db_user The database user.
	 * @param ?string $db_pass The database password.
	 * @param ?string $db_host The database host.
	 * @param ?string $db_port The database port.
	 * @return \wpdb|null The database connection or null if the connection fails.
	 */
	private function get_craft_db_connection( ?string $db_name, ?string $db_user, ?string $db_pass, ?string $db_host, ?string $db_port ) { 
		global $wpdb;

		// If not all params are provided, return the global $wpdb.
		if ( is_null( $db_name ) || is_null( $db_user ) || is_null( $db_pass ) || is_null( $db_host ) || is_null( $db_port ) ) {
			return $wpdb;
		}

		// Get a custom database connection.
		$new_db = new \wpdb( $db_user, $db_pass, $db_name, $db_host, $db_port );
		if ( ! empty( $new_db->last_error ) ) {
			return null;
		}

		return $new_db;
	}

	/**
	 * Logger setup.
	 *
	 * @param string $caller Calling __FUNCTION__ name.
	 * @return void
	 */
	private function setup_logger( $caller ) {
		$log_slug = str_replace( __NAMESPACE__ . '\\', '', __CLASS__ ) . '_' . $caller;

		// No colors, custom format.
		$no_colors_scheme = new DefaultScheme();
		$no_colors_scheme->setColorizeArray( array_fill_keys( Level::VALUES, '' ) );
		$no_colors_formatter = new ColoredLineFormatter( $no_colors_scheme, '%level_name% %message% %context%' );

		$this->logger = MultiLog::get_logger(
			$log_slug . '-multi',
			[
				CliLog::get_logger( $log_slug, $no_colors_formatter ),
				FileLog::get_logger( $log_slug, $log_slug . '.log', $no_colors_formatter ),
			] 
		);
	}

	/**
	 * Import command.
	 * 
	 * This is the main command that imports the data.
	 * 
	 * @param array $pos_args The positional arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function cmd_import( array $pos_args, array $assoc_args ): void {
		$this->dry_run                      = isset( $assoc_args['dry-run'] ) ? true : false;
		$craft_db_name                      = $assoc_args['craft-db-name'] ?? null;
		$craft_db_user                      = $assoc_args['craft-db-user'] ?? null;
		$craft_db_pass                      = $assoc_args['craft-db-pass'] ?? null;
		$craft_db_host                      = $assoc_args['craft-db-host'] ?? null;
		$craft_db_port                      = $assoc_args['craft-db-port'] ?? null;
		$entries_jsons_folder               = $assoc_args['json-expanded-entries-folder'];
		$users_json_file                    = $assoc_args['json-expanded-users'];
		$categories_news_expanded_json_file = $assoc_args['json-expanded-categories-news-sections'];
		
		// Set logger.
		$this->setup_logger( __FUNCTION__ );

		// If not all connection params are provided, this will return the local WP DB connection and try and work with Craft tables in the WP schema.
		$craft_db = $this->get_craft_db_connection( $craft_db_name, $craft_db_user, $craft_db_pass, $craft_db_host, $craft_db_port );

		// Get Craft users data.
		$users_data = json_decode( file_get_contents( $users_json_file ), true ); // phpcs:ignore -- WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown.
		if ( ! is_array( $users_data ) ) {
			$this->logger->error( sprintf( 'ERROR reading JSON file %s : %s is not an array', $users_json_file, $users_data ) );
		}
		// Get Craft sections/categories data.
		$sections_data = json_decode( file_get_contents( $categories_news_expanded_json_file ), true ); // phpcs:ignore -- WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown.
		if ( ! is_array( $sections_data ) ) {
			$this->logger->error( sprintf( 'ERROR reading JSON file %s : %s is not an array', $categories_news_expanded_json_file, $sections_data ) );
		}

		// Get entry JSON files.
		$entries_json_files = $this->get_json_entries_files_descending( $entries_jsons_folder );
		foreach ( $entries_json_files as $key_entries_json_file => $entries_json_file ) {
			// Progress.
			$this->logger->info( sprintf( '===== (%d)/(%d) JSON File %s', $key_entries_json_file + 1, count( $entries_json_files ), $entries_json_file ) );
			
			// Get entries.
			$entries = $this->get_entries_from_json_file_descending( $entries_json_file );
			foreach ( $entries as $key_entry => $entry ) {
				// Progress.                
				$this->logger->info( sprintf( '(%d)/(%d) ; (%d)/(%d) Entry ID %s', $key_entries_json_file + 1, count( $entries_json_files ), $key_entry + 1, count( $entries ), $entry['id'] ) );
				
				// Create post.
				$post_data = $this->get_basic_post_data( $entry, $sections_data, $craft_db, $entries_json_file );
				$post_id   = ! $this->dry_run ? wp_insert_post( $post_data ) : -1;
				if ( is_wp_error( $post_id ) || 0 === $post_id ) {
					$this->logger->error( sprintf( "ERROR inserting post '%s' : '%s'", $post_data['post_title'], is_wp_error( $post_id ) ? $post_id->get_error_message() : 'Post ID is 0' ) );
					continue;
				}
				$this->logger->info( sprintf( "Inserted post '%s' with ID '%s', entry ID %d", $post_data['post_title'], $post_id, $entry['id'] ) );

				// Set remaining post data: content, excerpt, modified date.
				$this->set_remaining_post_data( $post_id, $entry, $craft_db );

				// Set post coauthors.
				$this->set_post_coauthors( $post_id, $entry, $users_data, $craft_db );
				
				// Insert post comments.
				$this->set_post_comments( $post_id, $entry, $users_data, $craft_db );

				// Set post featured image.
				$this->set_post_featured_image( $post_id, $entry, $craft_db );

				// Save custom post metas.
				$postmetas = [
					'newspack_migration_legacy_id'     => $entry['id'],
					'newspack_migration_legacy_uid'    => $entry['uid'],
					'newspack_migration_legacy_url'    => $entry['url'],
					'newspack_migration_entry_type'    => $entry['fieldPreparsedEntryType'] ?? null,
					'newspack_migration_entry_status'  => $entry['status'],
					'newspack_migration_legacy_byline' => $this->get_entry_bylines( $entry, $users_data, $craft_db ),
				];
				if ( ! $this->dry_run ) {
					foreach ( $postmetas as $key => $value ) {
						update_post_meta( $post_id, $key, $value );
					}
				}
			}       
		}
	}

	/**
	 * Get post data.
	 * 
	 * @param array  $entry         The entry data.
	 * @param array  $sections_data The sections data.
	 * @param \wpdb  $craft_db       The production database connection.
	 * @param string $entries_json_file The JSON file containing the entry.
	 * @return array The post data.
	 */
	public function get_basic_post_data( array $entry, array $sections_data, wpdb $craft_db, string $entries_json_file ): array {
		$post_data = [];

		/**
		 * Basic post data.
		 */
		$post_data['post_type']  = 'post';
		$post_data['post_title'] = $entry['title'];
		$date_created            = new \DateTime( $entry['postDate'] );
		$post_data['post_date']  = $date_created->format( 'Y-m-d H:i:s' );
		// Slug.
		$url_path = wp_parse_url( $entry['url'], PHP_URL_PATH );
		if ( null !== $url_path && false !== $url_path ) {
			$post_data['post_name'] = basename( $url_path );
		}
		// Status.
		if ( isset( self::CRAFT_ENTRY_STATUSES_TO_WP_POST_STATUSES[ $entry['status'] ] ) ) {
			$post_data['post_status'] = self::CRAFT_ENTRY_STATUSES_TO_WP_POST_STATUSES[ $entry['status'] ];
		} else {
			$this->logger->error( sprintf( "ERROR getting post_status for entry ID %d, title '%s', status '%s'. Setting 'draft'.", $entry['id'], $post_data['title'], $entry['status'] ) );
			$post_data['post_status'] = 'draft';
		}
		// Comment status (it's a boolean in Craft CMS).
		$post_data['comment_status'] = isset( $entry['fieldComment']['commentEnabled'] ) && true === $entry['fieldComment']['commentEnabled'] ? 'open' : 'closed';


		/**
		 * Categories. Setting multiple types of categories for post.
		 */
		// Set "sectionId" -- only one per entry, category for primary site structure (e.g., "Main News," "Obituaries," "Legal Notices").
		if ( isset( $entry['sectionId'] ) && ! empty( $entry['sectionId'] ) ) {
			$section_name = $this->get_section_name_by_id( $entry['sectionId'], $craft_db );
			if ( is_null( $section_name ) ) {
				$this->logger->error( sprintf( "ERROR section not found, sectionId '%s' in entry ID %d. Skipping.", $entry['sectionId'], $entry['id'] ) );
			} else {
				// Get section category and parent category (if defined in constant).
				$section_parent_category_id = 0;
				if ( ! empty( self::SECTION_PARENT_CATEGORY_NAME ) ) {
					if ( ! $this->dry_run ) {
						$section_parent_category_id = $this->taxonomy->get_or_create_category_by_name_and_parent_id( self::SECTION_PARENT_CATEGORY_NAME, 0 );
					}
				}
				if ( ! $this->dry_run ) {
					$section_category_id = $this->taxonomy->get_or_create_category_by_name_and_parent_id( $section_name, $section_parent_category_id );
				}

				// Add section category to post.
				if ( ! $this->dry_run ) {
					if ( ! is_null( $section_category_id ) ) {
						$post_data['post_category'][] = $section_category_id;
					} else {
						$this->logger->error( sprintf( "ERROR creating section category, sectionId '%s' in entry ID %d, parent category ID '%s'.", $entry['sectionId'], $entry['id'], $section_parent_category_id ) );
					}
				}
			}
		}
		// Set "fieldSections" -- topical hierarchical categories (e.g., "Religion," "Arts & Culture," "Politics").
		if ( isset( $entry['fieldSections'] ) && ! empty( $entry['fieldSections'] ) ) {
			foreach ( $entry['fieldSections'] as $field_section_id ) { // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
				try {
					if ( ! $this->dry_run ) {
						$field_section_category_id = $this->get_category_from_fieldSection( $field_section_id, $sections_data );
						if ( is_null( $field_section_category_id ) ) {
							$this->logger->error( sprintf( "ERROR creating section category, sectionId '%s' in entry ID %d, parent category ID '%s'.", $entry['sectionId'], $entry['id'], $section_parent_category_id ) );
						} else {
							// Add fieldSection category to post.
							$post_data['post_category'][] = $field_section_category_id;
						}
					}
				} catch ( \Exception $e ) {
					$this->logger->error( sprintf( "ERROR getting category from fieldSection '%s' in entry ID %d, JSON filename %s. Skipping.", $field_section_id, $entry['id'], $entries_json_file ) );
					continue;
				}
			}
		}
		// Set neighborhoods.
		if ( ! $this->dry_run ) {
			$neighborhood_cats = $this->get_neighborhoods_categories( $entry['id'], $craft_db );
			if ( ! empty( $neighborhood_cats ) ) {
				foreach ( $neighborhood_cats as $neighborhood ) {
					$post_data['post_category'][] = $neighborhood;
				}
			}
		}
		// Set features.
		if ( ! $this->dry_run ) {
			$features = $this->get_features_categories( $entry['id'], $craft_db );
			if ( ! empty( $features ) ) {
				foreach ( $features as $feature ) {
					$post_data['post_category'][] = $feature;
				}
			}
		}

		// Also set and EntryType subcategory.
		if ( isset( $entry['fieldPreparsedEntryType'] ) && ! empty( $entry['fieldPreparsedEntryType'] ) ) {
			// Get entry type category and parent category (if defined in constant).
			$entry_type_parent_category_id = 0;
			if ( ! empty( self::CRAFT_ENTRY_TYPE_CATEGORY_NAME ) ) {
				if ( ! $this->dry_run ) {
					$entry_type_parent_category_id = $this->taxonomy->get_or_create_category_by_name_and_parent_id( self::CRAFT_ENTRY_TYPE_CATEGORY_NAME, 0 );
				}
			}
			if ( ! $this->dry_run ) {
				$entry_type_category_id = $this->taxonomy->get_or_create_category_by_name_and_parent_id( $entry['fieldPreparsedEntryType'], $entry_type_parent_category_id );
				if ( ! is_null( $entry_type_category_id ) ) {
					// Add entry type category to post.
					$post_data['post_category'][] = $entry_type_category_id;
				} else {
					$this->logger->error( sprintf( "ERROR creating entry type category, entry type '%s' in entry ID %d, parent category ID '%s'.", $entry['fieldPreparsedEntryType'], $entry['id'], $entry_type_parent_category_id ) );
				}
			}
		}
	   

		/**
		 * Tags.
		 */
		if ( isset( $entry['fieldTags'] ) && ! empty( $entry['fieldTags'] ) ) {
			foreach ( $entry['fieldTags'] as $field_tag_id ) { // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
				$tag_name = $this->get_tag_by_id( $field_tag_id, $craft_db ); // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
				if ( ! is_null( $tag_name ) ) {
					$post_data['tags_input'][] = $tag_name;
				} else {
					$this->logger->error( sprintf( "ERROR tag not found, fieldTag '%s' in entry ID %d, JSON filename %s. Skipping.", $field_tag_id, $entry['id'], $entries_json_file ) );
				}
			}
		}

		return $post_data;
	}

	/**
	 * Get Gutenberg post content blocks.
	 * 
	 * @param int   $entry_id     The entry ID.
	 * @param array $craft_blocks The Craft content blocks.
	 * @param int   $post_id      The post ID.
	 * @param \wpdb $craft_db      The production database connection.
	 * @return array The Gutenberg post content blocks.
	 */
	public function convert_craft_content_blocks_to_gutenberg_blocks( int $entry_id, array $craft_blocks, int $post_id, wpdb $craft_db ): array {
		
		// Both Craft and Gutenberg use "blocks".
		$gutenberg_blocks = [];

		foreach ( $craft_blocks as $craft_block_id => $craft_block ) {
			switch ( $craft_block['type'] ) {
				case 'blockHeading':
					$heading_level   = $craft_block['fields']['itemType'] ?? null;
					$heading_level   = $craft_block['fields']['itemType'] ?? null;
					$heading_content = $craft_block['fields']['itemHeading'] ?? null;
					if ( is_null( $heading_content ) || is_null( $heading_level ) ) {
						$this->logger->error( sprintf( "ERROR entry ID %d matrixMainContent blockHeading: level '%s', content '%s'.", $entry_id, $heading_level, $heading_content ) );
						break;
					}
					$heading_block      = $this->gutenberg_blocks->get_heading( $heading_content, $heading_level );
					$gutenberg_blocks[] = $heading_block;
					break;
				
				case 'blockText':
					$content = $craft_block['fields']['itemContent'] ?? null;
					if ( is_null( $content ) ) {
						$this->logger->error( sprintf( "ERROR entry ID %d matrixMainContent blockText: content '%s'.", $entry_id, $content ) );
						break;
					}
					// $this->gutenberg_blocks->get_paragraph will add a <p> tag, so let's remove it if it exists.
					$content            = $this->formatting_strip_outer_p_tag( $content );
					$text_block         = $this->gutenberg_blocks->get_paragraph( $content );
					$gutenberg_blocks[] = $text_block;
					break;

				case 'blockRawHTML':
					$html_content = $craft_block['fields']['itemContent'] ?? null;
					if ( is_null( $html_content ) ) {
						$this->logger->error( sprintf( "ERROR entry ID %d matrixMainContent blockRawHTML: content '%s'.", $entry_id, $html_content ) );
						break;
					}
					$html_block         = $this->gutenberg_blocks->get_html( $html_content );
					$gutenberg_blocks[] = $html_block;
					break;

				case 'blockVideo':
					$video_url = $craft_block['fields']['itemVideoEmbed']['url'] ?? null;
					if ( is_null( $video_url ) || empty( $video_url ) || empty( trim( $video_url ) ) ) {
						$this->logger->error( sprintf( 'ERROR entry ID %d matrixMainContent blockVideo: video URL is empty.', $entry_id ) );
						break;
					}
					if ( is_null( $video_url ) ) {
						$this->logger->error( sprintf( "ERROR entry ID %d matrixMainContent blockVideo: video URL '%s'.", $entry_id, $video_url ) );
						break;
					}

					// Get video hostname without the subdomains.
					$hostname = $this->get_hostname_from_url( $video_url );

					// Get Gutenberg block depending on hostname.
					$video_block = null;
					switch ( $hostname ) {
						case 'youtube.com':
						case 'youtu.be':
							$video_block = $this->gutenberg_blocks->get_youtube( $video_url );
							break;

						case 'vimeo.com':
							$video_block = $this->gutenberg_blocks->get_vimeo( $video_url );
							break;
							
						case 'facebook.com':
						case 'fb.watch':
							// Embedding some of these short URLs isn't working, final redirects are needed.
							$embed_url = $this->get_final_redirect_url( $video_url );
							if ( $this->dry_run ) {
								$this->logger->info( sprintf( "blockVideo facebook get_final_redirect_url entry ID %d: \n- from: '%s'\n-to: '%s'", $entry_id, $video_url, $embed_url ) );
							}
							$html_content_sprintf = sprintf(
								"\n%s\n%s\n",
								'<div id="fb-root"></div><script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v22.0"></script>',
								'<div class="fb-video" data-href="%s" data-width="500" data-show-text="false"></div>'
							);
							$html_content         = sprintf( $html_content_sprintf, $embed_url );
							$video_block          = $this->gutenberg_blocks->get_html( $html_content );
							break;
								
						case 'tiktok.com':
						case 'twitter.com':
							$video_block = $this->gutenberg_blocks->get_core_embed( $video_url );
							break;

						case 'instagram.com':
							$html_content_sprintf = sprintf(
								"\n%s\n%s\n",
								'<blockquote class="instagram-media" data-instgrm-permalink="%s" data-instgrm-version="14"></blockquote>',
								'<script async src="//www.instagram.com/embed.js"></script>'
							);
							$html_content         = sprintf( $html_content_sprintf, $video_url );
							$video_block          = $this->gutenberg_blocks->get_html( $html_content );
							break;

						case 'bandcamp.com':
						case 'cafenine.com':
						case 'christiansonlee.com':
						case 'google.com':
						case 'soundcloud.com':
						case 'spotify.com':
						case 'wpt.org':
							// These work with iframe block.                                
							$video_block = $this->gutenberg_blocks->get_iframe( $video_url );
							break;

						default:
							// Insert other unembeddable videos as links.
							$link        = sprintf( '<a href="%s" class="nhi-blockVideo-link" target="_blank">%s</a>', $video_url, $video_url );
							$video_block = $this->gutenberg_blocks->get_paragraph( $link );
							break;
					}
					if ( ! is_null( $video_block ) ) {
						$gutenberg_blocks[] = $video_block;
					}
					break;

				case 'blockImage':
					// Check if $craft_block_data['fields']['itemAsset'] contains more than one asset.
					if ( count( $craft_block['fields']['itemAsset'] ) > 1 ) {
						$this->logger->error( sprintf( 'ERROR -DEBUG- entry ID %d matrixMainContent blockImage: multiple assets found. Skipping.', $entry_id ) );
						break;
					}

					// Get asset data.
					$asset_id = $craft_block['fields']['itemAsset'][0] ?? null;
					if ( is_null( $asset_id ) ) {
						$this->logger->error( sprintf( 'ERROR entry ID %d matrixMainContent blockImage missing asset ID.', $entry_id ) );
						break;
					}
					// Caption is contained in JSON data.
					$caption = null;
					if ( isset( $craft_block['fields']['itemContent'] ) && ! empty( $craft_block['fields']['itemContent'] ) ) {
						$caption = $this->formatting_strip_outer_p_tag( $craft_block['fields']['itemContent'] ?? null );
					}

					// Get image classes.
					$image_classes = $this->get_craft_image_block_classes( $craft_block );

					// Import image.
					// Credit is contained in DB asset data.
					$image_id = ! $this->dry_run ? $this->import_image_from_asset( $asset_id, $post_id, $entry_id, $craft_db, $caption ) : -1;
					if ( is_wp_error( $image_id ) ) {
						$this->logger->error( sprintf( "ERROR downloading image for entry ID %d -- matrixMainContent blockImage itemAsset '%d' : '%s'.", $entry_id, $asset_id, $image_id->get_error_message() ) );
						break;
					}
					
					// Get block.
					if ( ! $this->dry_run ) {
						$image              = get_post( $image_id );
						$image_block        = $this->gutenberg_blocks->get_image( $image, 'full', true, $image_classes );
						$gutenberg_blocks[] = $image_block;
					}
					break;

				case 'blockExternalImage':
					$image_url = $craft_block['fields']['itemURL']['url'] ?? null;
					if ( is_null( $image_url ) ) {
						$this->logger->error( sprintf( "ERROR entry ID %d matrixMainContent blockExternalImage: image URL '%s'.", $entry_id, $image_url ) );
						break;
					}

					// Get image classes.
					$image_classes = $this->get_craft_image_block_classes( $craft_block );

					/**
					 * If image is hosted on own hostnames, download it and use regular image block.
					 */
					// Allow any subdomain of this host.
					$hostnames_no_subdomain = [
						'newhavenindependent.org',
					];
					// Only allow these specific subdomains (don't download the entire amazonaws.com :D ).
					$hostnames_with_subdomains   = [
						self::CDN_ASSET_HOSTNAME,
						self::S3_HOSTNAME,
					];
					$img_hostname_no_subdomain   = $this->get_hostname_from_url( $image_url );
					$img_hostname_with_subdomain = wp_parse_url( $image_url, PHP_URL_HOST );
					if ( in_array( $img_hostname_no_subdomain, $hostnames_no_subdomain ) || in_array( $img_hostname_with_subdomain, $hostnames_with_subdomains ) ) {

						// Caption and Credit are both contained in JSON data for external image block.
						$caption = null;
						if ( isset( $craft_block['fields']['itemContent'] ) && ! empty( $craft_block['fields']['itemContent'] ) ) {
							$caption = $this->formatting_strip_outer_p_tag( $craft_block['fields']['itemContent'] ?? null );
						}
						$credit = null;
						if ( isset( $craft_block['fields']['itemHeading'] ) && ! empty( $craft_block['fields']['itemHeading'] ) ) {
							$credit = $this->formatting_strip_outer_p_tag( $craft_block['fields']['itemHeading'] ?? null );
						}

						// Download and import image.
						$image_id = ! $this->dry_run ? $this->attachments->import_external_file(
							$image_url,
							null,
							$caption,
							null,
							null,
							$post_id
						) : -1;
						if ( is_wp_error( $image_id ) ) {
							$this->logger->error( sprintf( "ERROR downloading image for entry ID %d, post ID %d -- matrixMainContent blockExternalImage itemURL '%s' : '%s'.", $entry_id, $post_id, $image_url, $image_id->get_error_message() ) );
							break;
						}

						// Set postmetas, including credit.
						$image_metas = [
							'_media_credit'                => $credit,
							'newspack_migration_asset_url' => $image_url,
							'newspack_migration_asset_itemPosition' => $craft_block['fields']['itemPosition'] ?? null,
							'newspack_migration_asset_itemWidth' => $craft_block['fields']['itemWidth'] ?? null,
						];
						if ( ! $this->dry_run ) {
							foreach ( $image_metas as $key => $value ) {
								update_post_meta( $image_id, $key, $value );
							}
						}

						// Get image block.
						if ( ! $this->dry_run ) {
							$image              = get_post( $image_id );
							$image_block        = $this->gutenberg_blocks->get_image( $image, 'full', true, $image_classes );
							$gutenberg_blocks[] = $image_block;
						}
					} else {
						/**
						 * If image is hosted on some external hostname, use external image block.
						 */
						$image_caption      = $craft_block['fields']['itemContent'] ?? null;
						$image_block        = $this->gutenberg_blocks->get_external_image( $image_url, $image_caption, null, 'full', true, $image_classes );
						$gutenberg_blocks[] = $image_block;
					}
					break;

				case 'blockSeparator':
					$is_visible = $craft_block['fields']['itemIsVisible'] ?? null;
					// Only add separator if it's visible field is set.
					if ( is_null( $is_visible ) || false == $is_visible ) {
						break;
					}
					$separator_block    = $this->gutenberg_blocks->get_separator();
					$gutenberg_blocks[] = $separator_block;
					break;
					
				case 'blockQuote':
					$heading = $craft_block['fields']['itemHeading'] ?? null;
					$content = $craft_block['fields']['itemContent'] ?? null;
					if ( is_null( $content ) || empty( $content ) ) {
						break;
					}
					$quote_block        = $this->gutenberg_blocks->get_quote( $content, $heading );
					$gutenberg_blocks[] = $quote_block;
					break;
					
				case 'blockPoll':
					$this->logger->error( sprintf( 'ERROR, warning -- skipping blockPoll content in entry ID %d.', $entry_id ) );
					break;
					
				case 'blockGraphic':
					$this->logger->error( sprintf( 'ERROR, warning -- skipping blockGraphic content entry ID %d.', $entry_id ) );
					break;
	
				default:
					$this->logger->error( sprintf( "ERROR unknown block type '%s' in entry ID %d. Skipping.", $craft_block['type'], $entry_id ) );
					break;
			}
		}

		return $gutenberg_blocks;
	}

	/**
	 * Get image block classes from Craft blockImage or blockExternalImage.
	 * 
	 * @param array $craft_block The Craft blockImage or blockExternalImage data from Expanded JSON export.
	 * @return string|null       The image block classes.
	 */
	public function get_craft_image_block_classes( array $craft_block ): ?string {
		// Get image classes.
		$image_classes = null;
		// Add position class.
		if ( isset( $craft_block['fields']['itemPosition'] ) && ! empty( $craft_block['fields']['itemPosition'] ) ) {
			$image_classes = 'legacy-' . $craft_block['fields']['itemPosition'];
		}
		// Add width class.
		if ( isset( $craft_block['fields']['itemWidth'] ) && ! empty( $craft_block['fields']['itemWidth'] ) ) {
			$image_classes .= ! empty( $image_classes ) ? ' ' : '';
			$image_classes .= 'legacy-' . $craft_block['fields']['itemWidth'];
		}

		return $image_classes;
	}

	/**
	 * Get the final redirect URL from a given URL.
	 *
	 * @param string $url The URL to get the final redirect URL from.
	 * @return string The final redirect URL.
	 */
	public function get_final_redirect_url( string $url ): string {
		// phpcs:disable -- WordPress.WP.AlternativeFunctions.curl_curl_init.
		$ch = curl_init( $url );
		curl_setopt_array(
			$ch,
			[
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_NOBODY         => true,          // Don't fetch body.
				CURLOPT_USERAGENT      => 'Mozilla/5.0', // Soften Facebook's bot detection.
			]
		);
		curl_exec( $ch );
		$final_url = curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL );
		curl_close( $ch );
		// phpcs:enable

		return $final_url;
	}

	/**
	 * If the string is encapsulated in a <p> tag, remove it.
	 *
	 * @param string $text The input string, which may or may not be wrapped in a <p> tag.
	 * @return string The text with the outer <p> tag removed, or the original string.
	 */
	public function formatting_strip_outer_p_tag( string $text ): string {

		$text = trim( $text );

		/**
		 * Look for a string that starts and ends with a <p> tag.
		 *   ^\s*       : The start of the string (plus any leading whitespace, though we trimmed it)
		 *   <p[^>]*>   : Opening <p> tag, including any attributes
		 *   (.*?)      : Lazily capture everything in between -- this is the content we want to keep
		 *   <\/p>      : Closing </p> tag
		 *   \s*$       : Any trailing whitespace and the end of the string
		 *   /is        : 'i' case-insensitive, 's' (dotall) allows '.' to match newlines
		 */
		$pattern = '/^\s*<p[^>]*>(.*?)<\/p>\s*$/is';
		
		// If there is no match, it conveniently returns the original string unchanged.
		$result = preg_replace( $pattern, '$1', $text );
		
		// If preg_replace failed, return the original.
		$result = $result ?? $text;

		return $result;
	}

	/**
	 * Get the hostname from a given URL.
	 * 
	 * @param string $url The URL to get the hostname from.
	 * @return string The hostname.
	 */
	public function get_hostname_from_url( string $url ): string {
		$hostname = wp_parse_url( $url, PHP_URL_HOST );
		if ( substr_count( $hostname, '.' ) > 1 ) {
			// Remove one or more subdomains.
			$pos_1st_dot_from_right = strrpos( $hostname, '.' );
			$pos_2nd_dot_from_right = strrpos( substr( $hostname, 0, $pos_1st_dot_from_right ), '.' );
			$hostname               = substr( $hostname, $pos_2nd_dot_from_right + 1 );
		}

		return $hostname;
	}

	/**
	 * Update post content, excerpt, and modified date.
	 * 
	 * @param int   $post_id The post ID.
	 * @param array $entry   The entry data.
	 * @param \wpdb $craft_db The production database connection.
	 * @return void
	 */
	public function set_remaining_post_data( int $post_id, array $entry, wpdb $craft_db ): void {
		global $wpdb;

		/**
		 * Post excerpt.
		 */
		$post_excerpt = '';
		if ( ! empty( $entry['matrixLede'] ) ) {
			$post_excerpt_blocks = $this->convert_craft_content_blocks_to_gutenberg_blocks( $entry['id'], $entry['matrixLede'], $post_id, $craft_db );
			foreach ( $post_excerpt_blocks as $key_block => $block ) {
				// serialize_blocks() will glue block strings without line breaks. Let's add a double line break after each block.
				if ( $key_block > 0 ) {
					$post_excerpt .= "\n\n";
				}
				$post_excerpt .= serialize_block( $block );
			}
		}

		/**
		 * Post content.
		 */
		$post_content = null;
		if ( empty( $entry['matrixMainContent'] ) ) {
			$this->logger->error( sprintf( "ERROR, warning -- empty \$entry['matrixMainContent'] for entry ID %d.", $entry['id'] ) );
		} else {
			$post_content_blocks = $this->convert_craft_content_blocks_to_gutenberg_blocks( $entry['id'], $entry['matrixMainContent'], $post_id, $craft_db );
			// In Craft, the excerpt i.e. "Lede" is dynamically prepended to entity content, so it gets prepended to the post content.
			$post_content = $post_excerpt;
			foreach ( $post_content_blocks as $key_block => $block ) {
				// serialize_blocks() will glue block strings without line breaks. Let's add a double line break after each block.
				if ( $key_block > 0 || ! empty( $post_content ) ) {
					$post_content .= "\n\n";
				}
				$post_content .= serialize_block( $block );
			}
		}
		
		/**
		 * Date modified.
		 */
		$date_modified           = new \DateTime( $entry['dateUpdated'] );
		$date_modified_timestamp = $date_modified->format( 'Y-m-d H:i:s' );
		$date_modified_gmt       = clone $date_modified;
		$date_modified_gmt->setTimezone( new \DateTimeZone( 'UTC' ) );
		$date_modified_gmt_timestamp = $date_modified_gmt->format( 'Y-m-d H:i:s' );

		/**
		 * Update post.
		 */
		$post_data = [
			'post_excerpt'      => $post_excerpt,
			'post_content'      => $post_content,
			'post_modified'     => $date_modified_timestamp,
			'post_modified_gmt' => $date_modified_gmt_timestamp,
		];
		if ( ! $this->dry_run ) {
			$updated = $wpdb->update( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.DirectQuery.
				$wpdb->posts,
				$post_data,
				[ 'ID' => $post_id ]
			);
			if ( false === $updated ) {
				$this->logger->error( sprintf( "ERROR updating post ID %s, context %s : '%s'", $post_id, wp_json_encode( $post_data ), $wpdb->last_error ) );
			}
		}
	}

	/**
	 * Set post coauthors.
	 * 
	 * @param int   $post_id The post ID.
	 * @param array $entry   The entry data.
	 * @param array $users_data The users data.
	 * @param \wpdb $craft_db The production database connection.
	 * @return void
	 */
	public function set_post_coauthors( int $post_id, array $entry, array $users_data, wpdb $craft_db ): void {
		
		global $wpdb;
		$coauthors = [];
		
		/**
		 * Get post coauthors from entry bylines or entry author.
		 */
		$bylines = $this->get_entry_bylines( $entry, $users_data, $craft_db );
		if ( ! empty( $bylines ) ) {

			// If bylines are set, use those for post (co)authors.
			foreach ( $bylines as $byline ) {
				// Get or create WP user from byline.
				$wp_user_unique_identifier = 'newspack_migration_byline ' . $byline['name'];
				$wp_user_data              = [
					'display_name' => $byline['name'],
					'role'         => Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME,
				];
				try {
					$wp_user = ! $this->dry_run ? $this->users->create_or_get_user( $wp_user_data, $wp_user_unique_identifier ) : new \WP_User();
				} catch ( \Exception $e ) {
					$this->logger->error( sprintf( "ERROR inserting user from byline '%s' (data: %s) and unique identifier '%s' : %s'", $wp_user_data['display_name'], wp_json_encode( $byline ), $wp_user_unique_identifier, $e->getMessage() ) );
				}
				if ( is_wp_error( $wp_user ) ) {
					$this->logger->error( sprintf( "ERROR inserting user from byline '%s' (data: %s) and unique identifier '%s' : %s'", $wp_user_data['display_name'], wp_json_encode( $byline ), $wp_user_unique_identifier, $wp_user->get_error_message() ) );
				}

				// If there's a $byline['user_id'], save it as usermeta.
				if ( ! is_null( $byline['user_id'] ) ) {
					if ( ! $this->dry_run ) {
						update_user_meta( $wp_user->ID, 'newspack_migration_byline_user_id', $byline['user_id'] );
					}
				}

				// Add to coauthors.
				$coauthors[] = $wp_user;
			}
		} else {

			// If bylines aren't set, use Craft entry author as post author.
			if ( ! isset( $entry['authorId'] ) || empty( $entry['authorId'] ) ) {
				$this->logger->error( sprintf( "ERROR, entry ID %d, title '%s' : after no bylines were found, no author ID is set either", $entry['id'], $entry['title'] ) );
				return;
			}

			$author = $this->get_user_data( $entry['authorId'], $users_data, $craft_db );
			if ( is_null( $author ) ) {
				$this->logger->error( sprintf( "ERROR getting Craft author data for entry ID %d, title '%s', author ID %d", $entry['id'], $entry['title'], $entry['authorId'] ) );
				return;
			}
			
			// Create WP user from entry author.
			$wp_user_unique_identifier = 'newspack_migration_author_id ' . $author['id'];
			$wp_user_data              = [
				'user_email'   => $author['email'],
				'display_name' => $author['display_name'],
				'first_name'   => $author['first_name'],
				'last_name'    => $author['last_name'],
				'description'  => $author['bio'],
				'role'         => Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME,
			];
			try {
				$wp_user     = ! $this->dry_run ? $this->users->create_or_get_user( $wp_user_data, $wp_user_unique_identifier ) : new \WP_User();
				$coauthors[] = $wp_user;
			} catch ( \Exception $e ) {
				$this->logger->error( sprintf( "ERROR inserting user from author name '%s' (data: %s) and unique identifier '%s' : %s'", $wp_user_data['display_name'], wp_json_encode( $author ), $wp_user_unique_identifier, $e->getMessage() ) );
			}
			if ( is_wp_error( $wp_user ) ) {
				$this->logger->error( sprintf( "ERROR inserting user from author name '%s' (data: %s) and unique identifier '%s' : %s'", $wp_user_data['display_name'], wp_json_encode( $author ), $wp_user_unique_identifier, $wp_user->get_error_message() ) );
			}

			// User metas.
			$wp_user_metas = [
				'newspack_migration_legacy_id'  => $entry['authorId'],
				'newspack_migration_legacy_uid' => $author['uid'],
				'newspack_migration_legacy_avatar_username' => $author['username'],
			];
			
			// Import avatar image.
			$avatar_attachment_id = null;
			if ( ! empty( $author['avatar_image_url'] ) ) {
				$url = $author['avatar_image_url'];

				// Import image.
				$avatar_attachment_id = ! $this->dry_run ? $this->attachments->import_external_file( $url ) : -1;
				if ( is_wp_error( $avatar_attachment_id ) ) {
					$this->logger->error( sprintf( "ERROR inserting avatar image URL '%s' : %s", $url, $avatar_attachment_id->get_error_message() ) );
				} else {
					// Save custom attachment metas.
					if ( ! $this->dry_run ) {
						update_post_meta( $avatar_attachment_id, 'newspack_migration_asset_url', $url );
					}
	
					// Also add this to user metas.
					$wp_user_metas['newspack_migration_legacy_avatar_photo_id'] = $avatar_attachment_id;
				}
			}

			// Set user avatar.
			if ( ! $this->dry_run ) {
				if ( ! is_null( $avatar_attachment_id ) && ! is_wp_error( $avatar_attachment_id ) ) {
					$this->simple_local_avatars->assign_new_user_avatar( $avatar_attachment_id, $wp_user->ID );
				}
			}
			
			// Save user metas.
			if ( ! $this->dry_run ) {
				foreach ( $wp_user_metas as $key => $value ) {
					update_user_meta( $wp_user->ID, $key, $value );
				}
			}
		}

		/**
		 * Assign (co)authors to post.
		 */
		if ( empty( $coauthors ) ) {
			$this->logger->error( sprintf( "ERROR assigning coauthors to entry ID %d, title '%s' : no coauthors found", $entry['id'], $entry['title'] ) );
		} elseif ( count( $coauthors ) === 1 ) {
			// There's just one author -- use `wp_users`.`author`.
			if ( ! $this->dry_run ) {
				$wp_user_id = $coauthors[0]->ID;
				$updated = $wpdb->update( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.DirectQuery.
					$wpdb->posts,
					[ 'post_author' => $wp_user_id ],
					[ 'ID' => $post_id ]
				);
				if ( false === $updated ) {
					$this->logger->error( sprintf( 'ERROR updating post_author %s for post ID %s : %s', $wp_user_id, $post_id, $wpdb->last_error ) );
				}
			}

			// Unassign any coauthors.
			if ( ! $this->dry_run ) {
				$this->coauthors->unassign_all_guest_authors_from_post( $post_id, false );
			}
		} else { // phpcs:ignore -- Allow the single `if` inside this `else` instead of `elseif` for readability. Universal.ControlStructures.DisallowLonelyIf.Found.
			// Multiple coauthors.
			if ( ! $this->dry_run ) {
				$this->coauthors->assign_authors_to_post( $coauthors, $post_id, false );
			}
		}
	}

	/**
	 * Set post comments.
	 * 
	 * @param int   $post_id    The post ID.
	 * @param array $entry      The entry data.
	 * @param array $users_data The users data.
	 * @param \wpdb $craft_db    The production database connection.
	 * @return void
	 */
	public function set_post_comments( int $post_id, array $entry, array $users_data, wpdb $craft_db ): void {
		global $wpdb;

		// This map will store the mapping from Craft comment IDs to new WordPress comment IDs.
		$craft_comment_id_to_wp_id = [];

		// Get entry comments. The comments must be ordered by hierarchy (lft) for this to work.
		$comments = $this->get_entry_comments( $entry['id'], $craft_db );
		foreach ( $comments as $comment ) {

			/**
			 * Craft comment author name can be found in one of these two places in comment data:
			 *   - a custom text byline, in which case we have the 'author_name' and no 'author_user_id'
			 *   - an existing Craft user is the author comment, and in that case we have the 'author_user_id' and must get/create the author by ID
			 */
			$comment_autor_name = null;
			if ( ! empty( $comment['author_name'] ) ) {
				$comment_autor_name = $comment['author_name'];
			} else {
				$comment_autor      = $this->get_user_data( $comment['author_user_id'], $users_data, $craft_db );
				$comment_autor_name = $comment_autor['display_name'];
			}
			if ( is_null( $comment_autor_name ) ) {
				$this->logger->error( sprintf( 'ERROR getting comment author name for comment ID %d in entry ID %d, while importing post ID %d', $comment['comment_id'], $entry['id'], $post_id ) );
				continue;
			}

			// Insert comment.
			$comment_data = [
				'comment_post_ID'  => $post_id,
				'comment_approved' => 'approved' === $comment['status'] ? 1 : 0,
				'comment_author'   => $comment_autor_name,
				'comment_content'  => $comment['comment'],
				'comment_date'     => $comment['comment_date'],
				'comment_parent'   => 0,
			];

			// If this is a reply, find the parent's WP ID and set it.
			if ( ! is_null( $comment['reply_to_comment_id'] ) && isset( $craft_comment_id_to_wp_id[ $comment['reply_to_comment_id'] ] ) ) {
				$comment_data['comment_parent'] = $craft_comment_id_to_wp_id[ $comment['reply_to_comment_id'] ];
			}

			$comment_id = ! $this->dry_run ? wp_insert_comment( $comment_data ) : -1;
			if ( false === $comment_id || 0 === $comment_id ) {
				$this->logger->error( sprintf( 'ERROR inserting comment for post ID %s : %s', $post_id, $wpdb->last_error ) );
				continue;
			}

			// Store the new ID in our map.
			$craft_comment_id_to_wp_id[ $comment['comment_id'] ] = $comment_id;

			// Save comment metas.
			$commentmetas = [
				'newspack_migration_legacy_id'      => $comment['comment_id'],
				'newspack_migration_flagged'        => $comment['flagged'],
				'newspack_migration_status'         => $comment['status'],
				'newspack_migration_author_name'    => $comment['author_name'],
				'newspack_migration_author_user_id' => $comment['author_user_id'],
				'newspack_migration_user_id'        => $comment['user_id'],
			];
			if ( ! $this->dry_run ) {
				foreach ( $commentmetas as $key => $value ) {
					add_comment_meta( $comment_id, $key, $value );
				}
			}
		}
	}

	/**
	 * Set post featured image.
	 * 
	 * Featured image data is located in two places in Craft CMS:
	 * 1. asset image object itself has (e.g. https://www.newhavenindependent.org/admin/assets/edit/11903556-delauro1?site=siteNHI):
	 *    => this info is retrieved by `get_asset_image_data`:
	 *      asset "id"                  => postmeta "newspack_migration_asset_id"
	 *      asset "url"                 => postmeta "newspack_migration_asset_url"
	 *      asset "date_created"        => Attachment date_created, GMT. (e.g. 2025-06-15 12:00:00)
	 *      asset "filename"            => Attachment "newspack_migration_asset_filename"
	 *      asset "Title"               => Attachment "Title"
	 *      asset "Credit"              => Attachment "Credit"
	 *      asset "Description"         => Attachment "Description"
	 *      asset "Uploader"            => postmetameta "newspack_migration_asset_uploader"
	 *      asset "width"               => postmeta "newspack_migration_asset_width"
	 *      asset "height"              => postmeta "newspack_migration_asset_height"
	 * 2. lede ("excerpt") blockImage component also has ( e.g. https://www.newhavenindependent.org/admin/entries/sectionArticles/11903505-ethans_law?site=siteNHI#tab02--content):
	 *    => this info is retrieved by `get_matrixLede_itemAsset_data`:
	 *      lede "Photo Caption"        => Attachment "Caption"
	 * 
	 * @param int   $post_id The post ID.
	 * @param array $entry   The entry data.
	 * @param wpdb  $craft_db The production database connection.
	 * @return int|null The featured image ID, or null if there was an error.
	 */
	public function set_post_featured_image( int $post_id, array $entry, wpdb $craft_db ): ?int {
		// Get featured image data.
		$asset_json_data = $this->get_matrixLede_first_block_image_data( $entry );
		if ( is_null( $asset_json_data ) ) {
			// No featured image.
			return null;
		}

		$caption = $asset_json_data['itemContent'];

		// Import image.
		$featured_image_id = $this->import_image_from_asset( $asset_json_data['id'], $post_id, $entry['id'], $craft_db, $caption );
		if ( is_wp_error( $featured_image_id ) ) {
			$this->logger->error( sprintf( 'ERROR inserting featured image entry ID %d, asset ID %d : %s', $entry['id'], $asset_json_data['id'], $featured_image_id->get_error_message() ) );
			return null;
		}

		// Set featured image as post thumbnail.
		if ( ! $this->dry_run ) {
			set_post_thumbnail( $post_id, $featured_image_id );
		}

		// Hide the featured image, because in Craft it's a part of Lede, which is always prepended to the post content.
		if ( ! $this->dry_run ) {
			update_post_meta( $post_id, 'newspack_featured_image_position', 'hidden' );
		}

		return $featured_image_id;
	}

	/**
	 * Test command.
	 * 
	 * This is a test command to help with the migration.
	 * 
	 * @param array $pos_args The positional arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function cmd_test( array $pos_args, array $assoc_args ): void {
		$this->dry_run                      = isset( $assoc_args['dry-run'] ) ? true : false;
		$craft_db_name                      = $assoc_args['craft-db-name'];
		$craft_db_user                      = $assoc_args['craft-db-user'];
		$craft_db_pass                      = $assoc_args['craft-db-pass'];
		$craft_db_host                      = $assoc_args['craft-db-host'];
		$craft_db_port                      = $assoc_args['craft-db-port'];
		$entries_jsons_folder               = $assoc_args['json-expanded-entries-folder'];
		$users_json_file                    = $assoc_args['json-expanded-users'];
		$categories_news_expanded_json_file = $assoc_args['json-expanded-categories-news-sections'];
		
		$this->setup_logger( __FUNCTION__ );
		$craft_db = $this->get_craft_db_connection( $craft_db_name, $craft_db_user, $craft_db_pass, $craft_db_host, $craft_db_port );

		// phpcs:disable -- temporary dev code.

		$this->logger->info( '--- PARSE IMPORT LOG FILE FOR SUCCESSES AND WARNINGS/ERRORS  -----------------------------' );
		// Read lines from the log file.
		$in_log_file      = '/Users/ivanuravic/www/newhavenindependent/app/public/wp-content/plugins/newspack-custom-content-migrator/import_test_read.out';
		// Output files.
		$out_success_file = '/Users/ivanuravic/www/newhavenindependent/app/public/wp-content/plugins/newspack-custom-content-migrator/import_success_ids.txt';
		$out_warn_file    = '/Users/ivanuravic/www/newhavenindependent/app/public/wp-content/plugins/newspack-custom-content-migrator/import_warn_ids.txt';
		
		if ( ! file_exists(	$in_log_file) || !is_readable($in_log_file)) {
			WP_CLI::line( "Error: Log file not found." );
			exit;
		}
		$lines = file( $in_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( false === $lines ) {
			WP_CLI::line( "Error: Could not read the log file." );
			exit;
		}
	
		$success_ids = [];
		$warn_ids = [];
		$line_count = count($lines);
		for ($i = 0; $i < $line_count; $i++) {
			$current_line = $lines[$i];
	
			// Regex to identify the start of an entry's log block and capture its ID.
			// e.g., "INFO (1)/(630) ; (6)/(100) Entry ID 10705254"
			if (preg_match('/INFO \(\d+\)\/\(\d+\) ; \(\d+\)\/\(\d+\) Entry ID (\d+)/', $current_line, $matches)) {
				$current_entry_id = $matches[1];
				$is_clean_insert = false;
	
				// Check if the next line exists and is the expected "Inserted post" line.
				$insert_line_index = $i + 1;
				if ($insert_line_index < $line_count && strpos($lines[$insert_line_index], "INFO Inserted post") !== false) {
					// Now, check the line *after* the "Inserted post" line.
					$line_after_insert_index = $i + 2;
					if ( $line_after_insert_index >= $line_count ) {
						// This was the last entry in the file, and it had no errors following it. It's a success.
						$is_clean_insert = true;
					} else {
						$line_after_insert = $lines[$line_after_insert_index];
						// A clean insert is one where the next line is either a new entry ID or a new file marker.
						if (
							preg_match('/INFO \(\d+\)\/\(\d+\) ; \(\d+\)\/\(\d+\) Entry ID \d+/', $line_after_insert) ||
							strpos($line_after_insert, 'INFO =====') !== false
						) {
							$is_clean_insert = true;
						}
					}
				}
	
				// Based on the flag, add the ID to the correct list.
				if ( $is_clean_insert ) {
					$success_ids[] = $current_entry_id;
				} else {
					// If it wasn't a clean insert (e.g., an ERROR followed, or the "Inserted post" line was missing),
					// it belongs in the warning list.
					$warn_ids[] = $current_entry_id;
				}
			}
		}
	
		file_put_contents( $out_success_file, implode( "\n", $success_ids ) );
		file_put_contents( $out_warn_file, implode( "\n", $warn_ids ) );
		WP_CLI::line( count( $success_ids ) . " successfully imported entries." );
		WP_CLI::line( count( $warn_ids ) . " entry IDs with issues." );
		exit;

		
		$this->logger->info( '--- GET IMAGE BLOCKs PARAMS  -----------------------------' );
		// Extract all entry IDs available in JSONs.
		$folder_to_entries_jsons = '/Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/automated_manual_exports/puppeteer-automation/downloaded_entities';
		$entries_json_files = glob( $folder_to_entries_jsons . '/*.json' );
		$img_data = [];
		foreach ( $entries_json_files as $entries_json_file ) {
			$entries_file_data = json_decode( file_get_contents( $entries_json_file ), true );
			if ( ! is_array( $entries_file_data ) ) {
				continue;
			}
			foreach ( $entries_file_data as $entry ) {
				// Loop matrxLede
				$loop_blocks = [];
				if ( isset( $entry['matrixLede'] ) ) {
					$loop_blocks = $entry['matrixLede'];
				}
				if ( isset( $entry['matrixMainContent'] ) ) {
					$loop_blocks = array_merge( $loop_blocks, $entry['matrixMainContent'] );
				}
				foreach ( $loop_blocks as $block ) {
					if ( 'blockExternalImage' !== $block['type'] && 'blockImage' !== $block['type'] ) {
						continue;
					}

					// If it's been catalogued (Lede and Content will overlap), skip.
					if ( 'blockImage' === $block['type'] ) {
						if ( isset( $img_data[ $entry['id'] ]['_blockImage_asset_id'] ) ) {
							continue;
						}
					}
					if ( 'blockExternalImage' === $block['type'] ) {
						if ( isset( $img_data[ $entry['id'] ]['_blockExternalImage_url'] ) ) {
							continue;
						}
					}

					// Catalogue the image data.
					$img_data[ $entry['id'] ] = [
						// blockImage will have the asset ID, but blockExternalImage will not.
						'_blockImage_asset_id'    => $block['fields']['itemAsset'] ?? null,
						// blockExternalImage will have the URL, but blockImage will not.
						'_blockExternalImage_url' => $block['fields']['itemURL']['url'] ?? null,
						// Common.
						'entry_id'                => $entry['id'],
						'entry_title'             => $entry['title'],
						'itemPosition'            => $block['fields']['itemPosition'],
						'itemWidth'               => $block['fields']['itemWidth'],
					];

				}
			}
		}
		// Great. Let's analyze the data now.
		// Get unique itemPosition values with 15 example entry IDs.
		$image_positions_to_entry_ids = [];
		$image_widths_to_entry_ids    = [];
		foreach ( $img_data as $entry_id => $img_data_entry ) {
			$is_position_set              = isset( $image_positions_to_entry_ids[ $img_data_entry['itemPosition'] ] );
			$position_needs_more_examples = true;
			$position_has_this_title      = false;
			if ( $is_position_set ) {
				$existing_titles              = array_column( $image_positions_to_entry_ids[ $img_data_entry['itemPosition'] ], 1 );
				$position_has_this_title      = in_array( $img_data_entry['entry_title'], $existing_titles );
				$position_needs_more_examples = count( $existing_titles ) < 15;
			}
			if ( ! $is_position_set || ( $position_needs_more_examples && ! $position_has_this_title ) ) {
				$image_positions_to_entry_ids[ $img_data_entry['itemPosition'] ][] = [ $entry_id, $img_data_entry['entry_title'] ];
			}

			$is_width_set              = isset( $image_widths_to_entry_ids[ $img_data_entry['itemWidth'] ] );
			$width_needs_more_examples = true;
			$width_has_this_title      = false;
			if ( $is_width_set ) {
				$existing_titles           = array_column( $image_widths_to_entry_ids[ $img_data_entry['itemWidth'] ], 1 );
				$width_has_this_title      = in_array( $img_data_entry['entry_title'], $existing_titles );
				$width_needs_more_examples = count( $existing_titles ) < 15;
			}
			if ( ! $is_width_set || ( $width_needs_more_examples && ! $width_has_this_title ) ) {
				$image_widths_to_entry_ids[ $img_data_entry['itemWidth'] ][] = [ $entry_id, $img_data_entry['entry_title'] ];
			}
		}
		$this->logger->info( '--- UNIQUE ITEM POSITIONS  -----------------------------' );
		// var_dump( $image_positions_to_entry_ids );
		$this->logger->info( '--- UNIQUE ITEM WIDTHS  -----------------------------' );
		var_dump( $image_widths_to_entry_ids );
		// var_dump( $image_widths_to_entry_ids );
		
		// $this->logger->info( implode( "\n", $unique_item_positions ) );
		exit;

		$this->logger->info( '--- GET EXTERNAL IMAGE BLOCK URLS  -----------------------------' );
		// Extract all entry IDs available in JSONs.
		$folder_to_entries_jsons = '/Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/automated_manual_exports/puppeteer-automation/downloaded_entities';
		$entries_json_files = glob( $folder_to_entries_jsons . '/*.json' );
		foreach ( $entries_json_files as $entries_json_file ) {
			$entries_file_data = json_decode( file_get_contents( $entries_json_file ), true );
			if ( ! is_array( $entries_file_data ) ) {
				continue;
			}
			$urls = [];
			foreach ( $entries_file_data as $entry ) {
				if ( isset( $entry['matrixLede'] ) ) {
					foreach ( $entry['matrixLede'] as $block ) {
						if ( 'blockExternalImage' === $block['type'] ) {
							if ( ! in_array( $block['fields']['itemURL']['url'], $urls ) ) {
								$urls[] = $block['fields']['itemURL']['url'];
							}
						}
					}
				}
				if ( isset( $entry['matrixMainContent'] ) ) {
					foreach ( $entry['matrixMainContent'] as $block ) {
						if ( 'blockExternalImage' === $block['type'] ) {
							if ( ! in_array( $block['fields']['itemURL']['url'], $urls ) ) {
								$urls[] = $block['fields']['itemURL']['url'];
							}
						}
					}
				}
			}
			$this->logger->info( implode( "\n", $urls ) );
		}
		exit;

		// $this->logger->info( '--- TEST VARIOUS "BLOCK VIDEO" HOST EMBEDS  -----------------------------' );
		$video_urls = [
			'https://baba.com/c'
		];
		$post_content = '';
		foreach ( $video_urls as $video_url ) {
			$link        = sprintf( '<a href="%s" class="nhi-blockVideo-link" target="_blank">%s</a>', $video_url, $video_url );
			$video_block = $this->gutenberg_blocks->get_paragraph( $link );

			$paragraph_block          = $this->gutenberg_blocks->get_paragraph( $video_url );
			$separator_block      = $this->gutenberg_blocks->get_separator();
			$post_content .= ! empty( $post_content ) ? "\n\n" : '';
			$post_content .= serialize_block( $paragraph_block );
			$post_content .= serialize_block( $video_block );
			$post_content .= serialize_block( $separator_block );
		}
		// SAVE DIRECTLY TO TEST POST CONTENT.
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			[ 'post_content' => $post_content ],
			[ 'ID' => 12 ]
		);
		exit;

		// $this->logger->info( '--- TEST FB VIDEO EMBEDS  -----------------------------' );
		$video_urls = [
			'https://fb.watch/aWmrsqLA3N/',
			'https://facebook.com/watch/live/?ref=watch_permalink&v=1162598517894556',
			'https://www.facebook.com/100005396685702/videos/1583287642064956/',
			'https://www.facebook.com/100063466693955/posts/pfbid0URDs4XYD2HT2MbDexGsUkgwDxfMwnE61XhjxeoDp2QuNvRXiEdKr5RsPCu6uAFm5l/?app=fbl',
			'https://www.facebook.com/NewHavenIndependent/videos/1013196649872007',
			'https://www.facebook.com/watch/?v=429471115872806',
		];
		$post_content = '';
		foreach ( $video_urls as $video_url ) {
			$parsed_url           = wp_parse_url( $video_url );
			// $url_noparams         = sprintf( '%s://%s%s', $parsed_url['scheme'], $parsed_url['host'], $parsed_url['path'] );
			$html_content_sprintf = sprintf(
				"\n%s\n%s\n",
				'<div id="fb-root"></div><script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v22.0"></script>',
				'<div class="fb-video" data-href="%s" data-width="500" data-show-text="false"></div>'
			);
			$embed_url = $this->get_final_redirect_url( $video_url );
			$html_content         = sprintf( $html_content_sprintf, $embed_url );
			$video_block          = $this->gutenberg_blocks->get_html( $html_content );
			$separator_block      = $this->gutenberg_blocks->get_separator();
			$post_content .= ! empty( $post_content ) ? "\n\n" : '';
			$post_content .= serialize_block( $video_block );
			$post_content .= serialize_block( $separator_block );
		}
		// SAVE DIRECTLY TO TEST POST CONTENT.
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			[ 'post_content' => $post_content ],
			[ 'ID' => 12 ]
		);
		exit;

		$this->logger->info( '--- EXTRACT ENTRIES INTO SINGLE JSON FILE  -----------------------------' );
		$entry_ids = [
			// blockHeading
			10001903, 10001995, 10002432, 10003360, 
			// blockText.
			10000517, 100007, 10000737, 10000951, 10001, 
			// blockRawHTML.
			10101356, 10107340, 10114756, 10128962, 10134667, 
			// blockVideo.
			10001903, 10002432, 10003945, 10004356, 10005872, 
			// blockImage.
			10000517, 10001370, 10001536, 10001880, 10001906, 
			// blockExternalImage.
			9976675, 9977934, 9985266, 9985270, 9990704, 
			// blockPoll.
			10001995, 10008484, 10053994, 10066330, 10088942, 
			// blockSeparator.
			// -- JUST 10 TOTAL CONTENT.
			10106224, 10114756, 10229093, 10588553, 11645370, 
			// blockQuote.
			// -- JUST 4 TOTAL CONTENT:
			10282510, 10707615, 11623071, 372059, 
			// blockGraphic.
			// -- used in just 2 entites in Content:
			// -- and also in just 2 entites in Lede:
			9812751, 9864293, 
		];
		$folder_to_entries_jsons = '/Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/automated_manual_exports/puppeteer-automation/downloaded_entities';
		$path_single_json_entries = '/Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/eg_content_and_lede_blocktypes_IDS/entries_p1.json';
		$entries_json_files = glob( $folder_to_entries_jsons . '/*.json' );
		$entries_file_data = [];
		$entries_picked_data = [];
		foreach ( $entries_json_files as $entries_json_file ) {
			$entries_file_data = json_decode( file_get_contents( $entries_json_file ), true );
			if ( ! is_array( $entries_file_data ) ) {
				continue;
			}
			foreach ( $entries_file_data as $entry ) {
				if ( in_array( $entry['id'], $entry_ids ) ) {
					$entries_picked_data[] = $entry;
					// $entries_picked_data[ $entry['id'] ][] = $entry;
				}
			}
		}
		$this->logger->info( '--- $entry_ids: ' . count($entry_ids) );
		$this->logger->info( '--- $entries_picked_data: ' . count($entries_picked_data) );
		if ( file_exists( $path_single_json_entries ) ) {
			unlink( $path_single_json_entries );
		}
		file_put_contents( $path_single_json_entries, json_encode( $entries_picked_data, JSON_PRETTY_PRINT ) );
		exit;

		$this->logger->info( '--- TEST DELAURO ENTRY  -----------------------------' );
		$entries_json_file = '/Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/entries_delauroBringsBack_expanded.json';
		$users_data = json_decode( file_get_contents( $users_json_file ), true );
		$entry_data = json_decode( file_get_contents( $entries_json_file ), true );
		$entry      = $entry_data[0];
		exit;

		$this->logger->info( '--- GET VIDEO BLOCK URLS  -----------------------------' );
		// Extract all entry IDs available in JSONs.
		$folder_to_entries_jsons = '/Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/automated_manual_exports/puppeteer-automation/downloaded_entities';
		$entries_json_files = glob( $folder_to_entries_jsons . '/*.json' );
		foreach ( $entries_json_files as $entries_json_file ) {
			$entries_file_data = json_decode( file_get_contents( $entries_json_file ), true );
			if ( ! is_array( $entries_file_data ) ) {
				continue;
			}
			foreach ( $entries_file_data as $entry ) {
				if ( ! isset( $entry['matrixMainContent'] ) ) {
					continue;
				}
				foreach ( $entry['matrixMainContent'] as $block ) {
					if ( 'blockVideo' === $block['type'] ) {
						$this->logger->info( $block['fields']['itemVideoEmbed']['url'] );
					}
				}
			}
		}
		exit;

		$this->logger->info( '--- GET REPEATING/DUPLICATE ENTRY IDs FROM JSONS  -----------------------------' );
		// Extract all entry IDs available in JSONs.
		$folder_to_entries_jsons = '/Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/automated_manual_exports/puppeteer-automation/downloaded_entities';
		$entries_json_files = glob( $folder_to_entries_jsons . '/*.json' );
		$entry_ids_files = [];
		foreach ( $entries_json_files as $entries_json_file ) {
			$entries_file_data = json_decode( file_get_contents( $entries_json_file ), true );
			if ( ! is_array( $entries_file_data ) ) {
				continue;
			}
			foreach ( $entries_file_data as $entry ) {
				$entry_ids_files[$entry['id']]['title'] = $entry['title'];
				$entry_ids_files[$entry['id']]['files'][] = $entries_json_file;
			}
		}
		// Save CSV entry IDs to /Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/automated_manual_exports/puppeteer-automation/entry_ids_p1-p672.csv
		foreach ( $entry_ids_files as $entry_id => $arr ) {
			$files = $arr['files'];
			$title = $arr['title'];
			if ( count( $files ) > 1 ) {
				$this->logger->info( sprintf( "- ID '%d' title '%s' is repeating in %d files: \n- %s", $entry_id, $title, count( $files ), implode( "\n- ", $files ) ) );
			}
		}
		exit;

		$this->logger->info( '--- GET ALL ENTRY IDs FROM JSONS  -----------------------------' );
		// Extract all entry IDs available in JSONs.
		$folder_to_entries_jsons = '/Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/automated_manual_exports/puppeteer-automation/downloaded_entities';
		$entries_json_files = glob( $folder_to_entries_jsons . '/*.json' );
		$entry_ids = [];
		foreach ( $entries_json_files as $entries_json_file ) {
			$entries_file_data = json_decode( file_get_contents( $entries_json_file ), true );
			if ( ! is_array( $entries_file_data ) ) {
				continue;
			}
			foreach ( $entries_file_data as $entry ) {
				$entry_ids[] = $entry['id'];
			}
		}
		// Save CSV entry IDs to /Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/automated_manual_exports/puppeteer-automation/entry_ids_p1-p672.csv
		$csv_file = '/Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/automated_manual_exports/puppeteer-automation/entry_ids_p1-p672.csv';
		$csv_file_handle = fopen( $csv_file, 'w' );
		fputcsv( $csv_file_handle, [ 'entry_id' ] );
		foreach ( $entry_ids as $entry_id ) {
			fputcsv( $csv_file_handle, [ $entry_id ] );
		}
		fclose( $csv_file_handle );
		exit;

		// phpcs:enable
	}

	/**
	 * Get JSON files with entrief from folder in descending order of date created.
	 * 
	 * The JSONs are created via paginated exports, ordered by date created ascending.
	 * In order to read and import the newest versions of entries first (think revisions), we need to get them in reversed order, in date created descending:
	 *    - first order the JSON files in descending order
	 *    - then also when reading single JSON files, order entries from those files in reverse order as well
	 * 
	 * @param string $entries_jsons_folder The folder containing the entries JSON files.
	 * 
	 * @return array JSON files in descending order.
	 */
	private function get_json_entries_files_descending( string $entries_jsons_folder ): array {
		$entries_json_files = glob( $entries_jsons_folder . '/*.json' );

		/**
		 * File names will not sort correctly as strings, because page numbers have different number of digits:
		 *   - entries_p1.json
		 *   - entries_p10.json
		 *   - entries_p100.json
		 * So get the files in an array where keys are page numbers, and values are file paths, then it's easy to sort by keys.
		 */
		$entries_json_files_descending = [];
		foreach ( $entries_json_files as $entries_json_file ) {
			// Expected expanded JSON export file names: "entries_p{NUMBER}.json", where {NUMBER} is page number from paginated backend entries list ordered by date created ascending.
			$number = preg_replace( '/^.*entries_p(\d+)\.json$/', '$1', $entries_json_file );
			if ( ! is_numeric( $number ) ) {
				$this->logger->error( sprintf( 'ERROR ordering JSON file %s : %s is not a number', $entries_json_file, $number ) );
			}
			$entries_json_files_descending[ (int) $number ] = $entries_json_file;
		}

		// First sort by keys.
		ksort( $entries_json_files_descending );
		// Reverse the array, so that files are in date created descending -- newest first.
		$entries_json_files_descending = array_reverse( $entries_json_files_descending );
		
		return $entries_json_files_descending;
	}
	
	/**
	 * Get entries from a JSON file in reversed order.
	 * JSON file is expected to contain an array of entries, ordered by date created ascending.
	 * By reversing the entries, the newest ones come first.
	 * 
	 * @param string $entries_json_file The path to the JSON file.
	 * 
	 * @return array The entries data from JSON, reversed.
	 */
	private function get_entries_from_json_file_descending( string $entries_json_file ): array {
		$entries_data = json_decode( file_get_contents( $entries_json_file ), true ); // phpcs:ignore -- WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown.
		if ( ! is_array( $entries_data ) ) {
			$this->logger->error( sprintf( 'ERROR reading JSON file %s : %s is not an array', $entries_json_file, $entries_data ) );
		}
		// Reverse array.
		$entries_data = array_reverse( $entries_data );

		return $entries_data;
	}

	/**
	 * Get section name by ID.
	 * 
	 * @param int  $section_id The section ID.
	 * @param wpdb $craft_db    The production database connection.
	 * 
	 * @return ?string The section name, or null if not found.
	 */
	public function get_section_name_by_id( int $section_id, wpdb $craft_db ): ?string {
		$query  = $craft_db->prepare(
			'SELECT name FROM sections WHERE id = %d LIMIT 1',
			$section_id
		);
		$result = $craft_db->get_var( $query );

		return $result ?: null; // phpcs:ignore -- Allow truthy return, if empty string also return null, which is consistent with a well defined return we want here, Universal.Operators.DisallowShortTernary.Found.
	}

	/**
	 * Get WP category ID from fieldSection.
	 * 
	 * @param int   $field_section_id  The fieldSection ID.
	 * @param array $sections_data The categories/sections data "expanded" export from Craft CMS.
	 * 
	 * @return ?int The category ID, or null if not found.
	 * 
	 * @throws \RuntimeException If errors occur.
	 */
	public function get_category_from_fieldSection( int $field_section_id, array $sections_data ): ?int {
		foreach ( $sections_data as $category ) {
			if ( $field_section_id != $category['id'] ) {
				continue;
			}

			// Get section data.
			$section_data = null;
			foreach ( $sections_data as $section ) {
				if ( $field_section_id == $section['id'] ) {
					$section_data = $section;
					break;
				}
			}
			if ( ! $section_data ) {
				throw new \RuntimeException( sprintf( "Category section data not found for fieldSection id '%d'", esc_attr( $field_section_id ) ) );
			}

			// Arguments.
			$section_parent_id = $section_data['parentId'] ?? null;
			$category_title    = $section_data['title'] ?? null;
			if ( ! $category_title ) {
				throw new \RuntimeException( sprintf( 'Category title not found for fieldSection id %d', esc_attr( $field_section_id ) ) );
			}

			// If parent exists, recursively call this function to get the parent title.
			if ( ! $section_parent_id ) {
				$category_id = wp_create_category( $category_title );
				if ( is_wp_error( $category_id ) ) {
					throw new \RuntimeException( sprintf( "Category creation failed for '%s' : %s", esc_attr( $category_title ), esc_html( $category_id->get_error_message() ) ) );
				}
				
				return $category_id;
			} else {
				// If there is a parent, recursively call this function to create the parent category.
				$category_parent_id = $this->get_category_from_fieldSection( $section_parent_id, $sections_data );

				// Get or create category with parent.
				$category_id = $this->taxonomy->get_or_create_category_by_name_and_parent_id( $category_title, $category_parent_id );
				
				return $category_id;
			}
		}

		return null;
	}

	/**
	 * Getneighborhood sections for an entry.
	 *
	 * @param int   $entry_id The entry ID.
	 * @param \wpdb $craft_db  The production database connection.
	 * @return int[] Array of category IDs.
	 */
	public function get_neighborhoods_categories( int $entry_id, wpdb $craft_db ): array {
		// fieldId for 'fieldNeighborhoods' is 14. -- SELECT id FROM fields WHERE handle = 'fieldNeighborhoods';.
		$field_id_neighborhoods = 14;

		// Get neighborhood target IDs from relations table.
		$query_target_ids        = $craft_db->prepare(
			'SELECT targetId FROM relations WHERE sourceId = %d AND fieldId = %d',
			$entry_id,
			$field_id_neighborhoods
		);
		$neighborhood_target_ids = $craft_db->get_col( $query_target_ids );

		if ( empty( $neighborhood_target_ids ) ) {
			return [];
		}

		$category_ids = [];
		foreach ( $neighborhood_target_ids as $target_id ) {
			$category_id = $this->get_or_create_hierarchical_neighborhood_category( $target_id, $craft_db );
			if ( ! is_null( $category_id ) ) {
				$category_ids[] = $category_id;
			}
		}

		return array_unique( $category_ids );
	}

	/**
	 * Recursively get or create a hierarchical neighborhood category.
	 *
	 * @param int   $neighborhood_id The neighborhood category ID from Craft.
	 * @param \wpdb $craft_db         The production database connection.
	 *
	 * @return int|null The WordPress category ID, or null on failure.
	 */
	private function get_or_create_hierarchical_neighborhood_category( int $neighborhood_id, wpdb $craft_db ): ?int {
		// Get neighborhood details from Craft DB.
		$neighborhood_query = $craft_db->prepare(
			'SELECT c.title, se.lft, se.rgt, se.level
			FROM content c
			JOIN structureelements se ON c.elementId = se.elementId
			WHERE c.elementId = %d
			LIMIT 1',
			$neighborhood_id
		);
		$neighborhood_data  = $craft_db->get_row( $neighborhood_query );

		if ( ! $neighborhood_data ) {
			$this->logger->error( sprintf( 'Could not find neighborhood data for ID %d.', $neighborhood_id ) );
			return null;
		}

		// Find the parent in Craft DB.
		$parent_id        = null;
		$parent_wp_cat_id = $this->taxonomy->get_or_create_category_by_name_and_parent_id( self::NEIGHBORHOODS_PARENT_CATEGORY_NAME, 0 );

		if ( $neighborhood_data->level > 1 ) {
			$parent_query = $craft_db->prepare(
				'SELECT elementId FROM structureelements 
				WHERE lft < %d AND rgt > %d AND level = %d
				ORDER BY rgt ASC
				LIMIT 1',
				$neighborhood_data->lft,
				$neighborhood_data->rgt,
				$neighborhood_data->level - 1
			);
			$parent_id    = $craft_db->get_var( $parent_query );
		}

		// If a Craft parent exists, recursively create it in WordPress.
		if ( $parent_id ) {
			$parent_wp_cat_id = $this->get_or_create_hierarchical_neighborhood_category( $parent_id, $craft_db );
		}

		// Create the current neighborhood category under its WordPress parent.
		return $this->taxonomy->get_or_create_category_by_name_and_parent_id( $neighborhood_data->title, $parent_wp_cat_id );
	}

	/**
	 * Get features categories for an entry.
	 *
	 * @param int   $entry_id The entry ID.
	 * @param \wpdb $craft_db  The production database connection.
	 * @return int[] Array of category IDs.
	 */
	public function get_features_categories( int $entry_id, wpdb $craft_db ): array {
		// fieldId for 'fieldFeatures' is 15. -- SELECT id FROM fields WHERE handle = 'fieldFeatures';.
		$field_id_features = 15;

		$query_target_ids   = $craft_db->prepare(
			'SELECT targetId FROM relations WHERE sourceId = %d AND fieldId = %d',
			$entry_id,
			$field_id_features
		);
		$feature_target_ids = $craft_db->get_col( $query_target_ids );

		if ( empty( $feature_target_ids ) ) {
			return [];
		}

		$category_ids = [];
		foreach ( $feature_target_ids as $target_id ) {
			$category_id = $this->get_or_create_hierarchical_feature_category( $target_id, $craft_db );
			if ( ! is_null( $category_id ) ) {
				$category_ids[] = $category_id;
			}
		}

		return array_unique( $category_ids );
	}

	/**
	 * Recursively get or create a hierarchical feature category.
	 *
	 * @param int   $feature_id The feature category ID from Craft.
	 * @param \wpdb $craft_db    The production database connection.
	 *
	 * @return int|null The WordPress category ID, or null on failure.
	 */
	private function get_or_create_hierarchical_feature_category( int $feature_id, wpdb $craft_db ): ?int {
		// Get feature details from Craft DB.
		$feature_query = $craft_db->prepare(
			'SELECT c.title, se.lft, se.rgt, se.level
			FROM content c
			JOIN structureelements se ON c.elementId = se.elementId
			WHERE c.elementId = %d
			LIMIT 1',
			$feature_id
		);
		$feature_data  = $craft_db->get_row( $feature_query );

		if ( ! $feature_data ) {
			$this->logger->error( sprintf( 'Could not find feature data for ID %d.', $feature_id ) );
			return null;
		}

		// Find the parent in Craft DB.
		$parent_id        = null;
		$parent_wp_cat_id = $this->taxonomy->get_or_create_category_by_name_and_parent_id( self::FEATURES_PARENT_CATEGORY_NAME, 0 );

		if ( $feature_data->level > 1 ) {
			$parent_query = $craft_db->prepare(
				'SELECT elementId FROM structureelements 
				WHERE lft < %d AND rgt > %d AND level = %d
				ORDER BY rgt ASC
				LIMIT 1',
				$feature_data->lft,
				$feature_data->rgt,
				$feature_data->level - 1
			);
			$parent_id    = $craft_db->get_var( $parent_query );
		}

		// If a Craft parent exists, recursively create it in WordPress.
		if ( $parent_id ) {
			$parent_wp_cat_id = $this->get_or_create_hierarchical_feature_category( $parent_id, $craft_db );
		}

		// Create the current feature category under its WordPress parent.
		return $this->taxonomy->get_or_create_category_by_name_and_parent_id( $feature_data->title, $parent_wp_cat_id );
	}

	/**
	 * Featured image is found in entry['matrixLede'], in the first blockImage type, and fields itemAsset array.
	 * e.g.
	 *  "matrixLede": {
	 *      "11903606": {
	 *          "type": "blockImage",
	 *          "enabled": true,
	 *          "collapsed": false,
	 *          "fields": {
	 *              "itemHelp": null,
	 *              "itemAsset": [
	 *                  11903556
	 *              ],
	 *              "itemContent": "Photo Caption"
	 *          }
	 *      }
	 *  }
	 * 
	 * @param array $entry The entry data.
	 * 
	 * @return ?array Array with some matrixLede > blockImage data. {
	 *  int 'id'           This corresponds to the asset ID.
	 *  ?int 'itemContent' This corresponds to the "Photo Caption" field.
	 * }
	 */
	public function get_matrixLede_first_block_image_data( array $entry ): ?array {
		if ( ! isset( $entry['matrixLede'] ) ) {
			return null;
		}
		foreach ( $entry['matrixLede'] as $block ) {
			if ( 'blockImage' === $block['type'] ) {
				
				// Warn about multiple itemAssets.
				if ( count( $block['fields']['itemAsset'] ) > 1 ) {
					$this->logger->error( sprintf( "WARNING, multiple \$block['fields']['itemAsset'] for entry ID %d, block ID %d.", $entry['id'], $block['id'] ) );
				}

				// Get first itemAsset.
				$asset_id = $block['fields']['itemAsset'][0] ?? null;
				if ( is_null( $asset_id ) ) {
					$this->logger->error( sprintf( "ERROR, featured image asset ID is not set in \$block['fields']['itemAsset'][0] for entry ID %d, block data: %s .", $entry['id'], wp_json_encode( $block ) ) );
					return null;
				}

				$item_content = ( isset( $block['fields']['itemContent'] ) && ! empty( $block['fields']['itemContent'] ) )
					? $block['fields']['itemContent']
					: null;

				// Return first itemAsset.
				return [
					'id'          => $asset_id,
					'itemContent' => $item_content,
				];
			}
		}

		return null;
	}

	/**
	 * Get user data.
	 * 
	 * @param int   $user_id    The user ID.
	 * @param array $users_data The users data.
	 * @param \wpdb $craft_db    The production database connection.
	 * @return ?array Array with author data with following keys. {
	 *  int 'id'                   Author ID.
	 *  ?string 'uid'              Author UID.
	 *  ?string 'email'            Author email.
	 *  ?string 'username'         Username.
	 *  ?string 'display_name'     Display name.
	 *  ?string 'first_name'       First name.
	 *  ?string 'last_name'        Last name.
	 *  ?string 'bio'              Bio.
	 *  ?string 'avatar_photo_id'  Avatar image asset ID.
	 *  ?string 'avatar_image_url' Avatar image URL.
	 * }
	 */
	public function get_user_data( int $user_id, array $users_data, wpdb $craft_db ): ?array {
		// Search for author in users_data.
		foreach ( $users_data as $user ) {
			if ( $user_id === $user['id'] ) {
				$photo_id         = $user['photoId'] ?? null;
				$avatar_image_url = null;
				if ( ! is_null( $photo_id ) ) {
					$avatar_image_url = $this->get_author_photo_url_by_id( $photo_id, $craft_db );
				}

				return [
					'id'               => $user['id'],
					'uid'              => $user['uid'],
					'email'            => $user['email'] ?? null,
					'username'         => $user['username'] ?? null,
					'display_name'     => $user['fullName'] ?? null,
					'first_name'       => $user['firstName'] ?? null,
					'last_name'        => $user['lastName'] ?? null,
					'bio'              => $user['userBio'] ?? null,
					'avatar_photo_id'  => $user['photoId'] ?? null,
					'avatar_image_url' => $avatar_image_url,
				];
			}
		}
		return null;
	}

	/**
	 * Get author photo URL by asset ID.
	 * 
	 * @param int   $asset_id The asset ID.
	 * @param \wpdb $craft_db The production database connection.
	 * @return string|null The author photo URL if found, null otherwise.
	 */
	public function get_author_photo_url_by_id( int $asset_id, wpdb $craft_db ): ?string {
		// Get asset row.
		$query = $craft_db->prepare(
			'SELECT id, filename, folderId, volumeId FROM assets WHERE id = %d LIMIT 1',
			$asset_id
		);
		$asset = $craft_db->get_row( $query );
		if ( ! $asset ) {
			return null;
		}
		$folder_id = $asset->folderId; // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
		$filename  = $asset->filename;
		$volume_id = $asset->volumeId; // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.

		// Walk up the volumefolders tree to build the path, but stop before the root (parentId == null).
		$segments          = [];
		$current_folder_id = $folder_id;
		while ( $current_folder_id ) {
			$folder_query = $craft_db->prepare(
				'SELECT id, parentId, name FROM volumefolders WHERE id = %d LIMIT 1',
				$current_folder_id
			);
			$folder       = $craft_db->get_row( $folder_query );
			if ( ! $folder ) {
				break;
			}
			// Stop before including the root folder (parentId == null).
			if ( is_null( $folder->parentId ) ) { // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
				break;
			}
			array_unshift( $segments, $folder->name );
			$current_folder_id = $folder->parentId; // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
		}

		// Get the root volume name for the prefix.
		$volume_query = $craft_db->prepare(
			'SELECT name FROM volumes WHERE id = %d LIMIT 1',
			$volume_id
		);
		$volume_name  = $craft_db->get_var( $volume_query );
		// Map volume name to key prefix if needed.
		$prefix = null;
		if ( 'User-Uploaded Content' === $volume_name ) {
			$prefix = 'UserContent';
		} elseif ( 'Images' === $volume_name ) {
			$prefix = 'siteNHI';
		} else {
			$prefix = $volume_name;
		}

		// Build the key.
		$key = $prefix;
		if ( ! empty( $segments ) ) {
			$key .= '/' . implode( '/', $segments );
		}
		$key .= '/' . $filename;

		$url = sprintf(
			'https://%s/%s',
			self::CDN_ASSET_HOSTNAME,
			$key
		);

		return $url;
	}

	/**
	 * Get asset image data.
	 * 
	 * @param int   $asset_id The asset ID.
	 * @param \wpdb $craft_db  The production database connection.
	 * @return array Array with asset image data with following keys. {
	 *  int 'id'               Asset ID.
	 *  int 'width'            Asset width.
	 *  int 'height'           Asset height.
	 *  string 'date_created'  Timestamp, returns in NHI timezone.
	 *  string 'url'           Public URL.
	 *  string 'filename'      File name.
	 *  string 'title'         Title field.
	 *  string 'description'   Description field.
	 *  string 'credit'        Credit field.
	 *  string 'uploader'      Uploader full name
	 * }
	 */
	public function get_asset_image_data( int $asset_id, wpdb $craft_db ): array {
		// Fetch all asset data in one go.
		$query = $craft_db->prepare(
			'SELECT a.id, a.dateCreated, a.filename, a.folderId, a.width, a.height, c.title, c.field_fieldBlurb, c.field_fieldCredit, u.fullName
			FROM assets a
			LEFT JOIN content c ON c.elementId = a.id AND c.siteId = %d
			LEFT JOIN users u ON u.id = a.uploaderId
			WHERE a.id = %d
			LIMIT 1',
			self::SITE_ID_NEW_HAVEN_INDEPENDENT,
			$asset_id
		);
		$asset = $craft_db->get_row( $query );
		if ( ! $asset ) {
			return [];
		}

		// Get the folder name (slug) from volumefolders.
		$folder_name = null;
		if ( ! empty( $asset->folderId ) ) { // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
			$folder_query = $craft_db->prepare(
				'SELECT name FROM volumefolders WHERE id = %d LIMIT 1',
				$asset->folderId // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
			);
			$folder_name  = $craft_db->get_var( $folder_query );
		}

		// Parse dateCreated to get year and month, and convert to NHI timezone.
		$date_created           = $asset->dateCreated; // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
		$date_created_converted = $this->convert_utc_to_nhi_time( $date_created, self::NHI_TIMEZONE );
		$year                   = null;
		$month                  = null;
		if ( ! empty( $date_created_converted ) ) {
			$date  = new \DateTime( $date_created_converted );
			$year  = $date->format( 'Y' );
			$month = $date->format( 'm' );
		}

		// Build the URL as per the discovered pattern.
		$url = null;
		if ( $year && $month && $folder_name && ! empty( $asset->filename ) ) {
			$url = sprintf(
				'https://%s/Images/siteNHI/%s/%s/%s/%s',
				self::CDN_ASSET_HOSTNAME,
				$year,
				$month,
				$folder_name,
				$asset->filename
			);
		}

		return [
			'id'           => $asset->id,
			'width'        => $asset->width,
			'height'       => $asset->height,
			'date_created' => $date_created_converted,
			'url'          => $url,
			'filename'     => $asset->filename,
			'title'        => $asset->title,
			'description'  => $asset->field_fieldBlurb, // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
			'credit'       => $asset->field_fieldCredit, // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
			'uploader'     => $asset->fullName, // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
		];
	}

	/**
	 * Import image from asset.
	 * 
	 * @param int     $asset_id The asset ID.
	 * @param int     $post_id  The post ID.
	 * @param int     $entry_id The entry ID.
	 * @param \wpdb   $craft_db  The production database connection.
	 * @param ?string $caption  The caption.
	 * @return int|WP_Error Attachment image ID.
	 */
	public function import_image_from_asset( int $asset_id, int $post_id, int $entry_id, \wpdb $craft_db, ?string $caption = null ): int|WP_Error {
		global $wpdb;

		// Get asset data.
		$asset_db_data = $this->get_asset_image_data( $asset_id, $craft_db );

		// URL.
		$url = $asset_db_data['url'] ?? null;
		if ( ! isset( $url ) || empty( $url ) ) {
			$this->logger->error( sprintf( "ERROR -- empty \$asset_db_data['url'] for entry ID %d, asset ID %d.", $entry_id, $asset_id ) );
			return new \WP_Error( 'empty_asset_url', sprintf( "ERROR -- empty \$asset_db_data['url'] for entry ID %d, asset ID %d.", $entry_id, $asset_id ) );
		}

		// Other asset data.
		$title       = isset( $asset_db_data['title'] ) && ! empty( $asset_db_data['title'] ) ? $asset_db_data['title'] : null;
		$description = isset( $asset_db_data['description'] ) && ! empty( $asset_db_data['description'] ) ? $asset_db_data['description'] : null;
		$credit      = isset( $asset_db_data['credit'] ) && ! empty( $asset_db_data['credit'] ) ? $asset_db_data['credit'] : null;
		$filename    = isset( $asset_db_data['filename'] ) && ! empty( $asset_db_data['filename'] ) ? $asset_db_data['filename'] : null;
		$width       = isset( $asset_db_data['width'] ) && ! empty( $asset_db_data['width'] ) ? $asset_db_data['width'] : null;
		$height      = isset( $asset_db_data['height'] ) && ! empty( $asset_db_data['height'] ) ? $asset_db_data['height'] : null;
		$uploader    = isset( $asset_db_data['uploader'] ) && ! empty( $asset_db_data['uploader'] ) ? $asset_db_data['uploader'] : null;

		// Import image.
		$attachment_id = ! $this->dry_run ? $this->attachments->import_external_file(
			$url,
			$title,
			$caption,
			$description,
			null,
			$post_id,
			[],
			$filename,
			true
		) : -1;
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Save custom metas.
		$featured_image_metas = [
			'_media_credit'                     => $credit,
			'newspack_migration_legacy_id'      => $asset_id,
			'newspack_migration_asset_url'      => $url,
			'newspack_migration_asset_uploader' => $uploader,
			'newspack_migration_asset_width'    => $width,
			'newspack_migration_asset_height'   => $height,
		];
		if ( ! $this->dry_run ) {
			foreach ( $featured_image_metas as $key => $value ) {
				update_post_meta( $attachment_id, $key, $value );
			}
		}
		
		// Update creation date (not a requirement, just an extra convenience).
		$asset_date_created = $asset_db_data['date_created'];
		if ( ! $this->dry_run ) {
			$updated = $wpdb->update( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.DirectQuery.
				$wpdb->posts,
				[
					'post_date'     => $asset_date_created,
					'post_date_gmt' => $asset_date_created,
				],
				[ 'ID' => $attachment_id ]
			);
			if ( false === $updated ) {
				$this->logger->error( sprintf( "ERROR updating post_dates '%s' for featured image ID %s : %s", $asset_date_created, $attachment_id, $wpdb->last_error ) );
			}
		}

		return $attachment_id;
	}

	/**
	 * Get tag by ID.
	 * 
	 * @param int   $tag_id The tag ID.
	 * @param \wpdb $craft_db The production database connection.
	 * @return string|null The tag name if found, null otherwise.
	 */
	public function get_tag_by_id( int $tag_id, wpdb $craft_db ): ?string {
		$query  = $craft_db->prepare(
			'SELECT title FROM content WHERE elementId = %d AND siteId = %d LIMIT 1',
			$tag_id,
			self::SITE_ID_NEW_HAVEN_INDEPENDENT
		);
		$result = $craft_db->get_var( $query );
		
		return $result ?: null; // phpcs:ignore -- Universal.Operators.DisallowShortTernary.Found.
	}

	/**
	 * Get all bylines for an entry.
	 * 
	 * @example
	 *  "matrixAuthorsByline": {
	 *      "9790742": {
	 *          "type": "blockAuthor",
	 *          ...
	 *          "fields": {
	 *              "authorLink": "{\"linkedId\":255,\"linkedSiteId\":1,\"linkedTitle\":null,\"linkedUrl\":null,\"payload\":\"{\\\"customText\\\":\\\"255\\\"}\",\"type\":\"user\"}"
	 *          }
	 *      },
	 *      "9790743": {
	 *          "type": "blockAuthor",
	 *          ...
	 *          "fields": {
	 *              "authorLink": "{\"linkedUrl\":\"Arthur Author\",\"linkedId\":null,\"linkedSiteId\":null,\"linkedTitle\":null,\"payload\":\"{\\\"customText\\\":\\\"Arthur Author\\\"}\",\"type\":\"custom\"}"
	 *          }
	 *      },
	 *      "9790744": {
	 *          "type": "blockAuthor",
	 *          ...
	 *          "fields": {
	 *              "authorLink": "{\"linkedId\":254,\"linkedSiteId\":1,\"linkedTitle\":null,\"linkedUrl\":null,\"payload\":\"{\\\"customText\\\":\\\"254\\\"}\",\"type\":\"user\"}"
	 *          }
	 *      }
	 *  }
	 * 
	 *  Two types of bylines:
	 *  - "type":"user" -- byline is in "linkedId", then $this->get_user_data( $linkedId, $users_data, $craft_db )
	 *  - "type":"custom" -- byline is in "customText"
	 *
	 * @param array $entry_data The entry data.
	 * @param array $users_data The users data.
	 * @param wpdb  $craft_db The production database connection.
	 * 
	 * @return array Array of author names and their IDs. {
	 *  ?int   'user_id' If this byline came from an existing user, this is the user ID. Otherwise, null.
	 *  string 'name'    Existing user display name or custom text byline.
	 * }
	 */
	public function get_entry_bylines( array $entry_data, array $users_data, wpdb $craft_db ): array {
		$bylines = [];
		
		// Get byline data.
		$byline_data = $entry_data['matrixAuthorsByline'] ?? null;
		if ( ! $byline_data ) {
			return [];
		}

		// Process byline data.
		foreach ( $byline_data as $byline_id => $byline_item ) {
			// Skip if not a blockAuthor.
			if ( 'blockAuthor' !== $byline_item['type'] ) {
				continue;
			}
			
			$byline_item_fields_json = $byline_item['fields']['authorLink'] ?? null;
			if ( ! $byline_item_fields_json ) {
				continue;
			}
			$byline_item_fields = json_decode( $byline_item_fields_json, true );

			$byline_name = null;
			$user_id     = null;

			/**
			 * Get byline type and name.
			 * 
			 * Byline type can be:
			 * - "user" -- byline is in "linkedId", then $this->get_user_data( $linkedId, $users_data, $craft_db )
			 * - "custom" -- byline is in "customText"
			 */
			$type = $byline_item_fields['type'] ?? null;
			if ( 'user' === $type ) {
				$user_id     = $byline_item_fields['linkedId'] ?? null;
				$byline_name = $this->get_user_data( $user_id, $users_data, $craft_db )['display_name'] ?? null;
			} elseif ( 'custom' === $type ) {
				// Try getting byline from payload.customText field.
				$payload_json = $byline_item_fields['payload'] ?? null;
				$payload      = json_decode( $payload_json, true );
				$byline_name  = $payload['customText'] ?? null;

				// Try getting byline from linkedUrl field.
				if ( is_null( $byline_name ) ) {
					if ( isset( $byline_item_fields['linkedUrl'] ) && ! empty( $byline_item_fields['linkedUrl'] ) ) {
						$byline_name = $byline_item_fields['linkedUrl'];
					}
				}
			}

			// Add byline to array if name is not null.
			if ( $byline_name ) {
				$bylines[] = [
					'user_id' => $user_id,
					'name'    => $byline_name,
				];
			}
		}

		return $bylines;
	}

	/**
	 * Get all comments for an entry.
	 *
	 * @param int  $entry_id The entry ID.
	 * @param wpdb $craft_db  The production database connection.
	 * @return array[] Array of comments, each with keys. {
	 *  int     'comment_id'          The comment ID.
	 *  ?int    'reply_to_comment_id' The ID of the comment this is a reply to.
	 *  ?null   'level'               The depth of the comment in the reply chain.
	 *  ?string 'author_name'         User display name or custom text byline.
	 *  ?null   'author_user_id'      If this comment came from an existing user, this is the user ID. Otherwise, null -- this may be called an "anonymous" user (because it's not a registered user), but it still has a display name.
	 *  string  'comment'             The comment text.
	 *  string  'comment_date'        Converted to NHI timezone.
	 *  string  'status'              The comment status.
	 *  ?int    'user_id'             The user ID.
	 *  bool    'flagged'             Whether the comment is flagged.
	 * }
	 */
	public function get_entry_comments( int $entry_id, wpdb $craft_db ): array {
		// Fetch all flagged comment IDs for this entry in one query.
		$flagged_query = $craft_db->prepare(
			'SELECT commentId FROM comments_flags WHERE commentId IN (SELECT id FROM comments_comments WHERE ownerId = %d)',
			$entry_id
		);
		$flagged_ids   = $craft_db->get_col( $flagged_query );
		$flagged_set   = array_flip( $flagged_ids ); // For fast lookup.

		// Get comments from DB.
		$comments = [];
		// The comment hierarchy (i.e., replies) is stored in the `structureelements` table using a nested set model (lft, rgt, level columns).
		// This query joins the comments with their structure information and uses a subquery to find the direct parent of each comment.
		// The results are ordered by `lft` to ensure parents are processed before their children.
		$comments_query = $craft_db->prepare(
			'SELECT
				c.id, c.name, c.comment, c.commentDate, c.status, c.userId,
				se.level,
				(
					SELECT parent.elementId
					FROM structureelements AS child
					JOIN structureelements AS parent ON child.lft > parent.lft AND child.rgt < parent.rgt AND child.level = parent.level + 1
					WHERE child.elementId = c.id
					ORDER BY parent.lft DESC
					LIMIT 1
				) AS reply_to_comment_id
			FROM
				comments_comments c
			LEFT JOIN
				structureelements se ON c.id = se.elementId
			WHERE
				c.ownerId = %d
			ORDER BY
				se.lft ASC',
			$entry_id
		);
		$comments_rows  = $craft_db->get_results( $comments_query );
		foreach ( $comments_rows as $comment_row ) {
			$author_name = null;
			
			// Get author name from user object.
			$author_user_id = $comment_row->userId ? (int) $comment_row->userId : null; // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
			if ( $author_user_id ) {
				// Try and get the value of "Screen Name" field associated with user objects.
				$screen_name_query = $craft_db->prepare(
					'SELECT field_screenName FROM content WHERE elementId = %d AND siteId = %d LIMIT 1',
					$comment_row->userId, // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
					self::SITE_ID_NEW_HAVEN_INDEPENDENT
				);
				$screen_name       = $craft_db->get_var( $screen_name_query );
				if ( ! empty( $screen_name ) ) {
					$author_name = $screen_name;
				}
				
				// If there's no Screen Name, get full name or username from users table.
				if ( is_null( $author_name ) ) {
					$user_query = $craft_db->prepare(
						'SELECT fullName, username FROM users WHERE id = %d LIMIT 1',
						$comment_row->userId // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
					);
					$user_row   = $craft_db->get_row( $user_query );
					if ( $user_row ) {
						$author_name = $user_row->fullName ? $user_row->fullName : $user_row->username; // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
					}
				}
			} else {
				// For anonymous comments, use the name field if present.
				$author_name = $comment_row->name ? $comment_row->name : null;
			}

			// Comment timestamps are in UTC, and need to be converted.
			$comment_date           = $comment_row->commentDate ? date( 'Y-m-d H:i:s', strtotime( $comment_row->commentDate ) ) : null; // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
			$comment_date_converted = ! is_null( $comment_date ) ? $this->convert_utc_to_nhi_time( $comment_date, self::NHI_TIMEZONE ) : null;

			$comments[] = [
				'comment_id'          => (int) $comment_row->id,
				'reply_to_comment_id' => $comment_row->reply_to_comment_id ? (int) $comment_row->reply_to_comment_id : null, // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
				'level'               => $comment_row->level ? (int) $comment_row->level : null,
				'author_name'         => $author_name,
				'author_user_id'      => $author_user_id,
				'comment'             => $comment_row->comment,
				'comment_date'        => $comment_date_converted,
				'status'              => $comment_row->status,
				'user_id'             => $comment_row->userId ? (int) $comment_row->userId : null, // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
				'flagged'             => isset( $flagged_set[ $comment_row->id ] ),
			];
		}
		return $comments;
	}

	/**
	 * Get the SQL query for retrieving content blocks.
	 *
	 * @param string $content_type The content type (main_content or lede).
	 * @param int    $image_asset_field_id The field ID for image assets.
	 * @return string The SQL query.
	 */
	public function get_content_blocks_query( string $content_type, int $image_asset_field_id ): string {
		$mappings   = self::FIELD_MAPPINGS[ $content_type ];
		$table_name = 'main_content' === $content_type ? 'matrixcontent_matrixmaincontent' : 'matrixcontent_matrixlede';
		
		return "SELECT 
			mb.id as block_id,
			mb.typeId,
			mbt.handle as block_type,
			-- Text block fields
			mmc.{$mappings['text_content']} as text_content,
			-- Image block fields
			mmc.{$mappings['image_content']} as image_content,
			mmc.{$mappings['image_position']} as image_position,
			mmc.{$mappings['image_width']} as image_width,
			r.targetId as image_asset_id,
			-- External Image block fields
			mmc.{$mappings['external_image_heading']} as external_image_heading,
			mmc.{$mappings['external_image_content']} as external_image_content,
			mmc.{$mappings['external_image_position']} as external_image_position,
			mmc.{$mappings['external_image_width']} as external_image_width,
			mmc.{$mappings['external_image_url']} as external_image_url,
			-- Raw HTML block fields
			mmc.{$mappings['raw_html_content']} as raw_html_content,
			-- Video block fields
			mmc.{$mappings['video_embed']} as video_embed,
			mmc.{$mappings['video_width']} as video_width,
			mmc.{$mappings['video_position']} as video_position,
			mmc.{$mappings['video_content']} as video_content,
			-- Heading block fields
			mmc.{$mappings['heading_type']} as heading_type,
			mmc.{$mappings['heading_content']} as heading_content,
			-- Poll block fields
			mmc.{$mappings['poll_position']} as poll_position,
			-- Separator block fields
			mmc.{$mappings['separator_visible']} as separator_visible,
			-- Quote block fields
			mmc.{$mappings['quote_content']} as quote_content,
			mmc.{$mappings['quote_heading']} as quote_heading,
			-- Graphic block fields
			mmc.{$mappings['graphic_position']} as graphic_position,
			mmc.{$mappings['graphic_width']} as graphic_width,
			mmc.{$mappings['graphic_custom_width']} as graphic_custom_width
		FROM matrixblocks mb 
		JOIN matrixblocktypes mbt ON mb.typeId = mbt.id 
		JOIN {$table_name} mmc ON mb.id = mmc.elementId 
		LEFT JOIN relations r ON r.sourceId = mb.id AND r.fieldId = %d
		WHERE mb.primaryOwnerId = %d 
		ORDER BY mb.id";
	}

	/**
	 * Process a content block based on its type.
	 *
	 * @param object $block The block data from the database.
	 * @param \wpdb  $craft_db The production database connection.
	 * @return array The processed block data.
	 */
	public function process_content_block( object $block, $craft_db ): array {
		$content_block = [
			'id'   => $block->block_id,
			'type' => $block->block_type,
		];
		
		switch ( $block->block_type ) {
			case 'blockText':
				$content_block['content'] = $block->text_content;
				break;
				
			case 'blockImage':
				$content_block['content']  = $block->image_content;
				$content_block['position'] = $block->image_position;
				$content_block['width']    = $block->image_width;
				$content_block['asset_id'] = $block->image_asset_id;
				
				// Get the asset details.
				if ( $block->image_asset_id ) {
					$asset_query = $craft_db->prepare(
						'SELECT filename, dateCreated FROM assets WHERE id = %d',
						$block->image_asset_id
					);
					$asset       = $craft_db->get_row( $asset_query );
					
					if ( $asset ) {
						// Add the filename.
						$content_block['file_name'] = $asset->filename;
						
						// Construct the public URL using the CDN pattern.
						$content_block['public_url'] = sprintf(
							'https://%s/Images/siteNHI/%s',
							self::CDN_ASSET_HOSTNAME,
							$asset->filename
						);
						
						// Set image parameters based on asset ID.
						$height   = 1439; // default height.
						$position = 'right top'; // default position.
						
						if ( 11903557 === $block->image_asset_id ) {
							$height   = 1339;
							$position = 'left top';
						}
						
						// Get year and month from dateCreated.
						$date  = new \DateTime( $asset->dateCreated ); // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
						$date_converted = $this->convert_utc_to_nhi_time( $date->format( 'Y-m-d H:i:s' ), self::NHI_TIMEZONE );
						$year           = $date_converted->format( 'Y' );
						$month          = $date_converted->format( 'm' );
						
						// Construct the full path for the key.
						$key_path = sprintf( 'Images/siteNHI/%s/%s/Staff/%s', $year, $month, $asset->filename );
						
						// Construct the alternative public URL with transformations.
						$transform_params = [
							'bucket' => 'ojp-content',
							'key'    => $key_path,
							'edits'  => [
								'jpeg'    => [
									'quality'             => 100,
									'progressive'         => true,
									'trellisQuantisation' => true,
									'overshootDeringing'  => true,
									'optimizeScans'       => true,
								],
								'resize'  => [
									'width'    => 1984,
									'height'   => $height,
									'fit'      => 'cover',
									'position' => $position,
								],
								'sharpen' => true,
							],
						];
						
						// Use JSON_UNESCAPED_SLASHES to prevent escaping of forward slashes.
						$content_block['public_url2'] = sprintf(
							'https://d1zrh1jysedyjz.cloudfront.net/%s',
							base64_encode( wp_json_encode( $transform_params, JSON_UNESCAPED_SLASHES ) )
						);
					}
				}
				
				// The caption is stored in the image_content field.
				if ( $block->image_content ) {
					$content_block['caption'] = $block->image_content;
				}
				break;
				
			case 'blockExternalImage':
				$content_block['heading']  = $block->external_image_heading;
				$content_block['content']  = $block->external_image_content;
				$content_block['position'] = $block->external_image_position;
				$content_block['width']    = $block->external_image_width;
				$content_block['url']      = $block->external_image_url;
				break;
				
			case 'blockRawHTML':
				$content_block['content'] = $block->raw_html_content;
				break;
				
			case 'blockVideo':
				$content_block['embed']    = $block->video_embed;
				$content_block['width']    = $block->video_width;
				$content_block['position'] = $block->video_position;
				$content_block['content']  = $block->video_content;
				break;
				
			case 'blockHeading':
				$content_block['type']    = $block->heading_type;
				$content_block['content'] = $block->heading_content;
				break;
				
			case 'blockPoll':
				$content_block['position'] = $block->poll_position;
				break;
				
			case 'blockSeparator':
				$content_block['visible'] = $block->separator_visible;
				break;
				
			case 'blockQuote':
				$content_block['content'] = $block->quote_content;
				$content_block['heading'] = $block->quote_heading;
				break;
				
			case 'blockGraphic':
				$content_block['position']     = $block->graphic_position;
				$content_block['width']        = $block->graphic_width;
				$content_block['custom_width'] = $block->graphic_custom_width;
				break;
		}
		
		// Remove null values, but keep 0 values.
		return array_filter(
			$content_block,
			function ( $value ) {
				return null !== $value && '' !== $value;
			}
		);
	}

	/**
	 * Get content blocks from a specific content type.
	 *
	 * @param int    $article_id The article ID.
	 * @param \wpdb  $craft_db The production database connection.
	 * @param string $content_type The content type (main_content or lede).
	 * @param int    $image_asset_field_id The field ID for image assets.
	 * @return array The content blocks.
	 */
	public function get_content_blocks( int $article_id, $craft_db, string $content_type, int $image_asset_field_id ): array {
		$query = $craft_db->prepare(
			$this->get_content_blocks_query( $content_type, $image_asset_field_id ),
			$image_asset_field_id,
			$article_id
		);
		
		$results = $craft_db->get_results( $query );
		
		$content_blocks = [];
		foreach ( $results as $block ) {
			$content_blocks[] = $this->process_content_block( $block, $craft_db );
		}
		
		return $content_blocks;
	}

	/**
	 * Get the main content for an article.
	 *
	 * @param int   $article_id The article ID.
	 * @param \wpdb $craft_db The production database connection.
	 * @return array The main content.
	 */
	public function get_article_main_content( int $article_id, $craft_db ): array {
		$query = $craft_db->prepare(
			'SELECT 
				mb.id as block_id,
				mb.typeId,
				mbt.handle as block_type,
				-- Text block fields
				mmc.field_blockText_itemContent as text_content,
				-- Image block fields
				mmc.field_blockImage_itemContent as image_content,
				mmc.field_blockImage_itemPosition as image_position,
				mmc.field_blockImage_itemWidth as image_width,
				r.targetId as image_asset_id,
				-- External Image block fields
				mmc.field_blockExternalImage_itemHeading as external_image_heading,
				mmc.field_blockExternalImage_itemContent as external_image_content,
				mmc.field_blockExternalImage_itemPosition as external_image_position,
				mmc.field_blockExternalImage_itemWidth as external_image_width,
				mmc.field_blockExternalImage_itemURL as external_image_url,
				-- Raw HTML block fields
				mmc.field_blockRawHTML_itemContent as raw_html_content,
				-- Video block fields
				mmc.field_blockVideo_itemVideoEmbed as video_embed,
				mmc.field_blockVideo_itemWidth as video_width,
				mmc.field_blockVideo_itemPosition as video_position,
				mmc.field_blockVideo_itemContent as video_content,
				-- Heading block fields
				mmc.field_blockHeading_itemType as heading_type,
				mmc.field_blockHeading_itemHeading as heading_content,
				-- Poll block fields
				mmc.field_blockPoll_itemPosition as poll_position,
				-- Separator block fields
				mmc.field_blockSeparator_itemIsVisible as separator_visible,
				-- Quote block fields
				mmc.field_blockQuote_itemContent as quote_content,
				mmc.field_blockQuote_itemHeading as quote_heading,
				-- Graphic block fields
				mmc.field_blockGraphic_itemPosition as graphic_position,
				mmc.field_blockGraphic_itemWidth as graphic_width,
				mmc.field_blockGraphic_itemCustomWidth as graphic_custom_width
			FROM matrixblocks mb 
			JOIN matrixblocktypes mbt ON mb.typeId = mbt.id 
			JOIN matrixcontent_matrixmaincontent mmc ON mb.id = mmc.elementId 
			LEFT JOIN relations r ON r.sourceId = mb.id AND r.fieldId = 107
			WHERE mb.primaryOwnerId = %d 
			ORDER BY mb.id',
			$article_id
		);
		
		$results = $craft_db->get_results( $query );
		
		$content_blocks = [];
		foreach ( $results as $block ) {
			$content_blocks[] = $this->process_content_block( $block, $craft_db );
		}
		
		return $content_blocks;
	}

	/**
	 * Get the lede content for an article.
	 *
	 * @param int   $article_id The article ID.
	 * @param \wpdb $craft_db The production database connection.
	 * @return array The lede content.
	 */
	public function get_article_lede( int $article_id, $craft_db ): array {
		$query = $craft_db->prepare(
			'SELECT 
				mb.id as block_id,
				mb.typeId,
				mbt.handle as block_type,
				-- Text block fields
				mmc.field_blockText_itemContent as text_content,
				-- Image block fields
				mmc.field_blockImage_itemContent as image_content,
				mmc.field_blockImage_itemPosition as image_position,
				mmc.field_blockImage_itemWidth as image_width,
				r.targetId as image_asset_id,
				-- External Image block fields
				mmc.field_blockExternalImage_itemHeading as external_image_heading,
				mmc.field_blockExternalImage_itemContent as external_image_content,
				mmc.field_blockExternalImage_itemPosition as external_image_position,
				mmc.field_blockExternalImage_itemWidth as external_image_width,
				mmc.field_blockExternalImage_itemURL as external_image_url,
				-- Raw HTML block fields
				mmc.field_blockRawHTML_itemContent as raw_html_content,
				-- Video block fields
				mmc.field_blockVideo_itemVideoEmbed as video_embed,
				mmc.field_blockVideo_itemWidth as video_width,
				mmc.field_blockVideo_itemPosition as video_position,
				mmc.field_blockVideo_itemContent as video_content,
				-- Heading block fields
				mmc.field_blockHeading_itemType_epxdfidq as heading_type,
				mmc.field_blockHeading_itemHeading_rabumset as heading_content,
				-- Poll block fields
				mmc.field_blockPoll_itemPosition as poll_position,
				-- Separator block fields
				mmc.field_blockSeparator_itemIsVisible as separator_visible,
				-- Quote block fields
				mmc.field_blockQuote_itemContent as quote_content,
				mmc.field_blockQuote_itemHeading as quote_heading,
				-- Graphic block fields
				mmc.field_blockGraphic_itemPosition as graphic_position,
				mmc.field_blockGraphic_itemWidth as graphic_width,
				mmc.field_blockGraphic_itemCustomWidth as graphic_custom_width
			FROM matrixblocks mb 
			JOIN matrixblocktypes mbt ON mb.typeId = mbt.id 
			JOIN matrixcontent_matrixlede mmc ON mb.id = mmc.elementId 
			LEFT JOIN relations r ON r.sourceId = mb.id AND r.fieldId = 95
			WHERE mb.primaryOwnerId = %d 
			ORDER BY mb.id',
			$article_id
		);
		
		$results = $craft_db->get_results( $query );
		
		$lede_blocks = [];
		foreach ( $results as $block ) {
			$lede_blocks[] = $this->process_content_block( $block, $craft_db );
		}
		
		return $lede_blocks;
	}

	/**
	 * Convert server timestamp in UTC to the NHI (Connecticut) timezone, returning MySQL format.
	 *
	 * @param string $timestamp UTC timestamp (e.g. from DB).
	 * @param string $timezone  Target timezone (e.g. self::NHI_TIMEZONE).
	 * @return string Converted timestamp in 'Y-m-d H:i:s' format
	 */
	public function convert_utc_to_nhi_time( string $timestamp, string $timezone ): string {
		if ( ! $timestamp ) {
			return null;
		}

		// Server time is UTC.
		$dt = new \DateTime( $timestamp, new \DateTimeZone( 'UTC' ) );
		$dt->setTimezone( new \DateTimeZone( $timezone ) );

		return $dt->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Database mapping helper function.
	 * 
	 * @deprecated Use `get_asset_image_data` instead.
	 * @see get_asset_image_data
	 * 
	 * @param int   $asset_id Asset ID.
	 * @param \wpdb $craft_db Production database connection.
	 * @return string|null Image URL.
	 */
	public function get_asset_image_url( int $asset_id, wpdb $craft_db ): ?string {
		// Query the asset info from the prod db.
		$query = $craft_db->prepare(
			'SELECT filename, dateCreated, folderId FROM assets WHERE id = %d LIMIT 1',
			$asset_id
		);
		$asset = $craft_db->get_row( $query );

		if ( ! $asset || empty( $asset->filename ) || empty( $asset->dateCreated ) || empty( $asset->folderId ) ) { // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
			return null;
		}

		// Get the folder name (slug) from volumefolders.
		$folder_query = $craft_db->prepare(
			'SELECT name FROM volumefolders WHERE id = %d LIMIT 1',
			$asset->folderId // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
		);
		$folder_name  = $craft_db->get_var( $folder_query );

		if ( empty( $folder_name ) ) {
			return null;
		}

		// Parse dateCreated to get year and month.
		$date           = new \DateTime( $asset->dateCreated ); // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
		$date_converted = $this->convert_utc_to_nhi_time( $date->format( 'Y-m-d H:i:s' ), self::NHI_TIMEZONE );
		$year           = $date_converted->format( 'Y' );
		$month          = $date_converted->format( 'm' );

		// Build the URL as per the discovered pattern.
		$url = sprintf(
			'https://%s/Images/siteNHI/%s/%s/%s/%s',
			self::CDN_ASSET_HOSTNAME,
			$year,
			$month,
			$folder_name,
			$asset->filename
		);

		return $url;
	}

	/**
	 * Database mapping helper function.
	 * 
	 * @deprecated Use `get_asset_image_data` instead.
	 * @see get_asset_image_data
	 * 
	 * @param int   $asset_id Asset ID.
	 * @param \wpdb $craft_db Production database connection.
	 * @return string|null Image title.
	 */
	public function get_asset_image_title( int $asset_id, wpdb $craft_db ): ?string {
		$site_id = self::SITE_ID_NEW_HAVEN_INDEPENDENT;
		// Fetch the image title from the content table.
		$query = $craft_db->prepare(
			'SELECT title FROM content WHERE elementId = %d AND siteId = %d LIMIT 1',
			$asset_id,
			$site_id
		);
		$title = $craft_db->get_var( $query );
		return $title ?: null; // phpcs:ignore -- Universal.Operators.DisallowShortTernary.Found.
	}
	
	/**
	 * Database mapping helper function.
	 * 
	 * @deprecated Use `get_asset_image_data` instead.
	 * @see get_asset_image_data
	 * 
	 * @param int   $asset_id Asset ID.
	 * @param \wpdb $craft_db Production database connection.
	 * @return string|null Image description.
	 */
	public function get_asset_image_description( int $asset_id, wpdb $craft_db ): ?string {
		$site_id = self::SITE_ID_NEW_HAVEN_INDEPENDENT;
		// Fetch the image description from the content table.
		$query       = $craft_db->prepare(
			'SELECT field_fieldBlurb FROM content WHERE elementId = %d AND siteId = %d LIMIT 1',
			$asset_id,
			$site_id
		);
		$description = $craft_db->get_var( $query );
		return $description ?: null; // phpcs:ignore -- Universal.Operators.DisallowShortTernary.Found.
	}

	/**
	 * Database mapping helper function.
	 * 
	 * @deprecated Use `get_asset_image_data` instead.
	 * @see get_asset_image_data
	 * 
	 * @param int   $asset_id Asset ID.
	 * @param \wpdb $craft_db Production database connection.
	 * @return string|null Photo credit.
	 */
	public function get_asset_image_photo_credit( int $asset_id, wpdb $craft_db ): ?string {
		$site_id = self::SITE_ID_NEW_HAVEN_INDEPENDENT;
		// Fetch the photo credit from the content table.
		$query  = $craft_db->prepare(
			'SELECT field_fieldCredit FROM content WHERE elementId = %d AND siteId = %d LIMIT 1',
			$asset_id,
			$site_id
		);
		$credit = $craft_db->get_var( $query );
		return $credit ?: null; // phpcs:ignore -- Universal.Operators.DisallowShortTernary.Found.
	}
	
	/**
	 * Database mapping helper function.
	 * 
	 * @deprecated Use `get_asset_image_data` instead.
	 * @see get_asset_image_data
	 * 
	 * @param int   $asset_id Asset ID.
	 * @param \wpdb $craft_db Production database connection.
	 * @return string|null Uploader's full name.
	 */
	public function get_asset_image_uploader( int $asset_id, wpdb $craft_db ): ?string {

		// Fetch the uploader's full name.
		$image_uploader_full_name = null;
		$uploader_query           = $craft_db->prepare(
			'SELECT fullName FROM users WHERE id = (SELECT uploaderId FROM assets WHERE id = %d LIMIT 1) LIMIT 1',
			$asset_id
		);
		$image_uploader_full_name = $craft_db->get_var( $uploader_query );

		return $image_uploader_full_name;
	}
}
