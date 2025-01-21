<?php
/**
 * San Diego Voice & Viewpoint specific commands.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use Newspack\MigrationTools\Command\ShortcodeReplacementInterface;
use WP_CLI;

/**
 * RoughDraftAtlantaMigrator.
 */
class SanDiegoVoiceAndViewpointMigrator implements RegisterCommandInterface, ShortcodeReplacementInterface {

	use WpCliCommandTrait;

	/**
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * Register commands with WP CLI.
	 *
	 * @throws Exception If the command registration fails.
	 */
	public static function register_commands(): void {
	}

	/**
	 * Gets a custom replacement for a shortcode in a post.
	 * 
	 * @see ShortcodeReplacementInterface::replace_shortcode().
	 * 
	 * @param string $shortcode_name Shortcode name.
	 * @param int    $post_id        Post ID where the shortcode is being replaced.
	 * @return string
	 */
	public function replace_shortcode( string $shortcode_name, int $post_id ): string {
		$replacement = '';
		
		WP_CLI::line( sprintf( '> replace_shortcode method args: %s %s', $shortcode_name, $post_id ) );

		return $replacement;
	}
}
