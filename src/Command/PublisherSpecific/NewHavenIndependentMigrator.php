<?php
/**
 * Importer for New Haven Independent .
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Taxonomy;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;
use wpdb;

class NewHavenIndependentMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * Connecticut timezone for NHI (Eastern Time, handles DST automatically).
	 */
	public const NHI_TIMEZONE = 'America/New_York';

	/**
	 * All possible entry types in the prod DB ("fieldPreparsedEntryType").
	 */
	public const ENTRY_TYPES = [
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
	public const ENTRY_STATUSES_TO_POST_STATUSES = [
		'disabled' => 'draft',
		'expired'  => 'draft',
		'live'     => 'publish',
	];

	/**
	 * All possible comment status values in the prod db.
	 */
	public const COMMENT_STATUSES = [
		'approved',
		'trashed',
		'spam',
	];

	/**
	 * CDN asset hostname.
	 */
	public const CDN_ASSET_HOSTNAME = 'd2f1dfnoetc03v.cloudfront.net';

	/**
	 * Field mappings for different content types.
	 */
	private const FIELD_MAPPINGS = [
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
	 * Site ID for New Haven Independent.
	 */
	private const SITE_ID_NEW_HAVEN_INDEPENDENT = 1;

	/**
	 * Taxonomy logic.
	 *
	 * @var Taxonomy $taxonomy_logic The taxonomy logic.
	 */
	private $taxonomy_logic;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->taxonomy_logic = new Taxonomy();
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
						'type'     => 'assoc',
						'name'     => 'prod-db-name',
						'optional' => false,
					],
					[
						'type'     => 'assoc',
						'name'     => 'prod-db-user',
						'optional' => false,
					],
					[
						'type'     => 'assoc',
						'name'     => 'prod-db-pass',
						'optional' => false,
					],
					[
						'type'     => 'assoc',
						'name'     => 'prod-db-host',
						'optional' => false,
					],
					[
						'type'     => 'assoc',
						'name'     => 'prod-db-port',
						'optional' => false,
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
						'type'     => 'assoc',
						'name'     => 'prod-db-name',
						'optional' => false,
					],
					[
						'type'     => 'assoc',
						'name'     => 'prod-db-user',
						'optional' => false,
					],
					[
						'type'     => 'assoc',
						'name'     => 'prod-db-pass',
						'optional' => false,
					],
					[
						'type'     => 'assoc',
						'name'     => 'prod-db-host',
						'optional' => false,
					],
					[
						'type'     => 'assoc',
						'name'     => 'prod-db-port',
						'optional' => false,
					],

				],
			]
		);
	}

	/**
	 * Get a custom database connection.
	 *
	 * @param string $db_name The database name.
	 * @param string $db_user The database user.
	 * @param string $db_pass The database password.
	 * @param string $db_host The database host.
	 * @param string $db_port The database port.
	 * @return \wpdb|null The database connection or null if the connection fails.
	 */
	private function get_db_connection( string $db_name, string $db_user, string $db_pass, string $db_host, string $db_port ) {
		$new_db = new \wpdb( $db_user, $db_pass, $db_name, $db_host, $db_port );
		if ( ! empty( $new_db->last_error ) ) {
			return null;
		}

		return $new_db;
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
		$prod_db_name                       = $assoc_args['prod-db-name'];
		$prod_db_user                       = $assoc_args['prod-db-user'];
		$prod_db_pass                       = $assoc_args['prod-db-pass'];
		$prod_db_host                       = $assoc_args['prod-db-host'];
		$prod_db_port                       = $assoc_args['prod-db-port'];
		$entries_jsons_folder               = $assoc_args['json-expanded-entries-folder'];
		$users_json_file                    = $assoc_args['json-expanded-users'];
		$categories_news_expanded_json_file = $assoc_args['json-expanded-categories-news-sections'];
		
		$prod_db = $this->get_db_connection( $prod_db_name, $prod_db_user, $prod_db_pass, $prod_db_host, $prod_db_port );

		// phpcs:disable -- temporary dev code.

		// Test Delauro entry.
		$entries_json_file = '/Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/entries_delauroBringsBack_expanded.json';
		$users_data = json_decode( file_get_contents( $users_json_file ), true );
		$entry_data = json_decode( file_get_contents( $entries_json_file ), true );
		$entry      = $entry_data[0];
		// $entry_id = 11903505; // this example has a featured image.

		WP_CLI::print_value( '--- VALIDATION TEST: ARE ENTRY IDS UNIQUE IN ALL JSON FILES?  -----------------------------' );
		
		// Get all JSON files from folder (descending order).
		$entries_json_files = $this->get_json_entries_files_descending( $entries_jsons_folder );
		foreach ( $entries_json_files as $entries_json_file ) {
			$entries = $this->get_entries_from_json_file_descending( $entries_json_file );
		}
		
		exit;

		WP_CLI::print_value( '--- EXTRACT ENTRIES INTO SINGLE JSON FILE  -----------------------------' );
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
			//blockSeparator.
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
		$path_single_json_entries = '/Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/automated_manual_exports/content_and_lede_blocktypes_IDS/0_demo_entries.json';
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
					// $entries_picked_data[] = $entry;
					$entries_picked_data[ $entry['id'] ][] = $entry;
				}
			}
		}
		WP_CLI::print_value( '--- $entry_ids: ' . count($entry_ids) );
		WP_CLI::print_value( '--- $entries_picked_data: ' . count($entries_picked_data) );
exit;
		if ( file_exists( $path_single_json_entries ) ) {
			unlink( $path_single_json_entries );
		}
		file_put_contents( $path_single_json_entries, json_encode( $entries_picked_data, JSON_PRETTY_PRINT ) );
		exit;

		
		WP_CLI::print_value( '--- GET ALL CONTENT BLOCK TYPES JSONS and ENTRY IDS WHICH USE THEM  -----------------------------' );
		// Extract all entry IDs available in JSONs.
		$folder_to_entries_jsons = '/Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/automated_manual_exports/puppeteer-automation/downloaded_entities';
		$entries_json_files = glob( $folder_to_entries_jsons . '/*.json' );
		$folder_to_save_logs = '/Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/automated_manual_exports/content_and_lede_blocktypes';
		foreach ( $entries_json_files as $entries_json_file ) {
			$entries_file_data = json_decode( file_get_contents( $entries_json_file ), true );
			if ( ! is_array( $entries_file_data ) ) {
				continue;
			}
			foreach ( $entries_file_data as $entry ) {
				if ( isset( $entry['matrixTeaser'] ) && ! empty( $entry['matrixTeaser'] ) ) {
					foreach ( $entry['matrixTeaser'] as $teaser_block ) {
						// Save each matrixTeaser block type file with entry IDs which use it.
						file_put_contents(
							$folder_to_save_logs . '/' . sprintf( 'matrixTeaser_%s.csv', $teaser_block['type'] ),
							$entry['id'] . "\n",
							FILE_APPEND
						);
					}
				}
				if ( isset( $entry['matrixLede'] ) && ! empty( $entry['matrixLede'] ) ) {
					foreach ( $entry['matrixLede'] as $teaser_block ) {
						// Save each matrixTeaser block type file with entry IDs which use it.
						file_put_contents(
							$folder_to_save_logs . '/' . sprintf( 'matrixLede_%s.csv', $teaser_block['type'] ),
							$entry['id'] . "\n",
							FILE_APPEND
						);
					}
				}
				if ( isset( $entry['matrixMainContent'] ) && ! empty( $entry['matrixMainContent'] ) ) {
					foreach ( $entry['matrixMainContent'] as $teaser_block ) {
						// Save each matrixTeaser block type file with entry IDs which use it.
						file_put_contents(
							$folder_to_save_logs . '/' . sprintf( 'matrixMainContent_%s.csv', $teaser_block['type'] ),
							$entry['id'] . "\n",
							FILE_APPEND
						);
					}
				}
			}
		}
		exit;

		WP_CLI::print_value( '--- GET ALL ENTRY IDs FROM JSONS  -----------------------------' );
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

		WP_CLI::print_value( '--- COMMENTS  -----------------------------' );
		// $comments = $this->get_entry_comments( $entry['id'], $prod_db );
		// $entry_id = 8220233; // this example has comments by "anonymous" users, meaning it will have a display name, but not user_id because it's not a registered user.
		// $comments = $this->get_entry_comments( $entry_id, $prod_db );
		$entry_id = 8282206; // this example has flagged comment
		$comments = $this->get_entry_comments( $entry_id, $prod_db );
		var_dump( $comments );
		foreach ( $comments as $comment ) {
			if ( false !== strpos( $comment['comment'], 'If anyone is interested in learnin' ) ) {
				// $comment_date_converted = $this->convert_server_time_to_nhi_time( $comment['comment_date'], self::NHI_TIMEZONE );
				// var_dump( $comment_date_converted );
				WP_CLI::print_value( 'comment_date server: ' . $comment['comment_date'] );
				// WP_CLI::print_value( 'comment_date converted: ' . $comment_date_converted );
			}
		}
		exit;

		WP_CLI::print_value( '--- COMMENTS  -----------------------------' );
		// $comments = $this->get_entry_comments( $entry['id'], $prod_db );
		// $entry_id = 8220233; // this example has comments by "anonymous" users, meaning it will have a display name, but not user_id because it's not a registered user.
		// $comments = $this->get_entry_comments( $entry_id, $prod_db );
		$entry_id = 8282206; // this example has flagged comment
		$comments = $this->get_entry_comments( $entry_id, $prod_db );
		var_dump( $comments );
		foreach ( $comments as $comment ) {
			if ( false !== strpos( $comment['comment'], 'If anyone is interested in learnin' ) ) {
				// $comment_date_converted = $this->convert_server_time_to_nhi_time( $comment['comment_date'], self::NHI_TIMEZONE );
				// var_dump( $comment_date_converted );
				WP_CLI::print_value( 'comment_date server: ' . $comment['comment_date'] );
				// WP_CLI::print_value( 'comment_date converted: ' . $comment_date_converted );
			}
		}
		exit;

		exit;
		WP_CLI::print_value( '--- BYLINES  -----------------------------' );
		$entry_id        = 145569; // multiple bylines
		$test_entry_json = '/Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/automated_manual_exports/puppeteer-automation/downloaded_entities/entries_p251.json';
		$test_data       = json_decode( file_get_contents( $test_entry_json ), true );
		$entry_data      = null;
		foreach ( $test_data as $entry ) {
			if ( $entry['id'] === $entry_id ) {
				$entry_data = $entry;
				break;
			}
		}
		$bylines = $this->get_entry_bylines( $entry_id, $entry_data, $users_data, $prod_db );
		var_dump( $bylines );
		exit;

		WP_CLI::print_value( '--- LEDE FEATURED IMAGE  -----------------------------' );
		/**
		 * Featured image data is located in two places in Craft CMS:
		 *  - asset image object itself has (e.g. https://www.newhavenindependent.org/admin/assets/edit/11903556-delauro1?site=siteNHI):
		 *    => this info is retrieved by `get_asset_image_data`:
		 *      asset "id"                  => postmeta "newspack_migration_asset_id"
		 *      asset "url"                 => postmeta "newspack_migration_asset_url"
		 *                                  => Attachment "slug"
		 *      asset "date_created"        => Attachment date_created
		 *      asset "filename"            => Attachment "newspack_migration_asset_filename"
		 *      asset "Title"               => Attachment "Title"
		 *      asset "Credit"              => Attachment "Credit"
		 *      asset "Description"         => Attachment "Description"
		 *      asset "Uploader"            => postmetameta "newspack_migration_asset_uploader"
		 *      asset "width"               => postmeta "newspack_migration_asset_width"
		 *      asset "height"              => postmeta "newspack_migration_asset_height"
		 *  - lede ("excerpt") blockImage component also has ( e.g. https://www.newhavenindependent.org/admin/entries/sectionArticles/11903505-ethans_law?site=siteNHI#tab02--content):
		 *    => this info is retrieved by `_________`:
		 *      lede "Photo Caption"        => Attachment "Caption"
		 */

		$author_id   = $entry['authorId'] ?? null;
		$author_id   = 58453; // e.g. "Brian Slattery" has avatar image and bio.
		$author_data = $this->get_user_data( $author_id, $users_data, $prod_db );
		var_dump( $author_data );
		exit;
		$photo_id  = $author_data['avatar_photo_id'] ?? null;
		$photo_url = $this->get_author_photo_url_by_id( $photo_id, $prod_db );
		WP_CLI::print_value( sprintf( 'photo_id: %s photo_url: %s', $photo_id, $photo_url ) );
		exit;

		$asset_data         = $this->get_matrixLede_itemAsset_data( $entry );
		$asset_id           = $asset_data['id'];
		$asset_item_content = $asset_data['itemContent'];

		// $asset_id = 11903556; // Delauro1 https://www.newhavenindependent.org/admin/assets/edit/11903556-delauro1?site=siteNHI
		// $asset_id = 9555531; // https://www.newhavenindependent.org/admin/assets/edit/9555531-JR-Whirl-Pak_2022-01-03-012346_dxav?site=siteNHI
		// $asset_id = 10476150; // https://www.newhavenindependent.org/admin/assets/edit/10476150-Orange-tikka?site=siteNHI

		$asset_data = $this->get_asset_image_data( $asset_id, $prod_db );
		WP_CLI::print_value( '---> from asset data' );
		WP_CLI::print_value( 'asset_id: ' . $asset_data['id'] );
		WP_CLI::print_value( 'asset_date_created: ' . $asset_data['date_created'] );
		WP_CLI::print_value( 'asset_url: ' . $asset_data['url'] );
		WP_CLI::print_value( 'asset_filename: ' . $asset_data['filename'] );
		WP_CLI::print_value( 'asset_title: ' . $asset_data['title'] );
		WP_CLI::print_value( 'asset_description: ' . $asset_data['description'] );
		WP_CLI::print_value( 'asset_credit: ' . $asset_data['credit'] );
		WP_CLI::print_value( 'asset_uploader: ' . $asset_data['uploader'] );
		WP_CLI::print_value( 'asset_width: ' . $asset_data['width'] );
		WP_CLI::print_value( 'asset_height: ' . $asset_data['height'] );
		WP_CLI::print_value( '---> from matrixLede_itemAsset_data' );
		WP_CLI::print_value( 'asset_item_content: ' . $asset_item_content );

		exit;
		WP_CLI::print_value( '--- TEST IMAGE DATA -----------------------------' );
		$asset_data = $this->get_asset_image_data( $asset_id, $prod_db );
		var_dump( $asset_data );
		$asset_data = $this->get_asset_image_data( $asset_id, $prod_db );
		var_dump( $asset_data );
		$asset_data = $this->get_asset_image_data( $asset_id, $prod_db );
		var_dump( $asset_data );
		exit;

		// phpcs:enable
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
		$prod_db_name                       = $assoc_args['prod-db-name'];
		$prod_db_user                       = $assoc_args['prod-db-user'];
		$prod_db_pass                       = $assoc_args['prod-db-pass'];
		$prod_db_host                       = $assoc_args['prod-db-host'];
		$prod_db_port                       = $assoc_args['prod-db-port'];
		$entries_jsons_folder               = $assoc_args['json-expanded-entries-folder'];
		$users_json_file                    = $assoc_args['json-expanded-users'];
		$categories_news_expanded_json_file = $assoc_args['json-expanded-categories-news-sections'];
		
		// Get Craft CMS database connection.
		$prod_db = $this->get_db_connection( $prod_db_name, $prod_db_user, $prod_db_pass, $prod_db_host, $prod_db_port );

		// Get users data.
		$users_data = json_decode( file_get_contents( $users_json_file ), true ); // phpcs:ignore -- WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown.
		if ( ! is_array( $users_data ) ) {
			WP_CLI::error( sprintf( 'ERROR reading JSON file %s : %s is not an array', $users_json_file, $users_data ) );
		}

		// Get categories data.
		$sections_data = json_decode( file_get_contents( $categories_news_expanded_json_file ), true ); // phpcs:ignore -- WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown.
		if ( ! is_array( $sections_data ) ) {
			WP_CLI::error( sprintf( 'ERROR reading JSON file %s : %s is not an array', $categories_news_expanded_json_file, $sections_data ) );
		}

		// Import entries.
		// Get all JSON files from folder (descending order of date created).
		$entries_json_files = $this->get_json_entries_files_descending( $entries_jsons_folder );
		foreach ( $entries_json_files as $entries_json_file ) {
			$entries = $this->get_entries_from_json_file_descending( $entries_json_file );
			
			$entries = $this->get_entries_from_json_file_descending( '/Users/ivanuravic/www/newhavenindependent/app/public/00_initialJsonBuiltinExport/entries_eg_withChildCat_expanded.json' );

			foreach ( $entries as $entry ) {
				
				// Clear post data.
				$post_data = [];
				$postmetas = [];

				/**
				 * Post data.
				 */
				$post_data['title'] = $entry['title'];
				$post_data['url']   = $entry['url'];
				// Dates are in ISO 8601 and in UTC, convert to NHI timezone.
				$date_created               = new \DateTime( $entry['postDate'] );
				$date_created_timestamp     = $date_created->format( 'Y-m-d H:i:s' );
				$date_created_converted     = $this->convert_server_time_to_nhi_time( $date_created_timestamp, self::NHI_TIMEZONE );
				$post_data['post_date']     = $date_created_converted;
				$date_modified              = new \DateTime( $entry['dateUpdated'] );
				$date_modified_timestamp    = $date_modified->format( 'Y-m-d H:i:s' );
				$date_modified_converted    = $this->convert_server_time_to_nhi_time( $date_modified_timestamp, self::NHI_TIMEZONE );
				$post_data['post_modified'] = $date_modified_converted;
				// Check entry status.
				if ( ! isset( self::ENTRY_STATUSES_TO_POST_STATUSES[ $entry['status'] ] ) ) {
					WP_CLI::warning( sprintf( 'ERROR inserting post %s : status %s is not defined', $post_data['title'], $entry['status'] ) );
					continue;
				}
				$post_data['post_status']    = self::ENTRY_STATUSES_TO_POST_STATUSES[ $entry['status'] ];
				$post_data['comment_status'] = isset( $entry['fieldComment']['commentEnabled'] ) && true === $entry['fieldComment']['commentEnabled'] ? 'open' : 'closed';

				// TODO $post_data['post_content'];
				// TODO $post_data['post_excerpt'];
				
				/**
				 * Categories.
				 */
				foreach ( $entry['fieldSections'] as $field_section_id ) { // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
					$category_id               = $this->get_category_from_fieldSection( $field_section_id, $sections_data );
					$post_data['categories'][] = $category_id;
				}
				// Add entry type subcategory migration visibility.
				$entry_type_category_id    = $this->get_entry_type_category( $entry['fieldPreparsedEntryType'] );
				$post_data['categories'][] = $entry_type_category_id;
				// continue;                
				exit;
	
				/**
				 * Tags.
				 */
				foreach ( $entry['fieldTags'] as $fieldTag_data ) { // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
					$tag_name            = $this->get_tag_by_id( $fieldTag_data['tagId'], $prod_db ); // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
					$post_data['tags'][] = $tag_name;
				}
	
	
				/**
				 * Featured image.
				 * 
				 * Featured image data is located in two places in Craft CMS:
				 * 1. asset image object itself has (e.g. https://www.newhavenindependent.org/admin/assets/edit/11903556-delauro1?site=siteNHI):
				 *    => this info is retrieved by `get_asset_image_data`:
				 *      asset "id"                  => postmeta "newspack_migration_asset_id"
				 *      asset "url"                 => postmeta "newspack_migration_asset_url"
				 *                                  => Attachment "slug"
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
				 */
				// Featured image import data.
				$featured_image       = [
					'url'                 => null,
					'alt'                 => null,
					'title'               => null,
					'caption'             => null,
					'description'         => null,
					'credit'              => null,
					'credit_url'          => null,
					'credit_organization' => null,
				];
				$featured_image_metas = [
					'newspack_migration_legacy_id' => null,
				];
				// Get featured image data.
				$asset_data         = $this->get_matrixLede_itemAsset_data( $entry );
				$asset_id           = $asset_data['id'];
				$asset_item_content = $asset_data['itemContent'];
				$asset_data         = $this->get_asset_image_data( $asset_id, $prod_db );
				$asset_date_created = $asset_data['date_created'];
				$asset_url          = $asset_data['url'];
				$asset_filename     = $asset_data['filename'];
				$asset_title        = $asset_data['title'];
				$asset_description  = $asset_data['description'];
				$asset_credit       = $asset_data['credit'];
				$asset_uploader     = $asset_data['uploader'];
				$asset_width        = $asset_data['width'];
				$asset_height       = $asset_data['height'];
				$asset_item_content = $asset_data['itemContent'];
				// Featured image meta.
				$featured_image_metas['newspack_migration_legacy_id'] = $asset_id;
		
	
				/**
				 * Bylines.
				 * If bylines exist, they override author data.
				 */
				$bylines = $this->get_entry_bylines( $entry_id, $entry_data, $users_data, $prod_db );
	
	
				/**
				 * Author.
				 */
				$author_data  = [
					'email'            => null,
					'avatar_image_url' => null,
					'display_name'     => null,
					'first_name'       => null,
					'last_name'        => null,
					'bio'              => null,
				];
				$author_metas = [
					'newspack_migration_legacy_id'  => null,
					'newspack_migration_legacy_uid' => null,
					'newspack_migration_legacy_avatar_photo_id' => null,
				];
				// Get author data.
				$author_id   = $entry['authorId'] ?? null;
				$author_data = $this->get_user_data( $author_id, $users_data, $prod_db );
				// Author meta.
				$author_metas['newspack_migration_legacy_id']              = $author_id;
				$author_metas['newspack_migration_legacy_uid']             = $author_data['uid'];
				$author_metas['newspack_migration_legacy_avatar_photo_id'] = $author_data['avatar_photo_id'];
	
				// Set author.
				$post_data['post_author'];
	
				/**
				 * Comments.
				 */
				self::COMMENT_STATUSES;
				$comments = $this->get_entry_comments( $entry['id'], $prod_db );
	

				/**
				 * Post metas.
				 */
				$postmetas['newspack_migration_legacy_id']    = $entry['id'];
				$postmetas['newspack_migration_legacy_uid']   = $entry['uid'];
				$postmetas['newspack_migration_legacy_url']   = $entry['url'];
				$postmetas['newspack_migration_entry_type']   = $entry['fieldPreparsedEntryType'];
				$postmetas['newspack_migration_entry_status'] = $entry['status'];


				$post_id = wp_insert_post( $post_data );
				if ( is_wp_error( $post_id ) ) {
					WP_CLI::warning( sprintf( 'ERROR inserting post %s : %s', $post_data['title'], $post_id->get_error_message() ) );
				}
				WP_CLI::print_value( sprintf( 'Inserted post %s with ID %s', $post_data['title'], $post_id ) );
				exit;
	
			}
		}

		/**
		 * Redirections:
		 *      entries
		 *      categories
		 */
	}

	/**
	 * Get entries JSON files in descending order.
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
				WP_CLI::error( sprintf( 'ERROR ordering JSON file %s : %s is not a number', $entries_json_file, $number ) );
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
			WP_CLI::error( sprintf( 'ERROR reading JSON file %s : %s is not an array', $entries_json_file, $entries_data ) );
		}
		// Reverse array.
		$entries_data = array_reverse( $entries_data );

		return $entries_data;
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
				$category_id = $this->taxonomy_logic->get_or_create_category_by_name_and_parent_id( $category_title, $category_parent_id );
				
				return $category_id;
			}
		}

		return null;
	}

	/**
	 * Creates a special child category with entry type previously used in Craft CMS, under the parent category "Craft Entry Type".
	 * 
	 * @param string $entry_type 
	 * @return string|null
	 */
	public function get_entry_type_category( string $entry_type ): ?int {
		$category_parent_id = $this->taxonomy_logic->get_or_create_category_by_name_and_parent_id( 'Craft Entry Type', 0 );
		$category_id        = $this->taxonomy_logic->get_or_create_category_by_name_and_parent_id( $entry_type, $category_parent_id );

		return $category_id;
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
	 *  int 'id'          This corresponds to the asset ID.
	 *  int 'itemContent' This corresponds to the "Photo Caption" field.
	 * }
	 */
	public function get_matrixLede_itemAsset_data( array $entry ): ?array {
		if ( ! isset( $entry['matrixLede'] ) ) {
			return null;
		}
		foreach ( $entry['matrixLede'] as $block ) {
			if ( 'blockImage' === $block['type'] ) {
				
				// TODO handle multiple itemAssets.
				$asset_id     = $block['fields']['itemAsset'][0];
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
	 * @param \wpdb $prod_db    The production database connection.
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
	public function get_user_data( int $user_id, array $users_data, wpdb $prod_db ): array {
		// Search for author in users_data.
		$author_data = null;
		foreach ( $users_data as $user ) {
			if ( $user_id === $user['id'] ) {
				$photo_id         = $user['photoId'] ?? null;
				$avatar_image_url = $this->get_author_photo_url_by_id( $photo_id, $prod_db );

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
	 * @param \wpdb $prod_db The production database connection.
	 * @return string|null The author photo URL if found, null otherwise.
	 */
	public function get_author_photo_url_by_id( int $asset_id, wpdb $prod_db ): ?string {
		// Get asset row.
		$query = $prod_db->prepare(
			'SELECT id, filename, folderId, volumeId FROM assets WHERE id = %d LIMIT 1',
			$asset_id
		);
		$asset = $prod_db->get_row( $query );
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
			$folder_query = $prod_db->prepare(
				'SELECT id, parentId, name FROM volumefolders WHERE id = %d LIMIT 1',
				$current_folder_id
			);
			$folder       = $prod_db->get_row( $folder_query );
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
		$volume_query = $prod_db->prepare(
			'SELECT name FROM volumes WHERE id = %d LIMIT 1',
			$volume_id
		);
		$volume_name  = $prod_db->get_var( $volume_query );
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
	 * @param \wpdb $prod_db  The production database connection.
	 * @return array Array with asset image data with following keys. {
	 *  int 'id'               Asset ID.
	 *  int 'width'            Asset width.
	 *  int 'height'           Asset height.
	 *  string 'date_created'  Timestamp.
	 *  string 'url'           Public URL.
	 *  string 'filename'      File name.
	 *  string 'title'         Title field.
	 *  string 'description'   Description field.
	 *  string 'credit'        Credit field.
	 *  string 'uploader'      Uploader full name
	 * }
	 */
	public function get_asset_image_data( int $asset_id, wpdb $prod_db ): array {
		// Fetch all asset data in one go.
		$query = $prod_db->prepare(
			'SELECT a.id, a.dateCreated, a.filename, a.folderId, a.width, a.height, c.title, c.field_fieldBlurb, c.field_fieldCredit, u.fullName
			FROM assets a
			LEFT JOIN content c ON c.elementId = a.id AND c.siteId = %d
			LEFT JOIN users u ON u.id = a.uploaderId
			WHERE a.id = %d
			LIMIT 1',
			self::SITE_ID_NEW_HAVEN_INDEPENDENT,
			$asset_id
		);
		$asset = $prod_db->get_row( $query );
		if ( ! $asset ) {
			return [];
		}

		// Get the folder name (slug) from volumefolders.
		$folder_name = null;
		if ( ! empty( $asset->folderId ) ) { // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
			$folder_query = $prod_db->prepare(
				'SELECT name FROM volumefolders WHERE id = %d LIMIT 1',
				$asset->folderId // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
			);
			$folder_name  = $prod_db->get_var( $folder_query );
		}

		// Parse dateCreated to get year and month.
		$date_created           = $asset->dateCreated; // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
		$date_created_converted = $this->convert_server_time_to_nhi_time( $date_created, self::NHI_TIMEZONE );
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
	 * Get tag by ID.
	 * 
	 * @param int   $tag_id The tag ID.
	 * @param \wpdb $prod_db The production database connection.
	 * @return string|null The tag name if found, null otherwise.
	 */
	public function get_tag_by_id( int $tag_id, wpdb $prod_db ): ?string {
		$query = $prod_db->prepare(
			'SELECT title FROM content WHERE elementId = %d AND siteId = %d LIMIT 1',
			$tag_id,
			self::SITE_ID_NEW_HAVEN_INDEPENDENT
		);
		
		$result = $prod_db->get_var( $query );
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
	 *  - "type":"user" -- byline is in "linkedId", then $this->get_user_data( $linkedId, $users_data, $prod_db )
	 *  - "type":"custom" -- byline is in "customText"
	 *
	 * @param int   $entry_id The entry ID.
	 * @param array $entry_data The entry data.
	 * @param array $users_data The users data.
	 * @param wpdb  $prod_db The production database connection.
	 * 
	 * @return array Array of author names and their IDs. {
	 *  ?int   'user_id' If this byline came from an existing user, this is the user ID. Otherwise, null.
	 *  string 'name'    Existing user display name or custom text byline.
	 * }
	 */
	public function get_entry_bylines( int $entry_id, $entry_data, array $users_data, wpdb $prod_db ): array {
		$bylines     = [];
		$byline_data = $entry_data['matrixAuthorsByline'] ?? null;
		if ( ! $byline_data ) {
			return $bylines;
		}
		foreach ( $byline_data as $byline_id => $byline_item ) {
			if ( 'blockAuthor' !== $byline_item['type'] ) {
				continue;
			}
			
			$fields_json = $byline_item['fields']['authorLink'] ?? null;
			if ( ! $fields_json ) {
				continue;
			}
			$fields = json_decode( $fields_json, true );

			$byline_name = null;
			$user_id     = null;

			$type = $fields['type'] ?? null;
			if ( 'user' === $type ) {
				$user_id     = $fields['linkedId'] ?? null;
				$byline_name = $this->get_user_data( $user_id, $users_data, $prod_db )['display_name'] ?? null;
			} elseif ( 'custom' === $type ) {
				$payload_json = $fields['payload'] ?? null;
				$payload      = json_decode( $payload_json, true );
				$byline_name  = $payload['customText'] ?? null;
			}

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
	 * @param wpdb $prod_db  The production database connection.
	 * @return array[] Array of comments, each with keys:
	 *   - comment_id: int
	 *   - author_name: string|null User display name or custom text byline.
	 *   - author_user_id: int|null If this comment came from an existing user, this is the user ID. Otherwise, null -- this may be called an "anonymous" user (because it's not a registered user), but it still has a display name.
	 *   - comment: string
	 *   - comment_date: string The value of comments_comments.commentDate, which is the timestamp shown on the frontend (in UTC, convert to local time for display). Other date fields exist in the DB.
	 *   - status: string
	 *   - user_id: int|null
	 *   - flagged: bool Whether the comment is flagged.
	 */
	public function get_entry_comments( int $entry_id, wpdb $prod_db ): array {
		// Fetch all flagged comment IDs for this entry in one query.
		$flagged_query = $prod_db->prepare(
			'SELECT commentId FROM comments_flags WHERE commentId IN (SELECT id FROM comments_comments WHERE ownerId = %d)',
			$entry_id
		);
		$flagged_ids   = $prod_db->get_col( $flagged_query );
		$flagged_set   = array_flip( $flagged_ids ); // For fast lookup.

		$query    = $prod_db->prepare(
			'SELECT id, name, comment, commentDate, status, userId FROM comments_comments WHERE ownerId = %d ORDER BY commentDate ASC',
			$entry_id
		);
		$results  = $prod_db->get_results( $query );
		$comments = [];
		foreach ( $results as $row ) {
			$author_name    = null;
			$author_user_id = $row->userId ? (int) $row->userId : null; // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
			if ( $row->userId ) { // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
				$user_query = $prod_db->prepare(
					'SELECT fullName, username FROM users WHERE id = %d LIMIT 1',
					$row->userId // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
				);
				$user       = $prod_db->get_row( $user_query );
				if ( $user ) {
					$author_name = $user->fullName ? $user->fullName : $user->username; // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
				}
			} else {
				// For anonymous comments, use the name field if present.
				$author_name = $row->name ? $row->name : null;
			}
			// Convert the comment date to the NHI timezone.
			$comment_date           = $row->commentDate ? date( 'Y-m-d H:i:s', strtotime( $row->commentDate ) ) : null; // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
			$comment_date_converted = ! is_null( $comment_date ) ? $this->convert_server_time_to_nhi_time( $comment_date, self::NHI_TIMEZONE ) : null;

			$comments[] = [
				'comment_id'     => (int) $row->id,
				'author_name'    => $author_name,
				'author_user_id' => $author_user_id,
				'comment'        => $row->comment,
				'comment_date'   => $comment_date_converted,
				'status'         => $row->status,
				'user_id'        => $row->userId ? (int) $row->userId : null, // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
				'flagged'        => isset( $flagged_set[ $row->id ] ),
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
	 * @param \wpdb  $prod_db The production database connection.
	 * @return array The processed block data.
	 */
	public function process_content_block( object $block, $prod_db ): array {
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
					$asset_query = $prod_db->prepare(
						'SELECT filename, dateCreated FROM assets WHERE id = %d',
						$block->image_asset_id
					);
					$asset       = $prod_db->get_row( $asset_query );
					
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
						$date_converted = $this->convert_server_time_to_nhi_time( $date->format( 'Y-m-d H:i:s' ), self::NHI_TIMEZONE );
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
	 * @param \wpdb  $prod_db The production database connection.
	 * @param string $content_type The content type (main_content or lede).
	 * @param int    $image_asset_field_id The field ID for image assets.
	 * @return array The content blocks.
	 */
	public function get_content_blocks( int $article_id, $prod_db, string $content_type, int $image_asset_field_id ): array {
		$query = $prod_db->prepare(
			$this->get_content_blocks_query( $content_type, $image_asset_field_id ),
			$image_asset_field_id,
			$article_id
		);
		
		$results = $prod_db->get_results( $query );
		
		$content_blocks = [];
		foreach ( $results as $block ) {
			$content_blocks[] = $this->process_content_block( $block, $prod_db );
		}
		
		return $content_blocks;
	}

	/**
	 * Get the main content for an article.
	 *
	 * @param int   $article_id The article ID.
	 * @param \wpdb $prod_db The production database connection.
	 * @return array The main content.
	 */
	public function get_article_main_content( int $article_id, $prod_db ): array {
		$query = $prod_db->prepare(
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
		
		$results = $prod_db->get_results( $query );
		
		$content_blocks = [];
		foreach ( $results as $block ) {
			$content_blocks[] = $this->process_content_block( $block, $prod_db );
		}
		
		return $content_blocks;
	}

	/**
	 * Get the lede content for an article.
	 *
	 * @param int   $article_id The article ID.
	 * @param \wpdb $prod_db The production database connection.
	 * @return array The lede content.
	 */
	public function get_article_lede( int $article_id, $prod_db ): array {
		$query = $prod_db->prepare(
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
		
		$results = $prod_db->get_results( $query );
		
		$lede_blocks = [];
		foreach ( $results as $block ) {
			$lede_blocks[] = $this->process_content_block( $block, $prod_db );
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
	public function convert_server_time_to_nhi_time( string $timestamp, string $timezone ): string {
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
	 * @param \wpdb $prod_db Production database connection.
	 * @return string|null Image URL.
	 */
	public function get_asset_image_url( int $asset_id, wpdb $prod_db ): ?string {
		// Query the asset info from the prod db.
		$query = $prod_db->prepare(
			'SELECT filename, dateCreated, folderId FROM assets WHERE id = %d LIMIT 1',
			$asset_id
		);
		$asset = $prod_db->get_row( $query );

		if ( ! $asset || empty( $asset->filename ) || empty( $asset->dateCreated ) || empty( $asset->folderId ) ) { // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
			return null;
		}

		// Get the folder name (slug) from volumefolders.
		$folder_query = $prod_db->prepare(
			'SELECT name FROM volumefolders WHERE id = %d LIMIT 1',
			$asset->folderId // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
		);
		$folder_name  = $prod_db->get_var( $folder_query );

		if ( empty( $folder_name ) ) {
			return null;
		}

		// Parse dateCreated to get year and month.
		$date           = new \DateTime( $asset->dateCreated ); // phpcs:ignore -- Snake case matching production DB column names. WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
		$date_converted = $this->convert_server_time_to_nhi_time( $date->format( 'Y-m-d H:i:s' ), self::NHI_TIMEZONE );
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
	 * @param \wpdb $prod_db Production database connection.
	 * @return string|null Image title.
	 */
	public function get_asset_image_title( int $asset_id, wpdb $prod_db ): ?string {
		$site_id = self::SITE_ID_NEW_HAVEN_INDEPENDENT;
		// Fetch the image title from the content table.
		$query = $prod_db->prepare(
			'SELECT title FROM content WHERE elementId = %d AND siteId = %d LIMIT 1',
			$asset_id,
			$site_id
		);
		$title = $prod_db->get_var( $query );
		return $title ?: null; // phpcs:ignore -- Universal.Operators.DisallowShortTernary.Found.
	}
	
	/**
	 * Database mapping helper function.
	 * 
	 * @deprecated Use `get_asset_image_data` instead.
	 * @see get_asset_image_data
	 * 
	 * @param int   $asset_id Asset ID.
	 * @param \wpdb $prod_db Production database connection.
	 * @return string|null Image description.
	 */
	public function get_asset_image_description( int $asset_id, wpdb $prod_db ): ?string {
		$site_id = self::SITE_ID_NEW_HAVEN_INDEPENDENT;
		// Fetch the image description from the content table.
		$query       = $prod_db->prepare(
			'SELECT field_fieldBlurb FROM content WHERE elementId = %d AND siteId = %d LIMIT 1',
			$asset_id,
			$site_id
		);
		$description = $prod_db->get_var( $query );
		return $description ?: null; // phpcs:ignore -- Universal.Operators.DisallowShortTernary.Found.
	}

	/**
	 * Database mapping helper function.
	 * 
	 * @deprecated Use `get_asset_image_data` instead.
	 * @see get_asset_image_data
	 * 
	 * @param int   $asset_id Asset ID.
	 * @param \wpdb $prod_db Production database connection.
	 * @return string|null Photo credit.
	 */
	public function get_asset_image_photo_credit( int $asset_id, wpdb $prod_db ): ?string {
		$site_id = self::SITE_ID_NEW_HAVEN_INDEPENDENT;
		// Fetch the photo credit from the content table.
		$query  = $prod_db->prepare(
			'SELECT field_fieldCredit FROM content WHERE elementId = %d AND siteId = %d LIMIT 1',
			$asset_id,
			$site_id
		);
		$credit = $prod_db->get_var( $query );
		return $credit ?: null; // phpcs:ignore -- Universal.Operators.DisallowShortTernary.Found.
	}
	
	/**
	 * Database mapping helper function.
	 * 
	 * @deprecated Use `get_asset_image_data` instead.
	 * @see get_asset_image_data
	 * 
	 * @param int   $asset_id Asset ID.
	 * @param \wpdb $prod_db Production database connection.
	 * @return string|null Uploader's full name.
	 */
	public function get_asset_image_uploader( int $asset_id, wpdb $prod_db ): ?string {

		// Fetch the uploader's full name.
		$image_uploader_full_name = null;
		$uploader_query           = $prod_db->prepare(
			'SELECT fullName FROM users WHERE id = (SELECT uploaderId FROM assets WHERE id = %d LIMIT 1) LIMIT 1',
			$asset_id
		);
		$image_uploader_full_name = $prod_db->get_var( $uploader_query );

		return $image_uploader_full_name;
	}
}
