<?php
/**
 * Joomla to WordPress wrapper that uses the great fg-joomla-to-wordpress and fg-joomla-to-wordpress-premium plugins.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Util\FgHelper;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use Psr\Log\LoggerInterface;
use WP_CLI;

/**
 * Class. Yup. It's a class.
 */
class UgObserver implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * Logging to your display.
	 *
	 * @var LoggerInterface CLI Log.
	 */
	private LoggerInterface $cli_log;

	/**
	 * Logging to a file.
	 *
	 * @var LoggerInterface File Log.
	 */
	private LoggerInterface $file_log;

	/**
	 * FG Helper for simplification.
	 *
	 * @var FgHelper FG Helper instance.
	 */
	private FgHelper $fg_helper;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->fg_helper = new FgHelper( 'joomla' );

		$this->cli_log  = CliLog::get_logger( 'ug-observer' );
		$this->file_log = FileLog::get_logger( 'ug-observer' );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {

		WP_CLI::add_command(
			'newspack-content-migrator uc-wrap-import',
			self::get_command_closure( 'cmd_wrap_joomla_import' ),
			[
				'shortdesc' => 'Wrap the import command from FG Joomla.',
				'synopsis'  => [],
			]
		);
	}

	/**
	 * Run the import.
	 *
	 * We simply wrap the import command. Note that we can't batch this at all, so timeouts might be a thing.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_wrap_joomla_import( array $pos_args, array $assoc_args ): void {
		add_action( 'fg_helper_pre_import', [ $this, 'add_fg_hooks' ] );
		$this->fg_helper->import( $pos_args, $assoc_args );
	}

	/**
	 * Filter callback for after insert post action.
	 *
	 * @param int    $new_post_id New post ID.
	 * @param array  $post        Post data.
	 * @param string $post_type   Post type.
	 */
	public function fg_action_after_insert_post( $new_post_id, $post, $post_type ): void {
		$image_info = json_decode( $post['images'] ?? [], true );
		if ( ! empty( $image_info['image_intro'] ) ) {
			$all_content = ( $post['introtext'] ?? '' ) . ( $post['fulltext'] ?? '' );
			if ( str_contains( $all_content, $image_info['image_intro'] ) ) {
				// The migration is configured to leave the featured image in the content (because it can be far down in the post),
				// If the featured image is in the content too, set the featured image to hidden.
				update_post_meta( $new_post_id, 'newspack_featured_image_position', 'hidden' );
			}
		}

		$new_url = get_permalink( $new_post_id );
		$old_id  = $post['id'] ?? 'unknown';
		$this->file_log->notice(
			sprintf( 'Imported "%s"', $post['title'] ?? '' ),
			[
				'new_id'  => $new_post_id,
				'old_id'  => $post['id'] ?? 'unknown',
				'new_url' => $new_url,
				'old_url' => trailingslashit( NCCM_SOURCE_WEBSITE_URL ) . 'index.php?option=com_content&view=article&id=' . $old_id,
			]
		);
	}

	/**
	 * Add hooks needed for the migration.
	 */
	public function add_fg_hooks(): void {
		// Filters.
		add_filter( 'fgj2wp_import_media_filename', [ $this, 'filter_media_filename' ], 10, 2 );
		// There is also fgj2wp_pre_insert_post for each post if needed.

		// Actions.
		add_action( 'fgj2wp_post_insert_post', [ $this, 'fg_action_after_insert_post' ], 11, 3 );
	}

	/**
	 * Filter callback to fix image urls before downloading them from live.
	 *
	 * Some image "urls" are lacking domain and are just a bit broken, fix that here.
	 *
	 * @param string $file_name File name or url.
	 */
	public function filter_media_filename( $file_name ) {
		if ( ! str_starts_with( $file_name, 'http' ) ) {
			return $file_name;
		}
		$broken_img_pattern = '@https?://?images//?@';
		if ( preg_match( $broken_img_pattern, $file_name ) ) {
			$old_file_name = $file_name;
			$file_name     = preg_replace( $broken_img_pattern, NCCM_SOURCE_WEBSITE_URL . '/images/', $file_name );
			FileLog::get_logger( 'img' )->notice(
				'Repaired img path',
				[
					'old' => $old_file_name,
					'new' => $file_name,
				]
			);
		}
		if ( ! wp_http_validate_url( $file_name ) ) {
			FileLog::get_logger( 'img' )->error( 'Image path does not look like a valid url', [ 'url' => $file_name ] );
		}

		return $file_name;
	}
}
