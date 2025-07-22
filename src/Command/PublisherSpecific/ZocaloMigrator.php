<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use Exception;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\CoAuthorsPlusHelper;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Logic\Posts;
use Newspack\MigrationTools\Util\BatchLogic;
use Newspack\MigrationTools\Util\CsvWriter;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
use Newspack\MigrationTools\Util\MigrationMeta;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;
use WP_Post;

class ZocaloMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	const AUTHOR_CREDIT_META_KEY = 'author_credit';
    const POST_INFO_META_KEY = 'post_info';
    const BUY_LINKS_CTA_META_KEY = 'buy_book_cta';
    const BUY_LINKS_COUNT_META_KEY = 'buy_book_links';
    const BUY_LINK_LABEL_META_KEY = 'buy_book_links_%d_shop';
    const BUY_LINK_LINK_META_KEY = 'buy_book_links_%d_url';
    const BUY_LINK_SECOND_SET = 'buy_book_is_there_a_2nd_book';
	const BUY_LINKS_SECOND_SET_CTA_META_KEY = 'buy_book_2_cta';
	const BUY_LINKS_SECOND_SET_COUNT_META_KEY = 'buy_book_2_links';
	const BUY_LINK_SECOND_SET_LABEL_META_KEY = 'buy_book_2_links_%d_shop';
    const BUY_LINK_SECOND_SET_LINK_META_KEY = 'buy_book_2_links_%d_url';
    const POST_EDITED_BY_META_KEY = 'edited_by';
	const AUTHOR_METABOX_CLASS_NAME = 'zps-post-meta-metabox';

	private int $default_author_id;

	private CoAuthorsPlusHelper $coauthorsplus_logic;

	/**
	 * Gutenberg Block Generator logic.
	 * 
	 * @var GutenbergBlockGenerator
	 */
	private GutenbergBlockGenerator $gutenberg_block_generator;

	/**
	 * Posts logic.
	 * 
	 * @var Posts
	 */
	private Posts $posts_logic;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->coauthorsplus_logic       = new CoAuthorsPlusHelper();
		$this->gutenberg_block_generator = new GutenbergBlockGenerator();
		$this->posts_logic               = new Posts();
	}

	/**
	 * @throws Exception
	 */
	public static function register_commands(): void {
		$generic_args = [
			'synopsis' => '[--post-id=<post-id>] [--dry-run] [--num-items=<num-items>] [--refresh-existing]',
		];

		$batch_args = BatchLogic::get_batch_args();

		WP_CLI::add_command(
			'newspack-content-migrator zps-import-post-authors',
			self::get_command_closure( 'cmd_import_post_authors' ),
			[
				...$generic_args,
				'shortdesc' => 'Import authors from ACF data on posts.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator zps-import-sub-titles',
			self::get_command_closure( 'cmd_import_sub_titles' ),
			[
				...$generic_args,
				'shortdesc' => 'Import post sub-titles.',
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator zps-append-posts-author-metabox',
			self::get_command_closure( 'cmd_append_posts_author_metabox' ),
			[
				'shortdesc' => 'Appends the ACF Author metabox to the end of Posts',
				'synopsis'  => [
					[
						'type'        => 'flag',
						'name'        => 'refresh-metabox',
						'description' => 'Refresh metabox',
						'optional'    => true,
						'repeating'   => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'start-from',
						'description' => 'Which index to start from',
						'optional'    => true,
						'repeating'   => false,
					]
				],
			]
		);
	}

	public function cmd_import_sub_titles( array $pos_args, array $assoc_args ): void {
		$migration_meta = [
			'version' => 1,
			'key'     => 'import_sub_titles',
		];

		$site_url = trailingslashit( get_site_url() );
		$meta_key = 'sub_title';
		$file_loggger = FileLog::get_logger( 'import-subtitles', 'import-subtitles.log' );

		foreach ( $this->get_published_posts_with_meta_key( $meta_key, $assoc_args, $migration_meta ) as $post ) {
			$sub_title = trim( get_post_meta( $post->ID, $meta_key, true ) );
			if ( empty( $sub_title ) ) {
				continue;
			}
			$file_loggger->info( sprintf( 'Updated sub title on post: %s', "$site_url?p=p={$post->ID}" ) );

			update_post_meta( $post->ID, 'newspack_post_subtitle', $sub_title );
			MigrationMeta::update( $post->ID, $migration_meta['key'], 'post', $migration_meta['version'] );
		}
	}

	public function cmd_import_post_authors( array $pos_args, array $assoc_args ): void {

		$this->default_author_id = $this->coauthorsplus_logic->create_guest_author(
			[
				'display_name' => 'Zócalo Public Square',
				'user_login'   => 'zocalo-public-square',
			]
		);

		$migration_meta = [
			'version' => 1,
			'key'     => 'import_post_authors',
		];

		$site_url = trailingslashit( get_site_url() );
		$meta_key = 'by_line';

		$file_logger = FileLog::get_logger( 'import-post-authors', 'import-post-authors.log' );

		foreach ( $this->get_published_posts_with_meta_key( $meta_key, $assoc_args, $migration_meta ) as $post ) {
			$authors_to_assign = [];

			$byline = get_post_meta( $post->ID, $meta_key );
			if ( empty( $byline ) ) {
				continue;
			}
			if ( ! is_array( $byline ) ) {
				$authors_to_assign[] = $this->process_single_author( $byline, $post );
			} else {
				$author_strings = [];
				foreach ( $byline as $author ) {
					$author_strings = [
						...$author_strings,
						...$this->parse_author_string( wp_strip_all_tags( $author ) ),
					];
				}
				foreach ( array_unique( $author_strings ) as $author ) {
					$authors_to_assign[] = $this->process_single_author( $author, $post );
				}
			}
			$authors_to_assign = array_filter( $authors_to_assign );
			if ( ! empty( $authors_to_assign ) ) {
				$this->coauthorsplus_logic->assign_guest_authors_to_post( $authors_to_assign, $post->ID );
				$file_logger->info( sprintf( 'Assigned author(s): "%s" on post "%s"', implode( ',', $authors_to_assign ), "$site_url?p={$post->ID}" ));
			}

			MigrationMeta::update( $post->ID, $migration_meta['key'], 'post', $migration_meta['version'] );
		}
	}

	/**
	 * Callable for `wp newspack-content-migrator zps-append-posts-author-metabox` command.
	 * 
	 * @param array $pos_args   CLI positional args.
	 * @param array $assoc_args CLI assoc args.
	 * @return void
	 */
	public function cmd_append_posts_author_metabox( array $pos_args, array $assoc_args ): void {
		$refresh_metabox = isset( $assoc_args['refresh-metabox'] ) ? true : false;
		$start_from      = isset( $assoc_args['start-from'] ) ? intval( $assoc_args['start-from'] ) : 0;

		$logger = MultiLog::get_cli_and_file_logger( __FUNCTION__ );
		$qa_csv = new CsvWriter( __FUNCTION__ . '.csv' );
		$qa_csv->set_header( [
			'#',
			'Post ID',
			'Post Title',
			'Post URL',
		] );

		$logger->info( '🟢 Starting Migration...' );

		global $wpdb;

		$posts = $wpdb->get_results(
			"SELECT DISTINCT(`ID`), `post_content`
			FROM `$wpdb->posts`
			INNER JOIN `$wpdb->postmeta` ON `$wpdb->posts`.`ID` = `$wpdb->postmeta`.`post_id`
			WHERE `post_type` = 'post'
			AND `post_modified` < '2024-10-21 23:59:59'
			AND `post_status` = 'publish'
			AND `meta_key` IN ('author_credit', 'post_info', 'edited_by', 'buy_book_links', 'buy_book_is_there_a_2nd_book')
			AND `meta_value` != ''
			ORDER BY `ID` ASC"
		);

		$logger->info( sprintf( '👉 Found %d posts', count( $posts ) ) );

		foreach ( $posts as $index => $post ) {
			if ( $index < $start_from ) {
				continue;
			}

			if ( ! $refresh_metabox && get_post_meta( $post->ID, '_newspack_author_metabox_migrated', true ) === 'yes' ) {
				continue;
			}

			$logger->info( sprintf( '[Memory Usage: %s] 👉 Processing Post (%d / %s) #%d', size_format( memory_get_usage( true ) ), $index + 1, count( $posts ), $post->ID ) );

			$post_id      = $post->ID;
			$post_content = $post->post_content;

			$post_blocks  = parse_blocks( $post_content );

			$post_metabox = $this->generate_post_metabox( (int) $post_id );

			if ( empty( $post_metabox ) ) {
				$logger->warning( 'Metabox is empty' );

				update_post_meta( $post->ID, '_newspack_author_metabox_migrated', 'yes' );

				continue;
			}

			$last_block = end( $post_blocks );

			if ( is_array( $last_block ) && @$last_block['blockName'] === 'core/group' && @$last_block['attrs']['className'] === 'zps-post-meta-metabox' ) {
				$post_blocks[ count( $post_blocks ) - 1 ] = $post_metabox;
			} else {
				$post_blocks[] = $post_metabox;
			}

			$new_post_content = serialize_blocks( [ ...$post_blocks ] );

			if ( $new_post_content !== $post_content ) {
				wp_save_post_revision( $post_id );
	
				$wpdb->update(
					$wpdb->posts,
					[
						'post_content' => $new_post_content,
					],
					[
						'ID' => $post_id,
					],
				);

				$qa_csv->put( [
					$index + 1,
					$post_id,
					get_the_title( $post_id ),
					get_permalink( $post_id ),
				] );
			} else {
				$logger->warning( 'Post Content is the same' );
			}

			update_post_meta( $post->ID, '_newspack_author_metabox_migrated', 'yes' );
		}

		$qa_csv->close();
		
		$logger->info( '🏁 Migration Completed!' );
		
		wp_cache_flush();
	}

	private function generate_post_metabox( int $post_id ): ?array {
		$inner_blocks = [];

		// Author Credit.
		$author_credit = get_post_meta( $post_id, self::AUTHOR_CREDIT_META_KEY, true );
		if ( ! empty( $author_credit ) ) {
			$author_credit = nl2br( $author_credit );
			$author_credit = str_replace(
				[ '<b>', '</b>' ],
				[ '<strong>', '</strong><br>' ],
				$author_credit
			);

			$author_credit = str_replace(
				[ '<strong>', '</strong>' ],
				[ '<strong style="font-size: var(--newspack-theme-font-size-md);">', '</strong><br>' ],
				$author_credit
			);

			$inner_blocks[] = $this->get_paragraph_block( $author_credit );
		}
		
		// Buy Links
		$buy_links_cta         = get_post_meta( $post_id, self::BUY_LINKS_CTA_META_KEY, true );
		$buy_links_links_count = get_post_meta( $post_id, self::BUY_LINKS_COUNT_META_KEY, true );

		if ( ! empty( $buy_links_cta ) || ! empty( $buy_links_links_count ) ) {
			$paragraph_content = [];

			if ( ! empty( $buy_links_cta ) ) {
				$paragraph_content[] = sprintf( '<strong style="font-size: var(--newspack-theme-font-size-md);">%s</strong>', $buy_links_cta );
			}

			if ( ! empty( $buy_links_links_count ) ) {
				$buy_links = [];

				foreach ( range( 0, $buy_links_links_count - 1 ) as $index ) {
					$buy_link_label = get_post_meta( $post_id, sprintf( self::BUY_LINK_LABEL_META_KEY, $index ), true );
					$buy_link_link  = get_post_meta( $post_id, sprintf( self::BUY_LINK_LINK_META_KEY, $index ), true );

					if ( ! empty( $buy_link_label ) && ! empty( $buy_link_link ) ) {
						$buy_links[] = sprintf( '<a href="%s" target="_blank">%s</a>', $buy_link_link, $buy_link_label );
					}
				}

				if ( ! empty( $buy_links ) ) {
					$paragraph_content[] = implode( ' | ', $buy_links );
				}
			}

			if ( ! empty( $paragraph_content ) ) {
				$inner_blocks[] = $this->get_paragraph_block( implode( '<br>', $paragraph_content ) );
			}
		}

		// Second Buy Links
		if ( get_post_meta( $post_id, self::BUY_LINK_SECOND_SET, true ) ) {
			$buy_links_cta         = get_post_meta( $post_id, self::BUY_LINKS_SECOND_SET_CTA_META_KEY, true );
			$buy_links_links_count = get_post_meta( $post_id, self::BUY_LINKS_SECOND_SET_COUNT_META_KEY, true );

			if ( ! empty( $buy_links_cta ) || ! empty( $buy_links_links_count ) ) {
				$paragraph_content = [];

				if ( ! empty( $buy_links_cta ) ) {
					$paragraph_content[] = sprintf( '<strong style="font-size: var(--newspack-theme-font-size-md);">%s</strong>', $buy_links_cta );
				}

				if ( ! empty( $buy_links_links_count ) ) {
					$buy_links = [];

					foreach ( range( 0, $buy_links_links_count - 1 ) as $index ) {
						$buy_link_label = get_post_meta( $post_id, sprintf( self::BUY_LINK_SECOND_SET_LABEL_META_KEY, $index ), true );
						$buy_link_link  = get_post_meta( $post_id, sprintf( self::BUY_LINK_SECOND_SET_LINK_META_KEY, $index ), true );

						if ( ! empty( $buy_link_label ) && ! empty( $buy_link_link ) ) {
							$buy_links[] = sprintf( '<a href="%s" target="_blank">%s</a>', $buy_link_link, $buy_link_label );
						}
					}

					if ( ! empty( $buy_links ) ) {
						$paragraph_content[] = implode( ' | ', $buy_links );
					}
				}

				if ( ! empty( $paragraph_content ) ) {
					$inner_blocks[] = $this->get_paragraph_block( implode( '<br>', $paragraph_content ) );
				}
			}
		}

		// Post Info.
		$post_info = get_post_meta( $post_id, self::POST_INFO_META_KEY, true );
		if ( ! empty( $post_info ) ) {
			// Separator.
			if ( ! empty( $inner_blocks ) ) {
				$inner_blocks[] = $this
					->gutenberg_block_generator
					->get_separator( 'is-style-dots' );
			}

			$inner_blocks[] = $this->get_paragraph_block( $post_info );
		}

		// Edited by.
		$edited_by = get_post_meta( $post_id, self::POST_EDITED_BY_META_KEY, true );
		if ( ! empty( $edited_by ) ) {
			// Separator.
			if ( ! empty( $inner_blocks ) ) {
				$inner_blocks[] = $this
					->gutenberg_block_generator
					->get_separator( 'is-style-dots' );
			}

			$inner_blocks[] = $this->get_paragraph_block( $edited_by );
		}

		// Separator.
		if ( ! empty( $inner_blocks ) ) {
			$inner_blocks = [
				$this
					->gutenberg_block_generator
					->get_separator( 'is-style-wide' ),

				...$inner_blocks,

				$this
					->gutenberg_block_generator
					->get_separator( 'is-style-wide' ),
			];

			return $this
				->gutenberg_block_generator
				->get_group_constrained(
					$inner_blocks,
					[ 'zps-post-meta-metabox' ]
				);
		}

		return null;
	}

	private function process_single_author( string $author_name, WP_Post $post ): int {
		$guest_author_id = 0;
		$author_args     = [];
		// Remove "by" prefix on author name.
		$author_args['display_name'] = preg_replace( '/^by /i', '', trim( $author_name ) );

		$file_logger = FileLog::get_logger( 'import-post-authors', 'import-post-authors.log' );

		if ( empty( $author_args['display_name'] ) ) {
			$guest_author_id = $this->default_author_id;
		} else {
			$author_credit = get_post_meta( $post->ID, 'author_credit', true );
			if ( $author_credit ) {
				$author_args['description'] = trim( wp_strip_all_tags( $author_credit ) );
			}
			$guest_author_id = $this->coauthorsplus_logic->create_guest_author( $author_args );
			if ( is_wp_error( $guest_author_id ) ) {
				$guest_author_id = 0;
				$file_logger->error(
					sprintf( 'Could not create guest author with display name "%s" for post ID %d', $author_args['display_name'], $post->ID ) );
			}
		}

		return $guest_author_id;
	}

	private function parse_author_string( string $authors ): array {
		$strip    = [
			'translated by',
			'Translated by',
			'Interview by',
			'interview by',
			'as told to',
			'Updated by',
		];
		$replaced = str_replace( '\n', '', $authors );

		$good = [];
		// Split by , and &.
		foreach ( preg_split( '/(,\s|\s&\s|(\sand\s))/', $replaced ) as $candidate ) {
			$candidate = trim( $candidate );
			if ( empty( $candidate ) || is_numeric( $candidate ) ) {
				continue;
			}
			foreach ( $strip as $strip_candidate ) {
				$candidate = str_replace( $strip_candidate, '', $candidate );
			}
			if ( ! empty( $candidate ) ) {
				$good[] = trim( trim( $candidate, '.' ) );
			}
		}

		return $good;
	}

	private function get_published_posts_with_meta_key( string $meta_key, array $assoc_args, array $migration_meta ): iterable {
		$post_id          = $assoc_args['post-id'] ?? false;
		$refresh_existing = $assoc_args['refresh-existing'] ?? false;
		$num_items        = $assoc_args['num-items'] ?? PHP_INT_MAX;

		if ( ! $post_id ) {
			global $wpdb;
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID 
						FROM $wpdb->posts
						LEFT JOIN $wpdb->postmeta 
							ON (ID = post_id AND meta_key = %s) 
						WHERE post_type = 'post'
						AND post_status = 'publish'
						AND meta_key = %s
						AND meta_value IS NOT NULL
						ORDER BY ID DESC",
					[ $meta_key, $meta_key ]
				)
			);
		} else {
			$ids = [ $post_id ];
		}

		$counter = 0;
		foreach ( $ids as $id ) {
			if ( ! $refresh_existing && MigrationMeta::get( $id, $migration_meta['key'], 'post' ) >= $migration_meta['version'] ) {
				continue;
			}
			if ( ++$counter > $num_items ) {
				break;
			}

			$post = get_post( $id );
			if ( $post instanceof \WP_Post ) {
				yield $post;
			}
		}
	}

	private function get_paragraph_block( string $content, $align = 'center' ): array {
		$block_content = '<p class="has-text-align-' . $align . '">' . $content . '</p>';

		$attrs = [
			'align' => $align,
		];

		return [
			'blockName'    => 'core/paragraph',
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => $block_content,
			'innerContent' => [ $block_content ],
		];
	}
}
