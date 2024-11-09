<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Exception;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\Concrete5XmlParser;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Logic\Posts;
use Newspack\MigrationTools\Logic\Taxonomy;
use Newspack\MigrationTools\NMT;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use Psr\Log\LoggerInterface;
use simplehtmldom\HtmlDocument;
use WP_CLI;
use WP_CLI\ExitException;
use WP_Error;

class BailiwickMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	private LoggerInterface $cli_logger;
	private LoggerInterface $file_logger;

	private function __construct() {
		$this->cli_logger  = CliLog::get_logger( 'bw' );
		$this->file_logger = FileLog::get_logger( 'bw' );
	}

	public static function register_commands(): void {

		$xml_file = [
			'type'        => 'assoc',
			'name'        => 'xml-file',
			'description' => 'Path to XML file - can also be a url',
			'optional'    => false,
		];

		$refresh = [
			'type'        => 'flag',
			'name'        => 'refresh-existing',
			'description' => 'Refresh existing articles',
			'optional'    => true,
		];

		WP_CLI::add_command(
			'newspack-content-migrator bw-import-articles-from-xml',
			self::get_command_closure( 'cmd_import_articles_from_xml' ),
			[
				'shortdesc' => 'Import articles from an XML file.',
				'synopsis'  => [
					$xml_file,
					$refresh,
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator bw-import-featured-images',
			self::get_command_closure( 'cmd_import_featured_images' ),
			[
				'shortdesc' => 'Import featured images.',
				'synopsis'  => [
					$refresh,
					// TODO. Add more args like post id, etc.
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator bw-import-inline-images',
			self::get_command_closure( 'cmd_import_inline_images' ),
			[
				'shortdesc' => 'Import inline images.',
				'synopsis'  => [
					$refresh,
					// TODO. Add more args like post id, etc.
				],
			]
		);
	}


	/**
	 * TODO:
	 *  - Fetch inline images
	 *  - Get author
	 *  - Fix formatting in content
	 *  - Transform links. Probably impossible in this run.
	 *  - While it's great that this can fetch from urls and files, we should probably download the files when fetching from urls.
	 *  - Clean up <h1> tags in content.
	 *  - Sanitize the HTML a little bit if possible.
	 */
	public function cmd_import_articles_from_xml( array $pos_args, array $assoc_args ): void {
		$xml_file_path = $assoc_args['xml-file'];
		$refresh       = $assoc_args['refresh-existing'] ?? false;
		$parser        = null;
		try {
			$parser = new Concrete5XmlParser( $xml_file_path );
		} catch ( Exception $o_0 ) {
			NMT::exit_with_message( $o_0->getMessage(), [ $this->cli_logger ] );
		}

		$taxonomy_helper = new Taxonomy();
		$home_url        = home_url();

		$file_logger = FileLog::get_logger( 'bw-article-import' );

		$counter = 0;
		foreach ( $parser->get_articles() as $article_xml ) {
			$post = [
				'post_type'   => 'post',
				'post_status' => 'publish',
			];

			$url         = trim( (string) $article_xml->url );
			$path        = parse_url( $url, PHP_URL_PATH );
			$existing_id = $this->get_post_id_by_old_path( $path );
			if ( ! empty( $existing_id ) ) {
				if ( ! $refresh ) {
					$this->cli_logger->info( 'Article already imported', [ 'path' => $path, 'ID' => $existing_id ] );
					continue;
				}
				$post['ID'] = $existing_id;

			}

			if ( ++$counter % 10 === 0 ) {
				$this->cli_logger->info( sprintf( 'Imported %s articles', $counter ) );
			}

			$post['meta_input']['_old_path'] = $path;

			$category_name = (string) $article_xml->category;
			$taxonomy_helper->get_or_create_category_by_name_and_parent_id( $category_name, 0 );

			$tags = explode( ',', (string) $article_xml->tags );
			if ( ! empty( $tags ) ) {
				$post['tags_input'] = $tags;
			}

			$post['post_title'] = (string) $article_xml->title;
			$post['post_name']  = basename( $url );

//		$post-['post_author'] = $this->author; //TODO
			$post['post_date'] = (string) $article_xml->datePublic;
			$lead              = (string) $article_xml->lead;
			if ( ! empty( $lead ) ) {
				$post['meta_input']['newspack_post_subtitle'] = $lead;
			}

			$content     = (string) $article_xml->content;
			$description = (string) $article_xml->description;

			$post['post_content'] = $content . $description;
			$post_id              = wp_insert_post( $post );
			if ( is_wp_error( $post_id ) ) {
				$this->cli_logger->error( 'Failed to import article', [ 'error' => $post_id ] );
				continue;
			}

			$this->cli_logger->notice( 'Imported article', [ 'post_id' => $post_id, 'to_url' => "$home_url/?p=$post_id" ] );
			$file_logger->notice( 'Imported article', [ 'post_id' => $post_id, 'from_url' => $url ] );

			$featured_image = trim( ( (string) $article_xml->image ?? '' ) );
			if ( ! empty( $featured_image ) ) {
				$post['meta_input']['_old_image'] = $featured_image;

				$attachment_id = $this->get_image_from_url( $featured_image, $post_id );
				if ( ! is_wp_error( $attachment_id ) ) {
					set_post_thumbnail( $post_id, $attachment_id );
					FileLog::get_logger( 'bw-images' )->notice( 'Imported featured image', [ 'post_id' => $post_id, 'image' => $featured_image ] );
				} else {
					FileLog::get_logger( 'bw-images' )->error( 'Could not download featured image', [ 'post_id' => $post_id, 'image' => $featured_image ] );
				}
			}
		}
	}

	public function cmd_import_inline_images( array $pos_args, array $assoc_args ): void {
		$refresh     = $assoc_args['refresh-existing'] ?? false;
		$post_helper = new Posts();
		$gb_blocks   = new GutenbergBlockGenerator();
		foreach ( $post_helper->get_all_posts_ids() as $id ) {
			$content = get_post_field( 'post_content', $id );
			if ( ! str_contains( $content, '<img ' ) ) {
				$this->cli_logger->info( 'No inline images found in post', [ 'post_id' => $id ] );
				continue;
			}
			$trut     = '';
			$html_doc = new HtmlDocument( $content );

			$images = $html_doc->find( 'img' );
			if ( empty( $images ) ) {
				$this->cli_logger->info( 'No inline images found in post', [ 'post_id' => $id ] );
				continue;
			}
			foreach ( $images as $img ) {
				$src = $img?->getAttribute( 'src' );
				if ( ! $src ) {
					return; // TODO
				}
				if ( ! str_starts_with( $src, 'http' ) ) {
					$src = NP_LIVE . $src;
				}
				$att = $this->get_image_from_url( $src, $id );
				if ( is_wp_error( $att ) ) {
					$this->cli_logger->error( 'Failed to import inline image', [ 'post_id' => $id, 'src' => $src, 'error' => $att ] );
					continue;
				}
				$img_text = $img->find( '<p><strong>' );
				if ( ! empty( $img_text ) ) {
					// Might be risky. We don't know if this is the correct image it's under.
					$img_text = $img_text[0]->innertext;
				}

				$img->outertext = serialize_block(
					$gb_blocks->get_image(
						get_post( $att ),
						'full',
						false
					)
				);
			}
			$text = $html_doc->save();
			wp_update_post(
				[
					'ID'           => $id,
					'post_content' => $text,
				]
			);

			$this->cli_logger->notice( 'Imported inline images', [ 'post_id' => $id ] );


			// todo. Set migration meta.
		}
	}

	public function cmd_import_featured_images( array $pos_args, array $assoc_args ): void {
		$refresh     = $assoc_args['refresh-existing'] ?? false;
		$post_helper = new Posts();
		$posts       = get_posts(
			[
				// TODO. Better query - exlcude ones with featured image already.
				// Maybe use the BatchLogic class
				'meta_key'    => '_old_image',
				'numberposts' => -1,
			]
		);

		$counter = 0;
		foreach ( $posts as $post ) {
			$featured_image = get_post_meta( $post->ID, '_old_image', true );
			if ( empty( $featured_image ) ) {
				continue;
			}
			$featured_image_id = $this->get_image_from_url( $featured_image, $post->ID );
			if ( ! is_wp_error( $featured_image_id ) ) {
				$this->cli_logger->notice( 'Imported featured image', [ 'post_id' => $post->ID, 'image' => $featured_image ] );
				set_post_thumbnail( $post->ID, $featured_image_id );
			}
		}
	}

	private function get_image_from_url( string $url, int $post_id ): int|WP_Error {
		if ( empty( $url ) ) {
			return new WP_Error( '', 'No image URL provided' );
		}
		// TODO. Should this be optional? The predict?
		$path = self::get_predicted_file_path( $post_id, $url );
		if ( ! file_exists( $path ) ) {
			$featured_image_id = Attachments::import_attachment_for_post( $post_id, $url );
		} else {
			$featured_image_id = Attachments::maybe_get_existing_attachment_id( $path );
			if ( empty( $featured_image_id ) ) {
				$featured_image_id = Attachments::import_attachment_for_post( $post_id, $url );
			}
		}

		return $featured_image_id;
	}

	public function get_post_id_by_old_path( string $old_path ): int {
		$posts = get_posts(
			[
				'meta_key'   => '_old_path',
				'meta_value' => $old_path,
			]
		);

		return $posts[0]->ID ?? 0;
	}

	public static function get_predicted_file_path( int $post_id, string $filename ) {
		// TODO. This assumes that images are uploaded like that with the date. Are they always?
		$upload_dir = wp_upload_dir( get_post_time( 'Y/m', false, $post_id ), false );

		$sanitized_filename = sanitize_file_name( basename( $filename ) );

		return trailingslashit( $upload_dir['path'] ) . $sanitized_filename;
	}



}