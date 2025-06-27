<?php
/**
 * Migration tasks for Zan Times.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\GuestContributorsHelper;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;

/**
 * Custom migration scripts for Zan Times.
 */
class ZanTimesMigrator implements RegisterCommandInterface {
	use WpCliCommandTrait;

	const POST_AUTHOR_META_KEY          = 'zan_times_author';
	const AUTHORS_META_EXTRA_CHARACTERS = [ '*', '=' ];
	const AUTHORS_META_SEPARATORS       = [ ', and ', ' and ', ', ', ' و ', '،' ];
	const WHITESPACE_CHARACTERS         = [ "\xC2\xA0", "\xE2\x80\xAF", "\xE2\x80\x8C", "\xE2\x80\x8D" ];

	/**
	 * Constructor.
	 */
	private function __construct() {
	}

	/**
	 * Registers WP CLI Commands.
	 * 
	 * @return void
	 */
	public static function register_commands(): void {
		$generic_args = [];

		WP_CLI::add_command(
			'newspack-content-migrator zantimes-migrate-authors',
			self::get_command_closure( 'cmd_migrate_authors' ),
			[
				...$generic_args,
				'shortdesc' => 'Migrate Authors from post meta to Guest Contributors.',
			]
		);
	}

	/**
	 * Callable for `newspack-content-migrator zantimes-migrate-authors` command.
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_migrate_authors( $pos_args, $assoc_args ): void {
		global $wpdb;

		// Get all posts with the 'guest_contributor' post meta.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `post_id`, `meta_value`, `post_type`
                 FROM {$wpdb->postmeta}
                 INNER JOIN {$wpdb->posts} ON {$wpdb->postmeta}.`post_id` = {$wpdb->posts}.`ID`
                 WHERE `meta_key` = %s
                 AND `meta_value` != ''
                 AND `post_type` = 'post'",
				self::POST_AUTHOR_META_KEY
			)
		);

		$all_authors = [];

		foreach ( $posts as $post ) {
			WP_CLI::line( sprintf( 'Processing Post #%d.', $post->post_id ) );

			$post_authors = $this->parse_authors( $post->meta_value );

			$all_authors = [
				...$all_authors,
				...$post_authors,
			];

			$guest_contributors = [];

			foreach ( $post_authors as $post_author ) {
				$guest_contributor_data = [
					'display_name'  => $post_author,
					'user_nicename' => $this->parse_persian_to_slug( $post_author ),
					'user_login'    => empty( sanitize_user( $post_author, true ) ) || sanitize_user( $post_author, true ) === '.'
						? GuestContributorsHelper::generate_username( $this->parse_persian_to_slug( $post_author ) )
						: $post_author,
				];

				$guest_contributor    = GuestContributorsHelper::create_or_get_contributor( $guest_contributor_data, $post_author );
				$guest_contributors[] = $guest_contributor;
			}

			if ( ! empty( $guest_contributors ) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$wpdb->posts,
					[
						'post_author' => $guest_contributors[0]->ID,
					],
					[
						'ID' => $post->post_id,
					],
				);
			}

			GuestContributorsHelper::assign_contributors_to_post( $post->post_id, array_map( fn ( $u ) => $u->ID, $guest_contributors ) );
		}

		WP_CLI::success( 'Authors migrated successfully!' );
	}

	/**
	 * Convert a single string of Authors into filtered multiple Author list.
	 * 
	 * @param  string $authors  The Authors value.
	 * @return array
	 */
	private function parse_authors( string $authors ): array {
		// Cleanup extra characters.
		$authors = str_replace( self::AUTHORS_META_EXTRA_CHARACTERS, '', $authors );

		// Replace separators to generic one.
		$authors = str_replace( self::AUTHORS_META_SEPARATORS, ' && ', $authors );

		// Get a list of Authors.
		$authors = explode( ' && ', $authors );

		// Remove extra whitespace characters.
		$authors = array_filter(
			array_map(
				function ( $author ) {
					return trim( str_replace( self::WHITESPACE_CHARACTERS, ' ', $author ) );
				},
				$authors 
			) 
		);

		return $authors;
	}

	/**
	 * Converts a Persian name to slug.
	 * 
	 * @param  string $value The Persian Display Name.
	 * @return array
	 */
	private function parse_persian_to_slug( string $value ): string {
		$translit = [
			'ا'  => 'a',
			'آ'  => 'a',
			'ب'  => 'b',
			'پ'  => 'p',
			'ت'  => 't',
			'ث'  => 's',
			'ج'  => 'j',
			'چ'  => 'ch',
			'ح'  => 'h',
			'خ'  => 'kh',
			'د'  => 'd',
			'ذ'  => 'z',
			'ر'  => 'r',
			'ز'  => 'z',
			'ژ'  => 'zh',
			'س'  => 's',
			'ش'  => 'sh',
			'ص'  => 's',
			'ض'  => 'z',
			'ط'  => 't',
			'ظ'  => 'z',
			'ع'  => '',
			'غ'  => 'gh',
			'ف'  => 'f',
			'ق'  => 'gh',
			'ک'  => 'k',
			'گ'  => 'g',
			'ل'  => 'l',
			'م'  => 'm',
			'ن'  => 'n',
			'و'  => 'v',
			'ه'  => 'h',
			'ی'  => 'y',
			'ي'  => 'y',
			'ئ'  => 'y',
			'ة'  => 'h',
			'ؤ'  => 'v',
			'‌'  => '',
			'ٔ'  => '',
			'‍'  => '',
			'َ'  => '',
			'ُ'  => '',
			'ِ'  => '',
			'ً'  => '',
			'ٍ'  => '',
			'ٌ'  => '',
			'ْ'  => '',
			'ٰ'  => '',
			'‏'  => '',
			'،'  => '',
			'؛'  => '',
			'؟'  => '',
			' '  => '-',
			'ـ'  => '',
			'\'' => '',
			'"'  => '',
			'('  => '',
			')'  => '',
			'['  => '',
			']'  => '',
			'{'  => '',
			'}'  => '',
			'/'  => '-',
			'\\' => '-',
			'|'  => '',
			'<'  => '',
			'>'  => '',
			'*'  => '',
			'&'  => '',
			'^'  => '',
			'%'  => '',
			'$'  => '',
			'@'  => '',
			'!'  => '',
			'='  => '',
			'+'  => '',
			'#'  => '',
			'~'  => '',
			'`'  => '',
			'.'  => '-',
			','  => '-',
			'–'  => '-',
			'—'  => '-',
		];

		// 1. Transliterate
		$slug = strtr( $value, $translit );

		// 2. Lowercase
		$slug = strtolower( $slug );

		// 3. Remove any remaining invalid characters
		$slug = preg_replace( '~[^a-z0-9\-]~', '', $slug );

		// 4. Collapse multiple dashes
		$slug = preg_replace( '~-+~', '-', $slug );

		// 5. Trim dashes from both ends
		return trim( $slug, '-' );
	}
}
