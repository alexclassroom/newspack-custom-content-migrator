<?php
/**
 * Class to encapsulate Listing Type values.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Logic\ConsoleOutput;

use NewspackCustomContentMigrator\Enum\CAPRelatedUserFields;
use NewspackCustomContentMigrator\Logic\Users as UsersLogic;
use NewspackCustomContentMigrator\Utils\ConsoleColor;
use NewspackCustomContentMigrator\Utils\ConsoleTable;
use WP_User;

/**
 * Class to handle user related logic.
 */
class Users {

	/**
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * Outputs a table of user's from a given array of user ID's or WP_User objects.
	 *
	 * @param WP_User[]|int[] $users Array of user ID's or WP_User objects.
	 * @param string          $title The title of the table.
	 *
	 * @return void|null
	 */
	public static function output_users_table( array $users, string $title = "User's Table" ) {
		$users = array_map(
			function ( $user ) {
				if ( $user instanceof WP_User ) {
					return $user->to_array();
				}

				if ( is_numeric( $user ) ) {
					$user = get_user_by( 'id', $user );

					if ( $user ) {
						return $user->to_array();
					}
				}

				return null;
			},
			$users
		);

		$users = array_filter( $users );

		if ( empty( $users ) ) {
			ConsoleColor::bright_yellow( 'No users found.' )->output();

			return null;
		}

		ConsoleTable::output_data(
			$users,
			[
				'ID',
				'user_login',
				'user_nicename',
				'user_email',
				'display_name',
			],
			$title
		);
	}

	/**
	 * This function is a wrapper around the UsersLogic::obtain_unique_user_nicename method, outputting the result of
	 * each attempt to obtain a unique user_nicename to console.
	 *
	 * @param string $desired_user_nicename The desired user_nicename.
	 * @param int    $exclude_user_id The user ID to exclude from the check.
	 *
	 * @return string|null
	 */
	public function obtain_unique_user_nicename( string $desired_user_nicename, int $exclude_user_id = 0 ): ?string {
		if ( ! has_action( 'newspack_user_field_value_unique_check' ) ) {
			add_action(
				'newspack_user_field_value_unique_check',
				function ( CAPRelatedUserFields $field, string $value, int $exclude_user_id, bool $test, string $failed_at ) {
					$human_readable_result = $test ? '✅' : "❌ ($failed_at)";

					$console_output = ConsoleColor::white( "$field->value:" )->underlined_yellow( $value )->white( $human_readable_result );

					if ( $exclude_user_id ) {
						$console_output->white( " (excluding User ID: $exclude_user_id)" );
					}

					$console_output->output();
				},
				1,
				5
			);
		}

		$unique_user_nicename = ( new UsersLogic() )->obtain_unique_user_nicename( $desired_user_nicename, $exclude_user_id );

		if ( has_action( 'newspack_user_field_value_unique_check' ) ) {
			remove_all_actions( 'newspack_user_field_value_unique_check' );
		}

		return $unique_user_nicename;
	}
}
