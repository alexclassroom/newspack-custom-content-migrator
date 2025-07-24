<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland\Helpers;

use NewspackCustomContentMigrator\Utils\CommonDataFileIterator\FileImportFactory;

class NNENonJPGList {
	/**
	 * The singleton instance.
	 *
	 * @var $instance NNENonJPGList|null
	 */
	private static $instance = null;

	/**
	 * Flag to track if the list has been initialized.
	 *
	 * @var bool $initialized Flag to track if the list has been initialized.
	 */
	private static $initialized = false;

	/**
	 * The list of non-JPG files.
	 *
	 * @var array< array{ path: string, extension: string, full_file_name: string, file_name: string } > $list List of non-JPG files.
	 */
	public static $list = [];

	/**
	 * Private constructor to prevent creating a new instance of the class via the `new` operator.
	 */
	private function __construct() {
	}

	/**
	 * Returns the singleton instance of this class.
	 *
	 * @param string|null $path_to_non_jpg_list_file The path to the non-JPG list file.
	 *
	 * @return NNENonJPGList The singleton instance.
	 * @throws \Exception If the file path is not provided.
	 */
	public static function get_instance( ?string $path_to_non_jpg_list_file = null ): NNENonJPGList {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		if ( null !== $path_to_non_jpg_list_file ) {
			self::initialize( $path_to_non_jpg_list_file );
		} elseif ( ! self::$initialized ) {
			throw new \Exception( 'NNENonJPGList must be initialized with a valid file path before use' );
		}

		return self::$instance;
	}

	/**
	 * Initializes the list with the contents of the provided non-JPG list file.
	 *
	 * @param string $path_to_non_jpg_list_file The path to the non-JPG list file.
	 *
	 * @return void
	 * @throws \Exception If the file path is not valid.
	 */
	private static function initialize( string $path_to_non_jpg_list_file ): void {
		if ( self::$initialized ) {
			return;
		}

		$csv = ( new FileImportFactory() )->get_file( $path_to_non_jpg_list_file );

		foreach ( $csv->getIterator() as $partial_file_path ) {
			$parts = explode( '/', $partial_file_path['partial_path'] );
			if ( '.' === $parts[0] ) {
				array_shift( $parts );
			}
			$full_file_name                         = $parts[ array_key_last( $parts ) ];
			$file_name                              = strtolower( pathinfo( $full_file_name, PATHINFO_FILENAME ) );
			$extension                              = pathinfo( $full_file_name, PATHINFO_EXTENSION );
			self::$list[ strtoupper( $file_name ) ] = [
				'path'           => implode( '/', $parts ),
				'extension'      => $extension,
				'full_file_name' => $full_file_name,
				'file_name'      => "$file_name.$extension",
			];
		}

		self::$initialized = true;
	}
}
