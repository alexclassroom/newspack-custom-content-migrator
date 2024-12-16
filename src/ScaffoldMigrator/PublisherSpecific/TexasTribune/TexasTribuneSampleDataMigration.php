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
	 * @return bool|MigrationState|\WP_Error|null
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
						$migration_object->get_container()
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

		match ( $migration_object->metadata->type->get_value() ) {
			'article', 'sponsorcontent' => $posts_data->set_post_type(
				new MigrationObjectPropertyWrapper(
					'post',
					explode( '.', $migration_object->metadata->type->get_path() ),
					$migration_object
				)
			), // TODO: should sponsorcontent be handled differently?
			'flatpage' => $posts_data->set_post_type(
				new MigrationObjectPropertyWrapper(
					'page',
					explode( '.', $migration_object->metadata->type->get_path() ),
					$migration_object
				)
			),
		};

		if ( is_wp_error( $maybe_post_id ) ) {
			ConsoleColor::red( 'Error creating post (' )->bright_red( $maybe_post_id->get_error_code() )->red( '):' )->underlined_bright_red( $maybe_post_id->get_error_message() )->output();
			// TODO replace with FailedMigrationState
			return null;
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

		$post_content_value = $this->get_post_content_by_handling_components( $migration_object->components, $maybe_post_id, $maybe_featured_image_attachment_object );
		$posts_data->set_migration_object( $migration_object );
		$maybe_updated = $posts_data->set_id( $maybe_post_id )->set_post_content( $post_content_value )->update();

		if ( $maybe_updated ) {
			$migration_object->mark_as_processed();
		}

		return null;
	}

	/**
	 * Returns the migration objects.
	 *
	 * @return MigrationDataChest
	 */
	public function get_data_chest(): MigrationDataChest {
		return new JSONMigrationDataChest( '/var/www/html/2024-11-21_texas_tribune_all_sample_articles.json', 'identifier' );
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
				return $this->handle_video_component( $component, $post_id );
			case 'iframe':
				return $this->handle_iframe_component( $component );
			case 'tweet':
				return serialize_block( $this->block_generator->get_twitter( $component->url->get_value() ) );
			case 'caption':
				if ( $previous_sibling ) {
					if ( 'photo' === $previous_sibling->role->get_value() && $component->text->get_value() === $previous_sibling->caption->get_value() ) {
						return '';
					}

					if ( 'data graphic' === $previous_sibling->role->get_value() ) {
						return '';
					}
				}

				return $this->handle_caption_component( $component );
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

		$doc = new \DOMDocument();

		$matches = [];
		// regex to extract <li>'s and the contents in between from string.
		$pattern = '/<strong>(.*?)<\/strong>/';
		preg_match( $pattern, $component->text->get_value(), $matches );

		if ( ! empty( $matches ) ) {
			$doc->loadHTML( $matches[0] );
		} else {
			$doc->loadHTML( $component->text->get_value() );
		}

		$timestamp = $doc->getElementsByTagName( 'time' )->item( 0 );

		if ( $timestamp ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$correction['date'] = $timestamp->ownerDocument->saveHTML( $timestamp );
		} else {
			ConsoleColor::bright_magenta( 'Unable to find date' )->output();
		}

		if ( $timestamp->getAttribute( 'datetime' ) ) {
			$correction['timestamp'] = $timestamp->getAttribute( 'datetime' );
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

		return serialize_block(
			$this->block_generator->get_paragraph(
				'<a href="' . $component->article_url->get_value() . '">' . $component->article_headline->get_value() . '</a>',
				'',
				'',
				'',
				[
					'original-related-story-id' => $component->article_id->get_value(),
				]
			)
		);
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
								$migration_object->get_container()
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
		$alignment  = '';

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
		}

		return $this->handle_iframe_component( $component );
	}

	/**
	 * Handles getting a gutenberg video HTML block from a video component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 * @param int                            $post_id Post ID.
	 *
	 * @return string
	 */
	private function handle_video_component( MigrationObjectPropertyWrapper $component, int $post_id ): string {
		$caption_block = [];

		if ( $component->caption && ! empty( $component->caption->get_value() ) ) {
			$caption_block = $this->block_generator->get_paragraph( $component->caption->get_value() );
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

		return serialize_block( $this->block_generator->get_iframe( $component->url->get_value(), $width, $height ) );
	}

	/**
	 * Handles getting a gutenberg paragraph HTML block from a caption component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_caption_component( MigrationObjectPropertyWrapper $component ): string {
		return serialize_block( $this->block_generator->get_paragraph( $component->text->get_value(), '', 'gray', 'small' ) );
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

					return serialize_block(
						$this->block_generator->get_paragraph(
							$component->file_url->get_value(),
							'',
							'',
							'',
							[
								'original-file-url'  => $component->file_url->get_value(),
								'original-file-type' => $component->file_type->get_value(),
								'original-caption'   => $component->caption->get_value(),
							]
						)
					);
				}

				$pdf = serialize_block( $this->block_generator->get_file_pdf( get_post( $attachment_id ) ) );

				$caption = $component->caption ?
					serialize_block( $this->block_generator->get_paragraph( $component->caption->get_value() ) ) :
					'';

				return serialize_block(
					$this->block_generator->get_paragraph(
						$pdf . $caption
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
	 * Handles getting a custom gutenberg HTML block from a series snippet component.
	 *
	 * @param MigrationObjectPropertyWrapper $component Component to process.
	 *
	 * @return string
	 */
	private function handle_series_snippet_component( MigrationObjectPropertyWrapper $component ): string {
		// TODO need to update this after we've imported categories/tags. The series_url needs to be updated to the correct category URL.
		$more_in_series_link = '<a href="' . get_site_url( null, $component->series_url->get_value() ) . '">More in this series</a>';
		$paragraph_block     = $this->block_generator->get_paragraph(
			$component->text->get_value() . $more_in_series_link
		);

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
				3,
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
		$question_block = $this->block_generator->get_paragraph(
			'<strong>' . $component->question->get_value() . '</strong>'
		);

		$answer_block = $this->block_generator->get_quote(
			$component->answer->get_value()
		);

		return serialize_block(
			$this->block_generator->get_group_constrained(
				[
					$question_block,
					$answer_block,
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
			$copy_link = '<a href="#' . $section_entry_component->identifier->get_value() . '">&#x1F517</a>';
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
		return serialize_block(
			$this->block_generator->get_paragraph(
				'NEWSLETTER SIGNUP HERE',
				'',
				'',
				'',
				[
					'newsletter-id' => $component->newsletter ? $component->newsletter->get_value() : '',
					'slug'          => $component->slug ? $component->slug->get_value() : '',
					'name'          => $component->name ? $component->name->get_value() : '',
					'description'   => $component->description ? $component->description->get_value() : '',
				]
			)
		);
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

		$maybe_attachment_id = Attachments::import_external_file( $component->logo->get_value() );

		if ( is_wp_error( $maybe_attachment_id ) ) {
			ConsoleColor::red( 'Error getting pulitzer logo (' )
						->bright_red( $maybe_attachment_id->get_error_code() )
						->red( '):' )
						->underlined_bright_red( $maybe_attachment_id->get_error_message() )
						->output();
		} else {
			$image_url   = wp_get_attachment_image_url( $maybe_attachment_id );
			$image_block = $this->block_generator->get_image( get_post( $maybe_attachment_id ), 'full', false, null, 'center', $image_url );
		}

		return serialize_block(
			$this->block_generator->get_group_constrained(
				[ $image_block, $text_block ],
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

		$maybe_attachment_id = Attachments::import_external_file( $image_url, $title, $caption, $description, $alt );

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
						$migration_object->get_container()
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
}
