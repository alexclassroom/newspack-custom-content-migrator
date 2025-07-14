<?php
/**
 * Migration tasks for ICTNews.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\Posts as PostsLogic;
use Newspack\MigrationTools\Util\Log\FileLog;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;
use simplehtmldom\HtmlDocument;
use WP_Post;

/**
 * Custom migration scripts for Mountain Journal.
 */
class ICTNewsMigrator implements RegisterCommandInterface {
	use WpCliCommandTrait;

    const DOMAIN = 'ictnews.org';

    const SOURCE_THUMBNAIL_ID_META_KEY = '_tempest_featured_image_id';
    const SOURCE_ATTACHMENT_PREFIX = 'https://ictnews.org/.image/';

    const MIGRATED_THUMBNAIL_META_KEY = '_newspack_thumbnail_migrated';
    const MIGRATED_INLINE_ATTACHMENTS_META_KEY = '_newspack_inline_attachments_migrated';

    /**
	 * Attachments logic.
	 * 
	 * @var Attachments
	 */
	private Attachments $attachments;

    /**
	 * Posts logic.
	 * 
	 * @var PostsLogic
	 */
	private PostsLogic $posts_logic;

	/**
	 * Constructor.
	 */
	private function __construct() {
        $this->attachments = new Attachments();
        $this->posts_logic = new PostsLogic();
	}

	/**
	 * Registers WP CLI Commands.
	 * 
	 * @return void
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator ictnews-backfill-thumbnails',
			self::get_command_closure( 'cmd_backfill_thumbnails' ),
			[
				'shortdesc' => 'Backfills Attachments in Post Thumbnails.',
				'synopsis'  => [],
			]
		);

        WP_CLI::add_command(
			'newspack-content-migrator ictnews-backfill-inline-attachments',
			self::get_command_closure( 'cmd_backfill_inline_attachments' ),
			[
				'shortdesc' => 'Backfills Attachments in Post Content.',
				'synopsis'  => [],
			]
		);
	}

	/**
	 * Backfill Attachments.
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_backfill_thumbnails( array $pos_args, array $assoc_args ): void {
		$file_logger = FileLog::get_logger( 'backfill-thumbnails' );

		// CSV.
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$csv_filename = 'backfill-thumbnails.csv';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$csv_file_pointer = fopen( $csv_filename, 'a' );

        if ( 0 === filesize( $csv_filename ) ) {
            // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
            fputcsv(
                $csv_file_pointer,
                [
                    '#',
                    'Post ID',
                    'Post Title',
                    'Post URL',
                    '_thumbnail_id_backup',
                    '_tempest_featured_image_id',
                    '_thumbnail_id',
                ]
            );
        }

        $index = 0;

        $this
            ->posts_logic
            ->throttled_posts_loop(
                [
                    'post_type'    => 'post',
                    'post_status'  => 'publish',
                    'meta_key'     => self::MIGRATED_THUMBNAIL_META_KEY,
                    'meta_compare' => 'NOT EXISTS',
                ],
                function ( WP_Post $post ) use ( &$index, $csv_file_pointer, $file_logger ) {
                    $index++;

                    WP_CLI::log( sprintf( '[Memory Usage: %s] Processing Post #%d', size_format( memory_get_usage( true ) ), $post->ID ) );

                    // Check thumbnail.
                    $current_thumbnail_id = get_post_meta( $post->ID, '_thumbnail_id' );
                    $source_thumbnail_id  = get_post_meta( $post->ID, self::SOURCE_THUMBNAIL_ID_META_KEY, true );
                    $new_thumbnail_id     = null;

                    delete_post_meta( $post->ID, '_thumbnail_id' ); // Remove old thumbnail, as it is not valid.

                    if ( ! empty( $source_thumbnail_id ) ) {
                        // Post should have a thumbnail.
                        // Find new Attachment ID.
                        $new_thumbnail_id = $this->get_attachment_id_by_source_id( $source_thumbnail_id );

                        if ( ! is_null( $new_thumbnail_id ) ) {
                            add_post_meta( $post->ID, '_thumbnail_id', $new_thumbnail_id );
                        } else {
                            $file_logger->warning( sprintf( 'Attachment not found for %s. Post ID: %d', $source_thumbnail_id, $post->ID ) );
                        }
                    }

                    // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
                    fputcsv(
                        $csv_file_pointer,
                        [
                            $index,
                            $post->ID,
                            get_the_title( $post->ID ),
                            get_permalink( $post->ID ),
                            implode( ' | ', $current_thumbnail_id ),
                            $source_thumbnail_id,
                            $new_thumbnail_id,
                        ]
                    );

                    update_post_meta( $post->ID, self::MIGRATED_THUMBNAIL_META_KEY, 'yes' );
                },
                1,
                100
            );

        fclose( $csv_file_pointer );

        wp_cache_flush();
	}

    /**
	 * Backfill Attachments in Posts' Content.
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_backfill_inline_attachments( array $pos_args, array $assoc_args ): void {
        // Argument parsing.
        $dry_run = isset( $assoc_args['dry-run'] ) ? true : false;

		$file_logger = FileLog::get_logger( 'backfill-inline-attachments' );

		// CSV.
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
        $csv_filename = 'backfill-inline-attachments.csv';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$csv_file_pointer = fopen( $csv_filename, 'a' );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
        if ( 0 === filesize( $csv_filename ) ) {
            // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
            fputcsv(
                $csv_file_pointer,
                [
                    '#',
                    'Post ID',
                    'Post Title',
                    'Post URL',
                    'Replaced Inline Images Count',
                ]
            );
        }

        $index = 0;

        $this
            ->posts_logic
            ->throttled_posts_loop(
                [
                    'post_type'   => 'post',
                    'post_status' => 'publish',
                    's'           => self::SOURCE_ATTACHMENT_PREFIX,
                    'meta_query'  => [
                        'key'     => self::MIGRATED_INLINE_ATTACHMENTS_META_KEY,
                        'compare' => 'NOT EXISTS',
                    ]
                ],
                function ( WP_Post $post ) use ( &$index, $csv_file_pointer, $file_logger, $dry_run ) {
                    WP_CLI::log( sprintf( '[Memory Usage: %s] Processing Post #%d', size_format( memory_get_usage( true ) ), $post->ID ) );

                    $index++;

                    $count_replaced_inline_images = 0;

                    // First we check if there are inline images with the old reference.
                    $post_content_document = new HtmlDocument( $post->post_content );

                    // Find all images.
                    $imgs = $post_content_document->find( 'img' );

                    foreach ( $imgs as $img ) {
                        $img_src = $img->getAttribute( 'src' );

                        if ( ! str_starts_with( $img_src, self::SOURCE_ATTACHMENT_PREFIX ) ) {
                            continue;
                        }

                        // Search attachment with this src.
                        $attachment_id = $this->get_attachment_id_by_source_url( $img_src );

                        if ( ! is_null( $attachment_id ) ) {
                            $img->setAttribute( 'src', wp_get_attachment_url( (int) $attachment_id ) );

                            $count_replaced_inline_images++;
                        } else {
                            $file_logger->warning( sprintf( 'Attachment not found for %s. Post ID: %d', $img_src, $post->ID ) );
                        }
                    }

                    if ( ! $dry_run && $post->post_content !== $post_content_document->save() ) {
                        wp_save_post_revision( $post->ID );

                        wp_update_post( [
                            'ID'           => $post->ID,
                            'post_content' => $post_content_document->save(),
                        ] );
                    }

                    // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
                    fputcsv(
                        $csv_file_pointer,
                        [
                            $index,
                            $post->ID,
                            get_the_title( $post->ID ),
                            get_permalink( $post->ID ),
                            $count_replaced_inline_images
                        ]
                    );

                    if ( ! $dry_run ) {
                        update_post_meta( $post->ID, self::MIGRATED_INLINE_ATTACHMENTS_META_KEY, 'yes' );
                    }
                },
                0,
                100
            );

        fclose( $csv_file_pointer );

        wp_cache_flush();
	}

    /**
     * Get an Attachment ID from a source URL.
     * 
     * @param  string $img_source_url Image source URL.
     * @return int|null Attachment ID on success. Otherwise, null
     */
    private function get_attachment_id_by_source_url( string $img_source_url ): int|null {
        global $wpdb;

        $attachment_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT `post_id`
             FROM `{$wpdb->postmeta}`
             WHERE `meta_key` = '_newspack_source_guid'
             AND `meta_value` = %s",
            $img_source_url
        ) );

        return $attachment_id;
    }

    /**
     * Get an Attachment ID from a source ID.
     * 
     * @param  string $img_source_url Image source URL.
     * @return int|null Attachment ID on success. Otherwise, null
     */
    private function get_attachment_id_by_source_id( int $img_source_id ): int|null {
        global $wpdb;

        $attachment_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT `post_id`
             FROM `{$wpdb->postmeta}` AS `postmeta`
             INNER JOIN `{$wpdb->posts}` AS `posts` ON `postmeta`.`post_id` = `posts`.`ID`
             WHERE `posts`.`post_type` = 'attachment'
             AND `meta_key` = '_newspack_source_post_id'
             AND `meta_value` = %d",
            $img_source_id
        ) );

        return $attachment_id;
    }
}
