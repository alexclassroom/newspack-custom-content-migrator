<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland;

use CoAuthors_Plus;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMNode;
use Exception;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Logic\UsersHelper;
use NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland\Helpers\NNEBylineHelper;
use NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland\Helpers\NNECategoryMap;
use NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland\Helpers\NNEImageHelper;
use NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland\Helpers\NNEImportMetaEnum;
use NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland\Helpers\NNEInternalPublisherNamingMap;
use NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland\Helpers\NNEPublisherEnum;
use NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland\Helpers\NNETagMap;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use NewspackCustomContentMigrator\Utils\ConsoleColor;
use stdClass;
use WP_CLI;
use WP_CLI\ExitException;
use WP_Filesystem_Base;
use WP_Term;
use wpdb;

/**
 * The Newspapers of New England (NNE) custom content migrator.
 */
class NNEMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * The WordPress database object or database connection string.
	 *
	 * @var wpdb|string The WordPress database object or database connection string.
	 */
	protected wpdb $wpdb;

	/**
	 * The CoAuthors Plus instance.
	 *
	 * @var CoAuthors_Plus $co_authors_plus The CoAuthors Plus instance.
	 */
	protected CoAuthors_Plus $co_authors_plus;

	/**
	 * The WordPress filesystem object.
	 *
	 * @var WP_Filesystem_Base The WordPress filesystem object.
	 */
	protected WP_Filesystem_Base $wp_filesystem;

	/**
	 * The publisher being migrated.
	 *
	 * @var NNEPublisherEnum $publisher The publisher being migrated.
	 */
	protected NNEPublisherEnum $publisher;

	/**
	 * The year being migrated.
	 *
	 * @var int $year The year being migrated.
	 */
	protected int $year;

	/**
	 * A custom naming map for internal publisher names and folder paths.
	 *
	 * @var NNEInternalPublisherNamingMap $naming_map A custom naming map for internal publisher names and folder paths.
	 */
	protected NNEInternalPublisherNamingMap $naming_map;


	/**
	 * The host URL.
	 *
	 * @var string $host_url The URL for the host.
	 */
	protected string $host_url;

	/**
	 * The path to the images directory.
	 *
	 * @var string $path_to_images The path to the images directory.
	 */
	protected string $path_to_images;

	/**
	 * The path to the XMLs directory.
	 *
	 * @var string $path_to_xmls The path to the XMLs directory.
	 */
	protected string $path_to_xmls;

	/**
	 * UTC Timezone.
	 *
	 * @var DateTimeZone $utc The UTC timezone.
	 */
	protected DateTimeZone $utc;

	/**
	 * The paywall term.
	 *
	 * @var WP_Term $paywall_term The paywall term.
	 */
	protected WP_Term $paywall_term;

	/**
	 * Custom Gutenberg block generator.
	 *
	 * @var GutenbergBlockGenerator $block_generator Custom Gutenberg block generator.
	 */
	protected GutenbergBlockGenerator $block_generator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$this->wp_filesystem = $wp_filesystem;

		$this->naming_map = new NNEInternalPublisherNamingMap();

		$this->utc = new DateTimeZone( 'UTC' );

		$this->co_authors_plus = new CoAuthors_Plus();

		$this->block_generator = new GutenbergBlockGenerator();
	}

	/**
	 * Register commands with WP CLI.
	 *
	 * @throws Exception If the command registration fails.
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator newspapers-of-new-england-migrate',
			self::get_command_closure( 'cmd_migrate' ),
			[
				'shortdesc' => 'Migrate content from Newspapers of New England to the Newspack site.',
				'synopsis'  => [
					[
						'name'     => 'publisher-name',
						'type'     => 'positional',
						'desc'     => 'Name of the publisher.',
						'optional' => false,
						'options'  => array_map( fn( $pub ) => $pub->value, NNEPublisherEnum::cases() ),
					],
					[
						'name'     => 'year',
						'type'     => 'positional',
						'desc'     => 'Year to migrate content for (YYYY).',
						'optional' => false,
					],
					[
						'name'     => 'update-existing-posts',
						'type'     => 'flag',
						'desc'     => 'Proceed with updating existing posts, or skip them.',
						'optional' => true,
					],
				],
			],
		);

		WP_CLI::add_command(
			'newspack-content-migrator newspapers-of-new-england-update-tags-to-mapped-values',
			self::get_command_closure( 'cmd_update_tags' ),
			[
				'shortdesc' => 'Tags were migrated as-is from the XMLs, this command updates the tags to mapped values provided by the NNE Team',
			],
		);
	}

	/**
	 * Migrator command.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 * @throws WP_CLI\ExitException If the required directory paths do not exist.
	 */
	public function cmd_migrate( array $args, array $assoc_args ): void {
		$this->publisher       = NNEPublisherEnum::tryFrom( $args[0] );
		$this->year            = (int) $args[1];
		$this->host_url        = $this->naming_map->get_host_url( $this->publisher );
		$update_existing_posts = $assoc_args['update-existing-posts'] ?? false;

		$can_proceed = true;
		foreach ( $this->get_list_of_existing_required_paths() as $path => $exists ) {
			if ( ! $exists ) {
				ConsoleColor::magenta( 'Missing required directory:' )->bright_white( $path )->output();
				$can_proceed = false;
			}
		}

		if ( ! $can_proceed ) {
			WP_CLI::error( 'Please ensure that the required directory paths exist before attempting to migrate content.' );
		}

		$paywall_term = NNECategoryMap::get_term_by_name( 'Paywall', 'post_tag' );
		if ( null === $paywall_term ) {
			$this->paywall_term = NNECategoryMap::create_taxonomy( 'Paywall', 'post_tag' );
		} else {
			$this->paywall_term = $paywall_term;
		}

		foreach ( scandir( $this->path_to_xmls ) as $xml_file ) {
			if ( '.' === $xml_file || '..' === $xml_file ) {
				continue;
			}

			$this->migrate_xml_file( "$this->path_to_xmls/$xml_file", $update_existing_posts );
		}
	}

	/**
	 * This command fixes an issue where tags were imported directly, as-is, from XML files when they should've been
	 * mapped to either new tag values or categories.
	 *
	 * @return void
	 * @throws Exception If a new tag cannot be created.
	 */
	public function cmd_update_tags(): void {
		ConsoleColor::title_output( 'Updating tags...' );
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$tags = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, tt.term_taxonomy_id, t.name, t.slug, tt.taxonomy FROM $wpdb->terms t 
    					INNER JOIN $wpdb->term_taxonomy tt 
    					    ON t.term_id = tt.term_id 
    				LEFT JOIN $wpdb->termmeta tm ON t.term_id = tm.term_id AND tm.meta_key = %s
         			WHERE tt.taxonomy = %s AND tm.meta_value IS NULL",
				NNEImportMetaEnum::TAG_UPDATE_META_KEY->value,
				'post_tag'
			)
		);

		if ( empty( $tags ) ) {
			ConsoleColor::green( 'No tags to update.' )->output();
		}

		foreach ( $tags as $tag ) {
			echo "\n";
			ConsoleColor::white( 'Updating tag:' )->underlined_yellow( $tag->name )->output();

			if ( ! array_key_exists( $tag->name, NNETagMap::$mapping ) ) {
				ConsoleColor::magenta( 'No mapping found for tag. Skipping...' )->output();
				add_term_meta(
					$tag->term_id,
					NNEImportMetaEnum::TAG_UPDATE_META_KEY->value,
					NNEImportMetaEnum::TAG_UPDATE_SKIPPED_META_VALUE->value
				);
				continue;
			}

			$mapped_tag                = NNETagMap::$mapping[ $tag->name ];
			$count_of_new_tags         = ! empty( $mapped_tag['tags'] ) ? count( $mapped_tag['tags'] ) : 0;
			$contains_category_mapping = ! empty( $mapped_tag['category'] );
			$output                    = ConsoleColor::white( 'Contains Category Mapping:' );
			$output                    = $contains_category_mapping ? $output->green( 'Yes' ) : $output->yellow( 'No' );

			$output->white( 'Count of New Tags:' )->yellow( $count_of_new_tags )->output();

			if (
				! $contains_category_mapping &&
				1 === $count_of_new_tags &&
				strtolower( $tag->name ) === strtolower( $mapped_tag['tags'][0] )
			) {
				if ( $tag->name !== $mapped_tag['tags'][0] ) { // If the case dependent names are the same, we can skip renaming.
					ConsoleColor::green( 'Only name change required.' )
								->white( 'Old:' )
								->underlined_white( $tag->name )
								->white( 'New:' )
								->underlined_white( $mapped_tag['tags'][0] )
								->output();

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$maybe_term_updated = $wpdb->update(
						$wpdb->terms,
						[
							'name' => $mapped_tag['tags'][0],
						],
						[
							'term_id' => $tag->term_id,
						]
					);

					if ( 1 === $maybe_term_updated ) {
						ConsoleColor::cyan( 'Rename successful' )->output();
						add_term_meta(
							$tag->term_id,
							NNEImportMetaEnum::TAG_UPDATE_META_KEY->value,
							NNEImportMetaEnum::TAG_UPDATE_UPDATED_META_VALUE->value
						);
					} else {
						ConsoleColor::red( 'Rename failed' )->output();
					}
				} else {
					ConsoleColor::green( 'No name change required.' )->output();
					add_term_meta(
						$tag->term_id,
						NNEImportMetaEnum::TAG_UPDATE_META_KEY->value,
						NNEImportMetaEnum::TAG_UPDATE_UPDATED_META_VALUE->value
					);
				}

				continue;
			}

			$associated_post_ids = $this->get_associated_post_ids( $tag->term_taxonomy_id );

			$maybe_category_added_to_all_posts = null;
			$maybe_all_tags_added_to_posts     = [];

			// if category, find all posts associated with old tag, and add this category.
			// if no tag,
			// delete the old tag
			// If exactly 1 tag, rename to the new tag.
			// If more than 1 tag, use first tag to rename, add subsequent tag to all posts associated with the new tag.

			if ( $contains_category_mapping ) {
				$parent_term = (object) [
					'term_id'          => 0,
					'term_taxonomy_id' => 0,
				];
				if ( ! empty( $mapped_tag['category']['parent'] ) ) {
					$parent_term = NNECategoryMap::get_term_by_name( $mapped_tag['category']['parent'], 'category' );
				}

				$category = NNECategoryMap::get_term_by_name( $mapped_tag['category']['name'], 'category' );

				if ( null === $category || $category->parent !== $parent_term->term_taxonomy_id ) {
					ConsoleColor::red( 'Category without proper parent.' )->output();
					ConsoleColor::white( "\t" )->red( 'Name:' )->bright_red( $mapped_tag['category']['name'] )->output();
					ConsoleColor::white( "\t" )->red( 'Parent:' )->bright_red( $mapped_tag['category']['parent'] ?? '-' )->output();
					continue;
				}

				$maybe_category_added_to_all_posts = $this->add_taxonomy_to_post_ids( $category->term_taxonomy_id, $associated_post_ids );

				$category_output = 'Name: ' . $mapped_tag['category']['name'];
				if ( $mapped_tag['category']['parent'] ) {
					$category_output .= ' Parent: ' . $mapped_tag['category']['parent'];
				}

				if ( $maybe_category_added_to_all_posts ) {
					ConsoleColor::green( 'Successfully' )
								->white( 'added category' )
								->green( $category_output )
								->white( 'to all associated posts.' )
								->output();
				} else {
					ConsoleColor::red( 'Failed' )
								->white( 'to add category' )
								->red( $category_output )
								->white( 'to all associated posts.' )
								->output();
				}
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete(
				$wpdb->term_relationships,
				[
					'term_taxonomy_id' => $tag->term_taxonomy_id,
				]
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete(
				$wpdb->term_taxonomy,
				[
					'term_taxonomy_id' => $tag->term_taxonomy_id,
				]
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete(
				$wpdb->terms,
				[
					'term_id' => $tag->term_id,
				]
			);

			if ( ! empty( $mapped_tag['tags'] ) ) {
				foreach ( $mapped_tag['tags'] as $new_tag_name ) {
					ConsoleColor::white( "\t" )->underlined_white( $tag->name )->white( '👉🏼' )->bright_white( $new_tag_name )->output();

					$new_tag = NNECategoryMap::get_term_by_name( $new_tag_name, 'post_tag' );
					if ( null === $new_tag ) {
						$new_tag = NNECategoryMap::create_taxonomy( $new_tag_name, 'post_tag' );
					}

					$maybe_tag_added_to_posts = $this->add_taxonomy_to_post_ids( $new_tag->term_taxonomy_id, $associated_post_ids );

					if ( $maybe_tag_added_to_posts ) {
						ConsoleColor::green( "Successfully added new tag (`$new_tag->name`) to associated posts." )->output();
						$maybe_all_tags_added_to_posts[] = true;
					} else {
						ConsoleColor::red( "Failed to add new tag (`$new_tag->name`) to associated posts." )->output();
						$maybe_all_tags_added_to_posts[] = false;
					}
				}

				$maybe_all_tags_added_to_posts = ! empty( $maybe_all_tags_added_to_posts ) && ! in_array( false, $maybe_all_tags_added_to_posts, true );
			}

			$mark_as_complete = true;

			if ( null !== $maybe_category_added_to_all_posts ) {
				$mark_as_complete = $maybe_category_added_to_all_posts;
			}

			if ( is_bool( $maybe_all_tags_added_to_posts ) ) {
				$mark_as_complete = $mark_as_complete && $maybe_all_tags_added_to_posts;
			}

			if ( $mark_as_complete ) {
				add_term_meta(
					$tag->term_id,
					NNEImportMetaEnum::TAG_UPDATE_META_KEY->value,
					NNEImportMetaEnum::TAG_UPDATE_UPDATED_META_VALUE->value
				);
			}
		}

		ConsoleColor::green( 'Tag migration completed.' )->output();
	}

	/**
	 * Migrates a single XML file.
	 *
	 * @param string $xml_file_path Path The path to the XML file.
	 * @param bool   $update_existing_posts Whether to update existing posts, or skip them.
	 *
	 * @return void
	 * @throws WP_CLI\ExitException|DateMalformedStringException If multiple posts are found with the same legacy ID, or if ModificationDate is malformed.
	 */
	public function migrate_xml_file( string $xml_file_path, bool $update_existing_posts ): void {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$dom           = new DOMDocument( '1.0', 'UTF-8' );
		$dom->encoding = 'UTF-8';
		$dom->loadXML(
			$this->wp_filesystem->get_contents( $xml_file_path ),
			LIBXML_BIGLINES
			| LIBXML_HTML_NOIMPLIED
			| LIBXML_NOBLANKS
			| LIBXML_NOCDATA
			| LIBXML_PARSEHUGE
		);

		// Process article section first.
		$article_element = $dom->getElementsByTagName( 'tera.gn3article' )->item( 0 );
		$article_id      = $article_element->getAttribute( 'GN4Id' );
		$post_id         = $this->get_post_id_from_legacy_id( $article_id, NNEImportMetaEnum::ARTICLE_ID_KEY );
		$image_elements  = $dom->getElementsByTagName( 'tera.gn3picture' );

		// I want to be able to refer to export data directly as opposed to from within a foreach loop.
		$article_object = new stdClass();
		foreach ( $article_element->childNodes as $node ) {
			$article_object->{$node->nodeName} = $node->hasChildNodes() ? $this->get_inner_contents( $node, 'xml' ) : $node->nodeValue;
		}

		ConsoleColor::white( 'Article ID:' )
					->bright_yellow( $article_id )
					->white( 'Title:' )
					->bright_yellow( $article_object->DocumentPageTitle )
					->output();

		if ( null !== $post_id ) {
			$post_id = (int) $post_id;
			ConsoleColor::cyan( 'Previously imported article' )
						->bright_white( $article_id )
						->white( '→' )
						->bright_green( $post_id )
						->white( '(' . get_site_url( null, '/?p=' . $post_id ) . ')' )
						->output();

			if ( ! $update_existing_posts ) {
				ConsoleColor::cyan( 'Skipping...' )->output();
				echo "\n\n";
				return;
			}
		}

		ConsoleColor::title_output( 'Handling images...' );
		$attachments = $this->handle_images( $image_elements, $update_existing_posts );

		$post_date = new DateTimeImmutable( $article_object->ModificationDate );

		$post_data = [
			'post_author'    => 0,
			'post_date'      => $post_date->format( 'Y-m-d H:i:s' ),
			'post_date_gmt'  => $post_date->setTimezone( $this->utc )->format( 'Y-m-d H:i:s' ),
			'post_content'   => '',
			'post_title'     => $article_object->Headline,
			'post_name'      => trailingslashit( $article_object->DocumentUrlPath ),
			'post_excerpt'   => $article_object->DocumentPageDescription,
			'post_status'    => 'publish',
			'post_type'      => 'post',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'meta_input'     => [
				NNEImportMetaEnum::ARTICLE_ID_KEY->value => $article_id,
			],
		];

		ConsoleColor::title_output( 'Handling Bylines...' );
		ConsoleColor::white( 'Original:' )->yellow( $article_object->ByLine )->output();
		$bylines        = NNEBylineHelper::get_bylines( $article_object->ByLine );
		$byline_credits = [];
		$author_ids     = [];
		foreach ( $bylines as $byline ) {
			$byline_credit = trim( $article_object->ByCredit );

			if ( empty( $byline ) ) {
				$byline = 'Staff Report';
			}

			$byline = sanitize_user( $byline, true );

			$output = ConsoleColor::white( "\t-" )->underlined_bright_white( $byline );

			$user = NNEBylineHelper::get_user_by_display_name( $byline );

			if ( $user ) {
				$output->white( '→' )->bright_green_with_blue_background( "$user->ID" )->output();
			} else {
				$user = UsersHelper::create_or_get_user(
					[
						'display_name' => $byline,
						'role'         => 'author',
					],
					sha1( $byline )
				);

				$output->white( '→' )->bright_green( "$user->ID" )->output();
			}

			if ( 0 === $post_data['post_author'] ) {
				$post_data['post_author'] = $user->ID;
				$author_ids[]             = $user->ID;
			} else {
				$author_ids[] = $user->ID;
			}

			if ( ! empty( $byline_credit ) ) {
				$byline_credits[] = "[Author id=$user->ID]$user->display_name[/Author] $byline_credit";
			}
		}

		if ( ! empty( $byline_credits ) ) {
			$last_byline_credit = array_pop( $byline_credits );
			if ( count( $byline_credits ) >= 2 ) {
				$full_byline_credit = 'by ' . implode( ', ', $byline_credits ) . ', and ' . $last_byline_credit;
			} elseif ( count( $byline_credits ) === 1 ) {
				$full_byline_credit = 'by ' . $byline_credits[0] . ', and ' . $last_byline_credit;
			} else {
				$full_byline_credit = "by $last_byline_credit";
			}

			$post_data['meta_input'][ NNEImportMetaEnum::BYLINE_FEATURE_ACTIVE_KEY->value ] = true;
			$post_data['meta_input'][ NNEImportMetaEnum::BYLINE_KEY->value ]                = $full_byline_credit;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( str_starts_with( $article_object->InnerBody, '<webBody>' ) || str_starts_with( $article_object->InnerBody, '<body>' ) ) {
			$inner_body_dom = new DOMDocument( '1.0', 'ISO-8859-1' );
			// $inner_body_dom->encoding = 'ISO-8859-1';

			libxml_use_internal_errors( true );
			$inner_body_dom->loadHTML(
				'<?xml encoding="ISO-8859-1">' .
				html_entity_decode(
					htmlentities(
						$article_object->InnerBody,
						ENT_QUOTES | ENT_HTML5,
						'ISO-8859-1',
						false
					),
					ENT_QUOTES | ENT_HTML5,
					'ISO-8859-1'
				)
			);
			libxml_clear_errors();

			$article_object->InnerBody = $this->get_inner_contents( $inner_body_dom->lastChild->firstChild, 'html' );
		}

		$post_data['post_content'] = $article_object->InnerBody;

		$embeds = [];
		if ( ! empty( $article_object->WebEmbed ) ) {
			$embeds[] = [
				'embed'    => html_entity_decode( $article_object->WebEmbed, ENT_NOQUOTES, 'ISO-8859-1' ),
				'location' => $article_object->EmbedLocation,
			];
		}

		if ( ! empty( $article_object->{'WebEmbed2'} ) ) {
			$embeds[] = [
				'embed'    => html_entity_decode( $article_object->{'WebEmbed2'}, ENT_NOQUOTES, 'ISO-8859-1' ),
				'location' => $article_object->{'EmbedLocation2'},
			];
		}

		if ( ! empty( $embeds ) ) {
			ConsoleColor::title_output( 'Handling Embeds...' );
			// Sort embeds by location, Descending order.
			usort(
				$embeds,
				function ( $a, $b ) {
					return $b['location'] <=> $a['location'];
				}
			);

			$body_dom = new DOMDocument();
			libxml_use_internal_errors( true );
			$body_dom->loadHTML(
				'<?xml encoding="ISO-8859-1">' .
				html_entity_decode(
					htmlentities(
						$article_object->InnerBody,
						ENT_QUOTES | ENT_HTML5,
						'ISO-8859-1',
						false
					),
					ENT_QUOTES | ENT_HTML5,
					'ISO-8859-1'
				)
			);
			libxml_clear_errors();
			$paragraphs = $body_dom->getElementsByTagName( 'p' );

			if ( $paragraphs->count() > 0 ) {
				$update_post_content = false;

				foreach ( $embeds as $embed ) {
					$embed_dom = new DOMDocument();
					libxml_use_internal_errors( true );
					$embed_dom->loadHTML( $embed['embed'] );
					libxml_clear_errors();
					$embed_location = 0 === (int) $embed['location'] ? $paragraphs->count() - 1 : $embed['location'];

					if ( $embed_location >= $paragraphs->count() ) {
						ConsoleColor::magenta( 'Invalid embed location:' )
									->white( 'Total Paragraphs:' )
									->bright_white( $paragraphs->count() )
									->white( 'Embed Loc:' )
									->bright_white( $embed_location )
									->output();

						$paragraph = $paragraphs->item( $paragraphs->count() - 1 );
					} else {
						$paragraph = $paragraphs->item( $embed_location );
					}

					$current = $paragraph;
					$sibling = $current->nextSibling;
					foreach ( $embed_dom->documentElement->lastChild->childNodes as $node ) {
						$import_node = $paragraph->ownerDocument->importNode( $node, true );

						if ( $import_node ) {
							$current->parentNode->insertBefore( $import_node, $sibling );
							$current             = $current->nextSibling;
							$sibling             = $current->nextSibling;
							$update_post_content = true;
						}
					}
				}

				if ( $update_post_content ) {
					$article_object->InnerBody = $body_dom->saveHTML();
					$post_data['post_content'] = $body_dom->saveHTML();
				}
			}
		}

		if ( str_starts_with( $post_data['post_name'], '/' ) ) {
			$post_data['post_name'] = substr( $post_data['post_name'], 1 );
		}

		// TODO how should we handle images that are checksums and don't have dataId?
		if ( ! isset( $article_object->{'File02'} ) ) {
			$article_object->{'File02'} = '';
		}

		$file_2_image_helper = new NNEImageHelper( $article_object->{'File02'}, $this->host_url, $this->path_to_images );
		if ( $file_2_image_helper->has_data_id() && ! array_key_exists( 'dId' . $file_2_image_helper->get_data_id(), $attachments ) ) {
			ConsoleColor::title_output( 'Handling featured image...' );
			$maybe_attachment_id = $this->get_attachment_id( $file_2_image_helper );

			if ( null === $maybe_attachment_id ) {
				ConsoleColor::white( "\t-" )->white( 'Data ID:' )
							->bright_yellow( $file_2_image_helper->get_data_id() )
							->white( 'Exists Locally:' )
							->bright_yellow( $file_2_image_helper->exists_in_media_library() ? 'Yes' : 'No' )
							->output();

				$maybe_attachment_id = Attachments::import_external_file(
					$file_2_image_helper->get_best_path(),
					null, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					null,
					null,
					null,
					null,
					[
						'post_date' => $post_date->format( 'Y-m-d' ),
					],
					$file_2_image_helper->get_file_name()
				);

				if ( is_wp_error( $maybe_attachment_id ) ) {
					ConsoleColor::magenta( 'Error importing featured image' )
								->white( 'Filename:' )
								->bright_red( $article_object->{'File02'} )
								->white( 'Error:' )
								->bright_red( $maybe_attachment_id->get_error_message() )
								->output();
				} else {
					add_post_meta( $maybe_attachment_id, NNEImportMetaEnum::IMAGE_DATA_ID_KEY->value, $file_2_image_helper->get_data_id() );
				}
			}

			if ( null !== $maybe_attachment_id ) {
				$post_data['meta_input']['_thumbnail_id']                      = $maybe_attachment_id;
				$post_data['meta_input']['newspack_featured_image_position']   = 'hidden';
				$post_data['meta_input']['_newspack_featured_image_is_hidden'] = true;
			}
		} elseif ( ! empty( $attachments ) ) {
				$first_attachment                         = array_shift( $attachments );
				$post_data['meta_input']['_thumbnail_id'] = $first_attachment['ID'];
				unset( $attachments[ $first_attachment['meta_input'][ NNEImportMetaEnum::IMAGE_DATA_ID_KEY->value ] ] );
				unset( $attachments[ $first_attachment['meta_input'][ NNEImportMetaEnum::IMAGE_EDITORIAL_ID_KEY->value ] ] );
		}

		if ( 'false' === strtolower( $article_object->HidePhotos ) && $image_elements->count() > 0 ) {
			$images_block = [];

			if ( 1 === $image_elements->count() ) {
				if ( ! empty( $attachments ) ) {
					$first_attachment = array_shift( $attachments );
					$images_block     = $this->block_generator->get_image( get_post( $first_attachment['ID'] ) );
				}
			} elseif ( $image_elements->count() > 1 ) {
				$attachment_ids = [];
				foreach ( $attachments as $id => $attachment ) {
					if ( is_numeric( $id ) ) {
						$attachment_ids[] = $id;
					}
				}

				$images_block = $this->block_generator->get_jetpack_slideshow( $attachment_ids );
			}

			if ( ! empty( $images_block ) ) {
				$post_data['post_content'] = serialize_block( $images_block ) . PHP_EOL . $post_data['post_content'];
			}
		}

		if ( null !== $post_id ) {
			$post_data['meta_input']['_yoast_wpseo_primary_category'] = false;
			$post_updated = $this->raw_update_post( $post_id, $post_data, delete_existing_terms: true );

			$output = ConsoleColor::white( 'Update:' );

			if ( null === $post_updated ) {
				$output->yellow( 'None' )->output();
			} elseif ( $post_updated ) {
				$output->bright_green( 'Success' )->output();
			} else {
				$output->bright_red( 'Failed' )->output();
			}
		} else {
			$post_id = wp_insert_post( $post_data );
		}

		if ( ! empty( $article_object->Section ) ) {
			NNECategoryMap::add_mapped_categories_and_tags(
				$this->publisher,
				$post_id,
				$article_object->Section // phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			);
		}

		if ( ! empty( $article_object->SubSection ) ) {
			NNECategoryMap::add_mapped_categories_and_tags(
				$this->publisher,
				$post_id,
				$article_object->SubSection,
			);
		}

		$post_categories = $this->get_post_terms( $post_id, [ 'category' ] );
		if ( empty( $post_categories ) ) {
			$uncategorized = wp_create_category( 'Uncategorized' );

			if ( is_numeric( $uncategorized ) ) {
				wp_set_post_categories( $post_id, [ intval( $uncategorized ) ] );
			}
		} else {
			$uncategorized_category = null;

			foreach ( $post_categories as $index => $post_category ) {
				if ( 'uncategorized' === $post_category->slug ) {
					$uncategorized_category = $post_category;
					unset( $post_categories[ $index ] );
					break;
				}
			}

			if ( ! empty( $post_categories ) ) {
				$post_categories = array_values( $post_categories ); // Reset keys to start from 0.
				update_post_meta( $post_id, '_yoast_wpseo_primary_category', $post_categories[0]->term_id );

				if ( ! empty( $uncategorized_category ) ) {
					$this->wpdb->delete(
						$this->wpdb->term_relationships,
						[
							'object_id'        => $post_id,
							'term_taxonomy_id' => $uncategorized_category->term_taxonomy_id,
						]
					);
				}
			}
		}

		if ( 'metered' === $article_object->Paywall ) {
			wp_set_post_terms( $post_id, [ (int) $this->paywall_term->term_id ], 'post_tag', true );
		}

		$tags = explode( ',', $article_object->DocumentPageKeyWords );
		if ( ! empty( $tags ) ) {
			$tag_term_ids = [];

			ConsoleColor::title_output( 'Handling tags...' );
			foreach ( $tags as $tag ) {
				$tag = trim( $tag );
				if ( empty( $tag ) ) {
					continue;
				}

				ConsoleColor::white( "\t-" )->underlined_bright_white( $tag )->output();
				$tag_term = NNECategoryMap::get_term_by_name( $tag, 'post_tag' );

				if ( null === $tag_term ) {
					$tag_term = NNECategoryMap::create_taxonomy( $tag, 'post_tag' );
				}

				$tag_term_ids[] = (int) $tag_term->term_id;
			}

			wp_set_post_terms( $post_id, $tag_term_ids, 'post_tag', true );
		}

		if ( ! empty( $author_ids ) ) {
			$this->co_authors_plus->add_coauthors(
				$post_id,
				$author_ids,
				false, // Removes existing author-post relationships.
				'id'
			);
		}

		echo "\n\n";
	}

	/**
	 * Checks if an article with the given ID has already been imported.
	 *
	 * @param string            $article_id Legacy article ID.
	 * @param NNEImportMetaEnum $import_meta The meta key to search for.
	 *
	 * @return string|null
	 * @throws WP_CLI\ExitException If multiple articles are found with the same legacy ID.
	 */
	public function get_post_id_from_legacy_id( string $article_id, NNEImportMetaEnum $import_meta ): ?string {
		// phpcs:disable -- Query is already prepared and escaped.
		$result = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT post_id FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
					$import_meta->value,
					$article_id
				)
			);
		// phpcs:enable

		if ( count( $result ) > 1 ) {
			throw new WP_CLI\ExitException( 'Multiple articles found with the same legacy ID.' );
		}

		return ! empty( $result ) ? $result[0]->post_id : null;
	}

	/**
	 * Retries Post data from the database for a given post ID.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array{
	 *     ID: string,
	 *     post_author: string,
	 *     post_date: string,
	 *     post_date_gmt: string,
	 *     post_content: string,
	 *     post_title: string,
	 *     post_excerpt: string,
	 *     post_status: string,
	 *     comment_status: string,
	 *     ping_status: string,
	 *     post_password: string,
	 *     post_name: string,
	 *     post_modified: string,
	 *     post_modified_gmt: string,
	 *     post_parent: string,
	 *     guid: string,
	 *     post_type: string,
	 *     post_mime_type: string,
	 *     meta_input: array<string, string>,
	 *     tax_input: array<string, int[]>,
	 * }|null
	 */
	public function get_post_data( int $post_id ): ?array {
		// phpcs:disable -- All queries are already prepared and escaped.
		$post_data = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->posts} WHERE ID = %d",
				$post_id
			)
		);

		if ( null === $post_data ) {
			return null;
		}

		$meta_input = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM ( 
						SELECT ROW_NUMBER() over ( PARTITION BY meta_key ORDER BY meta_id DESC ) as row_num, 
						       meta_key, 
						       meta_value 
						FROM {$this->wpdb->postmeta} 
						WHERE post_id = %d ) as sub 
         			WHERE sub.row_num = 1",
                $post_id
			)
		);

		foreach ( $meta_input as $key => $meta ) {
			$meta_input[ $meta->meta_key ] = $meta->meta_value;
			unset( $meta_input[ $key ] );
		}

		$post_data->meta_input = $meta_input;

		$tax_input = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT tt.taxonomy, 
       				GROUP_CONCAT( t.term_id SEPARATOR ',') as term_ids 
				FROM {$this->wpdb->terms} t 
				    INNER JOIN {$this->wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
				    INNER JOIN {$this->wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id 
				WHERE tr.object_id = %d 
				GROUP BY tt.taxonomy",
				$post_id
			)
		);
		// phpcs:enable

		foreach ( $tax_input as $key => $tax ) {
			$tax_input[ $tax->taxonomy ] = explode( ',', $tax->term_ids );
			unset( $tax_input[ $key ] );
		}

		$post_data->tax_input = $tax_input;

		return (array) $post_data;
	}

	/**
	 * Retrieves post data from the database for a given legacy ID.
	 *
	 * @param string $legacy_id Legacy article ID.
	 *
	 * @return array|null
	 * @throws WP_CLI\ExitException If multiple posts are found with the same legacy ID.
	 */
	public function get_post_data_from_legacy_id( string $legacy_id ): ?array {
		return $this->get_post_data(
			$this->get_post_id_from_legacy_id( $legacy_id, NNEImportMetaEnum::ARTICLE_ID_KEY ) ?? 0
		);
	}

	/**
	 * Retrieves terms associated with the given post ID. Uncached.
	 *
	 * @param int      $post_id The post ID.
	 * @param string[] $taxonomies The taxonomies to retrieve terms for.
	 *
	 * @return array|null
	 */
	public function get_post_terms( int $post_id, array $taxonomies ): ?array {
		$taxonomy_constraint = '';

		if ( ! empty( $taxonomies ) ) {
			$taxonomy_constraint = 'AND tt.taxonomy IN (' . implode(
					',',
					array_map(
						function ( $taxonomy ) {

							return "'" . esc_sql( $taxonomy ) . "'";
						},
						$taxonomies
					)
				) . ')';
		}

		// phpcs:disable -- properly escaped and prepared.
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT t.term_id, 
       						tt.term_taxonomy_id, 
       						t.name, 
       						tt.taxonomy,
       						LOWER( t.slug ) as slug,
       						tr.term_order
						FROM {$this->wpdb->term_relationships} tr 
						    LEFT JOIN {$this->wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
						    LEFT JOIN {$this->wpdb->terms} t ON tt.term_id = t.term_id 
						WHERE tr.object_id = %d $taxonomy_constraint
						ORDER BY tr.term_order ASC",
				$post_id
			)
		);
		// phpcs:enable
	}

	/**
	 * Helps retrieve the post/attachment ID based off of meta data from the image.
	 *
	 * @param NNEImageHelper $image_helper Image helper object.
	 *
	 * @return int|null
	 * @throws WP_CLI\ExitException If multiple articles are found with the same legacy ID.
	 */
	public function get_attachment_id( NNEImageHelper $image_helper ): ?int {
		$attachment_id = null;

		if ( $image_helper->has_data_id() ) {
			$attachment_id = $this->get_post_id_from_legacy_id( $image_helper->get_data_id(), NNEImportMetaEnum::IMAGE_DATA_ID_KEY );

			if ( null !== $attachment_id ) {
				ConsoleColor::white( "\t-" )
							->bright_blue_with_white_background( 'Found image via data ID: - ' )
							->white( 'Data ID: ' )
							->cyan( $image_helper->get_data_id() )
							->white( 'Attachment ID: ' )
							->bright_green_with_blue_background( $attachment_id )
							->output();
			}
		} elseif ( $image_helper->has_editorial_key() ) {
			$attachment_id = $this->get_post_id_from_legacy_id( $image_helper->get_editorial_key(), NNEImportMetaEnum::IMAGE_EDITORIAL_ID_KEY );

			if ( null !== $attachment_id ) {
				ConsoleColor::white( "\t-" )
							->bright_white_with_blue_background( 'Found image via GN3 Editorial ID: - ' )
							->white( 'Data ID: ' )
							->bright_blue( $image_helper->get_data_id() )
							->white( 'Attachment ID: ' )
							->underlined_bright_blue( $attachment_id )
							->output();
			}
		}

		return $attachment_id;
	}

	/**
	 * Handles images in the given DOM node list or node.
	 *
	 * @param DOMNodeList|DOMNode $images The DOM node list or node containing the images.
	 * @param bool                $update_existing_data Whether to update existing image data.
	 *
	 * @return array<array{attachment_id:array,dId:array,GN3EditorialKey:array}>|null
	 * @throws DateMalformedStringException If the date is not in a recognized format.
	 * @throws ExitException If the image cannot be found or uploaded.
	 */
	public function handle_images( DOMNodeList|DOMNode $images, bool $update_existing_data ): ?array {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		if ( $images instanceof DOMNodeList ) {
			$attachments = [];

			foreach ( $images as $image ) {
				$attachment = $this->handle_images( $image, $update_existing_data );

				if ( null !== $attachment ) {
					$attachments[ $attachment['ID'] ] = $attachment;
					$attachments[ $attachment['meta_input'][ NNEImportMetaEnum::IMAGE_EDITORIAL_ID_KEY->value ] ] = $attachments[ $attachment['ID'] ];
					if ( array_key_exists( NNEImportMetaEnum::IMAGE_DATA_ID_KEY->value, $attachment['meta_input'] ) ) {
						$attachments[ 'dId' . $attachment['meta_input'][ NNEImportMetaEnum::IMAGE_DATA_ID_KEY->value ] ] = $attachments[ $attachment['ID'] ];
					}
				}
			}

			return $attachments;
		}

		$image_object = new stdClass();
		foreach ( $images->childNodes as $child ) {
			$image_object->{$child->nodeName} = $child->hasChildNodes() ? $this->get_inner_contents( $child, 'xml' ) : $child->nodeValue;
		}

		$image_helper = new NNEImageHelper(
			$image_object->fileattachment,
			$this->host_url,
			$this->path_to_images,
			$image_object->GN3EditorialKey,
		);

		if ( null === $image_helper->get_best_path() ) {
			ConsoleColor::white( "\t-" )
						->magenta( 'Skipping - Unable to find image -' )
						->white( 'Filename:' )
						->bright_yellow( $image_object->fileattachment ?? '' )
						->output();
			return null;
		}

		$attachment_id = $this->get_attachment_id( $image_helper );

		if ( null !== $attachment_id ) {
			$attachment_data = $this->get_post_data( $attachment_id );

			if ( $update_existing_data ) {
				$post_title   = $image_object->DocumentName;
				$post_content = $image_object->filecaption;

				$import_data = [
					'post_title'   => $post_title,
					'post_content' => $post_content,
					'post_date'    => ( new DateTimeImmutable( $image_object->ModificationDate ) )->format( 'Y-m-d H:i:s' ),
					'post_excerpt' => $image_object->filecaption,
					'meta_input'   => [],
				];

				if ( ! empty( trim( $image_object->filecredit ) ) ) {
					$import_data['meta_input']['_media_credit'] = $image_object->filecredit;
				}

				$result = $this->raw_update_post( $attachment_id, $import_data, $attachment_data );

				if ( null === $result ) {
					ConsoleColor::white( "\t-" )->white( 'Update:' )->yellow( 'None' )->output();
				} elseif ( $result ) {
					ConsoleColor::white( "\t-" )->white( 'Update:' )->bright_green( 'Success' )->output();
					// If there was some update, let's get the freshest post data from the DB.
					return $this->get_post_data( $attachment_id );
				} else {
					ConsoleColor::white( "\t-" )->white( 'Update:' )->bright_red( 'Failed' )->output();
				}
			}

			return $attachment_data;
		}

		ConsoleColor::white( "\t-" )->white( 'File Name:' )
									->bright_yellow( $image_object->DocumentName ?? '' )
									->white( 'Editorial Key:' )
									->bright_yellow( $image_object->GN3EditorialKey ?? '' )
									->white( 'Data ID:' )
									->bright_yellow( $image_helper->has_data_id() ? $image_helper->get_data_id() : '' )
									->white( 'Exists Locally:' )
									->bright_yellow( $image_helper->exists_in_media_library() ? 'Yes' : 'No' )
									->output();
		$attachment_meta = [
			'meta_input' => [],
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			'post_date'  => ( new DateTimeImmutable( $image_object->ModificationDate ) )->format( 'Y-m-d H:i:s' ),
		];

		if ( ! empty( trim( $image_object->filecredit ) ) ) {
			$attachment_meta['meta_input']['_media_credit'] = $image_object->filecredit;
		}

		$maybe_attachment_id = Attachments::import_external_file(
			$image_helper->get_best_path(),
			$image_object->DocumentName, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$image_object->filecaption,
			$image_object->filecaption,
			$image_object->filecaption,
			0,
			$attachment_meta,
			$image_helper->get_file_name()
		);

		if ( is_wp_error( $maybe_attachment_id ) ) {
			ConsoleColor::white( "\t-" )
						->red( 'Error importing image -' )
						->white( 'Filename:' )
						->bright_red( $image_object->fileattachment ?? '' )
						->white( 'Error:' )
						->bright_red( $maybe_attachment_id->get_error_message() )
						->output();

			return null;
		}

		add_post_meta( $maybe_attachment_id, NNEImportMetaEnum::IMAGE_EDITORIAL_ID_KEY->value, $image_object->GN3EditorialKey );
		add_post_meta( $maybe_attachment_id, NNEImportMetaEnum::IMAGE_DOC_NAME_KEY->value, $image_object->DocumentName );
		add_post_meta( $maybe_attachment_id, NNEImportMetaEnum::IMAGE_INCLUDE_IN_GALLERY_KEY->value, strtolower( $image_object->includeingallery ?? '' ) === 'true' );
		add_post_meta( $maybe_attachment_id, NNEImportMetaEnum::IMAGE_INLINE_KEY->value, strtolower( $image_object->InlineImage ?? '' ) === 'true' );
		add_post_meta( $maybe_attachment_id, NNEImportMetaEnum::IMAGE_POSITION_KEY->value, $image_object->InlineImageLocation );
		if ( $image_helper->has_data_id() ) {
			add_post_meta( $maybe_attachment_id, NNEImportMetaEnum::IMAGE_DATA_ID_KEY->value, $image_helper->get_data_id() );
		}

		return $this->get_post_data( $maybe_attachment_id );
	}

	/**
	 * Helper function to ensure that the required directory paths exist before attempting to migrate content.
	 *
	 * @return array{ string, bool }
	 */
	private function get_list_of_existing_required_paths(): array {
		$this->path_to_images = $this->naming_map->get_directory_path( $this->publisher ) . '/images';
		$this->path_to_xmls   = $this->naming_map->get_directory_path( $this->publisher ) . "/$this->year/xmls";

		$required_paths = [
			$this->path_to_images => null,
			$this->path_to_xmls   => null,
		];

		foreach ( $required_paths as $path => &$exists ) {
			$exists = is_dir( $path );
		}

		return $required_paths;
	}

	/**
	 * Helper function to retrieve the inner contents of a DOM element.
	 *
	 * @param DOMElement $element DOM element.
	 * @param string     $type Type of content to retrieve (html or xml).
	 *
	 * @return string
	 */
	private function get_inner_contents( DOMElement $element, string $type ): string {
		$inner_contents = '';

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		foreach ( $element->childNodes as $node ) {
			if ( 'html' === $type ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$inner_contents .= $node->ownerDocument->saveHTML( $node );
			} else {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$inner_contents .= $node->ownerDocument->saveXML( $node );
			}
		}

		return $inner_contents;
	}

	/**
	 * Helper function to perform a raw post update.
	 *
	 * @param int   $post_id       Post ID.
	 * @param array $new_post_data New post data.
	 * @param array $db_post_data Existing post data (optional).
	 * @param bool  $delete_existing_terms Delete existing terms for the post.
	 *
	 * @return bool|null
	 */
	private function raw_update_post( int $post_id, array $new_post_data, array $db_post_data = [], bool $delete_existing_terms = false ): ?bool {
		if ( empty( $new_post_data ) ) {
			return false;
		}

		if ( empty( $db_post_data ) ) {
			$db_post_data = $this->get_post_data( $post_id );
		}

		$new_tax_data  = $new_post_data['tax_input'] ?? [];
		$new_meta_data = $new_post_data['meta_input'] ?? [];
		unset( $new_post_data['tax_input'], $new_post_data['meta_input'] );

		$db_tax_data  = $db_post_data['tax_input'] ?? [];
		$db_meta_data = $db_post_data['meta_input'] ?? [];
		unset( $db_post_data['tax_input'], $db_post_data['meta_input'] );

		if ( array_key_exists( 'post_name', $new_post_data ) ) {
			$new_post_data['post_name'] = sanitize_title( untrailingslashit( $new_post_data['post_name'] ) );
		}

		$post_data_diff = array_diff_assoc( $new_post_data, $db_post_data );

		$maybe_post_updated = null;
		if ( ! empty( $post_data_diff ) ) {
			$maybe_post_updated = (bool) $this->wpdb->update(
				$this->wpdb->posts,
				$post_data_diff,
				[
					'ID' => $post_id,
				]
			);
		}

		$maybe_tax_updated        = null;
		$new_category_and_tag_ids = array_merge( $new_tax_data['category'] ?? [], $new_tax_data['post_tag'] ?? [] );
		if ( ( false !== $maybe_post_updated && ! empty( $new_category_and_tag_ids ) ) || $delete_existing_terms ) {
			$db_category_and_tag_ids = array_merge( $db_tax_data['category'] ?? [], $db_tax_data['post_tag'] ?? [] );
			$delete_term_ids         = array_diff( $db_category_and_tag_ids, $new_category_and_tag_ids );
			$set_term_ids            = array_diff( $new_category_and_tag_ids, $db_category_and_tag_ids );

			if ( ! empty( $delete_term_ids ) ) {
				$placeholders_for_term_ids = implode( ',', array_fill( 0, count( $delete_term_ids ), '%d' ) );

				// phpcs:disable -- properly escaped and prepared.
				$maybe_tax_updated = (bool) $this->wpdb->query(
					$this->wpdb->prepare(
						"DELETE FROM {$this->wpdb->term_relationships} 
						WHERE object_id = %d 
						  AND term_taxonomy_id IN (
							SELECT term_taxonomy_id 
							FROM {$this->wpdb->term_taxonomy} 
							WHERE term_id IN ({$placeholders_for_term_ids})
						)",
						$post_id,
						...$delete_term_ids
					)
				);
				// phpcs:enable
			}

			if ( $maybe_tax_updated ) {
				$term_set = true;
				foreach ( $set_term_ids as $term_id ) {
					if ( $term_set ) {
						$term_set = (bool) $this->wpdb->insert(
							$this->wpdb->term_relationships,
							[
								'object_id'        => $post_id,
								'term_taxonomy_id' => $term_id,
							]
						);
					}
				}

				$maybe_tax_updated = $term_set;
			}
		}

		$meta_data_diff = array_diff_assoc( $new_meta_data, $db_meta_data );

		$maybe_meta_updated = null;
		if ( ! empty( $meta_data_diff ) && false !== $maybe_post_updated ) {
			$all_meta_data_updated = [];
			foreach ( $meta_data_diff as $meta_key => $meta_value ) {
				if ( array_key_exists( $meta_key, $db_meta_data ) ) {
					$all_meta_data_updated[] = (bool) $this->wpdb->update(
						$this->wpdb->postmeta,
						[
							// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
							'meta_value' => $meta_value,
						],
						[
							'post_id'  => $post_id,
							'meta_key' => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						]
					);
				} else {
					$all_meta_data_updated[] = (bool) $this->wpdb->insert(
						$this->wpdb->postmeta,
						[
							'post_id'    => $post_id,
							'meta_key'   => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
							'meta_value' => $meta_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						]
					);
				}
			}
			$maybe_meta_updated = array_reduce( $all_meta_data_updated, fn( $a, $b ) => $a && $b, true );
		}

		$update_attempts = [
			$maybe_post_updated,
			$maybe_tax_updated,
			$maybe_meta_updated,
		];
		$update_attempts = array_filter( $update_attempts, fn( $attempt ) => null !== $attempt );

		if ( empty( $update_attempts ) ) {
			return null;
		}

		return array_reduce( $update_attempts, fn( $a, $b ) => $a && $b, true );
	}

	/**
	 * Returns a list of all post IDs associated with a specific term taxonomy.
	 *
	 * @param int $term_taxonomy_id Term taxonomy ID.
	 *
	 * @return array
	 */
	private function get_associated_post_ids( int $term_taxonomy_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT object_id FROM $wpdb->term_relationships tr WHERE term_taxonomy_id = %d",
				$term_taxonomy_id
			),
		);
	}

	/**
	 * Adds a specific term taxonomy to a list of post IDs.
	 *
	 * @param int   $term_taxonomy_id Term taxonomy ID.
	 * @param array $post_ids List of post IDs.
	 *
	 * @return bool
	 */
	private function add_taxonomy_to_post_ids( int $term_taxonomy_id, array $post_ids ): bool {
		global $wpdb;

		$currently_associated_post_ids = $this->get_associated_post_ids( $term_taxonomy_id );
		$post_ids                      = array_diff( $post_ids, $currently_associated_post_ids );

		$count_of_posts              = 0;
		$count_of_successful_inserts = 0;
		foreach ( $post_ids as $post_id ) {
			++$count_of_posts;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$maybe_category_added = $wpdb->insert(
				$wpdb->term_relationships,
				[
					'object_id'        => $post_id,
					'term_taxonomy_id' => $term_taxonomy_id,
				]
			);

			if ( false !== $maybe_category_added ) {
				++$count_of_successful_inserts;
			}
		}

		return $count_of_posts === $count_of_successful_inserts;
	}
}
