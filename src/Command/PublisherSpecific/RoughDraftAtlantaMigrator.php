<?php
/**
 * Southwest Regional Publishing specific commands.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific;

use WP_CLI;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;

/**
 * RoughDraftAtlantaMigrator.
 */
class RoughDraftAtlantaMigrator implements RegisterCommandInterface {

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
			'newspack-content-migrator roughdraftatlanta thegavoice-merging-update-cdiff-imported-post-categories',
			self::get_command_closure( 'cmd_thegavoice_update_post_categories' ),
			[
				'shortdesc' => 'After CDiff merging of thegavoice into roughdraft atlanta, update categories to all be children of one specific category.',
				'synopsis'  => [],
			]
		);
	}

	/**
	 * Callable for `newspack-content-migrator roughdraftatlanta thegavoice-merging-update-cdiff-imported-post-categories`.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function cmd_thegavoice_update_post_categories( array $pos_args, array $assoc_args ): void {
	}
}
