<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use DOMDocument;
use DOMXPath;
use Exception;
use Newspack\Guest_Contributor_Role;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\UsersHelper;
use Newspack\MigrationTools\Util\WordPressXMLHandler;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use NewspackCustomContentMigrator\Utils\ConsoleColor;
use WP_CLI;

/**
 * PalabraMigrator class.
 */
class PalabraMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * Register commands with WP CLI.
	 *
	 * @throws Exception If the command registration fails.
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator palabra-migrate',
			self::get_command_closure( 'cmd_migrate_data' ),
			[
				'shortdesc' => 'This command migrates Palabra content.',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'xml-path',
						'description' => 'Path to XML file to be migrated.',
						'optional'    => false,
						'repeating'   => true,
					],
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator palabra-update-thumbnail-ids',
			self::get_command_closure( 'cmd_update_thumbnail_ids' ),
			[
				'shortdesc' => 'This command performs an ID mapping procedure to correctly update thumbnail IDs.',
				'synopsis'  => [],
			]
		);
	}

	/**
	 * This command performs a basic migration of Palabra content using a provided XML file.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 * @throws Exception If a user cannot be found or created.
	 */
	public function cmd_migrate_data( array $args, array $assoc_args ) {
		$xml_file = $assoc_args['xml-path'];

		$xml = new DOMDocument( '1.0', 'UTF-8' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$xml->loadXML( file_get_contents( $xml_file ), LIBXML_PARSEHUGE | LIBXML_BIGLINES );

		$rss = $xml->getElementsByTagName( 'rss' )->item( 0 );

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$channel_children = $rss->childNodes->item( 1 )->childNodes;

		$posts   = [];
		$authors = get_users( [ 'role__in' => [ 'administrator', 'editor', 'author', 'contributor' ] ] );
		foreach ( $authors as $key => $author ) {
			$authors[ $author->user_login ] = $author;
			$modded_user_login              = strtolower( substr( $author->first_name, 0, 1 ) . $author->last_name );
			$authors[ $modded_user_login ]  = $author;
			unset( $authors[ $key ] );
		}
		$attachments = [];

		ConsoleColor::blue( 'Processing authors...' )->output();
		foreach ( $channel_children as $child ) {
			// Process only the authors first.
			if ( 'wp:author' === $child->nodeName ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$author = WordPressXMLHandler::get_or_create_author( $child );

				if ( ! array_key_exists( $author->user_login, $authors ) ) {
					$authors[ $author->user_login ] = $author;
				}
			}
		}
		ConsoleColor::bright_blue( 'Got authors...' )->output();

		$allowed_html_tags = wp_kses_allowed_html( 'post' );
		foreach ( $allowed_html_tags as $tag => $allowed_attributes ) {
			unset( $allowed_html_tags[ $tag ]['data-*'] );
		}

		global $coauthors_plus;

		ConsoleColor::cyan( 'Processing posts...' )->output();
		foreach ( $channel_children as $child ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			ConsoleColor::white( $child->nodeName )->output();

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( 'item' === $child->nodeName ) {
				$data = WordPressXMLHandler::get_parsed_data( $child, $authors );

				if ( ! isset( $data['post'] ) ) {
					ConsoleColor::magenta( 'No post data found.' )->output();
					continue;
				}

				ConsoleColor::white( 'Post ID:' )
							->bright_yellow( $data['post']['ID'] )
							->white( 'Post Type:' )
							->bright_yellow( $data['post']['post_type'] )
							->white( 'Post Status:' )
							->bright_yellow( $data['post']['post_status'] )
							->white( 'Post Date:' )
							->bright_yellow( $data['post']['post_date'] ?? '?' )
							->output();
				ConsoleColor::white( 'Title:' )->bright_yellow( $data['post']['post_title'] )->output();
				ConsoleColor::white( 'Post Name:' )->bright_yellow( $data['post']['post_name'] )->output();

				$article_authors = [];
				if ( ! empty( $data['post']['post_content'] ) ) {
					$data['post']['post_content'] = wp_kses( $data['post']['post_content'], $allowed_html_tags );
					// Regex to remove whitespace between HTML tags.
					$data['post']['post_content'] = preg_replace( '/(?<=>)\s+(?=<)|$\s+(?=<)|(&nbsp;)+/m', '', $data['post']['post_content'] );

					$html = new DOMDocument( '1.0', 'UTF-8' );
					// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					@$html->loadHTML( '<!doctype html><html><head><meta charset="UTF-8"></head>' . $data['post']['post_content'] . '</html>' );

					$finder        = new DOMXPath( $html );
					$found_authors = $finder->query( './/div[contains(concat(" ",normalize-space(@class)," ")," byline-container ")]/ul[contains(concat(" ",normalize-space(@class)," ")," article-byline ")]/li[contains(concat(" ",normalize-space(@class)," ")," author ")]' );

					$parent_block = null;
					foreach ( $found_authors as $author_node ) {
						// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						ConsoleColor::high_contrast_kv_output( 'AUTHOR', $author_node->nodeValue );

						$user = UsersHelper::create_or_get_user(
							[
								'user_email'    => '',
								'user_nicename' => '',
								'user_login'    => '',
								// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
								'display_name'  => $author_node->nodeValue,
								'role'          => 'contributor',
								// 'role'          => Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME,
							],
							$author_node->nodeValue // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						);

						if ( ! array_key_exists( $user->user_login, $authors ) ) {
							$authors[ $user->user_login ] = $user;
						}

						$article_authors[] = $user;

						$author_node = $author_node->parentNode; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$author_node = $author_node->parentNode; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$result      = $author_node->parentNode->removeChild( $author_node ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					}

					$found_thumbnails = $finder->query( './/*[contains(concat(" ",normalize-space(@class)," ")," byline-container-image ")]' );

					foreach ( $found_thumbnails as $found_thumbnail ) {
						$found_thumbnail->parentNode->removeChild( $found_thumbnail ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					}
				}

				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$data['post']['post_content'] = $html->saveHTML( $html->documentElement );
				$data['post']['meta'][]       = [
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_key'   => '_newspack_import_id',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'meta_value' => $data['post']['ID'],
				];
				unset( $data['post']['ID'] );
				unset( $data['post']['post_parent'] );
				$post = wp_insert_post( $data['post'], true );

				if ( is_wp_error( $post ) ) {
					ConsoleColor::red( 'Error inserting post:' )->underlined_bright_red( $post->get_error_message() )->output();
					continue;
				}

				$post = get_post( $post );

				$message = "{$data['post']['post_type']} inserted successfully";
				ConsoleColor::green( $message )
							->bright_green( $post->ID )
							->underlined_green( "http://docker.local/?p=$post->ID" )
							->output();

				foreach ( $data['post']['meta'] as $meta ) {
					add_post_meta( $post->ID, $meta['meta_key'], $meta['meta_value'] );
				}

				if ( ! empty( $article_authors ) ) {
					$coauthors_plus->add_coauthors(
						$post->ID,
						array_map( fn( $user ) => $user->user_nicename, $article_authors )
					);
				}
			}
		}
	}

	/**
	 * This is an XML import. The ID's that are referenced in the XML might not match the ID's in the WordPress database.
	 * The original ID's are stored in the `_newspack_import_id` meta key. This command will update the ID in
	 * to match the actual ID of the corresponding post.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function cmd_update_thumbnail_ids( array $args, array $assoc_args ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$thumbnails = $wpdb->get_results(
			"SELECT * FROM $wpdb->postmeta WHERE meta_key = '_thumbnail_id'"
		);

		foreach ( $thumbnails as $thumbnail ) {
			ConsoleColor::white( 'Post ID:' )
						->bright_yellow( $thumbnail->post_id )
						->white( 'Thumbnail ID:' )
						->bright_yellow( $thumbnail->meta_value )
						->output();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$post_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_newspack_import_id' AND meta_value = %d",
					$thumbnail->meta_value
				)
			);

			if ( $post_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$maybe_updated = $wpdb->update(
					$wpdb->postmeta,
					[
						'meta_value' => $post_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					],
					[
						'meta_id' => $thumbnail->meta_id,
					]
				);

				if ( $maybe_updated ) {
					ConsoleColor::green( $thumbnail->meta_value )->white( '→' )->bright_green( $post_id )->output();
				} else {
					ConsoleColor::red( 'Error updating thumbnail ID' )->output();
				}
			}
		}
	}
}
