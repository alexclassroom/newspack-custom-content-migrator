<?php

namespace NewspackCustomContentMigrator\Command\General;

use DateTime;
use DateTimeZone;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\CoAuthorsPlusHelper;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Logic\Posts as PostsLogic;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;
use WP_Error;
use WP_User;
use simplehtmldom\HtmlDocument;

/**
 * Custom migration scripts for Posts' content.
 */
class EllingtonCMSMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * Posts Logic.
	 * 
	 * @var PostsLogic
	 */
	private PostsLogic $posts_logic;

	/**
	 * Attachments Logic.
	 * 
	 * @var Attachments
	 */
	private Attachments $attachments;

	/**
	 * Co-Authors Plus.
	 * 
	 * @var CoAuthorsPlusHelper
	 */
	private CoAuthorsPlusHelper $cap;

	/**
	 * Gutenber Block Generator.
	 * 
	 * @var GutenbergBlockGenerator
	 */
	private GutenbergBlockGenerator $gutenberg_block_generator;

	/**
	 * Logger.
	 * 
	 * @var MultiLog
	 */
	private MultiLog $logger;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->posts_logic               = new PostsLogic();
		$this->attachments               = new Attachments();
		$this->cap                       = new CoAuthorsPlusHelper();
		$this->gutenberg_block_generator = new GutenbergBlockGenerator();
		$this->logger                    = MultiLog::get_logger( 'ellingtoncms-migrator', [
			CliLog::get_logger( 'ellingtoncms-migrator' ),
			FileLog::get_logger( 'ellingtoncms-migrator' ),
		] );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator ellington-cms-migrator migrate-posts',
			self::get_command_closure( 'cmd_migrate_posts' ),
			[
				'shortdesc' => 'Goes through all the Posts, and removes all occurrences of featured image from beginning of Post content.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'dir-path',
						'description' => 'Path to the directory where XML files are located.',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'source-timezone',
						'description' => 'The Timezone of the source',
						'optional'    => false,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'default-featured-image',
						'description' => 'The ID of the default Attachment to be used for Featured Thumbnail.',
						'optional'    => true,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'default-author',
						'description' => 'The ID of the default Author to be used for Posts.',
						'optional'    => true,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'post-tag',
						'description' => 'Apply the given Post Tag to posts.',
						'optional'    => true,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'from-index',
						'description' => 'Start from the given index.',
						'optional'    => true,
						'repeating'   => false,
					],
					[
						'type'        => 'flag',
						'name'        => 'refresh-content',
						'description' => 'Refresh the content of the posts that were already imported.',
						'optional'    => true,
						'repeating'   => false,
					],
				],
			]
		);
	}

	/**
	 * Migrates posts from XML.
	 *
	 * @param  array  $args
	 * @param  array  $assoc_args
	 * @return void
	 */
	public function cmd_migrate_posts( $args, $assoc_args ) {
		$xml_dir_path           = $assoc_args['dir-path'];
		$source_timezone        = $assoc_args['source-timezone'];
		$refresh_content        = isset( $assoc_args['refresh-content'] ) ? true : false;
		$post_tag               = $assoc_args['post-tag'];
		$default_featured_image = $assoc_args['default-featured-image'];
		$default_author         = $assoc_args['default-author'];
		$from_index             = isset( $assoc_args['from-index'] ) ? absint( $assoc_args['from-index'] ) : 0;

		$xml_files = scandir( $xml_dir_path );
		$xml_files = array_filter( $xml_files, fn ( $filename ) => ! in_array( $filename, [ '.', '..' ] ) );

		natsort( $xml_files );

		// QA
		$qa_filename    = 'ellingtoncms-migrator-migrate-posts.csv';
		$qa_file_exists = file_exists( $qa_filename );
		$qa_file        = fopen( $qa_filename, 'a' );

		$qa_header  = [
			'#',
			'Post ID',
			'Post URL',
			'XML Source File',
		];

		if ( ! $qa_file_exists ) {
			fputcsv( $qa_file, $qa_header );
		}

		$progress_bar = WP_CLI\Utils\make_progress_bar(
			sprintf(
				'[Memory Usage: %s] Ellington CMS Migrator: Migrating Posts',
				size_format( memory_get_usage( true ) )
			),
			count( $xml_files )
		);

		$this->logger->info( sprintf( 'Found %d files', count( $xml_files ) ) );

		foreach ( array_values( $xml_files ) as $index => $xml_file ) {
			$progress_bar->tick(
				1,
				sprintf(
					'[Memory Usage: %s] Ellington CMS Migrator: Migrating Posts (%d/%d)',
					size_format( memory_get_usage( true ) ),
					$index,
					count( $xml_files )
				)
			);

			if ( ! empty( $from_index ) && $index < $from_index ) {
				continue;
			}

			$this->logger->info(
				sprintf(
					'Processing %d / %d — %s',
					$index + 1,
					count( $xml_files ),
					$xml_file
				)
			);

			$xml_contents = file_get_contents( $xml_dir_path . DIRECTORY_SEPARATOR . $xml_file );

			$post_id = $this->upsert_post(
				file_contents: $xml_contents,
				filename: $xml_file,
				source_timezone: $source_timezone,
				default_author: $default_author,
				default_featured_image: $default_featured_image,
				post_tag: $post_tag,
				refresh: $refresh_content
			);

			fputcsv( $qa_file, [
				$index + 1,
				$post_id,
				get_permalink( $post_id ),
				$xml_file
			] );
		}

		$progress_bar->finish();

		fclose( $qa_file );

		wp_cache_flush();
		
		$this->logger->info( 'Completed! 🎉' );
	}

	/**
	 * Upsert Post from an XML file.
	 * 
	 * @param  string       $file_contents The contents of the XML file.
	 * @param  string       $filename The name of the XML file.
	 * @param  string       $source_timezone The Timezone of the Source.
	 * @param  string|null  $default_author The default author to use for the Posts.
	 * @param  string|null  $default_featured_image The default featured image to use for the Posts.
	 * @param  string|null  $post_tag The Post Tag to apply to the Post.
	 * @param  boolean      $refresh Whether the Post should be refreshed.
	 * @return int The ID of the created / updated Post.
	 */
	private function upsert_post(
		string $file_contents,
		string $filename,
		string $source_timezone,
		?string $default_author,
		?string $default_featured_image,
		?string $post_tag,
		bool $refresh = false
	): int {
		global $wpdb;

		preg_match( '~^story-(\d+)~', $filename, $post_source_id );

		$post_source_id = $post_source_id[1];

		$local_post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `post_id`
                FROM `$wpdb->postmeta`
                WHERE `meta_key` = 'newspack_post_source_id'
                AND `meta_value` = %s",
				$post_source_id
			)
		);

		if ( $local_post_id ) {
			$this->logger->warning( sprintf( 'Post #%d already exists', $local_post_id ) );
		}

		if ( $local_post_id && ! $refresh ) {
			$this->logger->info( 'Skipping update (Refresh not required)' );

			return absint( $local_post_id );
		}

		$post_data = $this->parse_post_xml( $file_contents );

		// Post Data Comments
		if ( ! empty( $post_data['comments'] ) ) {
			// Details
			// — Group
			// — — Comment Text
			// — — Comment Meta
			$comment_blocks = [];

			foreach ( $post_data['comments'] as $comment_index => $comment ) {
				$comment_block = [
					$this
						->gutenberg_block_generator
						->get_paragraph( $comment['Comment'] ),
					...parse_blocks(
						sprintf(
							'<!-- wp:paragraph {"style":{"elements":{"link":{"color":{"text":"%1$s"}}},"color":{"text":"%1$s"}},"fontSize":"small"} --><p class="has-text-color has-link-color has-small-font-size" style="color:%1$s">#%2$s | Author: %3$s | Date: %4$s</p><!-- /wp:paragraph -->',
							'#bbbbbb',
							$comment['ID'],
							$comment['Author'],
							date( 'M j Y', strtotime( $comment['Date'] ) )
						)
					)
				];

				$comment_blocks[] = $this
					->gutenberg_block_generator
					->get_group_constrained( $comment_block, [ 'jfp-comment-' . $comment['ID'] ] );

				if ( $comment_index !== count( $post_data['comments'] ) - 1 ) {
					$comment_blocks[] = $this
						->gutenberg_block_generator
						->get_separator( 'is-style-wide' );
				}
			}

			$details_block_inner_content = ['<details class="wp-block-details jfp-previous-comments"><summary>Previous Comments</summary>'];

			foreach ( $comment_blocks as $index => $comment_block ) {
				$details_block_inner_content[] = NULL;

				if ( $index < ( count( $comment_blocks ) - 1 ) ) {
					$details_block_inner_content[] = '';
				}
			}

			$details_block_inner_content[] = '</details>';

			$post_data['content'] .= serialize_block( [
				'blockName'    => 'core/details',
				'attrs'        => [
					'className' => 'jfp-previous-comments',
				],
				'innerBlocks'  => $comment_blocks,
				'innerHTML'    => '<details class="wp-block-details jfp-previous-comments"><summary>Previous Comments</summary> </details>',
				'innerContent' => $details_block_inner_content,
			] );
		}

		// Post Authors.
		$post_authors = array_values(
			array_filter(
				array_map(
					function ( $author_data ) use ( $post_source_id ) {
						$wp_user = $this->upsert_author( $author_data['first_name'], $author_data['last_name'] );

						if ( is_wp_error( $wp_user ) ) {
							$this->logger->critical( sprintf( '#' . $post_source_id . ' Couldn\'t upsert Author "%s" (%s)', implode( ', ', array_filter( $author_data ) ), $wp_user->get_error_message( 0 ) ) );

							return null;
						}

						return $wp_user;
					},
					$post_data['authors']
				) 
			) 
		);

		// Categories.
		$categories = $this->upsert_categories( $post_data['category_slugs'] );

		// Published Date
		$published_datetime     = new DateTime( $post_data['published_date'], new DateTimeZone( $source_timezone ) );
		$published_datetime_gmt = new DateTime( $post_data['published_date'], new DateTimeZone( $source_timezone ) );
		$published_datetime_gmt->setTimezone( new DateTimeZone( 'GMT' ) );

		// Insert / Update Post.
		$post_data = [
			'ID'            => $local_post_id ?? 0,
			'post_title'    => $post_data['title'],
			'post_excerpt'  => $post_data['excerpt'],
			'post_content'  => $post_data['content'],
			'post_status'   => 'publish',
			'post_author'   => ! empty( $post_authors ) ? $post_authors[0]->ID : $default_author,
			'post_date'     => $published_datetime->format( 'Y-m-d H:i:s' ),
			'post_date_gmt' => $published_datetime_gmt->format( 'Y-m-d H:i:s' ),
			'post_category' => wp_list_pluck( $categories, 'term_id' ),
			'tags_input'    => ! empty( $post_tag ) ? [ $post_tag ] : [], 
			'meta_input'    => [
				'_thumbnail_id'                    => $default_featured_image,
				'newspack_featured_image_position' => 'hidden', // Default Featured Image should be hidden, by default.
				'newspack_post_source_id'          => $post_source_id,
				'newspack_post_source_url'         => $post_data['full_slug'],
				'newspack_post_source_filename'    => $filename,
			],
		];

		$post_id = wp_insert_post( $post_data );
		
		try {
			if ( $this->cap->is_coauthors_active() && ! empty( $post_authors ) ) {
				$this->cap->assign_authors_to_post( $post_authors, $post_id );
			}
		} catch ( \Exception $e ) {
			$this->logger->critical( '🚫 Error assigning authors to post. Post ID: ' . $post_id . ' — ' . $e->getMessage() );
		}

		// Address media in post content.
		// We do it after the post has been imported in order to associate the
		// images inside with the post ID.
		$this->transform_and_update_post_content( $post_id );

		return $post_id;
	}

	/** 
	 * Parses the given Post XML and returns an array of raw mapped values.
	 * 
	 * @param  string $xml_file_contents
	 * @return array  The mapped raw values for the Post.
	 */
	private function parse_post_xml( string $xml_file_contents ): array {
		// Replace invalid HTML tags in order to read them later.
		$xml_file_contents = str_replace( '<date.release', '<date_release', $xml_file_contents );
		$xml_file_contents = str_replace( '<body.head>', '<body_head>', $xml_file_contents );
		$xml_file_contents = str_replace( '</body.head>', '</body_head>', $xml_file_contents );
		$xml_file_contents = str_replace( '<body.content>', '<body_content>', $xml_file_contents );
		$xml_file_contents = str_replace( '</body.content>', '</body_content>', $xml_file_contents );
		$xml_file_contents = str_replace( [ '<name.given>', '</name.given>' ], [ '<name_given>', '</name_given>' ], $xml_file_contents );
		$xml_file_contents = str_replace( [ '<name.family>', '</name.family>' ], [ '<name_family>', '</name_family>' ], $xml_file_contents );

		$post_doc = new HtmlDocument( $xml_file_contents );
		
		$post_data = [
			'title'          => $post_doc->find( 'nitf > body > body_head > hedline > hl1', 0 )?->text(),
			'subtitle'       => $post_doc->find( 'nitf > body > body_head > hedline > hl2', 0 )?->text(),
			'category_slugs' => $post_doc->find( 'nitf > head > meta[name="category"]', 0 )?->getAttribute( 'content' ),
			'full_slug'      => $post_doc->find( 'nitf > head > doc-id', 0 )?->getAttribute( 'id-string' ),
			'published_date' => $post_doc->find( 'nitf > head > docdata > date_release', 0 )?->getAttribute( 'norm' ),
			'excerpt'        => $post_doc->find( 'nitf > body > body_head > abstract', 0 )?->text(),
			'content'        => str_replace( [ '<body_content><div>', '</div></body_content>' ], '', $post_doc->find( 'nitf > body > body_content', 0 )?->text() ),
			'authors'        => array_map(
				function ( $author ) {
					return [
						'first_name' => $author->find( 'name_given', 0 )?->text(),
						'last_name'  => $author->find( 'name_family', 0 )?->text(),
					];
				},
				$post_doc->find( 'nitf > body > body_head > byline > person' ) ?? []
			),
		];

		// Handle Post Comments.
		if ( strpos( $post_data['content'], 'Previous Comments' ) ) {
			// Organize comments.
			$content_parts = explode( '<h3>Previous Comments</h3>', $post_data['content'] );
			$comments      = ( new HTMLDocument( $content_parts[1] ) )->find( 'dl' );
			
			$post_data['comments'] = array_map(
				function ( $comment ) {
					$comment_data    = [];
					$comment_details = array_chunk( $comment->nodes, 2 );

					foreach ( $comment_details as $comment_detail ) {
						$comment_data[ $comment_detail[0]->text() ] = $comment_detail[1]->text();
					}

					return $comment_data;
				},
				$comments
			);

			// Cleanup comments from body
			$post_data['content'] = trim( $content_parts[0] );
		}

		return $post_data;
	}

	/**
	 * Transform posts body content.
	 * 
	 * @param  int $post_id The ID of the Post to be updated.
	 * @return void
	 */
	private function transform_and_update_post_content( $post_id ): void {
		global $wpdb;

		$post_content = get_post_field( 'post_content', $post_id );

		if ( ! str_contains( $post_content, '<media' ) ) {
			return;
		}

		$post_content_doc = new HtmlDocument( $post_content );

		$medias = $post_content_doc->find( 'media' );

		foreach ( $medias as $media ) {
			$is_post_thumbnail = $media->find( 'media-metadata[name=lead_photo]', 0 )?->getAttribute( 'value' ) == 'true';
			$image_id          = $media->find( 'media-metadata[name=id]', 0 )->getAttribute( 'value' );
			$image_src         = $media->find( 'media-reference', 0 )->getAttribute( 'source' );
			$image_credit      = $media->find( 'media-producer', 0 )->text();
			$image_caption     = $media->find( 'media-caption', 0 )->text();

			$attachment_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT `post_id`
                    FROM `$wpdb->postmeta`
                    WHERE `meta_key` = 'newspack_attachment_source_id'
                    AND `meta_value` = %s",
					$image_id
				)
			);

			if ( ! $attachment_id ) {
				$attachment_id = $this
					->attachments
					->import_external_file( $image_src, null, $image_caption, null, null, $post_id );

				update_post_meta( $attachment_id, 'newspack_attachment_source_id', $image_id );
				update_post_meta( $attachment_id, '_media_credit', $image_credit );
			}

			if ( is_wp_error( $attachment_id ) ) {
				$this->logger->critical( '🚫 Error importing attachment. Post ID: ' . $post_id . ' — ' . $attachment_id->get_error_message() );
				continue;
			}

			if ( ! $attachment_id ) {
				continue;
			}

			if ( $is_post_thumbnail ) {
				update_post_meta( $post_id, '_thumbnail_id', $attachment_id );
				update_post_meta( $post_id, 'newspack_featured_image_position', '' ); // Show featured image.

				$post_content = str_replace( $media->outerText(), '', $post_content );
			} else {
				$post_content = str_replace(
					$media->outerText(),
					sprintf(
						'[caption id="attachment_%s" align="alignleft"]%s[/caption]',
						$attachment_id,
						wp_get_attachment_image( $attachment_id, 'thumbnail' ) . $image_caption
					),
					$post_content
				);
			}
		}

		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => $post_content,
			] 
		);
	}

	/**
	 * Inserts a new User with the given first and last (optional) name.
	 * The email of the User should be "name.given+name.family+jfp@mississippifreepress.org".
	 * 
	 * @param  string      $first_name
	 * @param  string|null $last_name
	 * @return WP_User|WP_Error The ID of the User on success. Otherwise, returns a WP_Error
	 */
	private function upsert_author( $first_name, $last_name = null ): WP_User|WP_Error {
		$username = substr( implode(
			'',
			array_map(
				fn ( $name_part ) => str_replace( '-', '', sanitize_title( $name_part ) ),
				array_filter( [ $first_name, $last_name ] )
			) 
		), 0, 60 ); // Username can be max 60 chars.
		$user_email = $username . '+jfp@mississippifreepress.org';

		$wp_user = get_user_by( 'email', $user_email ) ?: get_user_by( 'login', $username );

		if ( ! $wp_user ) {
			$wp_user = wp_insert_user(
				[
					'first_name'    => $first_name,
					'last_name'     => $last_name,
					'display_name'  => implode( ' ', array_filter( [ $first_name, $last_name ] ) ),
					'user_nicename' => substr( $username, 0, 50 ), // User Nicename can be max 50 chars.
					'user_login'    => $username,
					'user_email'    => $user_email,
					'user_pass'     => wp_generate_password(),
					'role'          => 'author',
					'meta_input'    => [
						'newspack_user_imported_date' => date( 'Y-m-d H:i:s' ),
						'newspack_user_source'        => 'Jackson Free Press',
					],
				] 
			);

			if ( ! is_wp_error( $wp_user ) ) {
				$wp_user = get_user_by( 'ID', $wp_user );
			}
		}

		return $wp_user;
	}

	/**
	 * Upserts categories.
	 * 
	 * The categories are passed by raw value such as "/candidate|/imported|/interview".
	 * The example above contains three categories, each of them representing a Category slug.
	 * 
	 * This method takes the raw value and tries to insert a new category for it, or returns an
	 * already existing Category.
	 * 
	 * @param  string $raw_category_slugs The category slugs passed in the format from the description.
	 * @return array  An array of WP_Term instances representing Categories.
	 */
	private function upsert_categories( string $raw_category_slugs ): array {
		$category_slugs = explode( '|', $raw_category_slugs );
		$category_slugs = array_map( function ( $category_slug ) {
			if ( $category_slug === '/' ) {
				$category_slug = 'news';
			}

			return str_replace( '/', '', $category_slug );
		}, $category_slugs );

		$category_names = array_map( fn ( $category ) => ucwords( str_replace( '-', ' ', $category ) ), $category_slugs );

		$categories = [];

		foreach ( $category_names as $index => $category_name ) {
			if ( empty( $category_name ) ) {
				continue;
			}

			$category = get_term_by( 'slug', $category_slugs[ $index ], 'category' );

			if ( ! $category ) {
				// Category couldn't be found. Trying to insert it.
				$category = wp_insert_term( $category_name, 'category' );
			}

			if ( is_wp_error( $category ) ) {
				$this->logger->critical( '🚫 Error inserting category: ' . $category->get_error_message() . ' | ' . $category_name . ' | ' . $raw_category_slugs );
			} else {
				$categories[] = $category;
			}
		}

		return $categories;
	}
}
