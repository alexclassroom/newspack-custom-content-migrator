<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Posts;
use Newspack\MigrationTools\Util\FgHelper;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;

// use Newspack\MigrationTools\Logic\GuestContributorsHelper;
use NewspackCustomContentMigrator\Command\PublisherSpecific\AmericaMagMigratorTempGC as GuestContributorsHelper;


class AmericaMagMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	const META_KEY_FEATURED_IMAGE_POSITION = 'newspack_featured_image_position';
	const META_KEY_PROFILE_POST_ID         = '_np_migration_profile_post_id';
	const META_KEY_OLD_POST_TYPE           = '_np_migration_old_post_type';
	const META_KEY_PROCESSED_CONTENT_TYPE  = '_np_migration_processed_content_type';

	/**
	 * Batch counts of imported nodes per type per CLI run.
	 *
	 * @var array
	 */
	private array $batch_counts;

	/**
	 * Batch max of imported nodes per type per CLI run.
	 * 
	 * Default to -1 so that 0 can be used to cause an "empty query" if needed.
	 *
	 * @var int
	 */
	private int $batch_max = -1;

	/**
	 * Custom Fields holds the related field definitions that are attached to nodes.
	 *
	 * @var array
	 */
	private array $custom_fields;

	/**
	 * FG Helper for simplification.
	 *
	 * @var FgHelper FG Helper instance.
	 */
	private FgHelper $fg_helper;
	
	/**
	 * Flag for importer to order descending.
	 *
	 * @var bool
	 */
	private bool $flag_order_desc = false;

	/**
	 * Flag for importer to set final data ( comments and redrects ).
	 *
	 * @var bool
	 */
	private bool $flag_set_final_data = false;

	/**
	 * Flag for importer to skip media.
	 *
	 * @var bool
	 */
	private bool $flag_skip_media = false;

	/**
	 * Logger
	 *
	 * @var MultiLog
	 */
	private $logger;

	/**
	 * Nodes to keep - lookup array.
	 *
	 * @var array
	 */
	private array $nodes_to_keep;

	/**
	 * Nodes only to process (override to defaults).
	 *
	 * @var array
	 */
	private array $nodes_only = [];

	/**
	 * Required wp-admin setting.
	 */
	private string $required_permalink = '/%category%/%year%/%monthnum%/%day%/%postname%/';
	private string $required_timezone  = 'America/New_York';

	/**
	 * CLI Commands
	 *
	 * @return void
	 */
	public static function register_commands(): void {

		WP_CLI::add_command(
			'newspack-content-migrator america-mag-co-authors',
			self::get_command_closure( 'cmd_co_authors' ),
			[
				'shortdesc' => 'Set co-authors per post.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator america-mag-content-types',
			self::get_command_closure( 'cmd_content_types' ),
			[
				'shortdesc' => 'Convert content types.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator america-mag-import',
			self::get_command_closure( 'cmd_import' ),
			[
				'shortdesc' => 'America Mag Importer',
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'batch-max',
						'description' => 'Max nodes to import. Integer.',
						'optional'    => true,
					],
					[
						'type'        => 'assoc',
						'name'        => 'nodes-only',
						'description' => 'Limited list of node types to import.',
						'optional'    => true,
					],
					[
						'type'        => 'flag',
						'name'        => 'order-desc',
						'description' => 'Import by order descending.',
						'optional'    => true,
					],
					[
						'type'        => 'flag',
						'name'        => 'set-final-data',
						'description' => 'FG only sets comments and redirects once. Do this once after everything is imported.',
						'optional'    => true,
					],
					[
						'type'        => 'flag',
						'name'        => 'skip-media',
						'description' => 'Skip media for faster testing.',
						'optional'    => true,
					],										
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator america-mag-profiles',
			self::get_command_closure( 'cmd_profiles' ),
			[
				'shortdesc' => 'America Mag Profiles (to guest contributors)',
			]
		);

	}

	/**
	 * Run co-authors per post.
	 */
	public function cmd_co_authors( array $pos_args, array $assoc_args ): void {

		$this->logger_set( __FUNCTION__ );
		$this->logger->info( 'Running command: ' . __FUNCTION__ );

		$this->validate_setup();

		global $coauthors_plus;

		// Loop through all posts.
		(new Posts())->throttled_posts_loop( 
			[], 
			function( $post ) use ( $coauthors_plus ) {
				
				$this->logger->info( '-- Post ID: ' . $post->ID );

				if ( $coauthors_plus->has_author_terms( $post->ID ) ) {
					$this->logger->notice( 'Authors already set.' );
					return;
				}

				// Migrated author list points to profile post type.
				$by_author = get_post_meta( $post->ID, 'by_author', true );
				if ( empty( $by_author ) ) {
					$this->logger->warning( 'Skip: No by_author value.' );
					return;
				}
				if ( ! is_array( $by_author ) ) {
					$this->logger->warning( 'Skip: by_author value is not array.' );
					return;
				}

				// The migration seemed to insert the same profile post id multiple times.
				// - keep order so "first" author is still "first" in byline.
				$by_author = array_unique( $by_author );

				// Match each profile post id to user meta id
				$co_authors = [];
				foreach( $by_author as $profile_post_id ) {

					$this->logger->info( 'Profile post id: ' . $profile_post_id );

					$users = get_users([
						'meta_key' => self::META_KEY_PROFILE_POST_ID,
   						'meta_value' => $profile_post_id,
						'fields' => 'ids',
					]);
					if ( empty( $users ) ) {
						$this->logger->warning( 'Skip: No user matched to meta.' );
						return;
					}
					if ( 1 !==  count( $users ) ) {
						$this->logger->warning( 'Skip: user match count <> 1.' );
						return;
					}

					// Get, print, and add user_id to co authors
					$user_id = reset( $users );
					$this->logger->info( 'User ID is: ' . $user_id );
					$co_authors[] = $user_id;
				}

				// Add co-authors to post
				$this->logger->info( 'co-authors (wp_users): ' . implode( ",", $co_authors ) );
				
				if ( ! $coauthors_plus->add_coauthors( $post->ID, $co_authors, false, 'id' ) ) {
					$this->logger->warning( 'Skip: unable to assign co-authors.' );
					return;
				}

			} // callback function
		); // throttled posts

		$this->logger->info( 'Done.' ); 
	}

	/**
	 * Convert content types.
	 */
	public function cmd_content_types( array $pos_args, array $assoc_args ): void {

		$this->logger_set( __FUNCTION__ );
		$this->logger->info( 'Running command: ' . __FUNCTION__ );

		$this->validate_setup();

		global $wpdb;

        do {

			// content types that have not been processed yet
            $posts = get_posts( [ 
				'post_type' => [ 'book_review', 'podcast' , 'video' ],
                'numberposts' => 10,
                'meta_query' => [
					[
						'key'     => self::META_KEY_PROCESSED_CONTENT_TYPE,
						'compare' => 'NOT EXISTS',
					],
				]
            ] );

            // Process items.
            foreach( $posts as $post ) {

                $this->logger->info( '------------ processing id: ' . $post->ID );

				$original_content_type = $post->post_type;
				$this->logger->info( 'original content type: ' . $original_content_type );

				$new_post_content = '';

				// Content types.
				switch ( $original_content_type ) {
					case 'book_review':
						$new_post_content = $this->convert_content_type_book_review( $post->ID, $post->post_content );
						break;
					case 'podcast':
						$new_post_content = $this->convert_content_type_podcast( $post->ID, $post->post_content );
						break;
					case 'video':
						$new_post_content = $this->convert_content_type_video( $post->ID, $post->post_content );
						break;
				}

				// error in sub function, skip.
				if( null === $new_post_content ) {
					update_post_meta( $post->ID, self::META_KEY_PROCESSED_CONTENT_TYPE, 'yes' );
					continue;
				}	

				// blank content could be OK.
				if( '' === $new_post_content ) {
					$this->logger->notice( 'Post content is blank.' );
				}

				// Don't use wp_update_post since that will update modified dates. But we still need to make
				// sure post_name is unique (since we're not using wp_update_post - which would done it for us).
				$new_post_name_unique = wp_unique_post_slug( $post->post_name, $post->ID, $post->post_status, 'post', 0 );

				if( $new_post_name_unique !== $post->post_name ) {
					$this->logger->notice( 'Post name was updated to be unique.' );
				}

				// Update to post type (with possibly new content) and unique post name.
				$wpdb->update(
					$wpdb->posts,
					[
						'post_type' => 'post',
						'post_content' => $new_post_content,
						'post_name' => $new_post_name_unique,
					],
					[
						'ID' => $post->ID,
					]
				);

				// Set to processed.
                update_post_meta( $post->ID, self::META_KEY_PROCESSED_CONTENT_TYPE, 'yes' );

				// Set old post type.
				update_post_meta( $post->ID, self::META_KEY_OLD_POST_TYPE, $original_content_type );

				$this->logger->info( '-- converted to post.' );

            } // foreach post in query.
            
        } while( ! empty( $posts ) ); // while posts to process.

		$this->logger->info( 'Done.' ); 
	}

	/**
	 * Run the import.
	 */
	public function cmd_import( array $pos_args, array $assoc_args ): void {

		$this->logger_set( __FUNCTION__ );
		$this->logger->info( 'Running command: ' . __FUNCTION__ );

		$this->validate_setup();

		if ( isset( $assoc_args['batch-max'] ) ) {
			if( ! preg_match( '/^\d+$/', $assoc_args['batch-max'] ) ) {
				$this->logger->error( 'Batch-max must be int 0 or greater.');
				exit();
			}
			$this->batch_max = (int) $assoc_args['batch-max'];
		}
		$this->logger->info( '--batch-max: ' . $this->batch_max );
		
		if ( isset( $assoc_args['nodes-only'] ) ) {
			if( ! preg_match( '/^[a-zA-Z_,]+$/', $assoc_args['nodes-only'] ) ) {
				$this->logger->error( 'Nodes only must be list.');
				exit();
			}
			$this->logger->info( '--nodes-only: ' . $assoc_args['nodes-only'] );
			$this->nodes_only = explode( ',', $assoc_args['nodes-only'] );
		}

		if ( isset( $assoc_args['order-desc'] ) )     $this->flag_order_desc     = true;
		if ( isset( $assoc_args['set-final-data'] ) ) $this->flag_set_final_data = true;
		if ( isset( $assoc_args['skip-media'] ) )     $this->flag_skip_media     = true;

		$this->logger->info( '--order-desc: ' . $this->flag_order_desc );
		$this->logger->info( '--set-final-data: ' . $this->flag_set_final_data );
		$this->logger->info( '--skip-media: ' . $this->flag_skip_media );

		// Setup FG plugin's filters.
		add_filter( 'fgd2wp_get_node_taxonomies_terms_sql',      [ $this, 'fgd2wp_get_node_taxonomies_terms_sql' ], 10, 5 );
		add_filter( 'fgd2wp_get_nodes_sql',                      [ $this, 'fgd2wp_get_nodes_sql' ], 10, 6 );
		add_filter( 'fgd2wp_map_acf_field_type',                 [ $this, 'fgd2wp_map_acf_field_type' ], 10, 3);
		add_filter( 'fgd2wp_map_taxonomy',                       [ $this, 'fgd2wp_map_taxonomy' ], 11, 3 );
		add_filter( 'fgd2wp_post_import_post',                   [ $this, 'fgd2wp_post_import_post' ], 10, 5 );
		add_action( 'fgd2wp_post_register_custom_fields',        [ $this, 'fgd2wp_post_register_custom_fields' ] );
		add_action( 'fgd2wp_post_set_node_taxonomies_relations', [ $this, 'fgd2wp_post_set_node_taxonomies_relations' ], 10, 3 );
		add_filter( 'fgd2wp_pre_insert_comment',                 [ $this, 'fgd2wp_pre_insert_comment' ], 10, 2 );
		add_filter( 'fgd2wp_pre_insert_post',                    [ $this, 'fgd2wp_pre_insert_post' ], 10, 2 );
		add_filter( 'fgd2wp_pre_insert_taxonomy_term',           [ $this, 'fgd2wp_pre_insert_taxonomy_term' ], 10, 3);
		add_filter( 'fgd2wp_pre_insert_user',                    [ $this, 'fgd2wp_pre_insert_user' ], 10, 3);

		// Premium filters. Note the extra "p" in hook name.
		add_action( 'fgd2wpp_post_add_user',             [ $this, 'fgd2wpp_post_add_user' ], 10, 2);
		add_filter( 'fgd2wpp_post_init_premium_options', [ $this, 'fgd2wpp_post_init_premium_options' ] );
	
		// Filter DB options.
		add_filter( "option_fgd2wp_options",             [ $this, 'option_fgd2wp_options' ], 11 );
		add_filter( "default_option_fgd2wp_options",     [ $this, 'option_fgd2wp_options' ], 11 );		
		
		// Call NMT's migrator with cms type. Contructor will verify that FG plugin is installed.
		$this->fg_helper = new FgHelper( 'drupal' );

		// Other checks that constructor doesn't check.
		if ( ! defined( 'NCCM_FG_MIGRATOR_PREFIX' ) ) {
			$this->logger->error( 'NCCM_FG_MIGRATOR_PREFIX is not defined in wp-config.php' );
			exit();
		}

		// Verify the FG Drupal "Entity Reference" add-on is active.
		if ( ! is_plugin_active( "fg-drupal-to-wp-premium-entityreference-module/fg-drupal-to-wp-entityreference.php" ) ) {
			$this->logger->error( 'FG Drupal Entity Refernce Add-on plugin not found. Install and activate it before using this class.' );
			exit();
		}
		
		// Do the import.
		$this->fg_helper->import( $pos_args, $assoc_args );

		// Done
		$this->logger->info( 'Done.' );
	}

	/**
	 * Run command Profiles to guest contributor.
	 * 			
	 * Note: profile post's post_name is imported from Drupal (it's not just the sanitized post_title).
	 * Instead it will match to the old_url in fg redirects. We could try to use this as the 
	 * "user_nicename" (url slug) so that redirect are easier, or we can re-sanitized the wordpress
	 * way. Hmm...we're doing to have to do redirects anyway, and wp_fg_redirects has the old_urls
	 * and the P2 Guest Contributors standization project recommends to have nice urls so that CAP
	 * co-authors taxonomies match to the user better, so let's build from the pretty post_title instead of 
	 * using the old post_name from drupal.
	 * 
	 */
	public function cmd_profiles( array $pos_args, array $assoc_args ): void {

		$this->logger_set( __FUNCTION__ );
		$this->logger->info( 'Running command: ' . __FUNCTION__ );

		$this->validate_setup();

		$simple_avatars = new \Simple_Local_Avatars();

		// Loop through all profile post type rows.
		(new Posts())->throttled_posts_loop( 
			[
				'post_type' => 'profile',
			], 
			function( $post ) use( $simple_avatars ) {
				
				$this->logger->info( '-- Profile Post ID: ' . $post->ID );

				// -- Check for existing user.

				$existing_check = new \WP_User_Query([
					'meta_key'   => self::META_KEY_PROFILE_POST_ID,
					'meta_value' => $post->ID
				]);

				if ( ! empty( $existing_check->get_results() ) ) {
					$this->logger->notice( 'Skip: Already migrated.' );
					return;
				}

				// -- New User.

				// todo
				WP_CLI::line( $post->post_title );
				WP_CLI::line( $post->post_name );
				// GuestContributorsHelper::create_by_display_name( $post->post_title, [], true );
				// user_nicename (see doc block) // how does this compare to wp santized post_title??
				// $userdata['description']   = $post->post_content;
				// $userdata['meta_input'] = [];
				// $userdata['meta_input'][ self::META_KEY_PROFILE_POST_ID ] = $post->ID;

				// Insert user with force since there can be multiple authors with the same display name.
				// $user_id = GuestContributorsHelper::create_by_display_name( $json_item->title, [ 'user_nicename' => str_replace( self::LIVE_AUTHOR_PATH, '', $json_item->url ) ], true );
				// if ( is_wp_error( $user_id ) ) {
				// 	$this->logger->error( sprintf( 'Failed to create Guest Contributor: %s', $user_id->get_error_message() ) );
				// 	exit();
				// }
				return;

				$this->logger->info( 'Inserted wp user id: ' . $user_id );

				// Simple Local Avatars.
				$thumbnail_id = get_post_meta( $post->ID, '_thumbnail_id', true );
				if ( is_numeric( $thumbnail_id ) && $thumbnail_id > 0 ) {
					$simple_avatars->assign_new_user_avatar( $thumbnail_id, $user_id );
					$this->logger->info( 'Avatar set to thumbnail_id: ' . $thumbnail_id ); 
				}

			} // callback function
		); // throttled posts

		$this->logger->info( 'Done.' ); 
	}

	/************************
	  BATCHING
	************************/

	/**
	 * Batch method to get key for counts.
	 *
	 * @param  string $content_type Node types: article, page, etc.
	 * @param  string $entity_type  Node, media, user, etc.
	 * @return string $batch_key    Array key. 
	 */
	private function batch_get_key( $content_type, $entity_type ) {
		$batch_key = $content_type . '---' . $entity_type;
		if ( !isset( $this->batch_counts[ $batch_key ] ) ) {
			$this->batch_counts[ $batch_key ] = 0;
		}
		return $batch_key;
	}

	/**
	 * Batch increment upon each insert.
	 */
	private function batch_increment( $content_type, $entity_type ) {
		$this->batch_counts[ $this->batch_get_key( $content_type, $entity_type ) ]++;
	}
	
	/**
	 * Batch stop when at or over max return true.
	 */
	private function batch_stop( $content_type, $entity_type ) {
		return ( $this->batch_counts[ $this->batch_get_key( $content_type, $entity_type ) ] >= $this->batch_max );
	}

	/************************************
	  CONTENT CONVERSIONS
	************************************/

	private function convert_content_type_book_review( $post_id, $post_content ) {

		$post_content = trim( $post_content );

		$placeholder = '[view:book_in_review]';

		// make sure post has placeholder.
		if( ! str_contains( $post_content, $placeholder ) ) {
			$this->logger->warning( 'Skip: book in review placeholder not found.' );
			return null; 
		}

		// get postmeta pointer to book post(s).
		$book_node_array = get_post_meta( $post_id, 'book_node' ); // could have multiple values.
		
		// sanity: has value(s)
		if( ! is_array( $book_node_array ) || empty( $book_node_array ) ) {
			$this->logger->warning( 'Skip: book_node is empty.' );
			return null; 
		}

		$html = '';

		foreach( $book_node_array as $book_post_id ) {

			$book_post = get_post( $book_post_id );

			// related book must be found otherwise this means the import didn't happen properly.
			if( ! is_object( $book_post ) || ! isset( $book_post->ID ) ) {
				$this->logger->warning( 'Skip: related book post not found.' );
				return null;
			}

			// image.
			$img_src = get_the_post_thumbnail_url( $book_post->ID );
			if( false === $img_src ) {
				$this->logger->warning( 'Skip: related book thumbnail not exists.' );
				return null;
			}

			// by author.
			$by_author = get_post_meta( $book_post->ID, 'book_author', true );

			// link.
			$a_href = '';
			$isbn = get_post_meta( $book_post->ID, 'isbn', true );
			if( ! empty( $isbn ) ) {
				$a_href = 'http://www.amazon.com/dp/' . $isbn . '?tag=americ01-20';
			} else {
				$a_href = 'http://www.amazon.com/s?index=books&field-title=' . urlencode( $book_post->post_title ) . '&field-author=' . urlencode( $by_author ). '&tag=americ01-20';
			}
			
			ob_start();
			?>
			<div class="np-migrated-view-book-in-review">
				<div>
					<a href="<?=$a_href?>" target="_blank"><img src="<?=$img_src?>" /></a>
				</div>
				<div>
					<a href="<?=$a_href?>" target="_blank"><?=$book_post->post_title?></a>
					<p>by <?=$by_author?></p>
					<p><?=$book_post->post_content?></p>
				</div>
			</div>
			<?php
			
			$html .= ob_get_clean();

		}

		if( empty( $html ) ) {
			$this->logger->warning( 'Skip: replacement html is blank.' );
			return null;
		}

		// Replace in content.
		return str_replace( $placeholder, $html, $post_content );
	
	}

	private function convert_content_type_podcast( $post_id, $post_content ) {

		$post_content = trim( $post_content );

		// don't replace if there is already content.
		if( ! empty( $post_content ) ) {
			$this->logger->warning( 'Skip: post content not empty.' );
			return null; 
		}

		// use the meta value for the post content.
		return trim( get_post_meta( $post_id, 'podcast_description', true ) );
	
	}

	private function convert_content_type_video( $post_id, $post_content ) {

		$post_content = trim( $post_content );

		// Check for video link:
		$video_url = trim( get_post_meta( $post_id, 'op_video_embed', true ) );

		if( ! empty( $video_url ) ) {

			// Make sure it's a link.
			if( ! preg_match( '#https?://#i', $video_url ) ) {
				$this->logger->warning( 'Skip: Video url not link: ' . $video_url );
				return null; 
			}
			
			// Prepend to content.
			$this->logger->info( 'Prepending video url: ' . $video_url );
			$post_content = $video_url . "\n\n" . $post_content;

			// Hide the featured image to just use the youtube video instead.
			update_post_meta( $post_id, self::META_KEY_FEATURED_IMAGE_POSITION, 'hidden' );

		}

		return $post_content;
	
	}

	/************************************
	  FG DRUPAL HOOKS (non-premium)
	************************************/

	/**
	 * FG hard codes 'categories' as the taxonomy lookup.  Change to 'channel' (primary) and 'sections' (secondary).
	 * 
	 * This hook is also important since it runs during post creation, otherwise no categories would be associated
	 * with the post during wp_insert_post which will result in 'uncategorized' being added. This hook will stop
	 * uncategorized being added to all the posts.
	 * 
	 */
	public function fgd2wp_get_node_taxonomies_terms_sql( $sql, $node_id, $entity_type, $taxonomy, $extra_cols ) {

		if( 'node' === $entity_type ) {
			$sql = str_replace( "AND t.vid = 'categories'", "AND t.vid IN( 'channel', 'sections' )", $sql );
		}

		return $sql;
	}

	/**
	 * FG Drupal get nodes sql.
	 *
	 * Use this filter to modify the sql query for the main import loop. When FG Drupal selects nodes to import
	 * this is the SQL that it runs. The default sql will get 10 nodes in ascending node id order where the 
	 * node ids are greater than the last previously imported node id. This sql will be run over-and-over again until
	 * there are no more nodes remaining to import. 
	 * 	
	 * When importing, it's easier to test and QA the content when importing the newest nodes first. The default sql
	 * will import the lower node ids first so this means the oldest articles, profiles, etc, will be imported before
	 * the newer ones. The following will change the SQL to import the newest content first.
	 * 
	 * Also, don't import drafts.
	 * 
	 * @param [type] $sql            Default sql.
	 * @param [type] $prefix         Database table prefix.
	 * @param [type] $last_drupal_id Last imported node id. Initial value: 0
	 * @param [type] $limit          Default limit of rows to select each batch: 10
	 * @param [type] $content_type   Node content type.
	 * @param [type] $entity_type    Node, media, user, taxonomy, etc.
	 * @return void
	 */
	public function fgd2wp_get_nodes_sql( $sql, $prefix, $last_drupal_id, $limit, $content_type, $entity_type ) {
		
		// Only for nodes and types.
		if ( 'node' !== $entity_type ) return $sql;
		if ( ! in_array( $content_type, $this->nodes_to_keep ) ) return $sql;
					
		// Remove drafts.
		$sql = str_replace( 'WHERE n.type = ', 'WHERE n.status <> 0 AND n.type = ', $sql );

		// Ordering.
		if ( $this->flag_order_desc ) {

			// Order by nid desc to force newest content first.
			$sql = str_replace( 'ORDER BY n.nid', 'ORDER BY n.nid DESC', $sql );

			// Where ids are less than the last imported id since we're doing the newest (largest) ids first.
			// But the first time this is called, the $last_drupal_id will be 0 so don't change the sql.
			if ( $last_drupal_id > 0 ) {
				$sql = str_replace( 'AND n.nid > ', 'AND n.nid < ', $sql );
			}

		}
		
		// Batching.
		if ( $this->batch_max >= 0 ) {

			// Stop at batch limit.
			if ( $this->batch_stop( $content_type, $entity_type ) ) {
				$sql = str_replace( 'LIMIT ' . $limit, 'LIMIT 0', $sql );
			}
			else {
				// To make debugging and batching easier, change the limit to just 1 row.
				$sql = str_replace( 'LIMIT ' . $limit, 'LIMIT 1', $sql );
			}

		}

		return $sql;
	}

	/**
	 * Adjust postmeta (acf types) if needed.
	 *
	 * @param string $acf_type
	 * @param string $field_type
	 * @param array $field
	 * @return $acf_type
	 */
	public function fgd2wp_map_acf_field_type( $acf_type, $field_type, $field ) {

		// Change "oembed" to just normal postmeta since Youtube links can't be imported as videos.
		if( $acf_type === 'oembed' && $field_type === 'video' ) {
			if( $field['node_type'] === 'video' && $field['table_name'] === 'node__field_op_video_embed' ) {
				return ''; // plain postmeta value
			}
		}
		
		return $acf_type;
	}

	/**
	 * FG Drupal map drupal-to-wordpress taxonomies.
	 */
	public function fgd2wp_map_taxonomy( $wp_taxonomy, $taxonomy ) {

		// Tell FG Drupal how to migrate taxonomies.
		switch ( strtolower( $taxonomy ) ) {
			case 'channel':
				$wp_taxonomy = 'category';
				break;
			case 'sections':
				$wp_taxonomy = 'category';
				break;
			case 'topics':
				$wp_taxonomy = 'post_tag';
				break;
		}

		return $wp_taxonomy;
	}

	/**
	 * FG Drupal after a "post" (this could be article, profile, etc) is inserted.
	 */
	public function fgd2wp_post_import_post( $new_post_id, $node, $content_type, $post_type, $entity_type ) {

		// Update batch count.
		$this->batch_increment( $content_type, $entity_type );

		// Print logging.
		$this->logger->info( 'fgd2wp_post_import_post (AFTER): ' . json_encode( array( 
			$new_post_id, $node, $content_type, $post_type, $entity_type,
		) ) );
	}

	/**
	 * FG Drupal after drupal custom fields are registered.
	 * 
	 * This filter will capture the custom fields into a lookup array for later use.
	 * An example is publication_date - this is a custom field in drupal that is needed
	 * before each post is inserted ( see fgd2wp_pre_insert_post below ).
	 *
	 * Format example: $this->custom_fields['node']['article']['publication_date']
	 * 
	 */
	public function fgd2wp_post_register_custom_fields( $custom_fields ) {
		$this->custom_fields = $custom_fields;
	}

	/**
	 * After post is inserted (and taxonomy relationships are added) set Yoast primary.
	 * 
	 * This hook runs after the post is created.  See hook above (fgd2wp_get_node_taxonomies_terms_sql) that runs
	 * before post is created.  That hook is important to cut down on all the 'uncategorized' being added to posts.
	 * 
	 * This hook runs after post is created so we have a $new_post_id for setting Yoast primary.
	 * 
	 */
	public function fgd2wp_post_set_node_taxonomies_relations( $new_post_id, $node, $node_terms ) {

		// Make sure just for articles to be safe.
		if( ! isset( $node['type'] ) || ! in_array( $node['type'], $this->nodes_to_keep ) ) return;

		// Look for primary taxonomy: "channel".
		foreach( $node_terms as $node_term ) {
			
			// Must be the primary taxonomy we want.
			if( ! isset( $node_term['taxonomy'] ) || 'channel' !== $node_term['taxonomy'] ) continue;

			// Verify id exists to be safe.
			if( ! isset( $node_term['tid'] ) ) continue;

			// Access the global FG Drupal Premium object (note the extra "p" in the name).
			global $fgd2wpp;
			
			// Convert tid to term_id. 
			if ( isset( $fgd2wpp->imported_taxonomies[ $node_term['tid'] ] ) ) {
				update_post_meta( $new_post_id, '_yoast_wpseo_primary_category', $fgd2wpp->imported_taxonomies[ $node_term['tid'] ] );
				$this->logger->info( 'Yoast primary set to term_id: ' . $fgd2wpp->imported_taxonomies[ $node_term['tid'] ] );
				$this->logger->info( 'Original term info: ' . json_encode( $node_term ) );
				return;
			}
		}

		$this->logger->notice( 'Did not set Yoast primary.' );

	}

	/**
	 * FG comments import can only run after everything is imported.  This is handled
	 * by the --set-final-data flag on the CLI.  But if the final data needs to run
	 * again (maybe an error occured), then this filter function will make sure already
	 * imported comments are not imported twice.
	 *
	 * @param array $data The WP comment object before imported.
	 * @param array $comment The drupal comment
	 * @return null|array Return null to stop the comment from being imported.
	 */
	public function fgd2wp_pre_insert_comment( $data, $comment ) {
		
		$comments = get_comments([
			'meta_query' => [
				[
					'key'     => '_fgd2wp_old_comment_id',
					'value'   => $comment['cid'],
					'compare' => '=',
				]
			]
		]);

		// if comment was already imported.
		if( ! empty( $comments ) ) { 
			return null;
		}

		return $data;
	}

	/**
	 * FG Drupal before inserting a post. Use this to make adjustments to a post prior to insertion.
	 * 
	 * Do not convert content_types to "post"! Do not change the post_type during FG migaration!
	 * Nor use 'fgd2wp_map_post_type' either because the needed post_meta (relationships) will not be imported for
	 * the content_type. Example: 'book_review' will not get the book_node relationship in the postmeta.
	 * Only change the post_type after FG migration.
	 *
	 * @param  array $new_post The new post array prior to insertion.
	 * @param  array $node     The drupal node being migrated into new post.
	 * @return array            The modified $new_post array.
	 */
	public function fgd2wp_pre_insert_post( $new_post, $node ) {
	
		// Logging before post is inserted.
		$this->logger->info( 'fgd2wp_pre_insert_post (BEFORE): ' . json_encode( array( 
			'nid'     => $node['nid'] ?? '',
			'title'   => $node['title'] ?? '',
			'type'    => $node['type'] ?? '',
			'created' => $node['created'] ?? '',			
		) ) );

		// Only do this for types have have Publication Date.
		if ( ! in_array( $node['type'], [ 'article', 'book_review', 'podcast', 'the_word', 'video' ] ) ) return $new_post;
	
		// Verify the custom field key exists.
		if ( empty( $this->custom_fields['node'][ $node['type'] ]['publication_date'] ) ) {
			$this->logger->warning( 'Missing custom field for publication_date.' );
			return $new_post;
		}
		
		// Access the global FG Drupal Premium object (note the extra "p" in the name) to get the value.
		global $fgd2wpp;
		$pub_date_arr = $fgd2wpp->get_node_custom_field_values( $node, $this->custom_fields['node'][ $node['type'] ]['publication_date'] );

		// Verify value.
		if ( 1 !== count( $pub_date_arr )
			|| empty( $pub_date_arr[0]['field_publication_date_value'] )
			|| false === strtotime( $pub_date_arr[0]['field_publication_date_value'] )
		) {
			$this->logger->warning( 'Custom field for publication_date is not valid date.' );
			return $new_post;
		}

		// Set new_post to use the publication date from field_publication_date_value (which is GMT).		
		$new_post['post_date'] = get_date_from_gmt( $pub_date_arr[0]['field_publication_date_value'] );
		$new_post['post_date_gmt'] = $pub_date_arr[0]['field_publication_date_value'];
	
		return $new_post;	
	}

	/**
	 * FG Drupal before inserting a taxonomy term.
	 * 
	 * FG Drupal by default will sanitize taxonomy titles into slugs by removing "-" dashes.
	 * Use the following to keep the existing taxonomy slugs.
	 *
	 */
	public function fgd2wp_pre_insert_taxonomy_term( $args, $term, $wp_taxonomy ) {

		if( ! in_array( $wp_taxonomy, [ 'category', 'post_tag' ], true ) ) {
			return $args;
		}

		// Get the alias from drupal.
		global $fgd2wpp;
		$prefix = $this->fg_helper->get_import_tables_prefix();
		$term_tid = (int) $term['tid'];
		$sql = "
			SELECT alias
			FROM {$prefix}path_alias
			WHERE path LIKE '/taxonomy/term/{$term_tid}'
			AND status = 1 AND langcode IN( 'und', 'en' )
			ORDER BY revision_id DESC
			LIMIT 1
		";
		$result = $fgd2wpp->drupal_query( $sql );

		// Verify alias was found.
		if( empty( $result ) ) {
			$this->logger->warning( 'Taxonomy slug alias not found: ' . json_encode( $term ) );
			return $args;
		}

		// Get the end of the url after last "/".
		$row = end( $result );
		$args['slug'] = basename( $row['alias'] );

		return $args;
	}

	/**
	 * Prior to inserting a user, do some clean up to make sure these users can't login without some
	 * sort of by-hand approval.
	 */
	public function fgd2wp_pre_insert_user( $userdata, $name, $email) {
		
		// Make sure unique password.
		$userdata['user_pass']  = wp_generate_password( 24 );

		// Make sure an email address we control.
		$userdata['user_email'] = str_replace( '@', '--at--', $email ) . '@example.com';

		return $userdata;
	}

	/************************************
	  FG DRUPAL HOOKS (premium)
	************************************/

	/**
	 * After user is insterted, don't allow old drupal password login.
	 */
	public function fgd2wpp_post_add_user( $new_user_id, $user ) {
		// delete the old drupal pass user meta.
		delete_user_meta( $new_user_id, 'drupalpass' );
	}

	/**
	 * FG Drupal after premium options are initialized.
	 * 
	 * Use this to change premuim options in code instead of wp-admin > Tools > Import > Drupal settings.
	 *
	 */
	public function fgd2wpp_post_init_premium_options( $premium_options ) {

		// Override default values from file:
		// fg-drupal-to-wp-premium/admin/class-fg-drupal-to-wp-premium-admin.php
		// FG_Drupal_to_WordPress_Premium_Admin->set_premium_options()

		// Only import authors.
		$premium_options['only_authors'] = true;

		// By default FG drupal will migrate all core and custom node types.
		// The core node types are 'article', 'page', 'post', 'story'
		// To skip a core or custom node type add to the following array. 
		// Note: if using hook fgd2wp_get_node_types it is possible to use that filter to skip
		// custom nodes (but not core nodes), so this list below is better.
		// to get node types with content: select distinct type from node order by type;
		// to get all node types from config:  select name from config where name like 'node.type.%' order by name;
		$premium_options['nodes_to_skip']  = [ 
			'america_special_topics', // skip, replace by hand to listings.
			'app_america_today_curated_articl', // only 1.
			'app_reels', // only 4
			'audio_news_update', // no longer used.
			'audio_prayer', // never activaly used.
			'global_module_configuration', // no longer used.
			'modular_page', // no longer used.
			'page', // rebuild by hand.
			'photo_gallery', // only 26.
			'press_release', // only 9.
			'subscription_offer', //no longer used.
			'webform_page', // no longer used.
			'who_we_are_page', // not activaly used.
	   ];
		
		// store the keep nodes in local lookup array.
		$this->nodes_to_keep = [
			 'article',
			 'book',
			 'book_review',
			 'issue',
			 'lectionary_date', // for app usage...keep/review content.
			 'podcast',
			 'profile', // authors
			 'sponsorship', // only 2, but keep them for sponsor->post reference.
			 'the_word',
			 'video',
		];

		// If CLI argument for nodes-only is being used, then skip all except for CLI list.
		if( ! empty( $this->nodes_only ) ) {
			
			// put all into nodes to skip.
			$premium_options['nodes_to_skip'] = array_merge( $premium_options['nodes_to_skip'], $this->nodes_to_keep );
			
			// clear nodes to keep.
			$this->nodes_to_keep = []; 
			
			// rebuild lists.
			foreach( $this->nodes_only as $node_only ) {

				// Make sure it's a real node type.
				if( ! in_array( $node_only, $premium_options['nodes_to_skip'] ) ) continue;

				// Remove.
			    unset( $premium_options['nodes_to_skip'][ array_search( $node_only, $premium_options['nodes_to_skip'] ) ]);

				// Add.
				$this->nodes_to_keep[] = $node_only;
			}
		}

		$premium_options['skip_blocks']    = true; // sidebar widgets
		$premium_options['skip_menus']     = true;

		// FG will only set redirects and comments once (wp_options: fgd2wp_last_comment_id / fgd2wp_last_drupal_url_id)
		// only run these after importer is done.
		$premium_options['skip_comments']  = true;
		$premium_options['skip_redirects'] = true;

		// Allow the redirects to be added.
		if( $this->flag_set_final_data ) {
			
			// Reset the counters so new content can be imported (if final data is being run again).
			update_option('fgd2wp_last_comment_id', 0); // uses fgd2wp_pre_insert_comment (above) for uniqueness.
			update_option('fgd2wp_last_drupal_url_id', 0); // uses "INSERT IGNORE" into wp_fg_redirects.
			
			// Allow import.
			$premium_options['skip_comments']  = false;
			$premium_options['skip_redirects'] = false;

		}
				
		// @todo: Launch: move redirects to Redirection plugin or turn on FG's redirect mechanism.
		$premium_options['url_redirect']   = false;

		return $premium_options;
	}

	/************************************
	  LOGGING
	************************************/

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

	/************************************
	  WP HOOKS
	************************************/

	/**
	 * Filter the options for the FG plugin (after FgHelper).
	 * 
	 * @param  array|false $options The options array to filter or boolean false if database option doesn't exist.
	 * @return array                The filtered options.
	 */
	public function option_fgd2wp_options( array|false $options ): array {
		
		// For when options don't exist yet in the db.
		if( false === $options ) $options = [];

		// Override default values from file:
		// fg-drupal-to-wp-premium/admin/class-fg-drupal-to-wp-admin.php
		// FG_Drupal_to_WordPress_Admin->set_plugin_options();

		// For testing only.
		if( $this->flag_skip_media ) {
			$options['skip_media'] = 1;
		}

		// Keep default: 'force_media_import' => 0 so that already downloaded images aren't fetched again from Live site.
		$options['force_media_import'] = 0;

		// @todo Should this go into Publisher specific migrator instead?
		$options['summary'] = 'in_excerpt'; // otherwise excerpt will go in top of content with <!--more--> link

		// @todo should we turn this on for images with the same filenames?
		// how are these store in drupal? in wordpress the same filename could be used if in different /year/mon/ folders...
		// but what about if the import was restarted...will images be fetched again and given unique -abc at the end?
		// import_duplicates = 1;

		return $options;
	}

	/************************************
	  VALIDATIONS
	************************************/

	private function validate_setup() {

		// Verify America/New_York (eastern / utc-4 timezone):
		if( wp_timezone_string() !== $this->required_timezone ) {
			$this->logger->error( 'WP-admin > settings > timezone must be set to: ' . $this->required_timezone );
			exit();
		}

        // Verify permalink.
        if( get_option( 'permalink_structure' ) !== $this->required_permalink ) {
            $this->logger->error( 'WP-admin > settings > permalinks must be set to: ' . $this->required_permalink );
			exit();
        }

		// Newspack Plugin is required.
		if ( ! defined( '\Newspack\Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME' ) ) {
			$this->logger->error( 'Newspack Plugin Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME not found.' );
			exit();
		}

		// CAP Plugin is required.
		if ( ! is_plugin_active( "co-authors-plus/co-authors-plus.php" ) ) {
			$this->logger->error( 'Co-Authors Plus plugin not found. Install and activate it before using this command.' );
			exit();
		}

		// Simple Local Avatars is required..
		if ( ! is_plugin_active( "simple-local-avatars/simple-local-avatars.php" ) ) {
			$this->logger->error( 'Simple Local Avatars plugin not found. Install and activate it before using this command.' );
			exit();
		}

		// Yoast is required..
		if ( ! is_plugin_active( "wordpress-seo/wp-seo.php" ) ) {
			$this->logger->error( 'Yoast (wordpress-seo) plugin not found. Install and activate it before using this command.' );
			exit();
		}

		// ACF PRO
		if( ! defined('ACF_PRO') ) {
			$this->logger->error( 'ACF PRO plugin not found. Install and activate it before using this command.' );
			exit();
		}

	}

}