<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland\Helpers;

use WP_User;

/**
 * The NNEBylineHelper class provides helper methods for working with bylines.
 */
class NNEBylineHelper {

	/**
	 * Splits a byline string into an array of bylines.
	 *
	 * @param string $byline The byline string to split.
	 *
	 * @return array
	 */
	public static function get_bylines( string $byline ): array {
		// A little preprocessing to ensure we don't accidentally split bylines because of suffixes.
		$byline = preg_replace(
			[
				'/,?\s?(JR\.?)\b/i', // JR, JR., jr, jr., Jr, Jr., Jr.
				'/,?\s?(SR\.?)\b/i', // SR, SR., sr, sr., Sr, Sr., Sr.
				'/,?\s?\b(III)\b/',
				'/,?\s?\b(II)\b/',
				'/,?\s?\b(IV)\b/',
				'/,?\s?\b(V)\b/',
			],
			[
				' </JUNIOR>',
				' </SENIOR>',
				' </III>',
				' </II>',
				' </IV>',
				' </V>',
			],
			$byline
		);

		$exploded_bylines = explode(
			' and ',
			preg_replace(
				[ '/^.*b[y|t|u]\s/mi', '/^.*from\s/mi', '/,/', '/([A-Z]+)and\s/m', '/\sand([A-Z]+)/m' ],
				[ '', '', ' and ', '$1 and ', ' and $1' ],
				trim(
					preg_replace(
						[
							'/[\x00-\x1F\x7F\xA0]/u',
							'/[’]/u',
							'/[‛]/u',
							'/[\']/',
						],
						[
							' ',
							"'",
							"'",
							"'",
						],
						$byline
					)
				)
			)
		);

		$proper_case_name = function ( $name ) {
			$copy_of_name   = $name;
			$length_of_name = strlen( $name );
			for ( $i = 0; $i < $length_of_name; $i++ ) {
				if ( in_array( $name[ $i ], [ ' ', "'" ], true ) ) {
					continue;
				}

				$name[ $i ] = strtolower( $name[ $i ] );

				if ( 0 === $i || in_array( $name[ $i - 1 ], [ ' ', "'" ], true ) ) {
					$name[ $i ] = strtoupper( $name[ $i ] );
					continue;
				}

				if ( ctype_lower( $copy_of_name[ $i - 1 ] ) ) {
					$name[ $i ] = strtoupper( $name[ $i ] );
				}
			}

			return $name;
		};

		return array_map(
			function ( $particle ) use ( $proper_case_name ) {
				if ( str_contains( $particle, ' / ' ) ) {
					$particle = substr( $particle, 0, strpos( $particle, ' / ' ) );
				}
				$particle = trim( preg_replace( '/\s+/', ' ', $particle ) );

				if ( empty( $particle ) ) {
					return $particle;
				}

				$percent_uppercase = ( 100 * count( preg_grep( '/[A-Z]/', str_split( $particle ) ) ) ) / count( preg_grep( '/[A-Za-z]/', str_split( $particle ) ) );
				if ( $percent_uppercase >= 80 ) {
					if ( str_contains( $particle, '-' ) ) {
						$exploded_particle = explode( '-', $particle );
						$exploded_particle = array_map( fn( $particle ) => $proper_case_name( $particle ), $exploded_particle );
						$particle          = implode( '-', $exploded_particle );
					} else {
						$particle = $proper_case_name( $particle );
					}
				} elseif ( $percent_uppercase <= 15 ) {
					$particle = ucwords( strtolower( $particle ) );
				}

				return preg_replace(
					[
						'/<\/JUNIOR>/i',
						'/<\/SENIOR>/i',
						'/<\/III>/i',
						'/<\/II>/i',
						'/<\/IV>/i',
						'/<\/V>/i',
					],
					[
						'Jr.',
						'Sr.',
						'III',
						'II',
						'IV',
						'V',
					],
					$particle
				);
			},
			$exploded_bylines
		);
	}

	/**
	 * Retrieves a user by their display name.
	 *
	 * @param string $display_name The display name of the user to find.
	 *
	 * @return WP_User|null
	 */
	public static function get_user_by_display_name( string $display_name ): ?WP_User {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$user = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->users WHERE display_name = %s",
				$display_name
			)
		);

		if ( $user ) {
			return new WP_User( $user );
		}

		return null;
	}
}
