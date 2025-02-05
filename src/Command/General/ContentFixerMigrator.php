<?php

namespace NewspackCustomContentMigrator\Command\General;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Posts as PostsLogic;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;

/**
 * Custom migration scripts for Posts' content.
 */
class ContentFixerMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	const POST_CONTENT_SHORTCODES_MIGRATION_LOG = 'POST_CONTENT_SHORTCODES_MIGRATION.log';

	/**
	 * @var PostsLogic.
	 */
	private $posts_logic;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->posts_logic = new PostsLogic();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator remove-first-image-from-post-body',
			self::get_command_closure( 'remove_first_image_from_post_body' ),
			array(
				'shortdesc' => 'Remove the first image from the post body, usefull to normalize the posts content in case some contains the featured image in their body and others not.',
				'synopsis'  => array(),
			)
		);
	}

	/**
	 * Callable for `newspack-content-migrator remove-first-image-from-post-body`.
	 */
	public function remove_first_image_from_post_body( array $pos_args, array $assoc_args ) {
		WP_CLI::confirm( "This will remove the first image from the post body if it's on a shortcode format ([caption ...]<img src=...>[/caption]), do you want to continue?" );

		$this->posts_logic->throttled_posts_loop(
			array(
				'post_type'   => 'post',
				'post_status' => array( 'publish' ),
			),
			function( $post ) {
				global $wpdb;

				if ( substr( $post->post_content, 0, strlen( '[caption' ) ) === '[caption' ) {
					$start_with_images[] = $post->ID;

					$pattern = get_shortcode_regex();
					if ( preg_match_all( '/' . $pattern . '/s', $post->post_content, $matches ) && array_key_exists( 2, $matches ) && in_array( 'caption', $matches[2] ) ) {
						$index = 0;
						$count = 0;
						// Remove the first image shortcode from the content.
						foreach ( $matches[2] as $match ) {
							if ( 'caption' === $match ) {
								// Found our first image.
								$index = $count;
								break; // We've done enough.
							}
							$count++;
						};
						// Remove first gallery from content.
						$content = str_replace( $matches[0][ $index ], '', $post->post_content );

						if ( $content !== $post->post_content ) {
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery
							$wpdb->update(
								$wpdb->prefix . 'posts',
								array( 'post_content' => $content ),
								array( 'ID' => $post->ID )
							);

							WP_CLI::line( sprintf( 'Updated post: %d', $post->ID ) );
						}
					}
				}
			}
		);
	}
}
