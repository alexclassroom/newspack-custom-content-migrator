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
	 * @param string $shortcode Shortcode string.
	 * @param int    $post_id   Post ID where the shortcode is being replaced.
	 * 
	 * @return string|false String replacement for the shortcode, or false if no replacement is successfully generated.
	 */
	public function replace_shortcode( string $shortcode, int $post_id ): string|false {
		$replacement = '';
		
		// Get url attribute from shortcode.
		$parsed_attrs = shortcode_parse_atts( $shortcode );
		$url          = $parsed_attrs['url'] ?? null;
		if ( ! $url ) {
			return false;
		}

		// Decode the URL to expose quotation mars, then trim all quotes.
		$url_trimmed = trim( html_entity_decode( $url ), '"”″' );

		// TODO: Add custom logic here to generate the replacement for the shortcode.
		
		return $replacement;
	}
}
