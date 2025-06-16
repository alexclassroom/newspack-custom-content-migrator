<?php
/**
 * Importer for New Haven Independent .
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;
use wpdb;

class NewHavenIndependentMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;


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
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator newhavenindependent research-single-post-import',
			self::get_command_closure( 'cmd_research_single_post_import' ),
			[
				'synopsis' => [
					[
						'type'     => 'assoc',
						'name'     => 'json-expanded-entries',
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
			'newspack-content-migrator newhavenindependent research',
			self::get_command_closure( 'cmd_research' ),
			[
				'synopsis' => [
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
	 * @param array $pos_args The positional arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function cmd_research_single_post_import( array $pos_args, array $assoc_args ): void {
		$entries_json_file    = $assoc_args['json-expanded-entries'];
		$users_json_file    = $assoc_args['json-expanded-users'];
		$categories_expanded_newsjson_file = $assoc_args['json-expanded-categories-news-sections'];
		$prod_db_name = $assoc_args['prod-db-name'];
		$prod_db_user = $assoc_args['prod-db-user'];
		$prod_db_pass = $assoc_args['prod-db-pass'];
		$prod_db_host = $assoc_args['prod-db-host'];
		$prod_db_port = $assoc_args['prod-db-port'];
		
		$prod_db = $this->get_prod_db( $prod_db_name, $prod_db_user, $prod_db_pass, $prod_db_host, $prod_db_port );

		
		WP_CLI::print_value( '--- LEDE FEATURED IMAGE  -----------------------------' );
		/**
		 * Featured image data is located in two places in Craft CMS:
		 *  - asset image object itself has (e.g. https://www.newhavenindependent.org/admin/assets/edit/11903556-delauro1?site=siteNHI):
		 *    => this info is retrieved by `get_asset_image_data`:
		 * 		asset "id"               	=> postmeta "newspack_migration_asset_id"
		 * 		asset "url"           		=> postmeta "newspack_migration_asset_url"
		 * 		                    		=> Attachment "slug"
		 * 		asset "date_created"  		=> Attachment date_created, GMT. (e.g. 2025-06-15 12:00:00)
		 * 		asset "filename"      		=> Attachment "newspack_migration_asset_filename"
		 * 		asset "Title" 				=> Attachment "Title"
		 * 		asset "Credit" 				=> Attachment "Credit"
		 * 		asset "Description" 		=> Attachment "Description"
		 * 		asset "Uploader" 			=> postmetameta "newspack_migration_asset_uploader"
		 * 		asset "width" 				=> postmeta "newspack_migration_asset_width"
		 * 		asset "height" 				=> postmeta "newspack_migration_asset_height"
		 *  - lede ("excerpt") blockImage component also has ( e.g. https://www.newhavenindependent.org/admin/entries/sectionArticles/11903505-ethans_law?site=siteNHI#tab02--content):
		 *    => this info is retrieved by `_________`:
		 * 		lede "Photo Caption" 		=> Attachment "Caption"
		 */
		
		$users_data = json_decode( file_get_contents( $users_json_file ), true );
		$entry_data = json_decode( file_get_contents( $entries_json_file ), true );
		$entry = $entry_data[0];
		
		$author_id = $entry['authorId'] ?? null;
		$author_id = 58453; // e.g. "Brian Slattery" has avatar image and bio.
		$author_data = $this->get_user_data( $author_id, $users_data, $prod_db );
		var_dump( $author_data );
exit;
		$photo_id = $author_data['avatar_photo_id'] ?? null;
		$photo_url = $this->get_author_photo_url_by_id( $photo_id, $prod_db );
		WP_CLI::print_value( sprintf( 'photo_id: %s photo_url: %s', $photo_id, $photo_url ) );
exit;
		
		$asset_data = $this->get_matrixLede_itemAsset_data( $entry );
		$asset_id = $asset_data['id'];
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

		$entries = json_decode( file_get_contents( $entries_json_file ), true );
		if ( ! $entries ) {
			WP_CLI::error( 'Failed to decode entries JSON file.' );
		}
		$categories_news_sections = json_decode( file_get_contents( $categories_expanded_newsjson_file ), true );
		if ( ! $categories_news_sections ) {
			WP_CLI::error( 'Failed to decode categories JSON file.' );
		}

		foreach ( $entries as $entry ) {
			$post_data = [
				"newspack_migration_meta" => [
					"legacy_id" => null,
					"legacy_uid" => null,
				],
				"title" => null,
				"content" => null,
				"excerpt" => null,
				"url" => null,
				"status" => null,
				"date_created" => null,
				"date_modified" => null,
				"featured_image" => [
					"newspack_migration_meta" => [
						"legacy_id" => null,
					],
					"url" => null,
					"alt" => null,
					"title" => null,
					"caption" => null,
					"description" => null,
					"credit" => null,
					"credit_url" => null,
					"credit_organization" => null,
				],
				"author" => [
					"newspack_migration_meta" => [
						"legacy_id" => null,
					],
					"email" => null,
					"avatar_image_url" => null,
					"display_name" => null,
					"first_name" => null,
					"last_name" => null,
					"bio" => null,
				],
				"bylines" => [
					null,
				],
				"categories" => [
					null,
				],
				"tags" => [
					null,
				],
				"comment_status" => null,
				"comments" => [
					null,
				],
				"attachments" => [
					null,
				],
			];
			
			// Meta.
			$post_data['newspack_migration_meta'] = [
				"legacy_id" => $entry['id'],
				"legacy_uid" => $entry['uid'],
			];
			
			// Dates.
			// ISO 8601 timestamps.
			$date_created = new \DateTime($entry['postDate']);
			$post_data['date_created'] = $date_created->format('Y-m-d H:i:s');
			$date_modified = new \DateTime($entry['dateUpdated']);
			$post_data['date_modified'] = $date_modified->format('Y-m-d H:i:s');
			
			// Title.
			$post_data['title'] = $entry['title'];

			// URL.
			$post_data['url'] = $entry['url'];

			// Categories.
			foreach ( $entry['fieldSections'] as $fieldSection ) {
				// TODO
				/**
				 * TODO
				 *  	title
				 * 		parent
				 * 		URL
				 * 		legacy_id meta
				 */
			}
			$post_data['categories'];
			
			// Tags.
			foreach ( $entry['fieldTags'] as $fieldTag ) {
				// TODO
				$tag_name = $this->get_tag_by_id( $fieldTag['tagId'], $prod_db );
			}
			$post_data['tags'];

			/**
			 * Featured image data is located in two places in Craft CMS:
			 * 1. asset image object itself has (e.g. https://www.newhavenindependent.org/admin/assets/edit/11903556-delauro1?site=siteNHI):
			 *    => this info is retrieved by `get_asset_image_data`:
			 * 		asset "id"               	=> postmeta "newspack_migration_asset_id"
			 * 		asset "url"           		=> postmeta "newspack_migration_asset_url"
			 * 		                    		=> Attachment "slug"
			 * 		asset "date_created"  		=> Attachment date_created, GMT. (e.g. 2025-06-15 12:00:00)
			 * 		asset "filename"      		=> Attachment "newspack_migration_asset_filename"
			 * 		asset "Title" 				=> Attachment "Title"
			 * 		asset "Credit" 				=> Attachment "Credit"
			 * 		asset "Description" 		=> Attachment "Description"
			 * 		asset "Uploader" 			=> postmetameta "newspack_migration_asset_uploader"
			 * 		asset "width" 				=> postmeta "newspack_migration_asset_width"
			 * 		asset "height" 				=> postmeta "newspack_migration_asset_height"
			 * 2. lede ("excerpt") blockImage component also has ( e.g. https://www.newhavenindependent.org/admin/entries/sectionArticles/11903505-ethans_law?site=siteNHI#tab02--content):
			 *    => this info is retrieved by `get_matrixLede_itemAsset_data`:
			 * 		lede "Photo Caption" 		=> Attachment "Caption"
			 */
			$asset_data = $this->get_matrixLede_itemAsset_data( $entry );
			$asset_id = $asset_data['id'];
			$asset_item_content = $asset_data['itemContent'];
			$asset_data = $this->get_asset_image_data( $asset_id, $prod_db );
			$asset_date_created = $asset_data['date_created'];
			$asset_url = $asset_data['url'];
			$asset_filename = $asset_data['filename'];
			$asset_title = $asset_data['title'];
			$asset_description = $asset_data['description'];
			$asset_credit = $asset_data['credit'];
			$asset_uploader = $asset_data['uploader'];
			$asset_width = $asset_data['width'];
			$asset_height = $asset_data['height'];
			$asset_item_content = $asset_data['itemContent'];
	
			// Author data, including avatar image URL.
			$author_id = $entry['authorId'] ?? null;
			$author_data = $this->get_user_data( $author_id, $users_data, $prod_db );

			$d=1;

			// $this->import_entry( $post_data );
		}

		/**
		 * Redirections:
		 * 		entries
		 * 		categories
		 */
	}

	/**
	 * Featured image is found in entry['matrixLede'], in the first blockImage type, and fields itemAsset array.
	 * e.g.
	 * 	"matrixLede": {
	 * 		"11903606": {
	 * 			"type": "blockImage",
	 * 			"enabled": true,
	 * 			"collapsed": false,
	 * 			"fields": {
	 * 				"itemHelp": null,
	 * 				"itemAsset": [
	 * 					11903556
	 * 				],
	 * 				"itemContent": "Photo Caption"
	 * 			}
	 * 		}
	 * 	}
	 * 
	 * @param array $entry
	 * 
	 * @return ?array Array with some matrixLede > blockImage data. {
	 * 	int 'id'          This corresponds to the asset ID.
	 * 	int 'itemContent' This corresponds to the "Photo Caption" field.
	 * }
	 */
	public function get_matrixLede_itemAsset_data( array $entry ): ?array {
		if ( ! isset( $entry['matrixLede'] ) ) {
			return null;
		}
		foreach ( $entry['matrixLede'] as $block ) {
			if ( 'blockImage' === $block['type'] ) {
				
				// TODO handle multiple itemAssets.
				$asset_id = $block['fields']['itemAsset'][0];
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
	 * @deprecated Use `get_asset_image_data` instead.
	 * @see get_asset_image_data
	 * 
	 * @param int $asset_id
	 * @param \wpdb $prod_db
	 * @return string|null
	 */
	public function get_asset_image_url( int $asset_id, wpdb $prod_db ): ?string {
		// Query the asset info from the prod db.
		$query = $prod_db->prepare(
			'SELECT filename, dateCreated, folderId FROM assets WHERE id = %d LIMIT 1',
			$asset_id
		);
		$asset = $prod_db->get_row( $query );

		if ( ! $asset || empty( $asset->filename ) || empty( $asset->dateCreated ) || empty( $asset->folderId ) ) {
			return null;
		}

		// Get the folder name (slug) from volumefolders.
		$folder_query = $prod_db->prepare(
			'SELECT name FROM volumefolders WHERE id = %d LIMIT 1',
			$asset->folderId
		);
		$folder_name = $prod_db->get_var( $folder_query );

		if ( empty( $folder_name ) ) {
			return null;
		}

		// Parse dateCreated to get year and month.
		$date = new \DateTime( $asset->dateCreated );
		$year = $date->format( 'Y' );
		$month = $date->format( 'm' );

		// Build the URL as per the discovered pattern.
		$url = sprintf(
			'https://d2f1dfnoetc03v.cloudfront.net/Images/siteNHI/%s/%s/%s/%s',
			$year,
			$month,
			$folder_name,
			$asset->filename
		);

		return $url;
	}

	/**
	 * 
	 * 
	 * @param int $user_id
	 * @param \wpdb $prod_db
	 * @return ?array Array with author data with following keys. {
	 * 	int 'id'                   Author ID.
	 * 	?string 'uid'              Author UID.
	 * 	?string 'email'            Author email.
	 * 	?string 'username'         Username.
	 * 	?string 'display_name'     Display name.
	 * 	?string 'first_name'       First name.
	 * 	?string 'last_name'        Last name.
	 * 	?string 'bio'              Bio.
	 * 	?string 'avatar_photo_id'  Avatar image asset ID.
	 * 	?string 'avatar_image_url' Avatar image URL.
	 * }
	 */
	public function get_user_data( int $user_id, array $users_data, wpdb $prod_db ): array {
		// Search for author in users_data.
		$author_data = null;
		foreach ( $users_data as $user ) {
			if ( $user_id === $user['id'] ) {
				$photo_id = $user['photoId'] ?? null;
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
		$folder_id = $asset->folderId;
		$filename = $asset->filename;
		$volume_id = $asset->volumeId;

		// Walk up the volumefolders tree to build the path, but stop before the root (parentId == null).
		$segments = [];
		$current_folder_id = $folder_id;
		while ( $current_folder_id ) {
			$folder_query = $prod_db->prepare(
				'SELECT id, parentId, name FROM volumefolders WHERE id = %d LIMIT 1',
				$current_folder_id
			);
			$folder = $prod_db->get_row( $folder_query );
			if ( ! $folder ) {
				break;
			}
			// Stop before including the root folder (parentId == null)
			if ( is_null( $folder->parentId ) ) {
				break;
			}
			array_unshift( $segments, $folder->name );
			$current_folder_id = $folder->parentId;
		}

		// Get the root volume name for the prefix.
		$volume_query = $prod_db->prepare(
			'SELECT name FROM volumes WHERE id = %d LIMIT 1',
			$volume_id
		);
		$volume_name = $prod_db->get_var( $volume_query );
		// Map volume name to key prefix if needed.
		$prefix = null;
		if ( $volume_name === 'User-Uploaded Content' ) {
			$prefix = 'UserContent';
		} elseif ( $volume_name === 'Images' ) {
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
			'https://d2f1dfnoetc03v.cloudfront.net/%s',
			$key
		);

		return $url;
	}

	/**
	 * 
	 * 
	 * @param int $asset_id
	 * @param \wpdb $prod_db
	 * @return array Array with asset image data with following keys. {
	 * 	int 'id'               Asset ID.
	 * 	int 'width'            Asset width.
	 * 	int 'height'           Asset height.
	 * 	string 'date_created'  Timestamp.
	 * 	string 'url'           Public URL.
	 * 	string 'filename'      File name.
	 * 	string 'title'         Title field.
	 * 	string 'description'   Description field.
	 * 	string 'credit'        Credit field.
	 * 	string 'uploader'      Uploader full name
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
		if ( ! empty( $asset->folderId ) ) {
			$folder_query = $prod_db->prepare(
				'SELECT name FROM volumefolders WHERE id = %d LIMIT 1',
				$asset->folderId
			);
			$folder_name = $prod_db->get_var( $folder_query );
		}

		// Parse dateCreated to get year and month.
		$date_created = $asset->dateCreated;
		$year = null;
		$month = null;
		if ( ! empty( $date_created ) ) {
			$date = new \DateTime( $date_created );
			$year = $date->format( 'Y' );
			$month = $date->format( 'm' );
		}

		// Build the URL as per the discovered pattern.
		$url = null;
		if ( $year && $month && $folder_name && ! empty( $asset->filename ) ) {
			$url = sprintf(
				'https://d2f1dfnoetc03v.cloudfront.net/Images/siteNHI/%s/%s/%s/%s',
				$year,
				$month,
				$folder_name,
				$asset->filename
			);
		}

		return [
			'id'          => $asset->id,
			'width'       => $asset->width,
			'height'      => $asset->height,
			'date_created'=> $date_created,
			'url'         => $url,
			'filename'    => $asset->filename,
			'title'       => $asset->title,
			'description' => $asset->field_fieldBlurb,
			'credit'      => $asset->field_fieldCredit,
			'uploader'    => $asset->fullName,
		];
	}

	public function get_asset_image_filename( int $asset_id, wpdb $prod_db ): ?string {
		// Fetch the image filename from the assets table.
		$query = $prod_db->prepare(
			'SELECT filename FROM assets WHERE id = %d LIMIT 1',
			$asset_id
		);
		$filename = $prod_db->get_var( $query );
		return $filename ?: null;
	}

	/**
	 * @deprecated Use `get_asset_image_data` instead.
	 * @see get_asset_image_data
	 * 
	 * @param int $asset_id
	 * @param \wpdb $prod_db
	 * @return string|null
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
		return $title ?: null;
	}
	
	/**
	 * @deprecated Use `get_asset_image_data` instead.
	 * @see get_asset_image_data
	 * 
	 * @param int $asset_id
	 * @param \wpdb $prod_db
	 * @return string|null
	 */
	public function get_asset_image_description( int $asset_id, wpdb $prod_db ): ?string {
		$site_id = self::SITE_ID_NEW_HAVEN_INDEPENDENT;
		// Fetch the image description from the content table.
		$query = $prod_db->prepare(
			'SELECT field_fieldBlurb FROM content WHERE elementId = %d AND siteId = %d LIMIT 1',
			$asset_id,
			$site_id
		);
		$description = $prod_db->get_var( $query );
		return $description ?: null;
	}

	/**
	 * @deprecated Use `get_asset_image_data` instead.
	 * @see get_asset_image_data
	 * 
	 * @param int $asset_id
	 * @param \wpdb $prod_db
	 * @return string|null
	 */
	public function get_asset_image_photo_credit( int $asset_id, wpdb $prod_db ): ?string {
		$site_id = self::SITE_ID_NEW_HAVEN_INDEPENDENT;
		// Fetch the photo credit from the content table.
		$query = $prod_db->prepare(
			'SELECT field_fieldCredit FROM content WHERE elementId = %d AND siteId = %d LIMIT 1',
			$asset_id,
			$site_id
		);
		$credit = $prod_db->get_var( $query );
		return $credit ?: null;
	}
	
	/**
	 * @deprecated Use `get_asset_image_data` instead.
	 * @see get_asset_image_data
	 * 
	 * @param int $asset_id
	 * @param \wpdb $prod_db
	 * @return string|null
	 */
	public function get_asset_image_uploader( int $asset_id, wpdb $prod_db ): ?string {

		// Fetch the uploader's full name.
		$image_uploader_full_name = null;
		$uploader_query = $prod_db->prepare(
			'SELECT fullName FROM users WHERE id = (SELECT uploaderId FROM assets WHERE id = %d LIMIT 1) LIMIT 1',
			$asset_id
		);
		$image_uploader_full_name = $prod_db->get_var( $uploader_query );

		return $image_uploader_full_name;
	}

	/**
	 * Get tag by ID.
	 * 
	 * @param int $tag_id The tag ID.
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
		return $result ?: null;
	}

	/**
	 * @param array $pos_args The positional arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function cmd_research( array $pos_args, array $assoc_args ): void {
		$prod_db_name = $assoc_args['prod-db-name'];
		$prod_db_user = $assoc_args['prod-db-user'];
		$prod_db_pass = $assoc_args['prod-db-pass'];
		$prod_db_host = $assoc_args['prod-db-host'];
		$prod_db_port = $assoc_args['prod-db-port'];
		
		$prod_db = $this->get_prod_db( $prod_db_name, $prod_db_user, $prod_db_pass, $prod_db_host, $prod_db_port );
		
		// Total.
		$article_ids    = $this->get_article_ids( $prod_db );
		$count_articles = count( $article_ids );

		// should be approx. 41,430.
		$entries_main_news_ids = $this->get_entries_main_news_ids( $prod_db );
		// should be approx. 19,230.
		$entries_extra_extra_ids = $this->get_entries_extra_extra_ids( $prod_db );
		// should be approx. 1,593.
		$entries_obituaries_ids = $this->get_entries_obituaries_ids( $prod_db );
		// should be approx. 2,085.
		$entries_legal_notice_ids = $this->get_entries_legal_notice_ids( $prod_db );
		// should be approx. 2,754.
		$entries_brandford_eagle_ids = $this->get_entries_brandford_eagle_ids( $prod_db );

		// Case study example.
		$article_title_like   = 'DeLauro Brings Back Ethan%s Law To Congress';
		$article_id           = $this->get_article_id( $article_title_like, $prod_db );
		$article_main_content = $this->get_article_main_content( $article_id, $prod_db );
		$article_lede         = $this->get_article_lede( $article_id, $prod_db );
		
		WP_CLI::print_value( 'count: ' . $count_articles );
		WP_CLI::print_value( $article_id );
		WP_CLI::print_value( $article_main_content );
		WP_CLI::print_value( $article_lede );
	}

	/**
	 * Get the production database connection.
	 *
	 * @param string $prod_db_name The production database name.
	 * @param string $prod_db_user The production database user.
	 * @param string $prod_db_pass The production database password.
	 * @param string $prod_db_host The production database host.
	 * @param string $prod_db_port The production database port.
	 * @return \wpdb|null The production database connection or null if the connection fails.
	 */
	private function get_prod_db( string $prod_db_name, string $prod_db_user, string $prod_db_pass, string $prod_db_host, string $prod_db_port ) {
		$new_db = new \wpdb( $prod_db_user, $prod_db_pass, $prod_db_name, $prod_db_host, $prod_db_port );
		// Verify the connection was successful.
		if ( ! empty( $new_db->last_error ) ) {
			return null;
		}

		return $new_db;
	}

	/**
	 * Get all article IDs from the New Haven Independent site.
	 * 
	 * Entry types included:
	 * - Regular Article
	 * - Static Pages
	 * - Top Story
	 * - Obituaries
	 * - Extra Extra
	 * - Legal Notices
	 * - Archival Article (Imported from EE)
	 *
	 * @param \wpdb $prod_db The production database connection.
	 * @return array Array of article IDs.
	 */
	private function get_article_ids( $prod_db ) {
		$query = $prod_db->prepare(
			"SELECT e.id 
			FROM entries e 
			JOIN sites s ON e.temp_siteID = s.id 
			JOIN entrytypes et ON e.typeId = et.id 
			WHERE s.name = 'New Haven Independent' 
			AND et.name IN (
				'Regular Article',
				'Static Pages',
				'Top Story',
				'Obituaries',
				'Extra Extra',
				'Legal Notices',
				'Archival Article (Imported from EE)'
			)
			AND (e.deletedWithEntryType IS NULL OR e.deletedWithEntryType = '0')
			ORDER BY e.id ASC"
		);

		$results = $prod_db->get_col( $query );

		return $results;
	}

	/**
	 * Get article ID by title.
	 *
	 * @param string $article_title The title of the article to find.
	 * @param \wpdb  $prod_db The production database connection.
	 * @return int|null The article ID if found, null otherwise.
	 */
	private function get_article_id( string $article_title, $prod_db ): ?int {
		$query = $prod_db->prepare(
			'SELECT e.id 
			FROM entries e 
			JOIN content c ON e.id = c.elementId 
			WHERE c.title LIKE %s 
			ORDER BY e.dateCreated ASC 
			LIMIT 1',
			$article_title
		);
		
		$result = $prod_db->get_var( $query );
		return $result ? (int) $result : null;
	}

	/**
	 * Get all Extra Extra article IDs from the New Haven Independent site.
	 * 
	 * This includes all entries of type "Extra Extra" (typeId = 9) that are not deleted.
	 * The expected count is approximately 19,230 entries.
	 *
	 * @param \wpdb $prod_db The production database connection.
	 * @return array Array of article IDs.
	 */
	private function get_entries_extra_extra_ids( $prod_db ) {
		$query = $prod_db->prepare(
			"SELECT e.id 
			FROM entries e 
			JOIN sites s ON e.temp_siteID = s.id 
			JOIN entrytypes et ON e.typeId = et.id 
			WHERE s.name = 'New Haven Independent' 
			AND et.name = 'Extra Extra'
			AND (e.deletedWithEntryType IS NULL OR e.deletedWithEntryType = '0')
			ORDER BY e.id ASC"
		);

		$results = $prod_db->get_col( $query );

		return $results;
	}

	/**
	 * Get all Main News article IDs from the New Haven Independent site.
	 * 
	 * This includes all entries in the Main News section (sectionId = 1) that are not deleted.
	 * The expected count is approximately 41,430 entries.
	 *
	 * @param \wpdb $prod_db The production database connection.
	 * @return array Array of article IDs.
	 */
	private function get_entries_main_news_ids( $prod_db ) {
		$query = $prod_db->prepare(
			"SELECT e.id 
			FROM entries e 
			JOIN sites s ON e.temp_siteID = s.id 
			JOIN sections sec ON e.sectionId = sec.id 
			WHERE s.name = 'New Haven Independent' 
			AND sec.name = 'Main News'
			AND (e.deletedWithEntryType IS NULL OR e.deletedWithEntryType = '0')
			ORDER BY e.id ASC"
		);

		$results = $prod_db->get_col( $query );

		return $results;
	}

	/**
	 * Get all Obituaries article IDs from the New Haven Independent site.
	 * 
	 * This includes all entries of type "Obituaries" (typeId = 6) that are not deleted.
	 * The expected count is approximately 1,593 entries.
	 *
	 * @param \wpdb $prod_db The production database connection.
	 * @return array Array of article IDs.
	 */
	private function get_entries_obituaries_ids( $prod_db ) {
		$query = $prod_db->prepare(
			"SELECT e.id 
			FROM entries e 
			JOIN sites s ON e.temp_siteID = s.id 
			JOIN entrytypes et ON e.typeId = et.id 
			WHERE s.name = 'New Haven Independent' 
			AND et.name = 'Obituaries'
			AND (e.deletedWithEntryType IS NULL OR e.deletedWithEntryType = '0')
			ORDER BY e.id ASC"
		);

		$results = $prod_db->get_col( $query );

		return $results;
	}

	/**
	 * Get all Legal Notices article IDs from the New Haven Independent site.
	 * 
	 * This includes all entries of type "Legal Notices" (typeId = 11) that are not deleted.
	 * The expected count is approximately 2,085 entries.
	 *
	 * @param \wpdb $prod_db The production database connection.
	 * @return array Array of article IDs.
	 */
	private function get_entries_legal_notice_ids( $prod_db ) {
		$query = $prod_db->prepare(
			"SELECT e.id 
			FROM entries e 
			JOIN sites s ON e.temp_siteID = s.id 
			JOIN entrytypes et ON e.typeId = et.id 
			WHERE s.name = 'New Haven Independent' 
			AND et.name = 'Legal Notices'
			AND (e.deletedWithEntryType IS NULL OR e.deletedWithEntryType = '0')
			ORDER BY e.id ASC"
		);

		$results = $prod_db->get_col( $query );

		return $results;
	}

	/**
	 * Get all Branford Eagle article IDs from the New Haven Independent site.
	 * 
	 * This includes all entries in the Branford Eagle section (sectionId = 10) that are not deleted.
	 * The expected count is approximately 2,085 entries.
	 *
	 * @param \wpdb $prod_db The production database connection.
	 * @return array Array of article IDs.
	 */
	private function get_entries_brandford_eagle_ids( $prod_db ) {
		$query = $prod_db->prepare(
			"SELECT e.id 
			FROM entries e 
			JOIN sections sec ON e.sectionId = sec.id 
			WHERE sec.name = 'Branford Eagle'
			AND (e.deletedWithEntryType IS NULL OR e.deletedWithEntryType = '0')
			ORDER BY e.id ASC"
		);

		$results = $prod_db->get_col( $query );

		return $results;
	}

	/**
	 * Get the SQL query for retrieving content blocks.
	 *
	 * @param string $content_type The content type (main_content or lede).
	 * @param int    $image_asset_field_id The field ID for image assets.
	 * @return string The SQL query.
	 */
	private function get_content_blocks_query( string $content_type, int $image_asset_field_id ): string {
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
	private function process_content_block( object $block, $prod_db ): array {
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
							'https://d2f1dfnoetc03v.cloudfront.net/Images/siteNHI/%s',
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
						$date  = new \DateTime( $asset->dateCreated ); // phpcs:ignore -- WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase.
						$year  = $date->format( 'Y' );
						$month = $date->format( 'm' );
						
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
	private function get_content_blocks( int $article_id, $prod_db, string $content_type, int $image_asset_field_id ): array {
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
	private function get_article_main_content( int $article_id, $prod_db ): array {
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
	private function get_article_lede( int $article_id, $prod_db ): array {
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
}
