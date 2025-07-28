<?php

namespace NewspackCustomContentMigrator\Command\General;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\CoAuthorsPlusHelper;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;

class MetroMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	private $ids_mappings;
	private $mappings_folder;

	/**
	 * @var null|InterfaceCommand Instance.
	 */
	private static $instance = null;

	/**
	 * @var CoAuthorPlus
	 */
	private $coauthor_plus;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->coauthor_plus = new CoAuthorsPlusHelper();

		$this->mappings_folder = WP_CONTENT_DIR . '/../621/mappings/';
		if ( ! file_exists( $this->mappings_folder ) ) {
			mkdir( $this->mappings_folder, 0755, true );
		}
		$this->load_mappings();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_instance(): self {
		$class = get_called_class();
		if ( null === self::$instance ) {
			self::$instance = new $class();
		}

		return self::$instance;
	}

	/**
	 * See InterfaceCommand::register_commands.
	 */
	public static  function register_commands(): void {

		/**
		 * Note, this migrator presently expects to have the export folder be "./621" in public root.
		 * That's because "mappings" folder and files are hardcoded to path ./621 via constructor.
		 * Improvement idea -- decouple these "mappings" from the constructor, and change `--files-folder` params to be path to the exports folder.
		 */

		WP_CLI::add_command( 'newspack-content-migrator metro-import-sections',
			self::get_command_closure( 'cmd_metro_import_sections' ),
			[
				'shortdesc' => 'Import Metro Sections as categories.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'files-folder',
						'description' => 'The folder containing the JSON files.',
						'optional'    => false,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command( 'newspack-content-migrator metro-find-tags-types',
			self::get_command_closure( 'cmd_metro_find_tags_types' ),
			[
				'shortdesc' => 'Find the tags types (author/normal tag) to use later.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'files-folder',
						'description' => 'The folder containing the JSON files.',
						'optional'    => false,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command( 'newspack-content-migrator metro-import-tags',
			self::get_command_closure( 'cmd_metro_import_tags' ),
			[
				'shortdesc' => 'Import Metro tags.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'files-folder',
						'description' => 'The folder containing the JSON files.',
						'optional'    => false,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command( 'newspack-content-migrator metro-import-authors',
			self::get_command_closure( 'cmd_metro_import_authors' ),
			[
				'shortdesc' => 'Import Metro authors.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'files-folder',
						'description' => 'The folder containing the JSON files.',
						'optional'    => false,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command( 'newspack-content-migrator metro-import-files',
			self::get_command_closure( 'cmd_metro_import_files' ),
			[
				'shortdesc' => 'Import Metro files.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'files-folder',
						'description' => 'The folder containing the JSON files.',
						'optional'    => false,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command( 'newspack-content-migrator metro-import-content',
			self::get_command_closure( 'cmd_metro_import_content' ),
			[
				'shortdesc' => 'Import Metro content (posts).',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'files-folder',
						'description' => 'The folder containing the JSON files.',
						'optional'    => false,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command( 'newspack-content-migrator metro-import-locations',
			self::get_command_closure( 'cmd_metro_import_locations' ),
			[
				'shortdesc' => 'Import Metro locations.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'files-folder',
						'description' => 'The folder containing the JSON files.',
						'optional'    => false,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command( 'newspack-content-migrator metro-import-events',
			self::get_command_closure( 'cmd_metro_import_events' ),
			[
				'shortdesc' => 'Import Metro events.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'files-folder',
						'description' => 'The folder containing the JSON files.',
						'optional'    => false,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command( 'newspack-content-migrator metro-update-posts',
			self::get_command_closure( 'cmd_metro_update_posts' ),
			[
				'shortdesc' => 'Update Metro posts.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'files-folder',
						'description' => 'The folder containing the JSON files.',
						'optional'    => false,
						'repeating'   => false,
					],
				],
			]
		);

		WP_CLI::add_command(
            'newspack-content-migrator metro-fix-jpe-images-posts',
			self::get_command_closure( 'cmd_metro_fix_jpe_images_posts' ),
			[
				'shortdesc' => 'Fix the JPE images by renaming them to JPEG extension.',
			]
		);

		WP_CLI::add_command(
            'newspack-content-migrator metro-fix-jpe-images-in-posts-content',
			self::get_command_closure( 'cmd_metro_fix_jpe_images_in_posts_content' ),
			[
				'shortdesc' => 'Fix the JPE images links inside post content.',
			]
		);
	}

	/**
	 * Callable for `newspack-content-migrator metro-fix-jpe-images-posts`.
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function cmd_metro_fix_jpe_images_posts( $args, $assoc_args ) {
		global $wpdb;

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, guid FROM $wpdb->posts WHERE guid LIKE %s",
				'%' . '.jpe'
			)
		);

		WP_CLI::warning( sprintf( 'Checking %d attachments...', count( $posts ) ) );
		foreach ( $posts as $post ) {
			$filename     = wp_upload_dir()['basedir'] . str_replace( '/wp-content/uploads', '', parse_url( $post->guid )['path'] );
			$new_filename = $filename . 'g';

			if ( ! file_exists( $filename ) && ! file_exists( $new_filename ) ) {
				WP_CLI::warning( sprintf( 'File not found. Skipping... %s', $filename ) );
				continue;
			}

			// fix post.
			$wpdb->update( $wpdb->posts, [ 'guid' => $post->guid . 'g' ], [ 'ID' => $post->ID ] );

			// fix file.
			if ( file_exists( $filename ) && ! file_exists( $new_filename ) ) {
				rename( $filename, $new_filename );
			}

			// fix meta.
			$attachment_meta = get_post_meta( $post->ID, '_wp_attached_file', true );
			if ( $attachment_meta && str_ends_with( $attachment_meta, 'jpe' ) ) {
				update_post_meta( $post->ID, '_wp_attached_file', $attachment_meta . 'g' );
			}

			$attachment_thumbs_meta          = get_post_meta( $post->ID, '_wp_attachment_metadata', true );
			$attachment_thumbs_meta['file'] .= 'g';

			if ( array_key_exists( 'original_image', $attachment_thumbs_meta ) ) {
				$attachment_thumbs_meta['original_image'] .= 'g';
			}

			foreach ( $attachment_thumbs_meta['sizes'] as $index => $size ) {
				$attachment_thumbs_meta['sizes'][ $index ]['file'] .= 'g';
			}
			update_post_meta( $post->ID, '_wp_attachment_metadata', $attachment_thumbs_meta );

			WP_CLI::success( sprintf( 'Attachment %d fixed', $post->ID ) );
		}

		WP_CLI::success( 'Done!' );
	}

	/**
	 * Callable for `newspack-content-migrator metro-fix-jpe-images-in-posts-content`.
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function cmd_metro_fix_jpe_images_in_posts_content( $args, $assoc_args ) {
		global $wpdb;

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				'select ID, post_content from wp_posts where post_content like %s and post_type in ("post", "page");',
                '%.jpe"%'
			)
		);

		foreach ( $posts as $post ) {
			$fixed_content = $post->post_content;
			preg_match_all( '/"(?P<url>[^"]+jpe)"/', $post->post_content, $jpe_links_matches );

			foreach ( $jpe_links_matches['url'] as $url ) {
				$wp_upload_dir = wp_upload_dir();
				$fixed_url     = str_starts_with( $url, 'http' ) ? $url . 'g' : $wp_upload_dir['baseurl'] . $url . 'g';
				$local_file    = $wp_upload_dir['basedir'] . str_replace( '/wp-content/uploads', '', parse_url( $fixed_url )['path'] );

				if ( file_exists( $local_file ) ) {
					$fixed_content = str_replace( $url, $fixed_url, $fixed_content );
					WP_CLI::success( sprintf( 'Fixed URL: %s', $fixed_url ) );
				} else {
					WP_CLI::warning( sprintf( 'Need to download file from older server: %s', $url ) );
				}
			}

			if ( $fixed_content !== $post->post_content ) {
				wp_update_post( ['ID' => $post->ID, 'post_content' => $fixed_content] );
				WP_CLI::success( sprintf( 'Post #%d is fixed!', $post->ID ) );
			}
			var_dump( $post->ID );
		}
	}

	public function cmd_metro_update_posts( $args, $assoc_args ) {
		$files_folder = $assoc_args['files-folder'];

		$posts = $this->get_objects_from_folder( $files_folder );

		$current_time = time();

		foreach ( $posts as $post ) {
			if ( 'article' != $post->content_type ) {
				continue;
			}

			if ( ! $this->post_exists( $post->uuid ) ) {
				WP_CLI::log( sprintf( 'Post "%s" does not exist. Skipping...', $post->title ) );
				continue;
			}

			$last_updated = get_post_meta( $this->get_post_id( $post->uuid ), '_newspack_last_updated', true );

			if ( $last_updated && $current_time - $last_updated < 3600 ) {
				WP_CLI::log( sprintf( 'Post "%s" was updated recently. Skipping...', $post->title ) );
				continue;
			}

			WP_CLI::log( sprintf( 'Updating post "%s"...', $post->title ) );

			$result = $this->add_post( $post, $files_folder, true );

			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( sprintf( 'Could not update psot "%s"', $post->title ) );
				WP_CLI::warning( $result->get_error_message() );
			}
		}
	}

	public function cmd_metro_import_locations( $args, $assoc_args ) {
		$files_folder = $assoc_args['files-folder'];

		$locations = $this->get_objects_from_folder( $files_folder );

		foreach ( $locations as $location ) {
			if ( $this->location_exists( $location->uuid ) ) {
				WP_CLI::log( sprintf( 'Location "%s" already exists. Skipping...', $location->title ) );
				continue;
			}

			WP_CLI::log( sprintf( 'Importing location "%s"...', $location->title ) );
		}
	}

	public function cmd_metro_import_events( $args, $assoc_args ) {
		$files_folder = $assoc_args['files-folder'];

		$events = $this->get_objects_from_folder( $files_folder );

		foreach ( $events as $event ) {
			if ( 'event' != $event->content_type ) {
				continue;
			}

			if ( $this->event_exists( $event->uuid ) ) {
				WP_CLI::log( sprintf( 'Event "%s" already exists. Skipping...', $event->title ) );
				continue;
			}

			WP_CLI::log( sprintf( 'Importing event "%s"...', $event->title ) );

			// $result = $this->add_post( $event, $files_folder );

		}
	}

	public function cmd_metro_import_content( $args, $assoc_args ) {
	$files_folder = $assoc_args['files-folder'];

		$posts = $this->get_objects_from_folder( $files_folder );

		foreach ( $posts as $key_post => $post ) {
			if ( 'article' != $post->content_type ) {
				continue;
			}

			if ( $this->post_exists( $post->uuid ) ) {
				WP_CLI::log( sprintf( 'Post "%s" already exists. Skipping...', $post->title ) );
				continue;
			}

			WP_CLI::log( sprintf( '(%d)/(%d) Importing post "%s"...', $key_post + 1, count( $posts ), $post->title ) );

			$result = $this->add_post( $post, $files_folder );

			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( sprintf( 'Could not add psot "%s"', $post->title ) );
				WP_CLI::warning( $result->get_error_message() );
			}
		}
	}

	public function cmd_metro_import_files( $args, $assoc_args ) {
		$files_folder = $assoc_args['files-folder'];

		$files = $this->get_objects_from_folder( $files_folder );

		foreach ( $files as $key_file_data => $file_data ) {
			if ( $this->attachment_exists( $file_data->uuid ) ) {
				WP_CLI::log( sprintf( 'Attachment "%s" already exists. Skipping...', $file_data->filename ) );
				continue;
			}

			WP_CLI::log( sprintf( '(%d)/(%d) Importing attachment "%s"', $key_file_data + 1, count( $files ), $file_data->filename ) );

			$file_path = path_join( $files_folder, $file_data->uuid . '.data' );

			$result = $this->add_file( $file_data, $file_path );
			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( sprintf( 'Could not add attachment "%s"', $file_data->filename ) );
				WP_CLI::warning( $result->get_error_message() );
			}
		}
	}

	public function cmd_metro_import_authors( $args, $assoc_args ) {
		$files_folder = $assoc_args['files-folder'];

		$authors = $this->get_objects_from_folder( $files_folder );

		foreach ( $authors as $author ) {
			if ( 'person' != $this->get_object_id( $author->uuid, 'tags_types' ) ) {
				continue;
			}

			if ( $this->tag_exists( $author->uuid ) ) {
				WP_CLI::log( sprintf( 'Author "%s" already exists. Skipping...', $author->title ) );
				continue;
			}

			WP_CLI::log( sprintf( 'Importing author "%s"', $author->title ) );

			$result = $this->add_author( $author );

			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( sprintf( 'Could not add author "%s"', $author->title ) );
				WP_CLI::warning( $result->get_error_message() );
			}
		}
	}

	public function cmd_metro_import_tags( $args, $assoc_args ) {
		global $wpdb;
		$files_folder = $assoc_args['files-folder'];

		$tags = $this->get_objects_from_folder( $files_folder );

		foreach ( $tags as $tag ) {
			if ( 'default' != $this->get_object_id( $tag->uuid, 'tags_types' ) ) {
				continue;
			}

			$tag_exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT wpt.`name`
				FROM wp_terms wpt
				JOIN wp_term_taxonomy wptt ON wpt.term_id = wptt.term_id
				WHERE wptt.taxonomy = 'post_tag'
				AND wpt.name = %s",
				$tag->title
			) );
			if ( $tag_exists ) {
				WP_CLI::log( sprintf( 'Tag "%s" already exists. Skipping...', $tag->title ) );
				continue;
			}

			WP_CLI::log( sprintf( 'Importing tag "%s"', $tag->title ) );

			$result = $this->add_tag( $tag );

			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( sprintf( 'Could not add tag "%s"', $tag->title ) );
				WP_CLI::warning( $result->get_error_message() );
			}
		}
	}

	public function cmd_metro_find_tags_types( $args, $assoc_args ) {
		$files_folder = $assoc_args['files-folder'];

		$files = $this->get_objects_from_folder( $files_folder, false );

		foreach ( $files as $file ) {
			$tags_file_contents = file_get_contents( $file );
			$tag = json_decode( $tags_file_contents );
			$tag_uuid = $tag->uuid;
			$tag_type = $tag->type;

			if ( ! $this->get_object_id( $tag_uuid, 'tags_types' ) ) {
				$this->add_object_id( $tag_uuid, $tag_type, 'tags_types' );
			}
		}
	}

	public function cmd_metro_import_sections( $args, $assoc_args ) {
		$files_folder = $assoc_args['files-folder'];

		$sections = $this->get_objects_from_folder( $files_folder );

		if ( $sections == false ) {
			WP_CLI::error( 'Could not open the provided folder.' );
		}

		$this->add_categories( $sections );
	}

	public function add_categories( $sections ) {
		$need_parents = array();

		foreach ( $sections as $section ) {
			if ( null === $section ) {
				continue;
			}


			if ( $this->section_exists( $section->uuid ) ) {
				WP_CLI::log( sprintf( 'Category "%s" already exists. Skipping...', $section->title ) );
				continue;
			}

			WP_CLI::log( sprintf( 'Importing category "%s"', $section->title ) );

			$parent_id = 0;

			if ( $section->parent_uuid ) {
				$parent_id = $this->get_section_id( $section->parent_uuid );
				if ( ! $parent_id ) {
					$need_parents[] = $section;
					WP_CLI::log( sprintf( 'Category "%s" needs parent. Postponing...', $section->title ) );
					continue;
				}
			}

			$this->add_category( $section, $parent_id );
		}

		if ( count( $need_parents ) > 0 ) {
			$this->add_categories( $need_parents );
		}
	}

	public function add_post( $post, $post_folder, $update = false ) {
		global $wpdb;

		$post_tags = array();
		$slots     = array();

		$tags_file = path_join( $post_folder, $post->uuid . '/tags.json' );

		$author_ids = [];
		if ( file_exists( $tags_file ) ) {
			$tags_file_contents = file_get_contents( $tags_file );

			$tags = json_decode( $tags_file_contents );

			foreach ( $tags as $tag ) {
				if ( 'describes' == $tag->predicate ) {
					$post_tags[] = $tag->title;
				}
				if ( 'authored' == $tag->predicate ) {
					$author_ids[] = $this->get_tag_id( $tag->uuid );
				}
			}
		}

		$media_file = path_join( $post_folder, $post->uuid . '/media.json' );

		if ( file_exists( $media_file ) ) {
			$media_file_contents = file_get_contents( $media_file );

			$media = json_decode( $media_file_contents );

			foreach ( $media as $slot ) {
				$slots[ $slot->slot_uuid ] = $slot;
			}
		}

		$post_content = '';
		if ( isset( $post->content ) && ! empty( $post->content ) ) {
			$post_content = html_entity_decode( $post->content );

			$slot_pattern = '/<slot id="(.+?)"\/*>\r*\n*(?:<\/slot>)*/m';
			$found = preg_match_all( $slot_pattern, $post_content, $slots_found );
			if ( $found ) {
				$post_content = $this->format_content( $post_content, $slots_found, $slots );
			}
		}

		$post->content = '';

		$post_meta = array(
			'newspack_post_subtitle' => $post->sub_title,
			'newspack_canonical_url' => $post->canonical_url,
		);

		$author_id = count( $author_ids ) >= 1 ? $author_ids[0] : 1;

		$post_args = array(
			'post_content'  => $post_content ?? '',
			'post_author'   => $author_id,
			'post_date'     => $post->issued,
			'post_modified' => $post->modified,
			'post_title'    => $post->title ?? '',
			'post_excerpt'  => $post->description ?? '',
			'post_status'   => 'publish',
			'post_name'     => $post->urlname,
			'tags_input'    => $post_tags,
			'post_category' => array( $this->get_section_id( $post->section_uuid ) ),
			'meta_input'    => $post_meta,
		);

		if ( $update ) {
			$post_args['ID'] = $this->get_post_id( $post->uuid );
			unset( $post_args['post_content'] );
			$post_id = wp_update_post( $post_args, true );
			update_post_meta( $post_id, '_newspack_last_updated', time() );
		} else {
			$post_id = wp_insert_post( $post_args, true );
		}

		if ( is_wp_error( $post_id ) || $update ) {
			return $post_id;
		}

		// Assign CoAuthors.
		if ( count( $author_ids ) > 1 ) {
			$authors = [];
			foreach ( $author_ids as $author_id ) {
				$author = get_user_by( 'id', $author_id );
				/**
				 * WARNING, this here is messy. It is a fix to legacy which was not working properly.
				 * For some reason, certain authors tags are of "type": "default", instead of the expected "type": "person", and so they were not created as authors by the cmd_metro_import_authors command, rather as tags.
				 * We need to create the authors from "tag" objects manually. We will also delete unused tags separately for cleanup.
				 */

				if ( ! $author ) {
					// Get this tag object. It was inserted as a regular tag, so find UUID in mappings.
					$tag_uuid = array_search( $author_id, $this->ids_mappings['tags'] );
					if ( ! $tag_uuid ) {
						WP_CLI::warning( sprintf( "ERROR Tag was previously inserted as regular tag instead of author (has type 'default' instead of 'person'). Tag UUID not found in mappings. Context: %s", wp_json_encode( [ 'post_id' => $post_id, 'post' => $post, 'tag_author_id' => $author_id ] ) ) );
						continue;
					}
					// Get tag from file.
					$tags_folder = WP_CONTENT_DIR . '/../621/tags/';
					$tag_file    = path_join( $tags_folder, $tag_uuid . '.json' );
					if ( ! file_exists( $tag_file ) ) {
						WP_CLI::warning( sprintf( "ERROR Tag was previously inserted as regular tag instead of author (has type 'default' instead of 'person'). Tag file '%s' not found. Context: %s", $tag_file, wp_json_encode( [ 'post_id' => $post_id, 'post' => $post, 'tag_uuid' => $tag_uuid, 'tag_id' => $author_id ] ) ) );
						continue;
					}
					$tag_file_contents = file_get_contents( $tag_file );
					$tag               = json_decode( $tag_file_contents );

					// Check if this user already created by this code block, from tag "type": "defult", try and fetch it by display_name directly.
					$author_existing_id = $wpdb->get_var( $wpdb->prepare(
						"SELECT ID
						FROM $wpdb->users
						WHERE display_name = %s",
						$tag->title
					) );
					if ( $author_existing_id ) {
						// It was previously created as user from tag, so fetch it.
						$author = get_user_by( 'id', $author_existing_id );
					} else {
						// It was not previously created as user from tag, so let's create it now.
						$author_inserted_id = $this->add_author( $tag );
						if ( is_wp_error( $author_inserted_id ) ) {
							WP_CLI::warning( sprintf( "ERROR Tag was previously inserted as regular tag instead of author (has type 'default' instead of 'person'). Author not successfully inserted. Context: %s", wp_json_encode( [ 'post_id' => $post_id, 'post' => $post, 'tag_uuid' => $tag_uuid, 'tag_file' => $tag_file ] ) ) );
							continue;
						}
						$author = get_user_by( 'id', $author_inserted_id );
					}
				}
				$authors[] = $author;
			}
			if ( ! empty( $authors ) ) {
				$this->coauthor_plus->assign_authors_to_post( $authors, $post_id );
			}
		}

		$featured_image = $post->feature_image_url ? $post->feature_image_url : $post->teaser_image_url;

		if ( $featured_image ) {
			$path_parts          = explode( '/', $featured_image );
			$featured_image_uuid = end( $path_parts );
			$featured_image_id   = $this->get_attachment_id( $featured_image_uuid );

			set_post_thumbnail( $post_id, $featured_image_id );
		}

		$this->add_post_id( $post->uuid, $post_id );

		return $post_id;
	}

	public function format_content( $post_content, $slots_found, $slots ) {
		$searches = array();
		$replaces = array();

		foreach ( $slots_found[1] as $index => $slot_uuid ) {
			if ( ! isset( $slots[ $slot_uuid ] ) ) {
				continue;
			}

			$searches[] = $slots_found[0][ $index ];
			$replaces   = $this->get_slot_html( $slots[ $slot_uuid ] );
		}

		return str_replace( $searches, $replaces, $post_content );
	}

	public function get_slot_html( $slot ) {
		if ( $slot->type == 'file' ) {
			return $this->get_image_slot_html( $slot );
		}

		if ( $slot->type == 'embed' ) {
			return $this->get_embed_slot_html( $slot );
		}

		if ( $slot->type == 'external' ) {
			return $this->get_external_slot_html( $slot );
		}
	}

	public function get_external_slot_html( $slot ) {
		$embed_block = <<<HTML

<!-- wp:embed {"url":"%s,"type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
%s
</div></figure>
<!-- /wp:embed -->

HTML;

		return sprintf( $embed_block, $slot->url, $slot->url );
	}

	public function get_image_slot_html( $slot ) {
		$caption_html   = '<figcaption class="wp-element-caption">%s</figcaption>';
		$image_block    = <<<HTML

<!-- wp:image {"id":%d,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="%s" alt="%s" class="wp-image-%d"/>%s</figure>
<!-- /wp:image -->

HTML;
		$caption        = $slot->content ? sprintf( $caption_html, $slot->content ) : '';
		$attachment_id  = $this->get_attachment_id( $slot->image_uuid );
		$attachment_url = wp_get_attachment_url( $attachment_id );
		$title          = get_the_title( $attachment_id );

		return sprintf( $image_block, $attachment_id, $attachment_url, $title, $attachment_id, $caption );
	}

	public function get_embed_slot_html( $slot ) {
		$searches = array();
		$replaces = array();

		$local_links_pattern = '/"(http(?:s)?:\/\/(?:www\.)?indyweek\.com(?:.)*?)"/m';

		$found = preg_match_all( $local_links_pattern, $slot->embed_code, $local_links );

		if ( $found ) {
			$attachments_logic = new Attachments();
		}

		foreach ( $local_links[1] as $local_link ) {
			$file           = end( explode( '/', $local_link ) );
			$filename       = pathinfo( $file, PATHINFO_FILENAME );
			$attachment_id  = $attachments_logic->get_attachment_by_filename( $filename );
			$attachment_url = wp_get_attachment_url( $attachment_id );
			$searches[]     = $local_link;
			$replaces[]     = $attachment_url;
		}

		return str_replace( $searches, $replaces, $slot->embed_code );
	}

	public function add_file( $data, $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$tmpfname = wp_tempnam( $file_path );
		copy( $file_path, $tmpfname );

		$file_array = array(
			'name'     => $data->filename,
			'tmp_name' => $tmpfname,
			'type'     => $data->mimetype,
		);

		$post_data = array(
			'post_title'   => $data->title ?? '',
			'post_date'    => $data->created,
			'post_content' => $data->description ?? '',
		);

		$attachment_id = media_handle_sideload( $file_array, 0, null, $post_data );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		if ( $data->credits ) {
			update_post_meta( $attachment_id, '_media_credit', $data->credits );
		}

		$this->add_attachment_id( $data->uuid, $attachment_id );

		return $attachment_id;
	}

	public function add_author( $author ) {
		// $name_parts = explode( ' ', $author->title );
		// $first_name = $author->first_name ?? $name_parts[0];
		// $last_name  = $name_parts[ count( $name_parts ) - 1 ];
		$user_email = substr( sha1( wp_json_encode( (array) $author ) ), 0, 10 ) . '@example.com';
		$user_args  = array(
			'user_login'    => substr( $author->urlname, 0, 60 ),
			'user_pass'     => wp_generate_password(),
			'user_nicename' => substr( $author->urlname, 0, 50 ),
			'user_email'    => $user_email,
			'display_name'  => $author->title,
			'description'   => $author->content,
			'role'          => 'contributor_no_edit', // Newspack Guest Contributor.
		);

		$user_id = wp_insert_user( $user_args );

		if ( ! is_wp_error( $user_id ) ) {
			$this->add_tag_id( $author->uuid, $user_id );
		}

		return $user_id;
	}

	public function add_tag( $tag ) {
		$tag_id = wp_insert_term(
			$tag->title,
			'post_tag',
			array(
				'slug' => $tag->urlname,
			),
		);

		if ( ! is_wp_error( $tag_id ) ) {
			$this->add_tag_id( $tag->uuid, $tag_id['term_id'] );
		}

		return $tag_id;
	}

	public function add_category( $section, $parent_id ) {
		$category_id = wp_insert_category(
			array(
				'cat_name'             => $section->title,
				'category_description' => $section->meta_description ?? '',
				'category_nicename'    => $section->urlname,
				'category_parent'      => $parent_id,
			),
			true,
		);

		if ( ! is_wp_error( $category_id ) ) {
			$this->add_section_id( $section->uuid, $category_id );
		}

		return $category_id;
	}

	public function attachment_exists( $uuid ) {
		return $this->get_attachment_id( $uuid ) != 0;
	}

	public function post_exists( $uuid ) {
		return $this->get_post_id( $uuid ) != 0;
	}

	public function location_exists( $uuid ) {
		return $this->get_location_id( $uuid ) != 0;
	}

	public function event_exists( $uuid ) {
		return $this->get_event_id( $uuid ) != 0;
	}

	public function section_exists( $uuid ) {
		return $this->get_section_id( $uuid ) != 0;
	}

	public function tag_exists( $uuid ) {
		return $this->get_tag_id( $uuid ) != 0;
	}

	public function get_attachment_id( $uuid ) {
		return $this->get_object_id( $uuid, 'attachments' );
	}

	public function get_post_id( $uuid ) {
		return $this->get_object_id( $uuid, 'posts' );
	}

	public function get_location_id( $uuid ) {
		return $this->get_object_id( $uuid, 'locations' );
	}

	public function get_event_id( $uuid ) {
		return $this->get_object_id( $uuid, 'events' );
	}

	public function get_tag_id( $uuid ) {
		return $this->get_object_id( $uuid, 'tags' );
	}

	public function get_section_id( $uuid ) {
		return $this->get_object_id( $uuid, 'categories' );
	}

	public function add_section_id( $uuid, $id ) {
		$this->add_object_id( $uuid, $id, 'categories' );
	}

	public function add_post_id( $uuid, $id ) {
		$this->add_object_id( $uuid, $id, 'posts' );
	}

	public function add_location_id( $uuid, $id ) {
		$this->add_object_id( $uuid, $id, 'locations' );
	}

	public function add_event_id( $uuid, $id ) {
		$this->add_object_id( $uuid, $id, 'events' );
	}

	public function add_tag_id( $uuid, $id ) {
		$this->add_object_id( $uuid, $id, 'tags' );
	}

	public function add_attachment_id( $uuid, $id ) {
		$this->add_object_id( $uuid, $id, 'attachments' );
	}

	public function add_object_id( $uuid, $id, $type ) {
		$this->ids_mappings[ $type ][ $uuid ] = $id;
		$mappings_file                        = path_join( $this->mappings_folder, $type . '.txt' );
		file_put_contents( $mappings_file, sprintf( "%s:%s\n", $uuid, $id ), FILE_APPEND );
	}

	public function get_object_id( $uuid, $type ) {
		return isset( $this->ids_mappings[ $type ][ $uuid ] ) ? $this->ids_mappings[ $type ][ $uuid ] : 0;
	}

	public function get_objects_from_folder( $folder, $decode = true ) {
		if ( ! file_exists( $folder ) ) {
			return false;
		}

		$handle = opendir( $folder );

		if ( ! $handle ) {
			return false;
		}

		$objects = [];

		while ( ( $file = readdir( $handle ) ) !== false ) {
			if ( pathinfo( $file, PATHINFO_EXTENSION ) != 'json' ) {
				continue;
			}

			if ( $decode ) {
				$file_content = file_get_contents( path_join( $folder, $file ) );
				$objects[]    = json_decode( $file_content );
			} else {
				$objects[] = path_join( $folder, $file );
			}
		}

		closedir( $handle );

		return $objects;
	}

	public function load_mappings() {
		if ( ! file_exists( $this->mappings_folder ) ) {
			return false;
		}

		$handle = opendir( $this->mappings_folder );

		if ( ! $handle ) {
			return false;
		}

		while ( ( $file = readdir( $handle ) ) !== false ) {
			if ( pathinfo( $file, PATHINFO_EXTENSION ) != 'txt' ) {
				continue;
			}

			$lines = file( path_join( $this->mappings_folder, $file ) );

			$type = str_replace( '.txt', '', $file );

			$this->ids_mappings[ $type ] = array();

			foreach ( $lines as $line ) {
				list( $uuid, $new_id ) = explode( ':', $line );

				$this->ids_mappings[ $type ][ $uuid ] = intval( $new_id ) == 0 ? trim( $new_id ) : intval( $new_id );
			}
		}
	}

}
