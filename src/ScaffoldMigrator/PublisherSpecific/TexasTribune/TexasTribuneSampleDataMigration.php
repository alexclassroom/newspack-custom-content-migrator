<?php
namespace NewspackCustomContentMigrator\ScaffoldMigration\PublisherSpecific\TexasTribune;

use Exception;
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Scaffold\Contracts\Migration;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationDataChest;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationState;
use Newspack\MigrationTools\Scaffold\Contracts\RunAwareMigrationObject;
use Newspack\MigrationTools\Scaffold\JSONMigrationDataChest;
use Newspack\MigrationTools\Scaffold\JSONDirectoryMigrationDataChest;
use Newspack\MigrationTools\Scaffold\MigrationObject;
use Newspack\MigrationTools\Scaffold\MigrationObjectPropertyWrapper;
use Newspack\MigrationTools\Scaffold\RunAwareMigrationObjectWrapper;
use Newspack\MigrationTools\Scaffold\WordPressData\WordPressPostsData;
use Newspack\MigrationTools\Scaffold\WordPressData\WordPressUsersData;
use NewspackCustomContentMigrator\Utils\ConsoleColor;
use WP_Error;
use WP_User;

/**
 * SampleDataMigration (for Texas Tribune) class.
 */
class TexasTribuneSampleDataMigration implements Migration {

	/**
	 * Class which helps generate Gutenberg Blocks.
	 *
	 * @var GutenbergBlockGenerator $block_generator Custom Gutenberg Block Generator.
	 */
	private GutenbergBlockGenerator $block_generator;

	/**
	 * Cache for sponsors data.
	 *
	 * @var array<string,array{name:string,url:string}>|null
	 */
	private ?array $sponsors_data = null;

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
	 * Constructor.
	 */
	public function __construct() {
		$this->block_generator = new GutenbergBlockGenerator();
	}

	/**
	 * Sets the name of this particular command.
	 *
	 * @param string $name Command name.
	 *
	 * @return void
	 */
	public function set_name( string $name ): void {
		// TODO: Implement set_name() method.
	}

	/**
	 * Returns the name of this particular command.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'Sample_Data_Migration';
	}

	/**
	 * This function houses the logic for the command.
	 *
	 * @param RunAwareMigrationObject $migration_object The object to perform the migration on.
	 *
	 * @return bool|MigrationState|WP_Error|null
	 * @throws Exception If an error occurs.
	 */
	public function command( RunAwareMigrationObject $migration_object ): bool|MigrationState|WP_Error|null {
		ConsoleColor::white( 'Processing:' )
					->bright_white( '(' )
					->bright_yellow( $migration_object->get_data_id() )
					->bright_white( ')' )
					->bright_white( $migration_object->title->get_value() )
					->output();

		$posts_data = new WordPressPostsData();
		$posts_data->set_migration_object( $migration_object );

		// Fetch all series data.
		$this->fetch_all_series_data();

		// Fetch all tags data.
		$this->fetch_all_tags_data();

		// Handle Authors first.
		foreach ( $migration_object['metadata']['authors'] as $author ) {
			/* @var $author MigrationObjectPropertyWrapper */ // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			ConsoleColor::white( 'Processing author:' )->bright_white( $author['name'] )->output();
			$user = $this->get_user_by_display_name( $author['name'] );

			if ( ! $user ) {
				$users_data = new WordPressUsersData();

				$author_migration_object = new RunAwareMigrationObjectWrapper(
					new MigrationObject(
						$author->get_value(),
						'id',
						$migration_object->get_data_chest()
					),
					$migration_object->get_run_key()
				);

				$users_data->set_migration_object( $author_migration_object );

				$users_data->set_display_name( $author['name'] );
				if ( 1547 === intval( $author['id']->get_value() ) ) { // Display Name: The Texas Tribune Staff, and the staffs of The Texas Newsroom, Fort Worth Report and Amarillo Tribune.
					$users_data->set_user_email( 'multiple-staffs@example.com' );
					$users_data->set_user_login( 'multiple-staffs' );
					$users_data->set_user_nicename( 'texas-tribune-newsroom-fort-worth-amarillo-staffs' );
				}

				$user_create_result = $users_data->create();

				if ( is_wp_error( $user_create_result ) ) {
					ConsoleColor::red( 'Error creating author (' )->bright_red( $user_create_result->get_error_code() )->red( '):' )->underlined_bright_red( $user_create_result->get_error_message() )->output();
					// TODO replace with FailedMigrationState
					return null;
				} else {
					ConsoleColor::green( 'Author created: ' )->bright_green( $user_create_result )->output();
					$user = get_user_by( 'ID', $user_create_result );
					$author_migration_object->mark_as_processed();
				}
			} else {
				ConsoleColor::bright_white( 'Author already exists:' )
							->bright_green( $user->display_name )
							->green( 'ID:' )
							->bright_green( $user->ID )
							->output();
			}

			$posts_data->add_author( $user );
			$posts_data->set_post_author( $user );
		}

		$posts_data->set_post_title( $migration_object->metadata->headline );

		$post_status_value = $migration_object->metadata->is_published->get_value() ? 'publish' : 'draft';
		$post_status_value = new MigrationObjectPropertyWrapper(
			$post_status_value,
			explode( '.', $migration_object->metadata->is_published->get_path() ),
			$migration_object
		);

		$post_name_value = \WP_CLI\Utils\basename( $migration_object->metadata->article_url->get_value() );

		if ( ! empty( $post_name_value ) ) {
			$posts_data->set_post_name(
				new MigrationObjectPropertyWrapper(
					$post_name_value,
					explode( '.', $migration_object->metadata->article_url->get_path() ),
					$migration_object
				)
			);
		}

		// TODO - sponsorcontent - record the sponsor information somewhere if it's not obvious from either the byline or some other metadata and migrate these into Newspack sponsors.
		match ( $migration_object->metadata->type->get_value() ) {
			'article', 'sponsorcontent' => $posts_data->set_post_type(
				new MigrationObjectPropertyWrapper(
					'post',
					explode( '.', $migration_object->metadata->type->get_path() ),
					$migration_object
				)
			),
			'flatpage' => $posts_data->set_post_type(
				new MigrationObjectPropertyWrapper(
					'page',
					explode( '.', $migration_object->metadata->type->get_path() ),
					$migration_object
				)
			),
		};

		try {
			$posts_data->set_post_date( $migration_object->metadata->date_created );
			$posts_data->set_post_modified( $migration_object->metadata->date_created );

			if ( $migration_object->metadata->date_published ) {
				$posts_data->set_post_date( $migration_object->metadata->date_published ); // Using MigrationObjectPropertyWrapper so that data source can be properly recorded.
			}

			if ( $migration_object->metadata->date_modified ) {
				$posts_data->set_post_modified( $migration_object->metadata->date_modified );
			}

			$maybe_post_id = $posts_data->set_post_status( $post_status_value )
										->set_post_excerpt( $migration_object->metadata->summary )
										->create();
		} catch ( \Exception $e ) {
			$maybe_post_id = $posts_data->get_post_id_from_legacy_id( $migration_object->get_data_id() );
		}

		if ( is_wp_error( $maybe_post_id ) ) {
			ConsoleColor::red( 'Error creating post (' )->bright_red( $maybe_post_id->get_error_code() )->red( '):' )->underlined_bright_red( $maybe_post_id->get_error_message() )->output();
			// TODO replace with FailedMigrationState
			return null;
		}

		// Handle sponsor if present.
		$sponsor_id = isset( $migration_object->metadata->sponsor ) ? $migration_object->metadata->sponsor->get_value() : null;
		if ( $sponsor_id ) {
			$sponsor_term_id = $this->get_sponsor_data( $sponsor_id );

			if ( $sponsor_term_id ) {
				// Link the sponsor to the post using the taxonomy.
				wp_set_object_terms( $maybe_post_id, [ $sponsor_term_id ], self::SPONSOR_TAXONOMY );
			}
		}

		// Handle tags if present.
		$user_facing_tags = $migration_object->metadata->user_facing_tags->get_value() ?? [];
		if ( ! empty( $user_facing_tags ) ) {
			$this->handle_post_tags( $maybe_post_id, $user_facing_tags );
		}

		// Let's handle the featured image here.
		$featured_image                         = $migration_object->metadata->share_image ?? null;
		$maybe_featured_image_attachment_object = null;
		if ( $featured_image ) {
			$maybe_featured_image_attachment_object = $this->handle_image_import(
				$featured_image->url->get_value(),
				null,
				null,
				$featured_image->photo_description->get_value()
			);

			// If there was some issue with the image download, this will be a WP_Error. In that case,
			// let's just set it to null, so that we can simplify the method signatures
			// and logic that will need this object down the line from here.
			if ( is_wp_error( $maybe_featured_image_attachment_object ) ) {
				$maybe_featured_image_attachment_object = null;
			} else {
				set_post_thumbnail( $maybe_post_id, $maybe_featured_image_attachment_object->attachment_id );
			}
		}

		update_post_meta( $maybe_post_id, 'newspack_show_updated_date', 1 );

		if ( $migration_object->metadata->summary ) {
			update_post_meta( $maybe_post_id, 'newspack_post_subtitle', $migration_object->metadata->summary->get_value() );
		}

		$post_content_value = $this->get_post_content_by_handling_components( $migration_object->components, $maybe_post_id, $maybe_featured_image_attachment_object );
		$posts_data->set_migration_object( $migration_object );
		$maybe_updated = $posts_data->set_id( $maybe_post_id )->set_post_content( $post_content_value )->update();

		if ( $maybe_updated ) {
			$migration_object->mark_as_processed();
		}

		// Handle series if present.
		$series = $migration_object->metadata->series->get_value() ?? [];
		if ( ! empty( $series ) ) {
			$this->handle_post_series( $maybe_post_id, $series );
		}

		return null;
	}

	/**
	 * Returns the migration objects.
	 *
	 * @return MigrationDataChest
	 */
	public function get_data_chest(): MigrationDataChest {
		return new JSONDirectoryMigrationDataChest( '/var/www/html/exports/', 'identifier' );
		// return new JSONDirectoryMigrationDataChest( '/var/www/html/2024-11-21_texas_tribune_all_sample_articles.json', 'identifier' );
	}

	/**
	 * Return fully formed HTML from the supplied migration components.
	 *
	 * @param MigrationObjectPropertyWrapper             $components Components to process.
	 * @param int                                        $post_id Post ID.
	 * @param TexasTribuneAttachmentMetadataObject| null $featured_image_object Object representing a featured image.
	 *
	 * @return string
	 */
	private function get_post_content_by_handling_components( MigrationObjectPropertyWrapper $components, int $post_id, ?TexasTribuneAttachmentMetadataObject $featured_image_object ): string {
		$content = '';

		$previous = null;
		foreach ( $components as $key => $component ) {
			$next     = $components[ $key + 1 ] ?? null;
			$content .= $this->handle_component( $component, $post_id, $previous, $next, $featured_image_object );
			$previous = $component;
		}

		return $content;
	}

	/**
	 * This function handles specific components and returns the HTML.
	 *
	 * @param MigrationObjectPropertyWrapper            $component Component to process.
	 * @param int                                       $post_id Post ID.
	 * @param MigrationObjectPropertyWrapper|null       $previous_sibling Previous sibling component.
	 * @param MigrationObjectPropertyWrapper|null       $next_sibling Next sibling component.
	 * @param TexasTribuneAttachmentMetadataObject|null $featured_image The featured image set for the post.
	 *
	 * @return string
	 */
	private function handle_component( MigrationObjectPropertyWrapper $component, int $post_id, ?MigrationObjectPropertyWrapper $previous_sibling, ?MigrationObjectPropertyWrapper $next_sibling, ?TexasTribuneAttachmentMetadataObject $featured_image ): string {
		switch ( strtolower( $component->role->get_value() ) ) {
			case 'header':
				return '';
			case 'container':
			case 'text container':
			case 'thumbnail container':
			case 'thumbnail text container':
			case 'thumbnail entry':
			case 'sections':
			case 'sections container':
				return $this->get_post_content_by_handling_components( $component->components, $post_id, $featured_image );
			case 'sections entry container':
				return $this->handle_sections_entry_container( $component, $post_id );
			case 'text':
				if ( $component->text && '* * *' === $component->text->get_value() ) {
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
				foreach ( $component->components as $sub_component ) {
					$this->handle_correction_component( $sub_component, $post_id, true );
				}

				return '';
			case 'normal corrections container':
				foreach ( $component->components as $sub_component ) {
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
				foreach ( $component->components as $sub_component ) {
					$established_path             = explode( '.', $sub_component->get_path() );
					$last_key                     = $established_path[ count( $established_path ) - 1 ];
					$modded_component             = $sub_component->get_value();
					$modded_component['location'] = $component->location->get_value();
					$modded_component             = new MigrationObjectPropertyWrapper( [ $last_key => $modded_component ], $established_path, $component->get_migration_object() );
					$thumbnail_block             .= $this->handle_photo_component(
						$modded_component,
						$post_id,
						$featured_image
					);
				}

				return $thumbnail_block;
			case 'data graphic':
				return $this->handle_data_graphic_component( $component, $next_sibling, $post_id );
			case 'video':
				return $this->handle_video_component( $component, $next_sibling, $post_id );
			case 'iframe':
				return $this->handle_iframe_component( $component );
			case 'tweet':
				return serialize_block( $this->block_generator->get_twitter( $component->url->get_value() ) );
			case 'caption':
				if ( $previous_sibling ) {
					if ( 'photo' === $previous_sibling->role->get_value() && $component->text->get_value() === $previous_sibling->caption->get_value() ) {
						return '';
					}

					$omit_caption_if_previous_sibling_matched_role = [
						'audio',
						'data graphic',
						'video',
					];

					if ( in_array( $previous_sibling->role->get_value(), $omit_caption_if_previous_sibling_matched_role, true ) ) {
						return '';
					}
				}

				return serialize_block( $this->handle_caption_component( $component ) );
			case 'audio':
				return $this->handle_audio_component( $component );
			case 'document link':
				return $this->handle_document_link_component( $component );
			case 'series list':
				return $this->handle_series_list_component( $component );
			case 'series snippet':
				return $this->handle_series_snippet_component( $component );
			case 'cta membership':
				return $this->handle_cta_membership_component( $component, $post_id );
			case 'simple cta':
				return $component->text ? $component->text->get_value() : '';
			case 'faq container':
				return $this->handle_faq_container_component( $component );
			case 'faq entry':
				return $this->handle_faq_entry_component( $component );
			case 'newsletter signup':
				return $this->handle_newsletter_signup_component( $component );
			case 'divider':
				return serialize_block(
					$this->block_generator->get_separator( 'is-style-wide' )
				);
			case 'pulitzer':
				return $this->handle_pulitzer_component( $component );
			default:
				ConsoleColor::yellow( 'Skipped Component' )->underlined_yellow( $component->role->get_value() )->output();
				return '';
		}
	}

	/**
	 * Handles getting a gutenberg paragraph HTML block from a text component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_text_component( MigrationObjectPropertyWrapper $component ): string {
		return serialize_block( $this->block_generator->get_paragraph( $component->text->get_value() ) );
	}

	/**
	 * Handles getting a gutenberg heading HTML block From a heading component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_heading_component( MigrationObjectPropertyWrapper $component ): string {
		return serialize_block(
			$this->block_generator->get_heading(
				$component->text->get_value(),
				'h' . $component->level->get_value(),
				$component->identifier ? $component->identifier->get_value() : '',
			)
		);
	}

	/**
	 * Handles storing metadata for use with a custom Newspack corrections plugin.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 * @param int                            $post_id Post ID.
	 * @param bool                           $important Whether the correction is important.
	 *
	 * @return void
	 */
	private function handle_correction_component( MigrationObjectPropertyWrapper $component, int $post_id, bool $important = false ): void {
		$correction = [
			'place_up_top' => $important,
			'date'         => '',
			'timestamp'    => '',
			'correction'   => '',
		];

		$text = $component->text->get_value();

		// TODO - perhaps we can ask TT to provide structured time data for when correction was made instead of this string parsing.
		$doc = new \DOMDocument();

		$matches = [];
		$pattern = '/<strong>(.*?)<\/strong>/';
		preg_match( $pattern, $text, $matches );

		if ( ! empty( $matches ) ) {
			$doc->loadHTML( $matches[0] );
		} else {
			$doc->loadHTML( $text );
		}

		$timestamp = $doc->getElementsByTagName( 'time' )->item( 0 );

		if ( $timestamp && $timestamp instanceof \DOMElement ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$correction['date'] = $timestamp->nodeValue;
		} else {
			ConsoleColor::bright_magenta( 'Unable to find date' )->output();
		}

		if ( $timestamp->getAttribute( 'datetime' ) ) {
			$correction['timestamp'] = $timestamp->getAttribute( 'datetime' );

			if ( ! empty( $correction['timestamp'] ) && str_contains( $correction['timestamp'], '.' ) ) {
				$correction['timestamp'] = substr(
					$correction['timestamp'],
					0,
					strpos( $correction['timestamp'], '.' )
				);
			}
		} else {
			ConsoleColor::bright_magenta( 'Unable to find timestamp' )->output();
		}

		if ( ! empty( $matches ) ) {
			$text = str_replace( $matches[0], '', $text );
		}

		$correction['correction'] = $text;
		update_post_meta( $post_id, 'has_corrections', true );
		update_post_meta( $post_id, 'article-corrections', $correction );
	}

	/**
	 * Handles getting HTML from an archival element component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_archival_element_component( MigrationObjectPropertyWrapper $component ): string {
		$allowed_html = wp_kses_allowed_html( 'post' );

		unset( $allowed_html['script'] );

		return wp_kses( $component->content->get_value(), $allowed_html, wp_allowed_protocols() );
	}

	/**
	 * Handles getting a gutenberg raw HTML block from a raw element component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_raw_element_compontent( MigrationObjectPropertyWrapper $component ): string {
		$raw_element = $component->content->get_value();

		if ( $component['css content'] ) {
			$raw_element .= $component['css content']->get_value();
		}

		return serialize_block( $this->block_generator->get_html( $raw_element ) );
	}

	/**
	 * Handles getting a gutenberg pullquote HTML block from a pullquote component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_pullquote_component( MigrationObjectPropertyWrapper $component ): string {
		return serialize_block(
			$this->block_generator->get_quote(
				$component->text->get_value(),
				$component->attribution->get_value()
			)
		);
	}

	/**
	 * Handles getting a gutenberg list HTML block from a list component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_list_component( MigrationObjectPropertyWrapper $component ): string {
		$array_list = $this->get_list_array( $component->text->get_value() );

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
	 * Handle getting a gutenberg HTML block showing a related post.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 * @throws Exception If more than one Post ID exists for the given Legacy ID.
	 */
	private function handle_related_link_component( MigrationObjectPropertyWrapper $component ): string {
		$wordpress_posts_data   = new WordPressPostsData();
		$post_id_from_legacy_id = $wordpress_posts_data->get_post_id_from_legacy_id( $component->article_id->get_value() );

		if ( null !== $post_id_from_legacy_id ) {
			return serialize_block(
				$this->block_generator->get_homepage_articles_for_specific_posts(
					[ $post_id_from_legacy_id ],
					[
						'postsToShow'   => 1,
						'showAuthor'    => false,
						'showCaption'   => true,
						'sectionHeader' => $component->kicker ? $component->kicker->get_value : 'Related Story',
					]
				)
			);
		}

		$paragraph_block = $this->block_generator->get_paragraph(
			'<a href="' . $component->article_url->get_value() . '">' . $component->article_headline->get_value() . '</a>',
		);

		$paragraph_block['attrs'] = array_merge(
			$paragraph_block['attrs'],
			[
				'original-related-story-id' => $component->article_id->get_value(),
			]
		);

		return serialize_block( $paragraph_block );
	}

	/**
	 * Handles getting a gutenberg image HTML block from a photo component.
	 *
	 * @param MigrationObjectPropertyWrapper            $component Component to process.
	 * @param int                                       $post_id Post ID.
	 * @param TexasTribuneAttachmentMetadataObject|null $featured_image The featured image set for the post.
	 *
	 * @return string
	 */
	private function handle_photo_component( MigrationObjectPropertyWrapper $component, int $post_id, ?TexasTribuneAttachmentMetadataObject $featured_image ): string {
		// If the (photo) component has already been imported as a featured image, we don't want to import it again.
		if ( null !== $featured_image ) {
			$photo_attachment_object = new TexasTribuneAttachmentMetadataObject( $component->url->get_value() );

			// Not sure if we'd need to do any additional tests to determine if the photo has already been imported as a featured image.
			$decoded_download_url = $photo_attachment_object->decoded_download_url === $featured_image->decoded_download_url;

			if ( $decoded_download_url ) {
				$attachment_data = new WordPressPostsData();
				$attachment_data->set_id( $featured_image->attachment_id );

				$attachment = get_post( $featured_image->attachment_id );

				if ( $component->caption && $attachment->post_excerpt !== $component->caption->get_value() ) {
					$attachment_data->set_post_excerpt( $component->caption );
				}

				if ( $component->file_description ) {
					$attachment_data->set_post_content( $component->file_description );
				} elseif ( $component->photo_description ) {
					$attachment_data->set_post_content( $component->photo_description );
				}

				$migration_object = $component->get_migration_object();
				if ( $migration_object instanceof RunAwareMigrationObject ) {
					$attachment_data->set_migration_object(
						new RunAwareMigrationObjectWrapper(
							new MigrationObject(
								array_merge(
									$component->get_value(),
									[
										'decoded_file_name' => $photo_attachment_object->decoded_file_name,
									],
								),
								'url',
								$migration_object->get_data_chest()
							),
							$migration_object->get_run_key()
						)
					);

					$attachment_data->update();
				}

				return '';
			}
		}

		$maybe_attachment_id = $this->handle_image_import_and_import_metadata( $component, $post_id );

		if ( is_wp_error( $maybe_attachment_id ) ) {
			return '';
		}

		$image_size = 'full';

		if ( $component->location ) {
			switch ( strtolower( $component->location->get_value() ) ) {
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
					$alignment = 'wide';
					break;
			}
		}

		return serialize_block( $this->block_generator->get_image( get_post( $maybe_attachment_id ), $image_size, true, null, $alignment ) );
	}

	/**
	 * Handles getting a gutenberg jetpack tiled gallery HTML block from a mosaic component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 * @param int                            $post_id Post ID.
	 *
	 * @return string
	 */
	private function handle_mosaic_component( MigrationObjectPropertyWrapper $component, int $post_id ): string {
		$attachment_ids = [];
		foreach ( $component->items as $item ) {
			$maybe_attachment_id = $this->handle_image_import_and_import_metadata( $item, $post_id );

			if ( is_wp_error( $maybe_attachment_id ) ) {
				return '';
			}

			$attachment_ids[] = $maybe_attachment_id;
		}

		return serialize_block( $this->block_generator->get_jetpack_tiled_gallery( $attachment_ids, 'attachment' ) );
	}

	/**
	 * Handles getting a gutenberg image or iFrame HTML block from a data graphic component.
	 *
	 * @param MigrationObjectPropertyWrapper      $component Component to process.
	 * @param MigrationObjectPropertyWrapper|null $next_sibling Next sibling component.
	 * @param int                                 $post_id Post ID.
	 *
	 * @return string
	 * @throws Exception If the author is not a valid user.
	 */
	private function handle_data_graphic_component( MigrationObjectPropertyWrapper $component, ?MigrationObjectPropertyWrapper $next_sibling, int $post_id ): string {
		/*
		if ( $component->use_static_graphic->get_value() ) {
			$attachment_metadata = [];

			if ( $next_sibling && 'caption' === $next_sibling->role->get_value() ) {
				$attachment_metadata['post_excerpt'] = $next_sibling->text->get_value();
			}

			$maybe_attachment_id = Attachments::import_external_file(
				$component->url->get_value(),
				$component->title ? $component->title->get_value() : null,
				$attachment_metadata['post_excerpt'] ?? null,
				$component->source ? $component->source->get_value() : null,
				null,
				$post_id,
				$attachment_metadata
			);

			if ( is_wp_error( $maybe_attachment_id ) ) {
				ConsoleColor::red( 'Error creating attachment (' )->bright_red( $maybe_attachment_id->get_error_code() )->red( '):' )->underlined_bright_red( $maybe_attachment_id->get_error_message() )->output();
				return '';
			}

			$authors = array_map(
				fn( $display_name ) => $this->get_user_by_display_name( $display_name ),
				$component->authors->get_value()
			);

			$migration_object = $component->get_migration_object();
			if ( ! empty( $authors ) && $migration_object instanceof RunAwareMigrationObject ) {
				$attachment_post = new WordPressPostsData();
				$attachment_post->set_migration_object(
					new RunAwareMigrationObjectWrapper(
						new MigrationObject(
							$component->get_value(),
							'url',
							$migration_object->get_container()
						),
						$migration_object->get_run_key()
					)
				);

				$attachment_post->set_id( $maybe_attachment_id )
								->set_authors( $authors )
								->update();
			}

			$image_size = 'large';
			$alignment  = 'center';

			if ( $component->location ) {
				switch ( strtolower( $component->location->get_value() ) ) {
					case 'left':
						$alignment = 'left';
						break;
					case 'right':
						$alignment = 'right';
						break;
					case 'giant':
						$alignment = 'full';
						break;
				}
			}

			return serialize_block( $this->block_generator->get_image( get_post( $maybe_attachment_id ), $image_size, true, null, $alignment ) );
		}*/

		return $this->handle_iframe_component( $component );
	}

	/**
	 * Handles getting a gutenberg video HTML block from a video component.
	 *
	 * @param MigrationObjectPropertyWrapper      $component Component to process.
	 * @param MigrationObjectPropertyWrapper|null $next_sibling Next sibling component.
	 * @param int                                 $post_id Post ID.
	 *
	 * @return string
	 */
	private function handle_video_component( MigrationObjectPropertyWrapper $component, ?MigrationObjectPropertyWrapper $next_sibling, int $post_id ): string {
		$caption_block = [];

		if ( $component->caption && ! empty( $component->caption->get_value() ) ) {
			$caption_block = $this->handle_caption_component(
				new MigrationObjectPropertyWrapper(
					[
						'text' => $component->caption->get_value(),
					],
					[ 'text' ],
					$component->get_migration_object()
				)
			);
		} elseif ( $next_sibling ) {
			if ( 'caption' === $next_sibling->role->get_value() ) {
				$caption_block = $this->handle_caption_component( $next_sibling );
			}
		}

		switch ( $component->player_type->get_value() ) {
			case 'youtube':
				$youtube_block = $this->block_generator->get_youtube(
					$component->external_id ?
						$component->external_id->get_value() :
						$component->url->get_value()
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
			default:
				ConsoleColor::bright_magenta( 'Different video type needs attention!' )->output();

				$maybe_attachment_id = Attachments::import_external_file(
					$component->url->get_value(),
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
	 * Handles getting a gutenberg iFrame HTML block from an iFrame component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_iframe_component( MigrationObjectPropertyWrapper $component ): string {
		$width  = $component->width ? $component->width->get_value() : null;
		$height = $component->height ? $component->height->get_value() : null;

		// TODO I believe we might need to update `get_iframe` to support alignment within post.
		return serialize_block( $this->block_generator->get_iframe( $component->url->get_value(), intval( $width ), intval( $height ) ) );
	}

	/**
	 * Handles getting a gutenberg paragraph HTML block from a caption component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return array
	 */
	private function handle_caption_component( MigrationObjectPropertyWrapper $component ): array {
		$caption = $component->text ?? $component->caption;

		return $this->block_generator->get_paragraph(
			$caption->get_value(),
			'',
			'',
			'',
			[
				'wp-caption-text',
			]
		);
	}

	/**
	 * Handles downloading and importing an audio file and generating a gutenberg audio HTML block.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_audio_component( MigrationObjectPropertyWrapper $component ): string {
		$audio_url        = $component->url->get_value();
		$caption          = $component->caption ? $component->caption->get_value() : '';
		$file_description = $component->file_description ? $component->file_description->get_value() : '';

		return serialize_block( $this->block_generator->get_audio( $audio_url, $caption, $file_description, true ) );
	}

	/**
	 * Handles getting a gutenberg file PDF HTML block from a document link component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_document_link_component( MigrationObjectPropertyWrapper $component ): string {
		switch ( $component->file_type->get_value() ) {
			case 'application/pdf':
				$attachment_id = Attachments::import_external_file( $component->file_url->get_value() );

				if ( is_wp_error( $attachment_id ) ) {
					ConsoleColor::red( 'Error creating attachment (' )
								->bright_red( $attachment_id->get_error_code() )
								->red( '):' )
								->underlined_bright_red( $attachment_id->get_error_message() )
								->output();

					$paragraph_block = $this->block_generator->get_paragraph( $component->file_url->get_value() );

					$paragraph_block['attrs'] = array_merge(
						$paragraph_block['attrs'],
						[
							'original-file-url'  => $component->file_url->get_value(),
							'original-file-type' => $component->file_type->get_value(),
							'original-caption'   => $component->caption->get_value(),
						]
					);

					return serialize_block( $paragraph_block );
				}

				$pdf_block = $this->block_generator->get_file_pdf( get_post( $attachment_id ), '', false );

				$caption_block = $this->handle_caption_component( $component );

				return serialize_block(
					$this->block_generator->get_group_constrained(
						[
							$pdf_block,
							$caption_block,
						]
					)
				);
			default:
				// TODO create a new function in gutenberg block generator for handling different file types.
				ConsoleColor::bright_magenta( 'Different file type needs attention!' )->output();
				var_dump( $component->get_value() );
				return '';
		}
	}

	/**
	 * Handles getting a custom gutenberg HTML block from a series list component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_series_list_component( MigrationObjectPropertyWrapper $component ): string {
		// $category = $this->get_category_from_series_id( $component->series_id );
		// TODO this needs to be updated after we've imported categories/tags.

		$url  = $component->series_url ? get_site_url( null, $component->series_url->get_value() ) : '';
		$text = $component->series_name ? $component->series_name->get_value() : $url;

		return serialize_block(
			$this->block_generator->get_heading(
				'<span style="text-transform:uppercase">Latest from the series</span>'
				. '<br>'
				. '<a href="' . $url . '" >' . $text . '</a>'
			)
		)
			.
				serialize_block(
					$this->block_generator->get_homepage_articles_for_category( [ 1 ], [] )
				);
	}

	/**
	 * Get or create a category term, optionally setting a parent and description.
	 *
	 * @param string      $name        The category name.
	 * @param string      $slug        Optional. The category slug. If not provided, will be generated from name.
	 * @param int|null    $parent_id   Optional. The parent term ID.
	 * @param string|null $description Optional. The category description.
	 *
	 * @return WP_Term|null The term object if successful, null otherwise.
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
	 * Handles getting a custom gutenberg HTML block from a series snippet component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_series_snippet_component( MigrationObjectPropertyWrapper $component ): string {
		$more_in_series_link = '<a href="' . get_site_url( null, $component->series_url->get_value() ) . '">More in this series</a>';
		$paragraph_block     = $this->block_generator->get_paragraph(
			strip_tags( $component->text->get_value(), [ 'a' ] ) . $more_in_series_link
		);

		// Get or create the series category.
		$series_data = $this->get_series_data( $component->series_id->get_value() );
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
						strip_tags( $component->text->get_value(), [ 'a' ] ) . $more_in_series_link
					);
				}
			}
		}

		if ( false === $component->show_logo->get_value() ) {
			return serialize_block( $paragraph_block );
		}

		$logo       = Attachments::import_external_file( $component->logo_url->get_value() );
		$logo_block = $this->block_generator->get_image( get_post( $logo ), 'full', false, null, 'center' );

		return serialize_block(
			$this->block_generator->get_group_constrained(
				[ $logo_block, $paragraph_block ]
			)
		);
	}

	/**
	 * Handles getting a custom gutenberg HTML block from a context snippet component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_context_snippet_component( MigrationObjectPropertyWrapper $component ): string {
		$title = '';

		if ( $component->title && $component->title->get_value() ) {
			$title = $component->title->get_value();
		} elseif ( $component->emphasized_title && $component->emphasized_title->get_value() ) {
			$title = $component->emphasized_title->get_value();
		}

		if ( ! empty( $title ) ) {
			$heading = serialize_block(
				$this->block_generator->get_heading( '<span style="text-transform:uppercase">' . $title . '</span>' )
			);

			$emphasized_paragraph = serialize_block(
				$this->block_generator->get_paragraph(
					'<em>' . $component->description->get_value() . '</em>'
				)
			);

			return $heading . $emphasized_paragraph;
		}

		return '';
	}

	/**
	 * Handles saving CTA Membership component data to post metadata.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 * @param int                            $post_id Post ID.
	 *
	 * @return string
	 */
	private function handle_cta_membership_component( MigrationObjectPropertyWrapper $component, int $post_id ): string {
		update_post_meta( $post_id, 'cta_membership_message', $component->message->get_value() );
		update_post_meta( $post_id, 'cta_membership_campaign_id', $component->campaign_id->get_value() );

		return '';
	}

	/**
	 * Handles getting a custom gutenberg HTML block from a FAQ container component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_faq_container_component( MigrationObjectPropertyWrapper $component ): string {
		$title = serialize_block(
			$this->block_generator->get_heading(
				$component->title->get_value(),
				'h3',
				$component->cta_url->get_value()
			)
		);

		if ( $component->sponsor_name && ! empty( $component->sponsor_name->get_value() ) ) {
			$title .= serialize_block(
				$this->block_generator->get_paragraph(
					'<a href="' . $component->sponsor_url->get_value() . '">' . $component->sponsor_name->get_value() . '</a>'
				)
			);
		}

		return $title;
	}

	/**
	 * Handles getting a custom gutenberg HTML block from a FAQ entry component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_faq_entry_component( MigrationObjectPropertyWrapper $component ): string {
		return serialize_block(
			$this->block_generator->get_details(
				$component->question->get_value(),
				[
					$this->block_generator->get_quote(
						$component->answer->get_value()
					),
				]
			)
		);
	}

	/**
	 * Handle getting a custom gutenberg HTML block from a table of contents component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_table_of_contents_component( MigrationObjectPropertyWrapper $component ): string {
		$blocks        = [];
		$heading_block = [];
		$list_block    = [];

		foreach ( $component->components as $sub_component ) {
			switch ( $sub_component->role->get_value() ) {
				case 'table of contents heading':
					$heading_block = $this->block_generator->get_heading(
						$sub_component->text->get_value()
					);
					break;
				case 'table of contents list':
					$list_array = $this->get_list_array( $sub_component->text->get_value() );

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
	 * Handles various different section entry components and return a custom gutenberg HTML block.
	 *
	 * @param MigrationObjectPropertyWrapper $section_entry_component Component to process.
	 * @param int                            $post_id Post ID.
	 *
	 * @return string
	 */
	private function handle_sections_entry_container( MigrationObjectPropertyWrapper $section_entry_component, int $post_id ): string {
		$sections_entry = '';
		$timestamp      = '';
		$copy_link      = '';

		if ( $section_entry_component->show_copy_link->get_value() ) {
			$copy_link = '<a id="' . $section_entry_component->identifier->get_value() . '"><span class="dashicons dashicons-admin-links"></span></a>';
		}

		foreach ( $section_entry_component->components as $component ) {
			switch ( $component->role->get_value() ) {
				case 'sections entry heading':
					$sections_entry .= serialize_block( $this->block_generator->get_heading( $component->text->get_value() ) );
					break;
				case 'sections entry content':
					$sections_entry .= $this->get_post_content_by_handling_components( $component->components, $post_id, null );
					break;
				case 'sections entry timestamp':
					$timestamp = '<time datetime="' . $component->timestamp->get_value() . '">' . $component->text->get_value() . '</time>';
					break;
				default:
					ConsoleColor::bright_magenta( 'Unhandled Section Entry Object' )
								->underlined_bright_magenta( $component->role->get_value() )
								->output();
			}
		}

		return $timestamp . $copy_link . $sections_entry;
	}

	/**
	 * Handles getting a custom gutenberg HTML block from a newsletter signup component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_newsletter_signup_component( MigrationObjectPropertyWrapper $component ): string {
		$paragraph_block = $this->block_generator->get_paragraph( 'NEWSLETTER SIGNUP HERE' );

		$paragraph_block['attrs'] = array_merge(
			$paragraph_block['attrs'],
			[
				'newsletter-id' => $component->newsletter ? $component->newsletter->get_value() : '',
				'slug'          => $component->slug ? $component->slug->get_value() : '',
				'name'          => $component->name ? $component->name->get_value() : '',
				'description'   => $component->description ? $component->description->get_value() : '',
			]
		);

		return serialize_block( $paragraph_block );
	}

	/**
	 * Handles getting a custom gutenberg HTML block from a pulitzer component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_pulitzer_component( MigrationObjectPropertyWrapper $component ): string {
		$text_block = $this->block_generator->get_paragraph( $component->text->get_value() );

		$image_block = '';

		$maybe_image_object = $this->handle_image_import( $component->logo->get_value() );

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
	 * This function the low-level task of URL sanitation, image download, and attachment data creation.
	 *
	 * @param string      $image_url The url of the image to be downloaded.
	 * @param string|null $title The title of the image.
	 * @param string|null $caption The caption for the image.
	 * @param string|null $description The description of the image.
	 * @param string|null $alt Alt text to display for the image.
	 *
	 * @return TexasTribuneAttachmentMetadataObject|WP_Error
	 */
	private function handle_image_import( string $image_url, ?string $title = null, ?string $caption = null, ?string $description = null, ?string $alt = null ): TexasTribuneAttachmentMetadataObject|WP_Error {
		$image_object = new TexasTribuneAttachmentMetadataObject( $image_url );

		$maybe_attachment_id = Attachments::import_external_file( $image_object->download_url, $title, $caption, $description, $alt );

		if ( is_wp_error( $maybe_attachment_id ) ) {
			ConsoleColor::bright_magenta( 'Error Importing Image' )
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
	 * Handles creating an attachment from a component which has a photo URL.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 * @param int                            $post_id Post ID.
	 *
	 * @return int|WP_Error
	 */
	private function handle_image_import_and_import_metadata( MigrationObjectPropertyWrapper $component, int $post_id ): int|WP_Error {
		$description = '';
		if ( $component->file_description && $component->file_description->get_value() ) {
			$description = $component->file_description->get_value();
		} elseif ( $component->photo_description && $component->photo_description->get_value() ) {
			$description = $component->photo_description->get_value();
		}

		$maybe_attachment_object = $this->handle_image_import(
			$component->url->get_value(),
			null,
			$component->caption ? $component->caption->get_value() : null,
			$description
		);

		if ( is_wp_error( $maybe_attachment_object ) ) {
			return $maybe_attachment_object;
		}

		$migration_object = $component->get_migration_object();

		if ( $migration_object instanceof RunAwareMigrationObject ) {
			$attachment_data = new WordPressPostsData();
			$attachment_data->set_migration_object(
				new RunAwareMigrationObjectWrapper(
					new MigrationObject(
						array_merge(
							$component->get_value(),
							[
								'attachment_object' => $maybe_attachment_object,
							],
							[
								'decoded_file_name' => $maybe_attachment_object->decoded_file_name,
							]
						),
						'decoded_file_name',
						$migration_object->get_data_chest()
					),
					$migration_object->get_run_key()
				)
			);

			$attachment_data->set_post_excerpt( $component->caption ?? '' )
							->set_post_content( $component->file_description ?? $component->photo_description ?? '' )
							->set_post_parent( $post_id )
							->set_id( $maybe_attachment_object->attachment_id );

			try {
				$attachment_data->update();
			} catch ( Exception $e ) {
				ConsoleColor::bright_yellow( 'Error saving some data sources for photos' )
							->underlined_white( $e->getCode() )
							->white( $e->getMessage() )
							->output();
			}
		}

		return $maybe_attachment_object->attachment_id;
	}

	/**
	 * Get a user by display name.
	 *
	 * @param string $display_name Display name.
	 *
	 * @return WP_User|null
	 */
	private function get_user_by_display_name( string $display_name ): ?WP_User {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->users WHERE display_name = %s",
				$display_name
			)
		);

		if ( ! $user_id ) {
			return null;
		}

		return get_user_by( 'ID', $user_id );
	}

	/**
	 * Get sponsor data by ID.
	 *
	 * @param int $sponsor_id The sponsor ID.
	 * @return int|null The sponsor term ID or null if not found.
	 */
	private function get_sponsor_data( int $sponsor_id ): ?int {
		if ( null === $this->sponsors_data ) {
			$sponsors_file = '/var/www/html/sponsors/sponsors_id_map.json';
			if ( ! file_exists( $sponsors_file ) ) {
				return null;
			}

			$json_content = file_get_contents( $sponsors_file );
			if ( false === $json_content ) {
				return null;
			}

			$decoded_data = json_decode( $json_content, true );
			if ( ! is_array( $decoded_data ) ) {
				return null;
			}

			$this->sponsors_data = $decoded_data;
		}

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
		$next_url        = 'https://www.texastribune.org/api/v2/tags/?limit=100&is_public=true';

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
				if ( isset( $tag['id'] ) && isset( $tag['name'] ) && isset( $tag['type'] ) && isset( $tag['is_public'] ) && $tag['is_public'] ) {
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
	 * Handle tags for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $tag_ids Array of tag IDs.
	 * @return void
	 */
	private function handle_post_tags( int $post_id, array $tag_ids ): void {
		$tag_names = [];
		foreach ( $tag_ids as $tag_id ) {
			$tag_data = $this->get_tag_data( $tag_id );
			if ( null !== $tag_data ) {
				// Get or create parent tag.
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
							$child_term = get_term( $child_result['term_id'], 'post_tag' );
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
						$tag_names[] = $tag_data['name'];
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

		if ( ! empty( $tag_names ) ) {
			wp_set_post_tags( $post_id, $tag_names, false );
		}
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
	 * Handle series for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $series_ids Array of series IDs.
	 * @return void
	 */
	private function handle_post_series( int $post_id, array $series_ids ): void {
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
			wp_set_post_categories( $post_id, $category_ids );
		}
	}
}
