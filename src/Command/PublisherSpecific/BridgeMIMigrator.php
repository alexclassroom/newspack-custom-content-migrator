<?php

/**
 * Publisher Specific migrator for BridgeMI.
 * 
 * @package NewspackCustomContentMigrator\Command\PublisherSpecific
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\GuestContributorsHelper;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Logic\Redirection;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use simplehtmldom\HtmlDocument;
use Red_Item;
use Simple_Local_Avatars;
use WP_Block_Type_Registry;
use WP_CLI;

/**
 * Custom migration scripts for BridgeMI.
 */
class BridgeMIMigrator implements RegisterCommandInterface {

    use WpCliCommandTrait;
    
    // Live site constants.
    
    const FEED_TYPES = [ 'articles', 'authors', 'tags', 'topics' ];
    
    const FEED_URL_ARTICLES  = 'https://www.bridgemi.com/article-export.json';
    const FEED_URL_AUTHORS   = 'https://www.bridgemi.com/authors-export.json';
    const FEED_URL_TAGS      = 'https://www.bridgemi.com/tags-export.json';
    const FEED_URL_TOPICS    = 'https://www.bridgemi.com/topics-export.json';

    const LIVE_SITE_URL      = 'https://www.bridgemi.com';
    const LIVE_AUTHOR_PATH   = '/about/';
    const LIVE_CATEGORY_PATH = '/topics/';
    const LIVE_TAG_PATH      = '/tags/';

    const LIVE_IMAGE_API     = 'https://www.bridgemi.com/api/image-metadata';

    // Staging site constants.

    const STAGING_TIMEZONE  = 'America/Detroit';
    const STAGING_PERMALINK = '/%category%/%postname%/';

    // Meta constants.

    const META_KEY_CHECKSUM         = '_np_import_bridgemi_checksum';
    const META_KEY_JSON_ITEM        = '_np_import_bridgemi_json_item';
    const META_KEY_OLD_SLUG         = '_np_import_bridgemi_old_slug';
    const META_KEY_HERO_IMAGE_COUNT = '_np_import_bridgemi_hero_image_count';
    const META_KEY_HEROS_PROCESSED  = '_np_import_bridgemi_heros_processed';
    const META_KEY_PROCESSED        = '_np_import_bridgemi_processed';

    // Newspack constants.

    const META_KEY_FEATURED_IMAGE_POSITION = 'newspack_featured_image_position';

    /**
     * WP allowed mime types.
     *
     * @var array
     */
    private $allowed_mime_types = [];

    /**
     * Dry run.
     *
     * @var bool
     */
    private $dry_run = false;

    /**
	 * Logger
	 *
	 * @var MultiLog
	 */
	private $logger;

    /**
	 * Constructor.
	 */
	private function __construct() {
        $all_mime_types = get_allowed_mime_types();
        foreach ( $all_mime_types as $ext => $mime ) {
            array_push( $this->allowed_mime_types, ...explode( '|', $ext ) );
        }
        $this->allowed_mime_types = array_unique( $this->allowed_mime_types );
    }

    /**
     * Register commands.
     */
    public static function register_commands(): void {
    
        WP_CLI::add_command(
            'newspack-content-migrator bridgemi-heros',
            self::get_command_closure( 'cmd_heros' ),
            [
                'shortdesc' => 'Convert hero image arrays to slideshows.',
                'synopsis'  => [],
            ]
        );

        WP_CLI::add_command(
            'newspack-content-migrator bridgemi-images',
            self::get_command_closure( 'cmd_images' ),
            [
                'shortdesc' => 'Fetch metadata for images.',
                'synopsis'  => [],
            ]
        );

        WP_CLI::add_command(
            'newspack-content-migrator bridgemi-import',
            self::get_command_closure( 'cmd_import' ),
            [
                'shortdesc' => 'Import JSON items from BridgeMI feeds (' . implode( ', ', self::FEED_TYPES ) . ').',
                'synopsis'  => [
                    [
                        'type'        => 'positional',
                        'name'        => 'type',
                        'description' => 'Type of items to import (' . implode( ', ', self::FEED_TYPES ) . ')',
                        'optional'    => false,
                    ],
                    [
                        'type'        => 'assoc',
                        'name'        => 'page-start',
                        'description' => 'Starting page to import "?page=" (integer) (inclusive) (zero-based) (default: 0)',
                        'optional'    => true,
                    ],
                    [
                        'type'        => 'assoc',
                        'name'        => 'page-end',
                        'description' => 'Final page to import "?page=" (integer) (inclusive) (zero-based) (default: PHP_INT_MAX)',
                        'optional'    => true,
                    ],
                    [
                        'type'        => 'flag',
                        'name'        => 'dry-run',
                        'description' => 'Show what would be imported without making changes',
                        'optional'    => true,
                    ],
                ],
            ]
        );

        WP_CLI::add_command(
            'newspack-content-migrator bridgemi-process',
            self::get_command_closure( 'cmd_process' ),
            [
                'shortdesc' => 'Process imported items (' . implode( ', ', self::FEED_TYPES ) . ').',
                'synopsis'  => [
                    [
                        'type'        => 'positional',
                        'name'        => 'type',
                        'description' => 'Type of items to process (' . implode( ', ', self::FEED_TYPES ) . ')',
                        'optional'    => false,
                    ],
                ],
            ]
        );

    }
    
    /**
     * Convert hero image arrays to slideshows.
     *
     * @param array $pos_args Command arguments.
     * @param array $assoc_args Command associative arguments.
     */
    public function cmd_heros( array $pos_args, array $assoc_args ): void {

        $this->validate_dependencies();

        // Logger.
        $logger_slug = __FUNCTION__;
        $this->logger_set( $logger_slug );

        // Run command.
        $this->logger->info( 'Running command: ' . $logger_slug );

        // Slideshow generator.
        $gutenberg_block_generator = new GutenbergBlockGenerator();

        do {

            // Has multiple hero images (also means processing has been run), but heros not processed yet.
            $meta_query = [
                [
                    'key'     => self::META_KEY_HERO_IMAGE_COUNT,
                    'compare' => '>',
                    'value'   => 1
                ],
                [
                    'key'     => self::META_KEY_HEROS_PROCESSED,
                    'compare' => 'NOT EXISTS',
                ],
            ];

            $limit = 10;
            
            $posts = get_posts( [ 
                'numberposts' => $limit,
                'meta_query' => $meta_query
            ] );

            // Process items.
            foreach( $posts as $post ) {

                $this->logger->info( '------------ processing id: ' . $post->ID );

                // Sanity: if post has content, and NCC hasn't been run.
                if( ! empty( trim( $post->post_content ) ) && ! str_starts_with( trim( $post->post_content ), '<!--' ) ) {
                    $this->logger->warning( 'Post content does not start with "<!--".  Please run NCC.' );
                    update_post_meta( $post->ID, self::META_KEY_HEROS_PROCESSED, 'yes' );
                    continue;
                }
                
                // Get from db and verify checksum.
                $json_item = $this->validate_get_from_db( 'post', $post->ID );

                // Choose which array to use.
                $images_arr = ( count( $json_item->hero_image ) > 0 ) ? $json_item->hero_image : $json_item->hero_image_legacy;
                $images_count = count( $images_arr );

                $this->logger->info( 'Hero image array count: ' . $images_count );

                // Sanity.
                if( $images_count != get_post_meta( $post->ID, self::META_KEY_HERO_IMAGE_COUNT, true ) ) {
                    $this->logger->warning( 'JSON array count does not match meta count.' );
                    update_post_meta( $post->ID, self::META_KEY_HEROS_PROCESSED, 'yes' );
                    continue;
                }

                // Foreach hero image, add to slideshow.

                $slideshow_ids = [];

                for ( $i = 0; $i < $images_count; $i++ ) {
                    
                    $attachments = get_posts([
                        'post_type'   => 'attachment',
                        'meta_key'    => self::META_KEY_OLD_SLUG,
                        'meta_value'  => $images_arr[ $i ],
                        'fields'      => 'ids',
                        'numberposts' => 1,
                    ]);

                    // Skip hero, but keep going with slideshow.
                    if( empty( $attachments ) ) {
                        $this->logger->warning( 'A hero image was not found in db: ' . $images_arr[ $i ] );
                        continue;
                    }

                    $slideshow_ids[] = reset( $attachments );
                    
                } // foreach hero image.

                if( empty( $slideshow_ids ) ){
                    $this->logger->warning( 'No slideshow image ids.' );
                    update_post_meta( $post->ID, self::META_KEY_HEROS_PROCESSED, 'yes' );
                    continue;
                }

                // Slideshow block.
                $slideshow_block = serialize_block( $gutenberg_block_generator->get_jetpack_slideshow( $slideshow_ids ) );
                $this->logger->info( 'Inserting slideshow block with length: ' . mb_strlen( $slideshow_block ) );

                wp_update_post( [
                    'ID' => $post->ID,
                    'post_content' => $slideshow_block . "\n\n" . $post->post_content,
                ]);

                // Hide the featured image since the slideshow will act as the featured now.
                update_post_meta( $post->ID, self::META_KEY_FEATURED_IMAGE_POSITION, 'hidden' );

                // Set as heros processed.
                update_post_meta( $post->ID, self::META_KEY_HEROS_PROCESSED, 'yes' );

            } // foreach post in query.
            
        } while( ! empty( $posts ) ); // while posts to process.

        $this->logger->info( 'Done.' );
    }

    /**
     * Fetch metadata for images.
     *
     * @param array $pos_args Command arguments.
     * @param array $assoc_args Command associative arguments.
     */
    public function cmd_images( array $pos_args, array $assoc_args ): void {
            
        $this->validate_dependencies();

        // Logger.
        $logger_slug = __FUNCTION__;
        $this->logger_set( $logger_slug );

        // Run command.
        $this->logger->info( 'Running command: ' . $logger_slug );

        // API properties.
        $expected_properties = [
            'alt'     => [ 'type' => 'string' ],
            'caption' => [ 'type' => 'string' ],
            'id'      => [ 'type' => 'integer', ],
            'url'     => [ 'type' => 'string' ],
        ];

        do {

            // Has old slug, but not processed.
            $meta_query = [
                [
                    'key'     => self::META_KEY_OLD_SLUG,
                    'compare' => 'EXISTS',
                ],
                [
                    'key'     => self::META_KEY_PROCESSED,
                    'compare' => 'NOT EXISTS',
                ],
            ];

            $limit = 10;
            
            $posts = get_posts( [ 
                'post_type' => 'attachment',
                'numberposts' => $limit,
                'meta_query' => $meta_query
            ] );

            // Process items.
            foreach( $posts as $post ) {

                // Add a small delay between requests to be nice to the server.
                sleep(1);

                $this->logger->info( '------------ processing id: ' . $post->ID );

                // Get the old slug.
                $old_slug = get_post_meta( $post->ID, self::META_KEY_OLD_SLUG, true );

                // Fetch the metadata.
                $json_metadata = $this->get_attachment_json( $old_slug );

                // Save.
                update_post_meta( $post->ID, self::META_KEY_CHECKSUM, $this->checksum_hash( $json_metadata ) );
                update_post_meta( $post->ID, self::META_KEY_JSON_ITEM, wp_slash( serialize( $json_metadata ) ) );

                // Check for null or {"message":"No media item found"}
                if( is_null( $json_metadata ) || isset( $json_metadata->message ) ) {
                    $this->logger->warning( 'Metadata error.' );
                    update_post_meta( $post->ID, self::META_KEY_PROCESSED, 'yes' );
                    continue;
                }

                // Validate JSON.
                $this->validate_json_item( $json_metadata, $expected_properties );

                // Save data.
                
                if( ! empty( $json_metadata->alt ) ) {
                    update_post_meta( $post->ID, '_wp_attachment_image_alt', $json_metadata->alt );
                }

                if ( ! empty( $json_metadata->caption ) ) {
                    wp_update_post( [
                        'ID' => $post->ID,
                        'post_excerpt' => $json_metadata->caption,
                    ]);
                }

                update_post_meta( $post->ID, self::META_KEY_PROCESSED, 'yes' );

            } // foreach item.
            
        } while( ! empty( $posts ) );

        $this->logger->info( 'Done.' );
    }

    /**
     * Import articles, authors, tags, and topics.
     *
     * @param array $pos_args Command arguments.
     * @param array $assoc_args Command associative arguments.
     */
    public function cmd_import( array $pos_args, array $assoc_args ): void {

        $this->validate_dependencies();

        $this->validate_feed_type( $pos_args );
        
        // Page start.
        $page_start = 0;
        if ( isset( $assoc_args['page-start'] ) ) {
            if ( is_numeric( $assoc_args['page-start'] ) ) {
                $page_start = intval( $assoc_args['page-start'] );
            } else {
                WP_CLI::error( 'Page start must be an integer.', true );
            }
        }
        
        // Page end.
        $page_end = PHP_INT_MAX;
        if ( isset( $assoc_args['page-end'] ) ) {
            if ( is_numeric( $assoc_args['page-end'] ) ) {
                $page_end = intval( $assoc_args['page-end'] );
            } else {
                WP_CLI::error( 'Page end must be an integer.', true );
            }
        }
        
        // Dry run.
        $this->dry_run = isset( $assoc_args['dry-run'] );
    
        // Logger.
        $logger_slug = __FUNCTION__ . '__' . $pos_args[0];
        $this->logger_set( $logger_slug );

        // Run command.
        $this->logger->info( 'Running command: ' . $logger_slug );
        $this->logger->info( '--arg: page-start: ' . $page_start );
        $this->logger->info( '--arg: page-end: ' . $page_end );
        if( $this->dry_run ) {
            $this->logger->info( '--arg: dry-run: true' );
        }
        
        // Import type.
        switch( $pos_args[0] ) {
            case 'articles':
                $feed_url = self::FEED_URL_ARTICLES;
                $item_callback = [ $this, 'import_json_article' ];
                break;
            case 'authors':
                $feed_url = self::FEED_URL_AUTHORS;
                $item_callback = [ $this, 'import_json_author' ];
                break;
            case 'tags':
                $feed_url = self::FEED_URL_TAGS;
                $item_callback = [ $this, 'import_json_tag' ];
                break;
            case 'topics':
                $feed_url = self::FEED_URL_TOPICS;
                $item_callback = [ $this, 'import_json_topic' ];
                break;
        }
        
        // Import pages.
        $has_pages = true;
        $page_index = $page_start;

        while ( $has_pages && $page_index >= $page_start && $page_index <= $page_end ) {

            // Continue if data was found.
            $has_pages = $this->import_feed_page( $feed_url, $page_index, $item_callback );

            ++$page_index;

            // Add a small delay between requests to be nice to the server
            sleep(1);

        }

        $this->logger->info( 'Done.' );
    }

    /**
     * Process articles, authors, tags, and topics.
     *
     * @param array $pos_args Command arguments.
     * @param array $assoc_args Command associative arguments.
     */
    public function cmd_process( array $pos_args, array $assoc_args ): void {

        $this->validate_dependencies();

        $this->validate_feed_type( $pos_args );
            
        // Logger.
        $logger_slug = __FUNCTION__ . '__' . $pos_args[0];
        $this->logger_set( $logger_slug );

        // Run command.
        $this->logger->info( 'Running command: ' . $logger_slug );
                
        do {

            // Has json item from import, but not processed.
            $meta_query = [
                [
                    'key'     => self::META_KEY_JSON_ITEM,
                    'compare' => 'EXISTS',
                ],
                [
                    'key'     => self::META_KEY_PROCESSED,
                    'compare' => 'NOT EXISTS',
                ],
            ];

            $limit = 10;
            
            // Get items for processing.
            switch( $pos_args[0] ) {
                case 'articles':
                    $db_items = get_posts( [ 'fields' => 'ids', 'numberposts' => $limit, 'meta_query' => $meta_query ] );
                    break;
                case 'authors':
                    $db_items = get_users( [ 'fields' => 'ID', 'number' => $limit, 'meta_query' => $meta_query ] );
                    break;
                case 'tags':
                    $db_items = get_terms( [ 'fields' => 'ids', 'taxonomy' => 'post_tag', 'number' => $limit, 'hide_empty' => false, 'meta_query' => $meta_query ] );
                    break;
                case 'topics':
                    $db_items = get_terms( [ 'fields' => 'ids', 'taxonomy' => 'category', 'number' => $limit, 'hide_empty' => false, 'meta_query' => $meta_query ] );
                    break;
            }

            // Process items.
            foreach( $db_items as $db_id ) {

                $this->logger->info( '------------ processing id: ' . $db_id );

                switch( $pos_args[0] ) {
                    case 'articles':
                        $json_item = $this->validate_get_from_db( 'post', $db_id );
                        $this->logger->info( 'old url: ' . $json_item->url );
                        $this->process_article( $db_id, $json_item );
                        update_post_meta( $db_id, self::META_KEY_PROCESSED, 'yes' );
                        break;
                    case 'authors':
                        $json_item = $this->validate_get_from_db( 'user', $db_id );
                        $this->logger->info( 'old url: ' . $json_item->url );
                        $this->process_author( $db_id, $json_item );
                        update_user_meta( $db_id, self::META_KEY_PROCESSED, 'yes' );
                        break;
                    case 'tags':
                        $json_item = $this->validate_get_from_db( 'term', $db_id );
                        $this->logger->info( 'old url: ' . $json_item->url );
                        $this->process_tag( $db_id, $json_item );
                        update_term_meta( $db_id, self::META_KEY_PROCESSED, 'yes' );
                        break;
                    case 'topics':
                        $json_item = $this->validate_get_from_db( 'term', $db_id );
                        $this->logger->info( 'old url: ' . $json_item->url );
                        $this->process_topic( $db_id, $json_item );
                        update_term_meta( $db_id, self::META_KEY_PROCESSED, 'yes' );
                        break;
                }

                $this->logger->info( '-- done with item' );

            } // foreach item.
            
        } while( ! empty( $db_items ) );

        $this->logger->info( 'Done.' );
    }

    /**
     * Import one feed page.
     *
     * @param string $feed_url Feed URL.
     * @param int $page_index Page number (zero-based).
     * @param callable $item_callback Callback function to import each item.
     * @return bool True if data was found, false otherwise.
     */
    private function import_feed_page( string $feed_url, int $page_index, callable $item_callback ): bool {

        $paged_url = add_query_arg( 'page', $page_index, $feed_url );
        
        $this->logger->info( sprintf( 'Fetching feed -------------------------------------------------------------' ) );
        $this->logger->info( sprintf( 'Feed url: %s', $paged_url ) );
        
        $response = wp_remote_get( $paged_url, [ 'timeout' => 30 ] ); // increase timeout to be safe.

        if ( is_wp_error( $response ) ) {
            $this->logger->error( sprintf( 'Failed to fetch JSON: %s', $response->get_error_message() ) );
            $this->logger->error( 'Run CLI again starting at current page with argument: --page-start=' . $page_index );
            exit();
        }

        $json_page = json_decode( wp_remote_retrieve_body( $response ) );
        
        // End of feed will be an empty array.
        if ( is_array( $json_page ) && 0 === count( $json_page ) ) {
            $this->logger->notice( 'No more data found.' );
            return false;
        }

        // Otherwise, if the JSON is invalid, exit.
        if ( ! $json_page ) {
            $this->logger->error( sprintf( 'Failed to parse JSON data' ) );
            exit();
        }

        $loop_index = 0;
        foreach ( $json_page as $json_item ) {

            $this->logger->info( sprintf( '------------ JSON index: %d:', $loop_index ) );

            // Log old slug url as unique key for easier debugging.
            $this->logger->info( sprintf( 'Old slug url: %s', $json_item->url ?? 'missing' ) );

            // Generate checksum for this json item for comparison with future imports.
            $checksum = $this->checksum_hash( $json_item );

            // Run callback for each item.
            call_user_func( $item_callback, $json_item, $checksum );

            $this->logger->info( sprintf( '-- done with item ----------------------- ' ) );

            $loop_index++;

        }

        return true;

    }

    /**
     * Import one article item.
     *
     * @param object $json_item JSON data.
     * @param string $checksum Checksum for comparison with future imports.
     */
    private function import_json_article( object $json_item, string $checksum ) {

        // Define expected properties and additional validation rules        
        $expected_properties = [
            'author'             => [ 'type' => 'array', ],
            // content - has extra "," commas in content. Use content_serialized. Or legacy_content (prior to 2017-01-25).
            'content'            => [ 'type' => 'string' ],
            'content_serialized' => [ 'type' => 'array' ],
            'date' => [
                'type' => 'string',
                'required' => true,
                'validate' => function( $value ) {
                    // check out of bounds: after 2010-01-01 (low end prior to May 9, 2011) or before now (high end).
                    return ( is_numeric( $value ) && $value >= 1262304000 && $value <= time() );
                }
            ],
            'drupal_author'                 => [ 'type' => 'string' ],
            'end_date'                      => [ 'type' => 'string', ],
            'exclude_from_popular_articles' => [ 'type' => 'string', ],
            'exclude_from_rss_feeds'        => [ 'type' => 'string', ],
            'feature_options'               => [ 'type' => 'string', ],
            'hide_from_river'               => [ 'type' => 'string', ],
            'hide_hero_images'              => [ 'type' => 'string', ],
            'hero_image'                    => [ 'type' => 'array', ], // if multiple, then slideshow?
            'hero_image_credit'             => [ 'type' => 'string', ],
            'hero_image_legacy'             => [ 'type' => 'array', ],
            'key_points'                    => [ 'type' => 'array', ],
            'legacy_content'                => [ 'type' => 'string', ],
            'published'                     => [ 'type' => 'string', 'required' => true, ],
            'start_date'                    => [ 'type' => 'string', ],
            'summary'                       => [ 'type' => 'string', ],
            'tags'                          => [ 'type' => 'array', ],
            'title'                         => [ 'type' => 'string', 'required' => true, ],
            'topic'                         => [ 'type' => 'string', ],
            'updated_date'                  => [ 'type' => 'string', ],
            'url' => [
                'type' => 'string',
                'required' => true,
                'validate' => fn( $value ) => str_starts_with( $value, '/' ), 
            ],
        ];

        // Error out if json_item is not valid.
        $this->validate_json_item( $json_item, $expected_properties );
        
        // Check if already exists with this URL (unique value).
        $existing_ids = get_posts( array(
            'meta_key'    => self::META_KEY_OLD_SLUG,
            'meta_value'  => $json_item->url,
            // List all possible statuses (including trash) since 'any' doesn't check trash.
            'post_status' => array( 'auto-draft', 'draft', 'future', 'inherit', 'pending', 'private', 'publish', 'trash' ),
            'fields'      => 'ids',
        ) );
        
        // Log validation and return if already exists.
        if( $this->validate_has_existing( 'post', $existing_ids, $checksum ) ) {
            return;
        }

        // Skip non-published posts.
        if( $json_item->published !== 'True' ) {
            $this->logger->notice( 'Skip: JSON post not published.' );
            return;
        }

        // Dry run, return.
        if ( $this->dry_run ) {
            $this->logger->notice( 'Skip: Dry run (otherwise post would be imported).' );
            return;
        }
        
        // Epoch to formated ( value is UTC ).
        $date_formated = date( 'Y-m-d H:i:s', $json_item->date );

        // Try to keep same url (see process article for clean-up).
        $post_name_arr = explode( '/', $json_item->url ); // split url.
        if( 3 !== count( $post_name_arr ) ) {
            $this->logger->error( sprintf( 'Failed to split url: %s', $json_item->url ) );
            exit();
        }

        // Prepare post data.
        $post_arr = [
            'post_title'    => trim( $json_item->title ),
            'post_excerpt'  => trim( $json_item->summary ?? '' ), // optional.
            'post_content'  => '', // will be set later with process command.
            'post_author'   => 0, // will be set later with process command.
            'post_date'     => get_date_from_gmt( $date_formated ), // feed is GMT.
            'post_date_gmt' => $date_formated, // feed is GMT.  
            'post_status'   => 'publish',
            'post_name'     => $post_name_arr[2], // get slug after topic name.
            'meta_input'    => [
                self::META_KEY_CHECKSUM  => $checksum,
                self::META_KEY_JSON_ITEM => wp_slash( serialize( $json_item ) ),
                self::META_KEY_OLD_SLUG  => $json_item->url,
            ],
        ];

        // Insert post
        $post_id = wp_insert_post( $post_arr );
        if ( is_wp_error( $post_id ) ) {
            $this->logger->error( sprintf( 'Failed to insert post: %s', $post_id->get_error_message() ) );
            exit();
        }
        if ( ! ( $post_id > 0 ) ) {
            $this->logger->error( sprintf( 'Failed to insert post (id not greater than 0): %s', $post_id ) );
            exit();
        }

        $this->logger->info( sprintf( 'Imported new post ID: %d', $post_id ) );
        
    }
    
    /**
     * Import one author item.
     *
     * @param object $json_item JSON data.
     * @param string $checksum Checksum for comparison with future imports.
     */
    private function import_json_author( object $json_item, string $checksum ) {

        // Define expected properties and additional validation rules        
        $expected_properties = [
            'biography'            => [ 'type' => 'string', ],
            'byline'               => [ 'type' => 'string', ],
            'email'                => [ 'type' => 'string', ],
            'facebook_page_url'    => [ 'type' => 'string', ],
            'guest_author'         => [ 'type' => 'string', ],
            'hide_from_about_page' => [ 'type' => 'string', ],
            'image'                => [ 'type' => 'string', ],
            'image_legacy'         => [ 'type' => 'string', ],
            'job_title'            => [ 'type' => 'string', ],
            'published'            => [ 'type' => 'string', 'required' => true, ],
            'title'                => [ 'type' => 'string', 'required' => true, ],
            'url' => [
                'type' => 'string',
                'required' => true,
                'validate' => fn( $value ) => str_starts_with( $value, self::LIVE_AUTHOR_PATH ), 
            ],
        ];

        // Error out if json_item is not valid.
        $this->validate_json_item( $json_item, $expected_properties );

        // Check if already exists with this URL (unique value).
        $existing_ids = get_users( array(
            'meta_key'   => self::META_KEY_OLD_SLUG,
            'meta_value' => $json_item->url,
            'fields'     => 'ids',
        ) );

        // Log validation and return if already exists.
        if( $this->validate_has_existing( 'user', $existing_ids, $checksum ) ) {
            return;
        }

        // Skip non-published.
        if( $json_item->published !== 'True' ) {
            $this->logger->notice( 'Skip: JSON author not published.' );
            return;
        }
        
        // Dry run, return.
        if ( $this->dry_run ) {
            $this->logger->notice( 'Skip: Dry run (otherwise author would be imported).' );
            return;
        }

        // Insert user with force since there can be multiple authors with the same display name.
        $user_id = GuestContributorsHelper::create_by_display_name( $json_item->title, [ 'user_nicename' => str_replace( self::LIVE_AUTHOR_PATH, '', $json_item->url ) ], true );
        if ( is_wp_error( $user_id ) ) {
            $this->logger->error( sprintf( 'Failed to create Guest Contributor: %s', $user_id->get_error_message() ) );
            exit();
        }

        // Set meta.
        update_user_meta( $user_id, self::META_KEY_CHECKSUM, $checksum );
        update_user_meta( $user_id, self::META_KEY_JSON_ITEM, wp_slash( serialize( $json_item ) ) );
        update_user_meta( $user_id, self::META_KEY_OLD_SLUG, $json_item->url );

        // Log success.
        $this->logger->info( sprintf( 'Imported user ID: %d', $user_id ) );

    }

    /**
     * Import one tag item.
     *
     * @param object $json_item JSON data.
     * @param string $checksum Checksum for comparison with future imports.
     */
    private function import_json_tag( object $json_item, string $checksum ) {

        // Define expected properties and additional validation rules        
        $expected_properties = [
            'description' => [ 'type' => 'string', ],
            'name'        => [ 'type' => 'string', 'required' => true, ],
            'url'         => [
                'type' => 'string',
                'required' => true,
                'validate' => fn( $value ) => str_starts_with( $value, self::LIVE_TAG_PATH ), 
            ],
        ];

        // Error out if json_item is not valid.
        $this->validate_json_item( $json_item, $expected_properties );

        // Check if already exists with this URL (unique value).
        $existing_ids = get_terms( array(
            'taxonomy'   => 'post_tag',
            'hide_empty' => false, // Newly imported tags will not have any posts.
            'meta_key'   => self::META_KEY_OLD_SLUG,
            'meta_value' => $json_item->url,
            'fields'     => 'ids',
        ) );

        // Log validation and return if already exists.
        if( $this->validate_has_existing( 'term', $existing_ids, $checksum ) ) {
            return;
        }

        // Dry run, return.
        if ( $this->dry_run ) {
            $this->logger->notice( 'Skip: Dry run (otherwise tag would be imported).' );
            return;
        }
        
        // Insert term.
        $term_id_arr = wp_insert_term( trim( $json_item->name ), 'post_tag', [ 'slug' => str_replace( self::LIVE_TAG_PATH, '', $json_item->url ) ] );

        // Validate insert.
        if( ! $this->validate_insert_term( $term_id_arr, $json_item ) ) {
            return;
        }

        // Set meta.
        update_term_meta( $term_id_arr['term_id'], self::META_KEY_CHECKSUM, $checksum );
        update_term_meta( $term_id_arr['term_id'], self::META_KEY_JSON_ITEM, wp_slash( serialize( $json_item ) ) );
        update_term_meta( $term_id_arr['term_id'], self::META_KEY_OLD_SLUG, $json_item->url );

        $this->logger->info( sprintf( 'Imported term ID: %d', $term_id_arr['term_id'] ) );

    }

    /**
     * Import one topic item.
     *
     * @param object $json_item JSON data.
     * @param string $checksum Checksum for comparison with future imports.
     */
    private function import_json_topic( object $json_item, string $checksum ) {

        // Define expected properties and additional validation rules        
        $expected_properties = [
            'background_image' => [ 'type' => 'string', ],
            'description'      => [ 'type' => 'string', ],
            'details'          => [ 'type' => 'string', ],
            'icon'             => [ 'type' => 'string', ],
            'name'             => [ 'type' => 'string', 'required' => true, ],
            'featured'         => [ 'type' => 'string', ],
            'published'        => [ 'type' => 'string', 'required' => true, ],
            'url'         => [
                'type' => 'string',
                'required' => true,
                'validate' => fn( $value ) => str_starts_with( $value, self::LIVE_CATEGORY_PATH ), 
            ],
        ];

        // Error out if json_item is not valid.
        $this->validate_json_item( $json_item, $expected_properties );
        
        // Check if already exists with this URL (unique value).
        $existing_ids = get_terms( array(
            'taxonomy'   => 'category',
            'hide_empty' => false, // Newly imported will not have any posts.
            'meta_key'   => self::META_KEY_OLD_SLUG,
            'meta_value' => $json_item->url,
            'fields'     => 'ids',
        ) );

        // Log validation and return if already exists.
        if( $this->validate_has_existing( 'term', $existing_ids, $checksum ) ) {
            return;
        }

        // Skip non-published.
        if( $json_item->published !== 'True' ) {
            $this->logger->notice( 'Skip: JSON topic not published.' );
            return;
        }
        
        // Dry run, return.
        if ( $this->dry_run ) {
            $this->logger->notice( 'Skip: Dry run (otherwise category would be imported).' );
            return;
        }
        
        // Remove link from name property: <a href="/topics/truth-squad-companion" hreflang="en">A Truth Squad companion</a>
        $name = str_replace( '<a href="' . $json_item->url . '" hreflang="en">', '', $json_item->name );
        $name = str_replace( '</a>', '', $name );
        $name = trim( $name );
        
        // Insert term.
        $term_id_arr = wp_insert_term( $name, 'category', [ 'slug' => str_replace( self::LIVE_CATEGORY_PATH, '', $json_item->url ) ] );

        // Validate insert.
        if( ! $this->validate_insert_term( $term_id_arr, $json_item ) ) {
            return;
        }

        // Set meta.
        update_term_meta( $term_id_arr['term_id'], self::META_KEY_CHECKSUM, $checksum );
        update_term_meta( $term_id_arr['term_id'], self::META_KEY_JSON_ITEM, wp_slash( serialize( $json_item ) ) );
        update_term_meta( $term_id_arr['term_id'], self::META_KEY_OLD_SLUG, $json_item->url );

        // Imported.
        $this->logger->info( sprintf( 'Imported term ID: %d', $term_id_arr['term_id'] ) );

    }

    /**
     * Process one article using it's verified (checksum) json_item.
     *
     * @param int $post_id Post ID.
     * @param object $json_item JSON data.
     */
    private function process_article( int $post_id, object $json_item ): void {

        // Featured image.        
        $this->set_post_hero_image( $post_id, $json_item->hero_image, $json_item->hero_image_legacy, $json_item->hero_image_credit, $json_item->hide_hero_images );
        
        // Process and clean up post content.  Use legacy content if no serialized content.
        $post_content = empty( $json_item->content_serialized ) ? trim( $json_item->legacy_content ) : $this->process_content_array( $json_item->content_serialized );
            
        // Get in-content assets.
        $post_content = $this->process_content_assets( $post_content, $post_id );

        // Log a warning if blank content.
        if ( empty( $post_content ) ) {
            $this->logger->warning( 'No content found.' );
        }
        
        // Update post content.
        wp_update_post( [ 'ID' => $post_id, 'post_content' => $post_content ] );
        
        // Set author relationships.
        $this->set_post_authors( $post_id, $json_item->author );

        // Set topic as category.
        if ( ! empty( $json_item->topic ) ) {
            $this->set_post_topic( $post_id, $json_item->topic );
        }

        // Set tags.
        if ( ! empty( $json_item->tags ) ) {
            $this->set_post_tags( $post_id, $json_item->tags );
        }

        // Remove home URL from permalink.
        $permalink = str_replace( home_url(), '', get_permalink( $post_id ) );
        
        // Remove trailing slash just for comparison.
        if( rtrim( $permalink, '/' ) === $json_item->url ) {
            $this->logger->info( 'Redirect not needed for article.' );
        }
        else {
            $this->set_redirect( $json_item->url, $permalink, 'article' );
        }
    }
    
    /**
     * Process one author using verified (checksum) json_item.
     *
     * @param int $user_id User ID.
     * @param object $json_item JSON data.
     */
    private function process_author( int $user_id, object $json_item): void {

        $user_data = get_userdata( $user_id );

        // Set job title.
        if( ! empty( trim( $json_item->job_title ) ) ) {
            update_user_meta( $user_id, 'newspack_job_title', trim( $json_item->job_title ) );
        }

        // Fetch image first, else legacy.
        $attachment_id = null;
        if ( ! empty( $json_item->image ) ) {
            $attachment_id = $this->get_or_import_attachment( $json_item->image );
        }
        if ( empty( $attachment_id ) && ! empty( $json_item->image_legacy ) ) {
            $attachment_id = $this->get_or_import_attachment( $json_item->image_legacy );
        }

        // Simple local avatars.
        if ( is_numeric( $attachment_id ) && $attachment_id > 0 ) {
            (new \Simple_Local_Avatars())->assign_new_user_avatar( $attachment_id, $user_id );
            $this->logger->info( 'Avatar set to attachment ID: ' . $attachment_id ); 
        }

        // All authors need redirects since they would exist under /about/ which is a WordPress page.
        $this->set_redirect( $json_item->url, '/author/' . $user_data->user_nicename, 'author' );

        // Combine into bio.
        $description = trim( trim( $json_item->byline ) . "\n\n" . trim( $json_item->biography ) );
        if( ! empty( $description ) ) {
            $this->logger->warning( 'Author bio is not empty - check for images/assets?' );
            // Set description by update instead of user meta so any filters are applied.
            wp_update_user( [
                'ID'         => $user_id,
                'description' => $description,
            ] );
        }
    }

    /**
     * Process one tag using verified (checksum) json_item.
     *
     * @param int   $term_id Term ID.
     * @param object $json_item JSON data.
     */
    private function process_tag( int $term_id, object $json_item ): void {

        // Update post tag description.
        $description = trim( $json_item->description );
        if( ! empty( $description ) ) {
            $this->logger->warning( 'Tag description is not empty - check for images/assets?' );
            wp_update_term( $term_id, 'post_tag', [ 'description' => $description ] );
        }

        // get term slug.
        $term = get_term( $term_id, 'post_tag' );

        // If tag slug doesn't match url slug, then special (non-regex) redirect.
        if( $json_item->url === '/tags/' . $term->slug ) {
            $this->logger->info( 'Redirect not needed for tag.' );
        }
        else {
            $this->set_redirect( $json_item->url, '/tag/' . $term->slug, 'tag' );
        }
    }

    /**
     * Process one topic using verified (checksum) json_item.
     *
     * @param int   $term_id Term ID.
     * @param object $json_item JSON data.
     */
    private function process_topic( int $term_id, object $json_item ): void {

        // Append details to description.
        $description = trim( trim( $json_item->description ) . "\n\n" . trim( $json_item->details ) );
        if( ! empty( $description ) ) {
            $this->logger->warning( 'Topic description is not empty - check for images/assets?' );
            wp_update_term( $term_id, 'category', [ 'description' => $description ] );
        }

        // Images.
        if( ! empty( $json_item->background_image ) ) {
            $this->get_or_import_attachment( $json_item->background_image );
        }

        if( ! empty( $json_item->icon ) ) {
          $this->get_or_import_attachment( $json_item->icon );
        }

        // Redirect.
        $term = get_term( $term_id, 'category' );
        if( $json_item->url === '/topics/' . $term->slug ) {
            $this->logger->info( 'Redirect not needed for topic.' );
        }
        else {
            $this->set_redirect( $json_item->url, '/category/' . $term->slug, 'topic' );
        }
    }

    /**
     * Process the content_serialized array to clean up HTML and extract content.
     *
     * @param array $content_array Array of HTML content sections
     * @return string Processed content ready for WordPress
     */
    private function process_content_array( array $content_array ): string {
        
        $leave_alone = [
            '/^<div class="dr-paragraph full-width-image dr-paragraph--full-width-image">/s',
            '/^<div class="dr-paragraph multi-column-content dr-paragraph--multi-column-content">/s',
            '/^<div class="dr-paragraph right-aligned-blockquote dr-paragraph--right-aligned-blockquote">/s',
            '/^<div class="dr-paragraph right-aligned-blockquote-xl dr-paragraph--right-aligned-blockquote-xl">/s',
            '/^<div class="dr-paragraph right-aligned-image dr-paragraph--right-aligned-image">/s',
            '/^<div\s*class="region">/s',
        ];

        $counter = 0;
        $count = count( $content_array );
        
        $content = '';
        
        foreach ( $content_array as $section ) {
        
            ++$counter;
            $section = trim( $section );
            $strtok_line = strtok( $section, "\n" );

            $this->logger->info( 'Content section:' . $strtok_line );

            // Skip ad sections
            if ( preg_match( '/^<div class="dr-paragraph (right|left)-aligned-ad dr-paragraph--.*googletag.cmd.push/s', $section ) ) {
                // Make sure we're only removing a resonable length of html.
                $strlen = mb_strlen( $section );
                if ( 592 <= $strlen && 604 >= $strlen ) {
                    continue; // do not add to $content.
                }
                $this->logger->warning( 'Unknown aligned-ad still in content: ' . print_r( $section, true ) );
                // exit(); // change error to warning.
            }

            // Verify ad was removed.
            if ( preg_match( '/googletag.cmd.push/', $section ) ) {
                $this->logger->warning( 'Ad unit still exists: ' . print_r( $section, true ) );
                // exit(); // switch to warnings only.
            }

            // Blank section (start and end), just leave it as it might have to do with clearfix or soemthing...
            if ( preg_match( '/^<div class="dr-paragraph full-width-text dr-paragraph--full-width-text">\s*<\/div>$/s', $section ) ) {
                $content .= "\n\n" . $section;
                continue;
            }

            // Clean up text sections
            $regex_open = '/^<div class="dr-paragraph full-width-text dr-paragraph--full-width-text">\s*<div class="text-long">/s';
            if ( preg_match( $regex_open, $section ) ) {
                $new_section = trim( $this->process_content_remove_by_regex( $regex_open, $section ) );
                $new_section = trim( $this->process_content_remove_by_regex( '/<\/div>\s*<\/div>$/', $new_section ) );
                if( empty( $new_section ) ) {
                    $this->logger->warning( 'Section is now empty: ' . print_r( $section, true ) );
                    // exit(); warning only.
                }
                $content .= "\n\n" . $new_section;
                continue;
            }

            // Remove related links at bottom.  Start to end if empty.
            if ( $count === $counter && preg_match( '/^<div class="dr-paragraph related-articles dr-paragraph--related-articles">\s*<\/div>$/s', $section ) ) {
                continue; // do not add
            }

            // Remove related links at bottom, start with secondary div.
            $regex_open = '/^<div class="dr-paragraph related-articles dr-paragraph--related-articles">\s*<div>/s';
            if ( $count === $counter && preg_match( $regex_open, $section ) ) {
                $new_section = trim( $this->process_content_remove_by_regex( $regex_open, $section ) );
                $new_section = trim( $this->process_content_remove_by_regex( '/<\/div>\s*<\/div>$/', $new_section ) );
                if( ! empty( $new_section ) ) {
                    $this->logger->warning( 'Related links is not empty: ' . print_r( $section, true ) );
                    // exit();
                }
                continue; // do not add since its now blank
            }

            // leave alone
            foreach( $leave_alone as $regex ) {
                if ( preg_match( $regex, $section ) ) {
                    $content .= "\n\n" . $section; // same for now...
                    continue 2;
                }    
            }

            // What is remaining?
            $this->logger->warning( 'Unhandled section: ' . $strtok_line );
            $content .= "\n\n" . $section; // same for now...

        }

        return $content;

    }
    
    private function process_content_remove_by_regex( $regex, $section ) {
                     
        // Remove open.
        $replaced_count = 0; // by reference
        $section = preg_replace( $regex, '', $section, -1, $replaced_count );
        if ( 1 !== $replaced_count ) {
            $this->logger->error( 'Replacement count was not 1: ' . $regex );
            $this->logger->error( 'Replacement count was not 1: ' . print_r( $section, true ) );
            exit();
        }

        return $section;
    }

    private function process_content_assets( $post_content, $post_id ) {

        $html_doc = new HtmlDocument( $post_content );
    
        // Assets in img src.
        $images = $html_doc->find( 'img' );
        foreach ( $images as $img ) {
            $src = $img?->getAttribute( 'src' );            
            if ( ! $src ) {
                $this->logger->warning( 'Content has img with no src.' );
                continue;
            }
            $post_content = $this->process_content_assets_single( $post_content, $post_id, $src );
        }

        // Assets in a href.
        $links = $html_doc->find( 'a' );
        foreach ( $links as $link ) {
            $href = $link?->getAttribute( 'href' );            
            if ( ! $href ) {
                $this->logger->warning( 'Content has a with no href.' );
                continue;
            }
            $post_content = $this->process_content_assets_single( $post_content, $post_id, $href );
        }

        return $post_content;
    }

    private function process_content_assets_single( $post_content, $post_id, $url_from_cralwer ) {

        // Everything already expects relative paths so convert to relative.
        $relative_path = trim( $url_from_cralwer );
        $relative_path = preg_replace( '#^//(www\.)?bridgemi\.com#i', '', $relative_path ); // no scheme
        $relative_path = preg_replace( '#^https?://(www\.)?bridgemi\.com#i', '', $relative_path ); // with scheme

        // Must be relative at this point or return;
        if( str_starts_with( $relative_path, '//' ) || ! str_starts_with( $relative_path, '/' ) ) return $post_content;

        // Must be link to an asset ext.
        $parsed_url_path = parse_url( $relative_path, PHP_URL_PATH );
        if( ! is_string( $parsed_url_path ) || empty( $parsed_url_path ) ) {
            return $post_content;
        }
        $parsed_url_ext = pathinfo( $parsed_url_path, PATHINFO_EXTENSION );
        if( ! is_string( $parsed_url_ext ) || empty( $parsed_url_ext ) ) {
            return $post_content;
        }

        // Check extension.
        if( ! in_array( strtolower( $parsed_url_ext ), $this->allowed_mime_types ) ) {
            $this->logger->warning( 'ext not in mime types: ' . $relative_path );
            return $post_content;
        }
        
        $this->logger->info( 'Allowed asset relative_path: ' . $relative_path );

        $attachment_id = $this->get_or_import_attachment( $relative_path, null, null, null, null, $post_id );
        if( ! ( $attachment_id > 0 ) ) {
            $this->logger->warning( 'No asset fetched.' );
            return $post_content;
        }

        // Get the local attachment url.
        $attachment_url = wp_get_attachment_url( $attachment_id );
        $this->logger->info( 'Asset attachment url: ' . $attachment_url );
        
        // Crawlers will convert "&amp;" to "&".  So check if this url is not a match in content.
        $url_to_replace = $url_from_cralwer;
        if( ! str_contains( $post_content, $url_to_replace ) ) {
            $this->logger->warning( 'Extracted url not found in content: ' . $url_to_replace );
            // try with entities converted back.
            $url_to_replace = esc_attr( $url_to_replace );
            if( ! str_contains( $post_content, $url_to_replace ) ) {
                $this->logger->warning( 'Esc_attr not found: ' . $url_to_replace );
                return $post_content;
            }
            $this->logger->warning( 'Using esc_attr: ' . $url_to_replace );
        }
        
        // Replace the url in the content.
        $replacement_count = 0;
        $post_content = str_replace( $url_to_replace, $attachment_url, $post_content, $replacement_count );

        // Make sure replacement was a success.
        if( ! ( $replacement_count > 0 ) ) {
            $this->logger->warning( 'Assset replacement failed.' );
            return $post_content;
        }
        else if( $replacement_count > 1 ) {
            $this->logger->notice( 'Assset replacement count: ' . $replacement_count );
        }
        
        return $post_content;

    }

    /**
	 * Get attachment (based on relative URL path) from database else import external file
     * from live site and set old url post_meta.
     * 
     * If not fetched, returns 0 and logs a warning ( asset might not exist anymore on live site ).
     * 
	 * @param string $relative_path URL.
	 * @param string $title Title (optional).
	 * @param string $caption Image caption (optional).
	 * @param string $description Image desc (optional).
	 * @param string $alt Image alt (optional).
	 * @param int    $post_id Post ID (optional).
	 * @return int   $attachment_id or int(0).
	 */
	private function get_or_import_attachment( string $relative_path, ?string $title = null, ?string $caption = null, ?string $description = null, ?string $alt = null, int $post_id = 0 ): int {

        $this->logger->info( 'Finding image: ' . $relative_path );

        // Check if already exists.
		$attachments = get_posts([
			'post_type'   => 'attachment',
			'meta_key'    => self::META_KEY_OLD_SLUG,
			'meta_value'  => $relative_path,
            'fields'      => 'ids',
            'numberposts' => 1,
		]);

        if( ! empty( $attachments ) ) {
            $attachment_id = reset( $attachments );
            $this->logger->info( 'Found attachment id: ' . $attachment_id );
            return $attachment_id;
        }

        // Fetch.
        $fetch_url = self::LIVE_SITE_URL . $relative_path;
        $this->logger->info( 'Fetching image url: ' . $fetch_url );

        // Since some urls could have querystring (in-content images), but WordPress needs just the filename portion for extension/mime parsing.
        $parsed_url = parse_url( $relative_path );
        $desired_filename = urldecode( basename( $parsed_url['path'] ) ); // just the last piece decoded.
        $this->logger->info( 'Desired image filename: ' . $desired_filename );

        // Get the file using full fetch url (with querystring incase it's at a CDN like '?width=100'), but remove querystring for filename (extension/mime parsing).
        // set $try_existing to false so that "similar" files are not merged into one.
        $attachment_id = Attachments::import_external_file( $fetch_url, $title, $caption, $description, $alt, $post_id, [], $desired_filename, false );

        if ( is_wp_error( $attachment_id ) ) {
			$this->logger->warning( sprintf( 'Attachment was not fetched: %s %s', $relative_path, $attachment_id->get_error_message() ) );
            return 0;
		}

		if ( ! is_numeric( $attachment_id ) || ! ( $attachment_id > 0 ) ) {
			$this->logger->warning( sprintf( 'Attachment was not integer gt 0: %s %s', $relative_path, json_encode( $attachment_id ) ) );
            return 0;
		}

        $this->logger->info( 'Imported attachment id: ' . $attachment_id );

        // Set post meta.
        update_post_meta( $attachment_id, self::META_KEY_OLD_SLUG, $relative_path );

		return $attachment_id;
	}

    /**
     * Fetch image json data form live feed.
     *
     * @param string $relative_path
     * @return object|null
     */
    private function get_attachment_json( string $relative_path ) : ?object {

        // Path only (no querystring).
        $parsed_url = parse_url( $relative_path );

        // Get Image data from live site.
        $fetch_url = self::LIVE_IMAGE_API . '?path=' . urlencode( $parsed_url['path'] );
        $this->logger->info( sprintf( 'Fetching image data: %s', $fetch_url ) );

        $response = wp_remote_get( $fetch_url, [ 'timeout' => 30 ] ); // increase timeout to be safe.
        if ( is_wp_error( $response ) ) {
            $this->logger->warning( sprintf( 'Failed to fetch JSON: %s', $response->get_error_message() ) );
            return null;
        }
        
        $json_metadata = json_decode( wp_remote_retrieve_body( $response ) );
        if ( ! $json_metadata ) {
            $this->logger->warning( sprintf( 'Failed to parse JSON data' ) );
            return null;
        }

        $this->logger->info( 'JSON metadata: ' . wp_json_encode( $json_metadata ) );
        
        return $json_metadata;

    }

    /**
     * Set post authors.
     *
     * @param int   $post_id Post ID.
     * @param array $authors Array of author urls.
     */
    private function set_post_authors( int $post_id, array $authors ): void {
        
        $this->logger->info( sprintf( 'Setting authors: %s', implode( ', ', $authors ) ) );

        // Default to staff it empty.
        if( empty( $authors ) ) {
            $authors = [ '/about/bridge-staff' ];
            $this->logger->info( sprintf( 'Defaulting to staff author: %s', implode( ', ', $authors ) ) );
        }

        // Convert old slugs to ids.
        $author_ids = [];
        foreach ( $authors as $author_url ) {

            // Get user ID from old slug.
            $existing_ids = get_users( array(
                'meta_key'   => self::META_KEY_OLD_SLUG,
                'meta_value' => $author_url,
                'fields'     => 'ids',
            ) );

            // Must exist and be unique.
            if( empty( $existing_ids ) || 1 !== count( $existing_ids ) ) {
                $this->logger->warning( sprintf( 'Author missing or multiple.  User count !== 1: %s', $author_url ) );
                // $this->logger->warning( "Re-run 'bridgemi-import authors' then re-process." ); // warning instead of error.
                // exit(); // warning instead of error.
                return;
            }

            $author_ids[] = reset( $existing_ids );
        }

        // Set authors to post.
        global $coauthors_plus;
        $success = $coauthors_plus->add_coauthors( $post_id, $author_ids, false, 'id' );
        if ( ! $success ) {
            $this->logger->warning( sprintf( 'Failed to set authors - add_coauthors return: %s', json_encode( $success ) ) );
            $this->logger->warning( "Re-run 'bridgemi-import authors' then re-process." );
            // exit();
            return;
        }
    }

    /**
     * Set post tags.
     *
     * @param int   $post_id Post ID.
     * @param array $tags    Array of tag names.
     */
    private function set_post_tags( int $post_id, array $tags ): void {
        
        $this->logger->info( sprintf( 'Setting tags: %s', json_encode( $tags ) ) );
        
        // Verify imported.
        foreach( $tags as $tag ) {
            
            $tags_arr = get_term_by( 'name', $tag, 'post_tag', ARRAY_A );
            
            // Must exist.
            if( empty( $tags_arr ) || empty( $tags_arr['term_id'] ) || ! ( $tags_arr['term_id'] > 0 ) ) {
                $this->logger->warning( sprintf( 'Failed to get tag ID for tag: %s', $tag ) );
                $this->logger->warning( "Re-run 'bridgemi-import tags' then re-process." );
                // exit();
                return;
            }
        }

        // Note: this will insert tag(s) if not exists...so keep the check above to make sure imported.
        $success = wp_set_post_tags( $post_id, $tags, false );
        if ( ! is_array( $success ) || empty( $success ) || count( $success ) !== count( $tags ) ) {
            $this->logger->warning( sprintf( 'Failed to set tags - wp_set_post_tags returned: %s', json_encode( $success ) ) );
            $this->logger->warning( "Re-run 'bridgemi-import tags' then re-process." );
            // exit();
            return;
        }

    }

    /**
     * Set post topic as category.
     *
     * @param int    $post_id Post ID.
     * @param string $topic   Topic name.
     */
    private function set_post_topic( int $post_id, string $topic ): void {
        
        $this->logger->info( sprintf( 'Setting topic: %s', $topic ) );

        $category_arr = get_term_by('name', $topic, 'category', ARRAY_A );

        // Must exist.
        if( empty( $category_arr ) || empty( $category_arr['term_id'] ) || ! ( $category_arr['term_id'] > 0 ) ) {
            $this->logger->warning( sprintf( 'Failed to get category ID for topic: %s', $topic ) );
            $this->logger->warning( "Re-run 'bridgemi-import topics' then re-process." );
            // exit();
            return;
        }

        // Set to post.
        $success = wp_set_post_categories( $post_id, $category_arr['term_id'], false );
        if ( ! is_array( $success ) || empty( $success ) || 1 !== count( $success ) ) {
            $this->logger->warning( sprintf( 'Failed to set category - wp_set_post_categories returned: %s', json_encode( $success ) ) );
            $this->logger->warning( "Re-run 'bridgemi-import topics' then re-process." );
            // exit();
            return;
        }
    }

    /**
     * Set post hero image.
     * 
     * @param int    $post_id           Post ID.
     * @param array  $hero_image        Hero image array.
     * @param array  $hero_image_legacy Hero image legacy array.
     * @param string $hero_image_credit Hero image credit.
     * @param string $hide_hero_images  Hide hero images.
     */
    private function set_post_hero_image( int $post_id, array $hero_image, array $hero_image_legacy, string $hero_image_credit, string $hide_hero_images ): void {

        $this->logger->info( sprintf( 'Setting hero image: %s %s %s %s', json_encode( $hero_image ), json_encode( $hero_image_legacy ), $hero_image_credit, $hide_hero_images ) );

        // Choose which array to use.
        $images_arr = ( count( $hero_image ) > 0 ) ? $hero_image : $hero_image_legacy;
        $images_count = count( $images_arr );
        
        // Foreach hero image, fetch.
        for ( $i = 0; $i < $images_count; $i++ ) {
            
            // Use post_id to organize /uploads/ folder.
            $attachment_id = $this->get_or_import_attachment( $images_arr[ $i ], null, null, null, null, $post_id );
            
            // Only for first one incase it's an array.
            if ( $i === 0 && $attachment_id > 0 ) {

                // Set thumbnail.
                \set_post_thumbnail( $post_id, $attachment_id );

                // Image credit for first one only.
                $hero_image_credit = trim( $hero_image_credit );
                if( ! empty( $hero_image_credit )  ) {
                    // Use "add" with "true" so first/newest is the credit that is used.
                    \add_post_meta( $attachment_id, '_media_credit', $hero_image_credit, true );
                }

            }

        } // foreach

        // Hide hero image.
        if( $hide_hero_images === 'True' ) {
            \update_post_meta( $post_id, 'newspack_featured_image_position', 'hidden' );
        }

        // Set counter for future CLI just incase (like slideshow).
        update_post_meta( $post_id, self::META_KEY_HERO_IMAGE_COUNT, $images_count );

    }

    /**
     * Set redirect if not already exists.
     * 
     * @param string $url_from From URL.
     * @param string $url_to   To URL.
     * @param string $batch    Batch (string for easy lookup in admin).
     */
    private function set_redirect( $url_from, $url_to, $batch ) {

        $redirection = new Redirection();

		if( $redirection->redirect_from_exists( $url_from ) ) {
			$this->logger->notice( 'Skip: redirect already exists: ' . $url_from . ' to ' . $url_to );
			return;
		}
		
		$redirection->create_redirection_rule(
			'Old site (' . $batch . ')',
			$url_from,
			$url_to
		);

        $this->logger->info( 'Added redirect (' . $batch . '): ' . $url_from . ' to ' . $url_to );
	}

    /**
     * Generate a checksum hash for an object.
     *
     * @param object $object Object to generate checksum for.
     * @return string Checksum hash.
     */
    private function checksum_hash( $object ) {
        return hash( 'sha256', serialize( $object ) );
    }

	/**
	 * Logger setup
	 *
	 * @param string $caller Calling __FUNCTION__ name.
	 * @return void
	 */
	private function logger_set( $caller ) {
		$log_slug     = str_replace( __NAMESPACE__ . '\\', '', __CLASS__ ) . '_' . $caller;
		$this->logger = MultiLog::get_logger(
			$log_slug . '-multi',
			[
				CliLog::get_logger( $log_slug ),
				FileLog::get_logger( $log_slug ),
			] 
		);
	}
    
    /**
     * Validate (and write to log) if an existing item (post, term, or user) exists and then compare the checksum.
     * 
     * @param string $type Type of item to check (post, term, or user).
     * @param array $existing_ids Array of existing ID(s).
     * @param string $checksum Checksum for comparison.
     * @return bool True in all cases, except if existing_ids is empty.
     * */
    private function validate_has_existing( string $type, array $existing_ids, string $checksum ): bool {

        if ( empty( $existing_ids ) ) return false;

        // Warning/skip if multiple exist for this URL - this should not happen.
        if( count( $existing_ids ) > 1 ) {
            $this->logger->warning( 'Skip: Multiple already exist.' );
            return true;
        }
        
        // Compare already imported's checksum.
        $meta_checksum = null;
        switch( $type ) {
            case 'post':
                $meta_checksum = get_post_meta( $existing_ids[0], self::META_KEY_CHECKSUM, true );
                break;
            case 'term':
                $meta_checksum = get_term_meta( $existing_ids[0], self::META_KEY_CHECKSUM, true );
                break;
            case 'user':
                $meta_checksum = get_user_meta( $existing_ids[0], self::META_KEY_CHECKSUM, true );
                break;
        }
        
        if( $meta_checksum === $checksum ) {
            $this->logger->notice( 'Skip: Already exists.' );
            return true;
        }
    
        // So existing_ids is not empty, it's not greater than 1, and the checksum doesn't match.
        $this->logger->warning( 'Skip: Existing has a different checksum.' );
        return true;

    }

    /**
     * Validate and get the JSON item from the database.
     * 
     * @param string $type Type of item (post, term, or user).
     * @param int $db_id Database ID.
     * @return object JSON item.
     */
    private function validate_get_from_db( string $type, int $db_id ): object {

        $checksum = null;
        $json_item = null;
        
        // Verify db values.
        switch( $type ) {
            case 'post':
                $checksum  = get_post_meta( $db_id, self::META_KEY_CHECKSUM, true );
                $json_item = unserialize( get_post_meta( $db_id, self::META_KEY_JSON_ITEM, true ) );
                break;
            case 'term':
                $checksum  = get_term_meta( $db_id, self::META_KEY_CHECKSUM, true );
                $json_item = unserialize( get_term_meta( $db_id, self::META_KEY_JSON_ITEM, true ) );
                break;
            case 'user':
                $checksum  = get_user_meta( $db_id, self::META_KEY_CHECKSUM, true );
                $json_item = unserialize( get_user_meta( $db_id, self::META_KEY_JSON_ITEM, true ) );
                break;
        }

        if( $checksum !== $this->checksum_hash( $json_item ) ) {
            $this->logger->error( sprintf( 'Checksum mismatch for db ID %d', $db_id ) );
            exit();
        }

        return $json_item;

    }

    /**
     * Validate the result of inserting a term.
     * 
     * @param mixed $term_id_arr Insert term result.
     * @param object $json_item JSON item.
     * @return bool True if no errors, false otherwise.
     */
    private function validate_insert_term( mixed $term_id_arr, object $json_item ): bool {

        // Any possible error.
        if ( is_wp_error( $term_id_arr ) || ! is_array( $term_id_arr ) || empty( $term_id_arr ) || empty( $term_id_arr['term_id'] ) || ! ( $term_id_arr['term_id'] > 0 ) ) {

            $error_message = sprintf( 'Failed to insert term: %s %s', wp_json_encode( $term_id_arr ), wp_json_encode( $json_item ) );

            // For already exists, just log as a warning and examine in the output logs
            if ( is_wp_error( $term_id_arr ) && 'term_exists' === $term_id_arr->get_error_code() ) {
                $this->logger->warning( $error_message );
                return false;
            }
            
            // all other errors, exit.
            $this->logger->error( $error_message );
            exit();

        }

        return true;
    }

    /**
     * Validate the properties of JSON item.
     *
     * @param object $json_item JSON item to validate.
     */
    private function validate_json_item( object $json_item, array $expected_properties ): void {

        // Validate there aren't any added or missing properties in json_item not in expected.
        $additional_properties = array_diff_key( (array) $json_item, $expected_properties );
        $missing_properties    = array_diff_key( $expected_properties, (array) $json_item );
        if( ! empty( $additional_properties ) || ! empty( $missing_properties ) ) {
            $this->logger->error( sprintf( 'Possible additional properties: %s', implode( ', ', array_keys( $additional_properties ) ) ) );
            $this->logger->error( sprintf( 'Possible missing properties: %s', implode( ', ', array_keys( $missing_properties ) ) ) );
            exit();
        }

        // Validate each property of the JSON post object.
        foreach ( $json_item as $property => $value ) {

            $value_type = gettype( $value );

            // Type validation
            if ( $value_type !== $expected_properties[$property]['type'] ) {
                $this->logger->error( 'Type mis-match: ' . $property );
                exit();    
            }

            // Required.
            if ( isset( $expected_properties[$property]['required'] ) && $expected_properties[$property]['required'] ) {
            
                $has_value = false;
            
                if ( 'string' === $value_type && ! empty( trim( $value ) ) ) $has_value = true;
                else if ( 'array' === $value_type && ! empty( $value ) && ! empty( trim( reset( $value ) ) ) ) $has_value = true;
            
                if ( ! $has_value ) {
                    $this->logger->error( 'Required value is missing: ' . $property );
                    exit();                            
                }
            }

            // Validate
            if ( isset( $expected_properties[$property]['validate'] ) ) {
                if ( ! call_user_func( $expected_properties[$property]['validate'], $value ) ) {
                    $this->logger->error( 'Validation failed: ' . $property );
                    exit();                            
                }
            }

        }
    }

    /**
     * Validate dependencies.
     */
    private function validate_dependencies(): void {

        // Validate NMT function is callable.
        if ( ! is_callable( [ GuestContributorsHelper::class, 'create_by_display_name' ] ) ) {
            WP_CLI::error( 'Wrong NMT branch? Function not callable: GuestContributorsHelper::create_by_display_name()', true );
        }

        // Validate NMT function is callable.
        if ( ! is_callable( [ Attachments::class, 'import_external_file' ] ) ) {
            WP_CLI::error( 'Wrong NMT branch? Function not callable: Attachments::import_external_file()', true );
        }

        // Verify try_existing is the 9th parameter.
        $reflection_method = new \ReflectionMethod( Attachments::class, 'import_external_file' );
        $reflection_method_params = array_map( function( \ReflectionParameter $param ) { return $param->getName(); }, $reflection_method->getParameters() );
        if( ! isset( $reflection_method_params[8] ) || $reflection_method_params[8] !== 'try_existing' ) {
            WP_CLI::error( 'Wrong NMT branch? Function Attachments::import_external_file() should have 9th parameter: try_existing.', true );
        }
    
        // Newspack Plugin is required.
        if ( ! defined( '\Newspack\Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME' ) ) {
            WP_CLI::error( 'Newspack Plugin Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME not found.', true );
        }

        // CAP Plugin is required.
        if ( ! is_plugin_active( "co-authors-plus/co-authors-plus.php" ) ) {
            WP_CLI::error( 'Co-Authors Plus plugin not found. Install and activate it before using this command.', true );
        }
        
        // Simple Local Avatars is required..
        if ( ! is_plugin_active( "simple-local-avatars/simple-local-avatars.php" ) ) {
            WP_CLI::error( 'Simple Local Avatars plugin not found. Install and activate it before using this command.', true );
        }

        // Redirection plugin is required.
		if( ! class_exists ( '\Red_Item' ) ) {
			WP_CLI::error( 'Redirection plugin must be active.', true );
		}

        // Redirections tables must exist in db.
        global $wpdb;
        $wpdb->get_results( "SELECT 1 FROM {$wpdb->prefix}redirection_items" );
        if( !empty( $wpdb->last_error ) ) {
            WP_CLI::error( "Redirection plugin's setup is required ( db tables not found ).", true );
        }

        // Verify America/Detroit (eastern / utc-5 timezone):
        if( wp_timezone_string() !== self::STAGING_TIMEZONE ) {
            WP_CLI::error( 'WP-admin > settings > timezone must be set to: ' . self::STAGING_TIMEZONE, true );
        }

        // Verify permalink.
        if( get_option( 'permalink_structure' ) !== self::STAGING_PERMALINK ) {
            WP_CLI::error( 'WP-admin > settings > permalinks must be set to: ' . self::STAGING_PERMALINK, true );
        }

        // Jetpack is required.
        if ( ! is_plugin_active( "jetpack/jetpack.php" ) ) {
            WP_CLI::error( 'Jetpack plugin is required for slideshows.', true );
        }

        // Jetpack blocks (for Slideshow).
        if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'jetpack/slideshow' ) ) {
            WP_CLI::error( 'Jetpack Slideshow block is required. Turn on /wp-admin/admin.php?page=jetpack#writing -> Jetpack Blocks.', true );
        }
        
    }

    /**
     * Validate feed type.
     */
    private function validate_feed_type( array $pos_args ): void {
        if( empty( $pos_args ) || ! in_array( $pos_args[0], self::FEED_TYPES, true ) ) {
            WP_CLI::error( 'Positional argument must be one of: ' . implode( ', ', self::FEED_TYPES ), true );
        }
    }

}
