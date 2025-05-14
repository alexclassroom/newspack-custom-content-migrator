<?php
/**
 * Importer for Bailiwick sites.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use Newspack\MigrationTools\Logic\Taxonomy;
use Newspack\MigrationTools\Logic\CoAuthorsPlusHelper;
use Newspack\MigrationTools\Logic\Posts;
use WP_CLI;

class TimesOfSanDiegoMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * Taxonomy logic.
	 *
	 * @var Taxonomy $taxonomy Taxonomy logic.
	 */
	private Taxonomy $taxonomy;
	
	/**
	 * Posts logic.
	 *
	 * @var Posts $posts Posts logic.
	 */
	private Posts $posts;

	/**
	 * CoAuthorsPlusHelper.
	 *
	 * @var CoAuthorsPlusHelper $coauthors_plus_helper CoAuthorsPlusHelper.
	 */
	private CoAuthorsPlusHelper $coauthors_plus_helper;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->taxonomy              = new Taxonomy();
		$this->posts                 = new Posts();
		$this->coauthors_plus_helper = new CoAuthorsPlusHelper();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {

		WP_CLI::add_command(
			'newspack-content-migrator times-of-san-diego migrate-gas',
			self::get_command_closure( 'cmd_migrate_gas' ),
			[
				'shortdesc' => 'Migrate Guest Authors.',
				'synopsis'  => [
					[
						'type'     => 'flag',
						'name'     => 'dry-run',
						'optional' => true,
					],
					[
						'type'     => 'assoc',
						'name'     => 'live-table-prefix',
						'optional' => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'content-diff__imported-post-ids',
						'description' => 'Path to the content-diff__imported-post-ids.log file.',
						'optional'    => false,
					],
				],
			]
		);
	}

	/**
	 * Callback for the `bw-download-xml` command.
	 *
	 * Downloads XML files for a given date range.
	 *
	 * @param array $pos_args   Positional arguments from WP_CLI.
	 * @param array $assoc_args Associative arguments from WP_CLI.
	 *
	 * @throws \Exception If something goes wrong.
	 */
	public function cmd_migrate_gas( array $pos_args, array $assoc_args ): void {
		$dry_run               = $assoc_args['dry-run'] ?? false;
		$imported_post_ids_log = $assoc_args['content-diff__imported-post-ids'];
		$live_table_prefix     = $assoc_args['live-table-prefix'] ?? '';
		if ( empty( $live_table_prefix ) ) {
			WP_CLI::error( 'live-table-prefix is required' );
		}
		
		global $wpdb;

		// Read imported_post_ids_log file created by CDiff import.
		$imported_post_ids = [];
		$file              = file_get_contents( $imported_post_ids_log ); // phpcs:ignore -- WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		if ( false === $file ) {
			echo( "Failed to read imported post IDs log file.\n" );
			exit( 1 );
		}
		$lines = explode( "\n", $file );
		foreach ( $lines as $line ) {
			$ids = json_decode( $line, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				continue;
			}
			if ( 'post' !== $ids['post_type'] ) {
				continue;
			}
			$imported_post_ids[ $ids['id_old'] ] = $ids['id_new'];
		}
		if ( empty( $imported_post_ids ) ) {
			echo( "No imported posts found.\n" );
			exit( 1 );
		}
		
		// Loop over imported post IDs and migrate Co-Authors.
		$live_table_prefix = esc_sql( $live_table_prefix );
		$i                 = -1;
		foreach ( $imported_post_ids as $post_id_old => $post_id_new ) {
			++$i;
			echo( sprintf( "(%d)/(%d) old post ID %d\n", esc_attr( $i + 1 ), esc_attr( count( $imported_post_ids ) ), esc_attr( $post_id_old ) ) );

			// Get Co-Authors assigned to post.
			$ga_terms = $this->get_guest_author_terms_for_post( $live_table_prefix, (int) $post_id_old );
			if ( empty( $ga_terms ) ) {
				// Nothing to do, no Co-Authors assigned to post. Regular wp_posts.post_author is used for author.
				echo( "No Co-Authors assigned, skipping.\n" );
				continue;
			}

			// Co-Authors author terms can represent either 1) GA objects, or 2) WP_User objects.
			$authors = [];
			foreach ( $ga_terms as $ga_term ) {
				
				// 1) Check if this term represents a GA object -- where wp_posts.post_name = term.slug.
				$guest_author_display_name = $wpdb->get_var( $wpdb->prepare( "select post_title from wp_posts where post_name = %s and post_type = 'guest-author'", $ga_term['slug'] ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( ! is_null( $guest_author_display_name ) ) {
					// Get GA object by display name.
					$ga = $this->coauthors_plus_helper->get_guest_author_by_display_name( $guest_author_display_name );
					if ( is_null( $ga ) || ! is_object( $ga ) ) {
						echo( sprintf( "ERROR: getting guest author by display name:'%s'\n", esc_html( $guest_author_display_name ) ) );
						exit( 1 );
					}
	
					// Found GA.
					$authors[] = $ga;
					continue;
				} 

				// 2) Check if this term represents a WP_User object -- where wp_users.user_login = term.name.
				$user_id = $wpdb->get_var( $wpdb->prepare( 'select ID from wp_users where user_login = %s', $ga_term['name'] ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( ! is_null( $user_id ) ) {
					// Get WP_User object by ID.
					$user = get_user_by( 'id', $user_id );
					if ( is_null( $user ) || ! is_object( $user ) ) {
						echo( sprintf( "ERROR: getting user by ID: '%s'\n", esc_attr( $user_id ) ) );
						exit( 1 );
					}

					// Found WP_User.
					$authors[] = $user;
					continue;
				}

				// No user found from term, that's an error.
				echo( sprintf( "ERROR: No user found for old post ID:%d guest-author term_id:%d term_name:%s\n", esc_attr( $post_id_old ), esc_attr( $ga_term['term_id'] ), esc_html( $ga_term['name'] ) ) );
				exit( 1 );
			}

			// Assign authors to imported post.
			try {
				if ( ! $dry_run ) {
					$this->coauthors_plus_helper->assign_authors_to_post( $authors, $post_id_new );
				}
				echo( sprintf( "Assigned %d authors to post ID %d\n", count( $authors ), esc_attr( $post_id_new ) ) );
			} catch ( \Exception $e ) {
				echo( sprintf( "ERROR assigning authors to post ID:%d, error:'%s'. Debug authors:%s\n", esc_attr( $post_id_new ), esc_html( $e->getMessage() ), print_r( $authors, true ) ) ); // phpcs:ignore -- WordPress.PHP.DevelopmentFunctions.error_log_print_r
				continue;
			}       
		}

		wp_cache_flush();
		echo( "Done.\n" );
	}

	/**
	 * Get guest author terms for a post.
	 *
	 * @param string $table_prefix The table prefix.
	 * @param int    $post_id      The post ID.
	 * @return array The guest author terms, result from wpdb->get_results.
	 */
	public function get_guest_author_terms_for_post( string $table_prefix, int $post_id ): array {
		global $wpdb;

		$table_prefix = esc_sql( $table_prefix );

		// phpcs:disable -- WordPress.DB.PreparedSQL.NotPrepared, statement is prepared and params are sanitized.
		$ga_terms = $wpdb->get_results(
			$wpdb->prepare(
				"select t.*
				from {$table_prefix}term_relationships tr
				join {$table_prefix}term_taxonomy tt
					on tt.term_taxonomy_id = tr.term_taxonomy_id
					and tt.taxonomy = 'author'
				join {$table_prefix}terms t
					on t.term_id = tt.term_id
				where tr.object_id in ( %d )
				order by tr.term_order;",
				$post_id 
			),
			ARRAY_A
		);
		// phpcs:enable
		
		return $ga_terms;
	}
}
