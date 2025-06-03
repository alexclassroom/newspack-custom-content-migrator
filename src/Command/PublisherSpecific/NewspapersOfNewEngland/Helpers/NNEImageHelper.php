<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland\Helpers;

/**
 * Class NNEImageHelper
 */
class NNEImageHelper {

	/**
	 * Original value of the file attachment field.
	 *
	 * @var string $original_value Original value of the file attachment field.
	 */
	protected string $original_value;

	/**
	 * Flag indicating whether the file name was obtained from the data ID.
	 *
	 * @var bool $from_data_id Flag indicating whether the file name was obtained from the data ID.
	 */
	protected bool $from_data_id = false;

	/**
	 * Data ID of the image.
	 *
	 * @var string $data_id Data ID of the image.
	 */
	protected string $data_id;

	/**
	 * Editorial key of the image.
	 *
	 * @var string|null $editorial_key Editorial key of the image.
	 */
	protected ?string $editorial_key;

	/**
	 * Name of the file.
	 *
	 * @var string $file_name Name of the file.
	 */
	protected string $file_name;

	/**
	 * Full file path of the image.
	 *
	 * @var string $full_file_path Full file path of the image.
	 */
	protected string $full_file_path;

	/**
	 * Site URL.
	 *
	 * @var string $site_url URL of the publisher's site.
	 */
	protected string $site_url;

	/**
	 * Site identifier.
	 *
	 * @var string $site_identifier Identifier of the publisher's site.
	 */
	protected string $site_identifier;

	/**
	 * Local search directory for images.
	 *
	 * @var string $local_search_directory Local search directory for images.
	 */
	protected string $local_search_directory;

	/**
	 * Flag indicating whether the image exists on the site.
	 *
	 * @var bool $exists_on_site Flag indicating whether the image exists on the site.
	 */
	protected bool $exists_on_site;

	/**
	 * Flag indicating whether the image exists in the media library.
	 *
	 * @var bool $exists_in_media_library Flag indicating whether the image exists in the media library.
	 */
	protected bool $exists_in_media_library = false;

	/**
	 * Constructor.
	 *
	 * @param string $file_attachment File attachment field value.
	 * @param string $publisher_site_url URL of the publisher's site.
	 * @param string $local_search_directory Local search directory for images.
	 */
	public function __construct( string $file_attachment, string $publisher_site_url, string $local_search_directory, ?string $editorial_key = null ) {
		$this->original_value         = $file_attachment;
		$this->site_url               = untrailingslashit( $publisher_site_url );
		$this->local_search_directory = untrailingslashit( $local_search_directory );
		$this->editorial_key = $editorial_key;

		$this->initialize();
	}

	/**
	 * Initialize the class properties.
	 *
	 * @return void
	 */
	private function initialize(): void {
		if ( ! str_contains( $this->original_value, 'dataId' ) ) {
			// File attachment is a checksum value.
			$this->file_name = "$this->original_value.jpg";
		} else {
			$this->from_data_id = true;
			$value              = str_replace( '@attachment=', '', $this->original_value );
			// Do we need to make sure $value is a valid URL?

			preg_match( '/dataId=(\d+)\|/i', $value, $matches );
			if ( ! empty( $matches ) ) {
				$this->data_id         = $matches[1];
				$this->file_name       = "{$this->data_id}.jpg";
				$this->site_identifier = substr( $this->data_id, -2 );
			}
		}

		$this->exists_in_media_library = file_exists( $this->get_full_local_file_path() );
		if ( ! $this->exists_in_media_library() ) {
			$search = glob( "{$this->local_search_directory}/*/{$this->file_name}" );
			if ( $search ) {
				$this->full_file_path          = $search[0];
				$this->exists_in_media_library = true;
			}
		}
	}

	/**
	 * Gets the full path to the local file.
	 *
	 * @return string
	 */
	public function get_full_local_file_path(): string {
		if ( ! isset( $this->full_file_path ) ) {
			return $this->local_search_directory . '/' . $this->file_name;
		}

		return $this->full_file_path;
	}

	/**
	 * Returns whether the image exists in the media library.
	 *
	 * @return bool
	 */
	public function exists_in_media_library(): bool {
		return $this->exists_in_media_library;
	}

	/**
	 * Returns the file name.
	 *
	 * @return string
	 */
	public function get_file_name(): string {
		return $this->file_name;
	}

	/**
	 * Returns the best path to the image (i.e. media library or site URL).
	 *
	 * @return string|null
	 */
	public function get_best_path(): ?string {
		if ( $this->exists_in_media_library() ) {
			return $this->get_full_local_file_path();
		} elseif ( $this->exists_on_site() ) {
			return $this->get_file_url();
		}

		return null;
	}

	/**
	 * Returns whether the image exists on the site.
	 *
	 * @return bool
	 */
	public function exists_on_site(): bool {
		if ( ! $this->exists_in_media_library() && $this->has_data_id() ) {
			if ( ! isset( $this->exists_on_site ) ) {
				$status_code          = wp_remote_retrieve_response_code( wp_remote_head( $this->get_file_url() ) );
				$this->exists_on_site = 200 === $status_code;

				return $this->exists_on_site;
			}

			return $this->exists_on_site;
		}

		return false;
	}

	/**
	 * Returns whether the file attachment value has a data ID.
	 *
	 * @return bool
	 */
	public function has_data_id(): bool {
		return $this->from_data_id;
	}

	/**
	 * Gets the URL to the image.
	 *
	 * @return string|null
	 */
	public function get_file_url(): ?string {
		if ( $this->from_data_id ) {
			return "$this->site_url/attachments/$this->site_identifier/$this->file_name";
		}

		return null;
	}

	/**
	 * Returns the data ID of the image, if available.
	 *
	 * @return string|null
	 */
	public function get_data_id(): ?string {
		return $this->data_id;
	}

	/**
	 * Returns the editorial key of the image, if available.
	 *
	 * @return string|null
	 */
	public function get_editorial_key(): ?string {
		return $this->editorial_key;
	}
}
