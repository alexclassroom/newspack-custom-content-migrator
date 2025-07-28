<?php

namespace NewspackCustomContentMigrator\Logic;

class Compare {

	/**
	 * This function will compare the values of two arrays, and return the matching, different, and undetermined values.
	 *
	 * @param array  $keys Specific keys to compare. If empty, all keys will be compared.
	 * @param array  $left First array to compare.
	 * @param array  $right Second array to compare.
	 * @param bool   $strict Whether to use strict comparison or not.
	 * @param string $left_handle The name of the first/left array.
	 * @param string $right_handle The name of the second/right array.
	 *
	 * @return array[]
	 */
	public static function values( array $keys, array $left, array $right, bool $strict = true, string $left_handle = 'left', string $right_handle = 'right' ): array {
		if ( empty( $keys ) ) {
			$keys = array_keys( array_merge( $left, $right ) );
		}

		$matching_rows     = [];
		$different_rows    = [];
		$undetermined_rows = [];

		foreach ( $keys as $key ) {
			$values = [];
			foreach ( [ [ $left_handle, $left ], [ $right_handle, $right ] ] as [ $handle, $set ] ) {
				if ( array_key_exists( $key, $set ) ) {
					$values[ $handle ] = $set[ $key ];
				}
			}

			if ( array_key_exists( $key, $left ) && array_key_exists( $key, $right ) ) {
				if ( $strict ) {
					$comparison = $left[ $key ] === $right[ $key ];
				} else {
					// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
					$comparison = $left[ $key ] == $right[ $key ];
				}
			} else {
				$comparison = null;
			}

			if ( null === $comparison ) {
				$undetermined_rows[ $key ] = $values;
			} elseif ( $comparison ) {
				$matching_rows[ $key ] = $values;
			} else {
				$different_rows[ $key ] = $values;
			}
		}

		return [
			'keys'         => $keys,
			'matching'     => $matching_rows,
			'different'    => $different_rows,
			'undetermined' => $undetermined_rows,
		];
	}
}
