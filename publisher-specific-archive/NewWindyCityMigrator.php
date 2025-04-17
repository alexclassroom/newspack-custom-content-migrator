<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Exception;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;

class NewWindyCityMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;


	/**
	 * Register commands with WP CLI.
	 *
	 * @throws Exception If the command registration fails.
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator windy-city-fix-queercast-mp3s',
			self::get_command_closure( 'cmd_windy_city_fix_queercast_mp3s' ),
			[
				'shortdesc' => 'Fix MP3 links for Queercast episodes.',
				'synopsis'  => [
					[
						'type'        => 'positional',
						'name'        => 'mp3-folder-path',
						'description' => 'Path to MP3 folder.',
						'optional'    => false,
					],
				],
			],
		);
	}

	public function cmd_windy_city_fix_queercast_mp3s( array $args, array $assoc_args ) {
		$mp3_folder_path = $args[0];
		$mp3_folder_path = untrailingslashit( $mp3_folder_path );

		$queercast_category = get_category_by_slug( 'queercast' );

		global $wpdb;

		$target_pattern = 'https?:\/\/windycitytimes\.com\/wp-content\/.*\.mp3';

		$affected_posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->posts p INNER JOIN $wpdb->term_relationships wtr ON p.ID = wtr.object_id AND wtr.term_taxonomy_id = %d WHERE p.post_content REGEXP %s AND p.post_content NOT REGEXP %s",
				$queercast_category->term_taxonomy_id,
				$target_pattern,
				'https?:\/\/windycitytimes\.com\/wp-content\/uploads\/.*\.mp3'
			)
		);

		WP_CLI::log( sprintf( 'Found %d Queercast MP3 posts that need fixing.', count( $affected_posts ) ) );

		foreach ( $affected_posts as $post ) {
			echo "\n";
			WP_CLI::log( sprintf( 'Processing Queercast MP3 post id: %d.', $post->ID ) );

			$mp3_url = preg_match( "/$target_pattern/", $post->post_content, $matches );

			WP_CLI::log( sprintf( 'MP3 URL found: %s.', $matches[0] ) );

			if ( ! $mp3_url ) {
				WP_CLI::error( sprintf( 'No MP3 URL found in post content for ID: %d.', $post->ID ) );
			}

			$mp3_file_name = basename( $matches[0] );
			$search = $mp3_folder_path . '/*/' . $mp3_file_name;

			WP_CLI::log( sprintf( 'Searching for MP3 file at path: %s.', $search ) );

			$file_search   = glob( $mp3_folder_path . '/*/' . $mp3_file_name  );

			if ( empty( $file_search ) ) {
				WP_CLI::error( sprintf( 'No MP3 file found at path: %s for ID: %d.', $mp3_folder_path, $post->ID ) );
			}

			$mp3_file_path = $file_search[0];

			$destination_file_path = wp_upload_dir( date( 'Y/m', strtotime( $post->post_date ) ) )['path'] . '/' . $mp3_file_name;
			$destination_file_url  = 'https://windycitytimes.com/' . substr( $destination_file_path, strpos( $destination_file_path, 'wp-content' ) );

			WP_CLI::log( sprintf( 'Destination file path: %s.', $destination_file_path ) );
			WP_CLI::log( sprintf( 'Destination file URL: %s.', $destination_file_url ) );

			$maybe_file_copied = copy( $mp3_file_path, $destination_file_path );

			if ( ! $maybe_file_copied ) {
				WP_CLI::error( sprintf( 'Failed to copy MP3 file from path: %s to destination path: %s for ID: %d.', $mp3_file_path, $destination_file_path, $post->ID ) );
			}

			WP_CLI::log( sprintf( 'MP3 file copied successfully from path: %s to destination path: %s for ID: %d.', $mp3_file_path, $destination_file_path, $post->ID ) );

			$post_content       = str_replace( $matches[0], $destination_file_url, $post->post_content );
			$maybe_post_updated = wp_update_post(
				[
					'ID'           => $post->ID,
					'post_content' => $post_content,
				]
			);

			if ( ! $maybe_post_updated ) {
				WP_CLI::error( sprintf( 'Failed to update post content for ID: %d.', $post->ID ) );
			}

			WP_CLI::log( sprintf( 'Post content updated successfully for ID: %d.', $post->ID ) );
			usleep( 500 );
		}
		WP_CLI::success( 'Queercast MP3 posts fixed successfully.' );
	}
}