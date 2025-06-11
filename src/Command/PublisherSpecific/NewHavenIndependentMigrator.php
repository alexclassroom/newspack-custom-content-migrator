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

class NewHavenIndependentMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * Constructor.
	 */
	private function __construct() {
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {

		WP_CLI::add_command(
			'newspack-content-migrator newhavenindependent',
			self::get_command_closure( 'cmd_' ),
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
	 * Main command function.
	 *
	 * @param array $pos_args The positional arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function cmd_( array $pos_args, array $assoc_args ): void {
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
