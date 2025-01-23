<?php
/**
 * San Diego Voice & Viewpoint specific functionality.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use Newspack\MigrationTools\Command\ShortcodeReplacementInterface;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;

/**
 * SanDiegoVoiceAndViewpointMigrator.
 */
class SanDiegoVoiceAndViewpointMigrator implements RegisterCommandInterface, ShortcodeReplacementInterface {

	use WpCliCommandTrait;

	/**
	 * Gutenberg block generator.
	 *
	 * @var $blocks GutenbergBlockGenerator Gutenberg block generator.
	 */
	private $blocks;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->blocks = new GutenbergBlockGenerator();
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

		// Decode the quotes, then trim all the quotes.
		$url_trimmed = trim( html_entity_decode( $url ), '"”″' );

		// Remove get parameters from the URL.
		$parsed_url   = wp_parse_url( $url_trimmed );
		$url_noparams = sprintf( '%s://%s%s', $parsed_url['scheme'], $parsed_url['host'], $parsed_url['path'] );

		// If host contains "facebook.com".
		$is_facebook_video = strpos( $parsed_url['host'], 'facebook.com' ) !== false;
		// If host contains "youtube.com" or "youtu.be".
		$is_youtube_video = ( strpos( $parsed_url['host'], 'youtube.com' ) !== false ) || ( strpos( $parsed_url['host'], 'youtu.be' ) !== false );

		// Generate replacements for shortcode.
		if ( $is_facebook_video ) {
			$replacement_sprintf = <<<HTML
<!-- wp:html -->
<div id="fb-root"></div><script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v22.0"></script>
<div class="fb-video" data-href="%s" data-width="500" data-show-text="false"><blockquote cite="%s" class="fb-xfbml-parse-ignore"><a href="%s">Link to video</a><p></p>Posted by <a href="https://facebook.com/SDVoiceandViewpoint">The San Diego Voice &amp; Viewpoint Newspaper</a></blockquote></div>
<!-- /wp:html -->
HTML;
			$replacement         = sprintf( $replacement_sprintf, $url_noparams, $url_noparams, $url_noparams );
		} elseif ( $is_youtube_video ) {
			$replacement = serialize_block( $this->blocks->get_youtube( $url_trimmed ) );
		} else {
			$replacement = sprintf( '<a href="%s" target="_blank">%s</a>', $url_trimmed, $url_trimmed );
		}
		
		return $replacement;
	}
}
