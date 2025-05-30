<?php

namespace NewspackCustomContentMigrator\Command\General;

use NewspackCustomContentMigrator\Command\InterfaceCommand;
use NewspackCustomContentMigrator\Utils\JsonIterator;
use \WP_CLI;

class JSONManipulatorMigrator implements InterfaceCommand {

	/**
	 * Instance.
	 *
	 * @var null|InterfaceCommand Instance.
	 */
	private static $instance = null;

	/**
	 * @var JsonIterator $json_iterator JSON iterator.
	 */
	private JsonIterator $json_iterator;

	/**
	 * Singleton get_instance().
	 *
	 * @return InterfaceCommand|null
	 */
	public static function get_instance() {
		$class = get_called_class();
		if ( null === self::$instance ) {
			self::$instance = new $class();
			self::$instance->json_iterator = new JsonIterator();
		}

		return self::$instance;
	}

	/**
	 * See InterfaceCommand::register_commands.
	 */
	public function register_commands() {
		WP_CLI::add_command(
			'newspack-content-migrator json-unique',
			[ $this, 'cmd_json_unique' ],
			[
				'shortdesc' => 'Takes a JSON file, and returns a new JSON file with unique values for a given attribute',
				'synopsis' => [
					[
						'type'        => 'assoc',
						'name'        => 'file',
						'description' => 'The full path to the JSON file.',
						'optional'    => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'attribute',
						'description' => 'The `.` separated JSON path to the attribute to make unique.',
						'optional'    => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'output',
						'description' => 'The output file to write to.',
						'optional'    => false,
					],
				]
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator json-intersect',
			[ $this, 'cmd_json_intersect' ],
			[
				'shortdesc' => 'Takes a JSON file, and returns a new JSON file with values matching a predefined list',
				'synopsis' => [
					[
						'type'        => 'assoc',
						'name'        => 'file',
						'description' => 'The full path to the JSON file.',
						'optional'    => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'attribute',
						'description' => 'The attribute to use for the intersection.',
						'optional'    => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'values',
						'description' => 'Comma-separated list of values',
						'optional'    => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'output',
						'description' => 'The output file to write to.',
						'optional'    => false,
					],
				]
			]
		);
	}

	/**
	 * @param $args
	 * @param $assoc_args
	 *
	 * @return void
	 */
	public function cmd_json_unique( $args, $assoc_args ) {
		$json_path =  $assoc_args['file'];
		$attribute = $assoc_args['attribute'];
		$output_path = $assoc_args['output'];

		$unique_values = [];

		foreach ( $this->json_iterator->items( $json_path ) as $item ) {
			$json_attribute_path = explode( '.', $attribute );
			$destination = array_pop( $json_attribute_path );

			foreach ( $json_attribute_path as $attribute_path ) {
				if ( is_numeric( $attribute_path ) ) {
					$item = $item[ $attribute_path ];
				} else {
					$item = $item->$attribute_path;
				}
			}

			if ( is_numeric( $destination ) ) {
				if ( ! isset( $unique_values[ $item[ $destination ] ] ) ) {
					$unique_values[ $item[ $destination ] ] = $item[ $destination ];
				}
			} else {
				if ( ! isset( $unique_values[ $item->$destination ] ) ) {
					$unique_values[ $item->$destination ] = $item;
				}
			}
		}

		if ( empty( $unique_values ) ) {
			WP_CLI::error( 'No unique values found.' );
		}

		file_put_contents( $output_path, json_encode( array_values( $unique_values ) ) );
	}

	public function cmd_json_intersect( $args, $assoc_args ) {
		//TODO: implement
	}
}