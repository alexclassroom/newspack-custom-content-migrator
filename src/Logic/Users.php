<?php

namespace NewspackCustomContentMigrator\Logic;

use NewspackCustomContentMigrator\Enum\CAPPostMetaKeys;
use NewspackCustomContentMigrator\Enum\CAPRelatedUserFields;
use WP_Error;
use WP_User;

class Users {

	/**
	 * A recursive function that checks to see if the desired value is unique, and handles applying a value manipulator
	 * when it is not, and then checking to see if that new value is unique, until a maximum number of attempts
	 * has been reached.
	 *
	 * @param CAPRelatedUserFields       $field The user field to check.
	 * @param string                     $desired_value The desired value.
	 * @param callable( string ): string $value_manipulation_callback The callback to manipulate the desired value if not unique.
	 * @param int                        $exclude_user_id The user ID to exclude from the check.
	 * @param int                        $attempt The current attempt.
	 * @param int                        $max_attempts The maximum number of attempts.
	 *
	 * @return string|null A unique value for the user field, or null if it couldn't be obtained.
	 */
	public function obtain_unique_user_field_value( CAPRelatedUserFields $field, string $desired_value, callable $value_manipulation_callback, int $exclude_user_id = 0, int $attempt = 1, int $max_attempts = 3 ): ?string {
		if ( $attempt > $max_attempts ) {
			if ( $this->is_user_field_value_unique( $field, $desired_value, $exclude_user_id ) ) {
				return $desired_value;
			}

			return null;
		}

		if ( ! $this->is_user_field_value_unique( $field, $desired_value, $exclude_user_id ) ) {
			$new_value = $value_manipulation_callback( $desired_value );

			return $this->obtain_unique_user_field_value( $field, $new_value, $value_manipulation_callback, $exclude_user_id, $attempt + 1, $max_attempts );
		}

		return $desired_value;
	}

	/**
	 * Obtains a unique user nicename.
	 *
	 * @param string $desired_user_nicename The desired user nicename.
	 * @param int    $exclude_user_id The user ID to exclude from the check.
	 *
	 * @return string|null The unique user nicename, or null if it couldn't be obtained.
	 */
	final public function obtain_unique_user_nicename( string $desired_user_nicename, int $exclude_user_id = 0 ): ?string {
		// Let's try and see if we can get away with doing this quickly.
		$copy_desired_user_nicename = $desired_user_nicename;
		$start_at                   = 0;
		$unique_user_nicename       = $this->obtain_unique_user_field_value(
			CAPRelatedUserFields::NICE_NAME,
			$copy_desired_user_nicename,
			fn( $value ) => $this->callback_simple_user_nicename_incrementer()( $value, $start_at ),
			$exclude_user_id,
			1,
			1
		);

		if ( null !== $unique_user_nicename ) {
			return $unique_user_nicename;
		}

		// At this point, desired user_nicenames in the form of user-nicename and user-nicename-1 are already taken.
		// So let's try to occupy the next available numbered slot.

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$number_of_similar_nicenames = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->users WHERE user_nicename LIKE %s",
				$this->strip_number_from_user_nicename( $desired_user_nicename ) . '-%'
			)
		);

		return $this->obtain_unique_user_field_value(
			CAPRelatedUserFields::NICE_NAME,
			$desired_user_nicename,
			fn( $value ) => $this->callback_strip_number_user_nicename_incrementer()( $value, $number_of_similar_nicenames ),
			$exclude_user_id,
			1,
			7
		);
	}


	/**
	 * Strips the suffix number from a user nicename.
	 *
	 * @param string $user_nicename The user nicename.
	 *
	 * @return string The user nicename without the number.
	 */
	private function strip_number_from_user_nicename( string $user_nicename ): string {
		return preg_replace( '/-\d+$/', '', $user_nicename );
	}

	/**
	 * Returns a callback that increments the number of similar nicenames and appends it to the user nicename.
	 *
	 * @return callable The callback.
	 */
	private function callback_simple_user_nicename_incrementer(): callable {
		return function ( string $desired_user_nicename, int &$number ): string {
			++$number;

			return $desired_user_nicename . "-$number";
		};
	}

	/**
	 * Returns a callback that increments the number of similar nicenames and appends it to the user nicename.
	 *
	 * @return callable The callback.
	 */
	private function callback_strip_number_user_nicename_incrementer(): callable {
		return function ( string $desired_user_nicename, int &$number ): string {
			return $this->callback_simple_user_nicename_incrementer()( $this->strip_number_from_user_nicename( $desired_user_nicename ), $number );
		};
	}

	/**
	 * Returns a callback that appends a random string to the supplied value.
	 *
	 * @return callable The callback.
	 */
	private function callback_random_string_appender(): callable {
		return function ( string $value ): string {
			return $value . '-' . substr( md5( wp_rand() ), 0, 5 );
		};
	}

	/**
	 * Checks if a user field value is unique.
	 *
	 * @param CAPRelatedUserFields $field The user field to check.
	 * @param string               $value The value to check.
	 * @param int                  $exclude_user_id The user ID to exclude from the check.
	 *
	 * @return bool True if the value is unique, false otherwise.
	 */
	private function is_user_field_value_unique( CAPRelatedUserFields $field, string $value, int $exclude_user_id = 0 ): bool {
		global $wpdb;

		$failed_at = '';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reason: We are using enum values here.
		$sql_prepared = $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE {$field->value} = %s", $value );

		if ( $exclude_user_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reason: $sql_prepared is a prepared statement.
			$sql_prepared = $wpdb->prepare( "$sql_prepared AND ID <> %d", $exclude_user_id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$test = null === $wpdb->get_var( $sql_prepared );

		$failed_at = $test ? '' : 'users';

		if ( $test ) { // If unique across all users, check CAP specific data points as well.
			$cap_data_fixer = new CoAuthorPlusDataFixer();
			$test           = match ( $field ) {
				CAPRelatedUserFields::NICE_NAME, CAPRelatedUserFields::LOGIN => $cap_data_fixer->is_unique_value( CAPPostMetaKeys::LOGIN, $value ),
				CAPRelatedUserFields::EMAIL => $cap_data_fixer->is_unique_value( CAPPostMetaKeys::EMAIL, $value ),
			};

			$failed_at = $test ? '' : 'cap_data';
		}

		if ( $test ) { // If unique across CAP specific data points, check for uniqueness in wp_terms table.
			if ( CAPRelatedUserFields::NICE_NAME === $field ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$test      = null === $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM $wpdb->terms WHERE slug = %s", $value ) );
				$failed_at = $test ? '' : 'wp_terms.slug';
			}

			if ( CAPRelatedUserFields::LOGIN === $field ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$test      = null === $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM $wpdb->terms WHERE name = %s", $value ) );
				$failed_at = $test ? '' : 'wp_terms.name';
			}
		}

		do_action( 'newspack_user_field_value_unique_check', $field, $value, $exclude_user_id, $test, $failed_at );

		return $test;
	}

	/**
	 * Gets a WP_User object, with validated name fields (i.e. user_login, user_nicename, and display_name).
	 *
	 * @param WP_User $user The user to validate.
	 *
	 * @return WP_User|WP_Error The validated user, or a WP_Error if the user could not be validated.
	 */
	public function get_user_with_validated_data( WP_User $user ): WP_User|WP_Error {
		$clone = new WP_User( clone $user->data );

		if (
			empty( $clone->user_login ) ||
			is_email( $clone->user_login ) ||
			! $this->is_user_field_value_unique( CAPRelatedUserFields::LOGIN, $clone->user_login, $clone->ID )
		) {
			// If the user_login is empty, or an email, or not unique, generate a unique user_login.
			// We're using the user's email to generate the user_login.
			$email = $clone->user_email;

			// If $clone->user_email is empty, use user_login if that is an email.
			if ( empty( $email ) && is_email( $user->user_login ) ) {
				$email = $user->user_login;
			}

			if ( empty( $email ) ) {
				return new WP_Error( 'newspack-user-login-empty', 'User login is empty and no email is available to generate a unique user login.' );
			}

			$user_login = sanitize_user( substr( $email, 0, strpos( $email, '@' ) ) );

			if ( ! $this->is_user_field_value_unique( CAPRelatedUserFields::LOGIN, $user_login, $clone->ID ) ) {
				$first_attempt_user_login = $user_login;

				$user_login = $this->obtain_unique_user_field_value(
					CAPRelatedUserFields::LOGIN,
					$user_login,
					$this->callback_random_string_appender(),
					$clone->ID
				);

				if ( null === $user_login ) {
					$user_provided_seed                 = apply_filters( 'newspack_provide_unique_user_login', $first_attempt_user_login, new WP_User( clone $user->data ) );
					$user_provided_make_unique_callback = apply_filters( 'newspack_provide_make_unique_user_login_callback', $this->callback_random_string_appender(), new WP_User( clone $user->data ) );

					if ( $user_provided_seed !== $first_attempt_user_login ) {
						$user_login = $this->obtain_unique_user_field_value(
							CAPRelatedUserFields::LOGIN,
							$user_provided_seed,
							$user_provided_make_unique_callback,
							$clone->ID,
						);
					}
				}

				if ( null === $user_login ) {
					return new WP_Error( 'newspack-unable-to-obtain-unique-user-login', 'Unable to obtain a unique user login.' );
				}
			}

			// At this point, we have a unique user_login.
			$clone->user_login = $user_login;
		}

		// Here we are establishing the correct way to create user_nicenames.
		// if display_name is not empty and not an email, use that, with sanitize_title
		// If display_name is empty, then use first_name and last_name, if those are not empty.

		if ( ! empty( $clone->display_name ) && ! is_email( $clone->display_name ) ) {
			$sanitized_display_name = sanitize_title( $clone->display_name );
			$unique_user_nicename   = $this->obtain_unique_user_nicename( $sanitized_display_name, $clone->ID );

			if ( null === $unique_user_nicename ) {
				if ( ! empty( $clone->first_name ) && ! empty( $clone->last_name ) ) {
					$clone->display_name    = "{$clone->first_name} {$clone->last_name}";
					$sanitized_display_name = sanitize_title( $clone->display_name );
					$unique_user_nicename   = $this->obtain_unique_user_nicename( $sanitized_display_name, $clone->ID );
				}
			}

			if ( null === $unique_user_nicename ) {
				return new WP_Error( 'newspack-unable-to-obtain-unique-user-nicename-from-display-name', 'Unable to obtain a unique user nicename.' );
			}

			$clone->user_nicename = $unique_user_nicename;

			return $clone;
		}

		if ( ! empty( $clone->first_name ) && ! empty( $clone->last_name ) ) {
			// At this point we know that $clone->display_name is either empty or an email, so it should be set/updated.
			$clone->display_name    = "{$clone->first_name} {$clone->last_name}";
			$sanitized_display_name = sanitize_title( $clone->display_name );
			$unique_user_nicename   = $this->obtain_unique_user_nicename( $sanitized_display_name, $clone->ID );

			if ( null === $unique_user_nicename ) {
				return new WP_Error( 'newspack-unable-to-obtain-unique-user-nicename-from-names', 'Unable to obtain a unique user nicename from first and last name fields.' );
			}

			$clone->user_nicename = $unique_user_nicename;
		}

		return $clone;
	}
}
