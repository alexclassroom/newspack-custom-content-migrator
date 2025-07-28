<?php
/**
 * County17Migrator.
 * 
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use WP_CLI;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;

/**
 * County17Migrator.
 */
class County17Migrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * Constructor.
	 */
	private function __construct() {
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator county17-restore-postmeta',
			self::get_command_closure( 'cmd_restore_postmeta' ),
			[
				'synopsis' => [
					[
						'type'     => 'assoc',
						'name'     => 'cdiff-prefix',
						'optional' => false,
					],
					[
						'type'     => 'assoc',
						'name'     => 'post-ids-csv',
						'optional' => false,
					],
					[
						'type'     => 'assoc',
						'name'     => 'content-diff__imported-post-ids',
						'optional' => false,
					],
				],
			]
		);
	}

	/**
	 * Callable for 'newspack-content-migrator county17-restore-postmeta' command.
	 * 
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function cmd_restore_postmeta( array $pos_args, array $assoc_args ): void {
		global $wpdb;

		$cdiff_prefix                         = esc_sql( $assoc_args['cdiff-prefix'] );
		$post_ids                             = explode( ',', $assoc_args['post-ids-csv'] );
		$content_diff__imported_post_ids_path = $assoc_args['content-diff__imported-post-ids'];

		/**
		 * Read contents of content-diff__imported-post-ids file line by line.
		 * If a line is a JSON object, get these tree things from it: "post_type" (either "post" or "attachment"), "id_old" (key of array), "id_new" (value).
		 */
		$attachments_oldnew_ids               = [];
		$posts_oldnew_ids                     = [];
		$content_diff__imported_post_ids_file = file_get_contents( $content_diff__imported_post_ids_path ); // phpcs:ignore -- WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown.
		$lines                                = explode( "\n", $content_diff__imported_post_ids_file );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}
			$json_object = json_decode( $line, true );
			if ( is_array( $json_object ) ) {
				$post_type = $json_object['post_type'];
				$id_old    = $json_object['id_old'];
				$id_new    = $json_object['id_new'];
				if ( 'attachment' === $post_type ) {
					$attachments_oldnew_ids[ $id_old ] = $id_new;
				} elseif ( 'post' === $post_type ) {
					$posts_oldnew_ids[ $id_old ] = $id_new;
				}
			}
		}

		// Loop imported post IDs.
		foreach ( $post_ids as $key_post_id => $post_id ) {
			$post_id = intval( $post_id );
			// Get old post ID (array key is old ID, value is new ID, so searching for existing value).
			$old_post_id = array_search( $post_id, $posts_oldnew_ids );
			if ( false === $old_post_id ) {
				WP_CLI::error( sprintf( 'ERROR: Old post ID not found for new post ID %d', $post_id ) );
				continue;
			}

			WP_CLI::success( sprintf( '(%d)/(%d) OLD: %d => NEW: %d', $key_post_id + 1, count( $post_ids ), $old_post_id, $post_id ) );

			// Get old postmeta.
			$cdiff_prefix = esc_sql( $cdiff_prefix );
			$old_postmeta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$cdiff_prefix}postmeta WHERE post_id = %d", $old_post_id ), ARRAY_A ); // phpcs:ignore -- WordPress.DB.PreparedSQL.InterpolatedNotPrepared Allow, prefix is sanitized.

			// Get updated postmeta.
			$new_postmeta = $old_postmeta;
			foreach ( $old_postmeta as $postmeta_key => $postmeta ) {
				$meta_key   = $postmeta['meta_key'];
				$meta_value = $postmeta['meta_value'];

				$meta_value_updated = $meta_value;
				if ( '_thumbnail_id' === $meta_key ) {
					$old_attachment_id = $meta_value;
					// Old ID is key, new is value.
					$new_attachment_id = $attachments_oldnew_ids[ $old_attachment_id ] ?? false;
					if ( false !== $new_attachment_id ) {
						$meta_value_updated = $new_attachment_id;
						WP_CLI::line( sprintf( 'ATT ID CHANGED -- oldID %d newID %d', $old_attachment_id, $new_attachment_id ) );
					} else {
						WP_CLI::line( sprintf( 'ATT ID NOT CHANGED -- attID %d', $old_attachment_id ) );
					}
				}

				$new_postmeta[ $postmeta_key ]['meta_value'] = $meta_value_updated; // phpcs:ignore -- WordPress.DB.SlowDBQuery.slow_db_query_meta_value.
				$new_postmeta[ $postmeta_key ]['meta_key']   = $meta_key;
			}

			// If meta count old != new, error and exit.
			if ( count( $old_postmeta ) !== count( $new_postmeta ) ) {
				WP_CLI::warning( sprintf( 'ERROR: Meta count mismatch for post ID %d, old count: %d, new count: %d', $post_id, count( $old_postmeta ), count( $new_postmeta ) ) );
				continue;
			}

			// Insert new postmeta using WP native function.
			foreach ( $new_postmeta as $postmeta_key => $postmeta ) {
				$meta_key   = $postmeta['meta_key'];
				$meta_value = $postmeta['meta_value'];
				$updated    = update_post_meta( $post_id, $meta_key, $meta_value );
				if ( false === $updated ) {
					WP_CLI::warning( sprintf( 'ERROR: Failed to update postmeta for post ID %d: %s => %s', $post_id, $meta_key, $meta_value ) );
				}
			}

			echo "Done\n";
		}
	}
}
