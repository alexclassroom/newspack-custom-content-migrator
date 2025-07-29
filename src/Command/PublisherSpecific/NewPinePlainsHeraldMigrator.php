<?php
/**
 * Importer for New Pine Plains Herald.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Posts;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use Newspack\MigrationTools\Logic\UsersHelper;
use Newspack\MigrationTools\Logic\CoAuthorsPlusHelper;
use Newspack\Guest_Contributor_Role;
use WP_CLI;

class NewPinePlainsHeraldMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;
	
	/**
	 * Temp dev debug replaced authors.
	 *
	 * @var array $debug_replaced_authors Debug replaced authors.
	 */
	private $debug_replaced_authors = [];

	/**
	 * Posts logic.
	 *
	 * @var Posts $posts Posts logic.
	 */
	private Posts $posts;
	
	/**
	 * Users helper.
	 *
	 * @var UsersHelper $users_helper Users helper.
	 */
	private UsersHelper $users_helper;
	
	/**
	 * Coauthors plus helper.
	 *
	 * @var CoAuthorsPlusHelper $coauthors_plus_helper Coauthors plus helper.
	 */
	private CoAuthorsPlusHelper $coauthors_plus_helper;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->posts                 = new Posts();
		$this->users_helper          = new UsersHelper();
		$this->coauthors_plus_helper = new CoAuthorsPlusHelper();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {

		WP_CLI::add_command(
			'newspack-content-migrator newpineplainsherald bylines-to-authors',
			self::get_command_closure( 'cmd_bylines_to_authors' ),
			[
				'shortdesc' => 'Sets authors from byline meta.',
			]
		);
	}

	/**
	 * Callback for the `newpineplainsherald bylines-to-authors` command.
	 *
	 * Sets authors from byline meta.
	 *
	 * @param array $pos_args   Positional arguments from WP_CLI.
	 * @param array $assoc_args Associative arguments from WP_CLI.
	 */
	public function cmd_bylines_to_authors( array $pos_args, array $assoc_args ): void {
		global $wpdb;

		// Get bylines meta.
		$byline_results = $this->get_byline_meta();
		if ( ! $byline_results ) {
			\WP_CLI::error( 'No bylines found.' );
			return;
		}

		// QC data.
		$qc_post_bylines           = [];
		$qc_byline_to_author_names = [];

		// Loop posts and get author names from byline metas.
		foreach ( $byline_results as $key_byline_result => $byline_result ) {
			
			// Get post ID.
			$post_id = $byline_result['post_id'];
			if ( ! $post_id ) {
				WP_CLI::error( sprintf( 'Post ID is empty for byline meta %d.', $byline_result['meta_id'] ) );
			}

			// Progress.
			WP_CLI::line( sprintf( '(%d/%d) ID %d', $key_byline_result + 1, count( $byline_results ), $post_id ) );
			
			// Check post type.
			$post_type = $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $post_id ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching.
			if ( 'oht_article' !== $post_type ) {
				WP_CLI::error( sprintf( 'Post %d is not an oht_article.', $post_id ) );
			}

			/**
			 * Get bylines.
			 * 
			 * Some byline metas are arrays with multiple elements containing author names, e.g.:
			 *      a:2:{i:0;s:15:"Murphy Birdsall";i:1;s:11:"Sara McGhee";}
			 * while others are arrays with single element containing a byline string which needs to be parsed, e.g.:
			 *      a:1:{i:0;s:36:"By Mary Jenkins and Peter Klebnikov ";}
			 */  
			$bylines = unserialize( $byline_result['meta_value'] ); // phpcs:ignore -- WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize.

			// Parse byline strings.
			$post_author_names = [];
			foreach ( $bylines as $byline ) {
				// Warn if byline is empty.
				if ( empty( $byline ) ) {
					WP_CLI::warning( sprintf( 'WARNING: Empty byline for post %d.', $post_id ) );
					continue;
				}

				$byline_author_names = $this->get_author_names_from_byline( $byline );
				if ( empty( $byline_author_names ) ) {
					WP_CLI::warning( sprintf( "WARNING: No author names parsed for post ID %d, byline: '%s.'", $post_id, $byline ) );
					continue;
				}

				// Save QC data -- bylines parsed to author names.
				if ( ! isset( $qc_byline_to_author_names[ $byline ] ) ) {
					$qc_byline_to_author_names[ $byline ] = $byline_author_names;
				}
				
				$post_author_names = array_merge(
					$post_author_names,
					$byline_author_names
				);
			}
			
			// Save QC data -- post IDs, bylines.
			$qc_post_bylines[ $post_id ] = $bylines;

			// Assign post authors.
			if ( 0 === count( $post_author_names ) ) {
				WP_CLI::warning( sprintf( 'WARNING: No author names found for post %d, byline: %s.', $post_id, $byline ) );
			} else {
				$this->assign_authors_to_post( $post_id, $post_author_names );
			}
		}

		// LOG QC data -- bylines parsed to author names.
		$file = 'qc_bylines_to_author_names.csv';
		if ( file_exists( $file ) ) {
			unlink( $file ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink.
		}
		
		$fp = fopen( $file, 'w' ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink.
		fputcsv( $fp, [ 'Byline', 'Count parsed names', 'Parsed names' ] ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv.
		foreach ( $qc_byline_to_author_names as $byline => $author_names ) {
			fputcsv( // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv.
				$fp,
				[
					'"' . $byline . '"',
					count( $author_names ),
					implode( "\n", array_map( fn( $name ) => '"' . $name . '"', $author_names ) ),
				] 
			);
		}
		fclose( $fp );

		// LOG QC data -- post IDs, original bylines, actual author assignments.
		$file = 'qc_post_bylines_to_author_names.csv';
		if ( file_exists( $file ) ) {
			unlink( $file ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink.
		}
		
		$fp = fopen( $file, 'w' ); // phpcs:ignore -- WordPress.WP.AlternativeFunctions.file_system_operations_fopen.
		fputcsv( $fp, [ 'Post ID', 'Bylines', 'Count bylines', 'Parsed Author names', 'Count parsed names', 'Assigned authors', 'Count assigned authors' ] ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv.
		foreach ( $qc_post_bylines as $post_id => $bylines ) {
			$parsed_author_names = [];
			foreach ( $bylines as $byline ) {
				$parsed_author_names = array_merge( $parsed_author_names, $qc_byline_to_author_names[ $byline ] );
			}

			$assigned_authors_names = [];
			$assigned_authors       = $this->coauthors_plus_helper->get_all_authors_for_post( $post_id );
			foreach ( $assigned_authors as $assigned_author ) {
				$assigned_authors_names[] = $assigned_author->display_name;
			}

			fputcsv( // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv.
				$fp,
				[
					$post_id,
					implode( "\n", array_map( fn( $byline ) => '"' . $byline . '"', $bylines ) ),
					count( $bylines ),
					implode( "\n", array_map( fn( $name ) => '"' . $name . '"', $parsed_author_names ) ),
					count( $parsed_author_names ),
					implode( "\n", array_map( fn( $name ) => '"' . $name . '"', $assigned_authors_names ) ),
					count( $assigned_authors_names ),
				] 
			);
		}
		fclose( $fp );
	}

	/**
	 * Get and deduplicate byline meta data.
	 * 
	 * @return array|false Array of byline meta data result from $wpdb->get_results with ARRAY_A, or false if none found.
	 */
	private function get_byline_meta() {
		global $wpdb;

		// Get bylines meta.
		$byline_results = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'oht_article_byline'", ARRAY_A ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching.
		if ( ! $byline_results ) {
			return false;
		}

		/**
		 * Byline postmetas may be duplicated (due to how two wp imports were used per XML file -- as recommended by AI, now needs cleaning up -- lesson learned !).
		 * Filter out just one per post_id, and report error if any of the duplicate is different.
		 */ 
		$byline_results_deduped = [];
		foreach ( $byline_results as $byline_result ) {
			if ( isset( $byline_results_deduped[ $byline_result['post_id'] ] )
				&& $byline_result['meta_value'] !== $byline_results_deduped[ $byline_result['post_id'] ]
			) {
				WP_CLI::error( sprintf( "Duplicate byline meta for post ID %d: '%s' vs '%s'.", $byline_result['post_id'], $byline_result['meta_value'], $byline_results_deduped[ $byline_result['post_id'] ] ) );
			}
			$byline_results_deduped[] = $byline_result;
		}

		return $byline_results_deduped;
	}

	/**
	 * Assigns either single WP post_author or coauthors to a post.
	 * 
	 * @param int   $post_id Post ID.
	 * @param array $author_names Author names.
	 * @return void
	 */
	private function assign_authors_to_post( int $post_id, array $author_names ): void {
		global $wpdb;

		// Get post.
		$post = get_post( $post_id );
		if ( ! $post ) {
			WP_CLI::error( sprintf( 'Post %d not found.', $post_id ) );
		}

		// Assign authors to post.
		if ( 1 === count( $author_names ) ) {
			/**
			 * Assign single wp_posts.post_author.
			 */
			$existing_user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE display_name = %s", $author_names[0] ) ); // phpcs:ignore -- WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users.
			if ( 1 === count( $existing_user_ids ) ) {
				$user = get_user_by( 'ID', $existing_user_ids[0] );
			} elseif ( count( $existing_user_ids ) > 1 ) {
				// Not expected within this specific migration, check for duplicates.
				WP_CLI::error( sprintf( "Multiple users found with same display name '%s'.", $author_names[0] ) );
			} else {
				$user = $this->users_helper->create_or_get_user(
					[
						'display_name' => $author_names[0],
						'role'         => Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME,
					],
					$author_names[0] 
				);
			}
			$updated = $wpdb->update( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching.
				$wpdb->posts,
				[ 'post_author' => $user->ID ],
				[ 'ID' => $post_id ]
			);
			if ( false === $updated ) {
				WP_CLI::error( sprintf( "Failed to assign author '%s' to post %d.", $author_names[0], $post_id ) );
			}

			// Delete any coauthors assigned to the post.
			$this->coauthors_plus_helper->unassign_all_guest_authors_from_post( $post_id );
		} else {
			/**
			 * Assign multiple coauthors.
			 */
			// Make sure the users exist.
			$coauthors = [];
			foreach ( $author_names as $author_name ) {
				$existing_user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE display_name = %s", $author_name ) ); // phpcs:ignore --WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users, WordPress.DB.DirectDatabaseQuery.NoCaching.
				if ( 1 === count( $existing_user_ids ) ) {
					$user = get_user_by( 'ID', $existing_user_ids[0] );
				} elseif ( count( $existing_user_ids ) > 1 ) {
					// Not expected within this specific migration, check for duplicates.
					WP_CLI::error( sprintf( "Multiple users found with same display name '%s'.", $author_name ) );
				} else {
					$user = $this->users_helper->create_or_get_user( [ 'display_name' => $author_name ], $author_name );
				}
				$coauthors[] = $user;
			}

			$this->coauthors_plus_helper->assign_authors_to_post( $coauthors, $post_id );
		}
	}

	/**
	 * Get author names from byline.
	 * Recursively explodes by separators and trims each individual part/name.
	 * 
	 * @param string $byline Byline.
	 * @return string[]      Exploded and trimmed author names from byline.
	 */
	private function get_author_names_from_byline( string $byline ): array {
		// If empty return empty array.
		if ( empty( $byline ) ) {
			return [];
		}

		// Hardcoded array of polluting characters found in byline metas.
		// phpcs:disable -- Squiz.PHP.CommentedOutCode.Found.
		$unsupported_chars = [
			"\x80" => 0x0080, // (binary: 10000000)
			"\xA0" => 0x00A0, // (binary: 10100000)
			"\xAF" => 0x00AF, // (binary: 10101111)
			"\xC2" => 0x00C2, // (binary: 11000010)
			"\xE2" => 0x00E2, // (binary: 11100010)
		];
		// phpcs:enable
		
		// Remove these characters.
		$byline_before = $byline;
		$byline        = str_replace( array_keys( $unsupported_chars ), '', $byline );
		if ( $byline !== $byline_before && ! isset( $this->debug_replaced_authors[ $byline_before ] ) ) {
			$this->debug_replaced_authors[ $byline_before ] = $byline;
		}

		// Trim.
		$byline = trim( $byline );

		// Remove 'By ' prefix case-insensitively.
		$byline = preg_replace( '/^by /i', '', $byline );

		// Explode by multiple separators.
		$separators = [ ' and ', '&', ',' ];
		foreach ( $separators as $separator ) {
			if ( false === stripos( $byline, $separator ) ) {
				continue;
			}
			
			// Explode by separator and trim each exploded part.
			$byline_exploded_parts = array_map( 'trim', explode( $separator, $byline ) );
			
			// Recursively process each exploded part.
			$result = [];
			foreach ( $byline_exploded_parts as $part ) {
				$result = array_merge(
					$result,
					$this->get_author_names_from_byline( $part )
				);
			}
			
			return $result;
		}
		
		// If no separators found, return the cleaned byline as a single author.
		$byline = trim( $byline );

		// Finally, apply manual substitutions.
		$byline = $this->manual_author_name_substitutions( $byline );

		return [ $byline ];
	}

	/**
	 * Manual cleanup of some author names.
	 *
	 * @param string $author_name Author name.
	 * @return string Author name after manual substitution.
	 */
	private function manual_author_name_substitutions( string $author_name ): string {
		switch ( $author_name ) {
			case 'the New Pine Plains Herald Staff':
				return 'New Pine Plains Herald Staff';
			case 'The Herald Staff':
				return 'Herald Staff';
			case 'J. R. Tracy':
				return 'J.R. Tracy';
			case 'R. A. Hermans':
				return 'R.A. Hermans';
			default:
				return $author_name;
		}
	}

	/**
	 * Temporary dev code, will be removed.
	 * 
	 * @return void
	 */
	private function temp_dev() {

		// phpcs:disable

		// $unsupported_characters = [];
		// 
		// // Detect for unsupported characters, check every character of $byline and if it's not a letter, number, space (make sure to allow only the plain standard white space), add it to $unsupported_characters.
		// foreach ( str_split( $byline ) as $char ) {
		// 	if ( ! ctype_alnum( $char ) && ! in_array( $char, [ ' ', "\t", "\n", "\r", "\f", "\v" ] ) ) {
		// 		if ( ! in_array( $char, $unsupported_characters ) ) {
		// 			$unsupported_characters[] = $char;
		// 		}
		// 	}
		// }


		// // Loop through $unsupported_characters and warn if any of them are in $byline_results.
		// if ( ! empty( $unsupported_characters ) ) {
		// 	// Remove duplicates and sort.
		// 	$unsupported_characters = array_unique( $unsupported_characters );
		// 	sort( $unsupported_characters );

		// 	// Create a programmatic representation of the characters.
		// 	$encoded_chars = [];
		// 	foreach ( $unsupported_characters as $char ) {
		// 		// Get the character's Unicode code point.
		// 		$code_point = mb_ord( $char );
		// 		// Format as hex.
		// 		$hex = sprintf( '0x%04X', $code_point );
				
		// 		// Get binary representation of the character.
		// 		$binary = '';
		// 		for ( $i = 0; $i < strlen( $char ); $i++ ) { // phpcs:ignore -- Squiz.PHP.DisallowSizeFunctionsInLoops.Found, Generic.CodeAnalysis.ForLoopWithTestFunctionCall.NotAllowed.
		// 			$binary .= sprintf( '%08b ', ord( $char[ $i ] ) );
		// 		}
				
		// 		// Create a readable representation.
		// 		$encoded_chars[] = sprintf(
		// 			"'%s' => %s, // %s (binary: %s)",
		// 			addslashes( $char ),
		// 			$hex,
		// 			$char,
		// 			trim( $binary )
		// 		);
		// 	}

		// 	// Output the encoded characters in a format that can be copied into code.
		// 	WP_CLI::line( "\nDetected unsupported characters:" );
		// 	WP_CLI::line( "[\n    " . implode( "\n    ", $encoded_chars ) . "\n]" );
		// }

		// phpcs:enable
	}
}
