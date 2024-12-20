<?php

namespace NewspackCustomContentMigrator\ScaffoldMigration\PublisherSpecific\TexasTribune;

/**
 * Needed a well-defined object which I could pass around among private functions within the main TexasTribuneSampleDataMigration class.
 */
class TexasTribuneAttachmentMetadataObject {

	/**
	 * The unchanged image URL.
	 *
	 * @var string $original_url
	 */
	public readonly string $original_url;

	/**
	 * The url-decoded URL.
	 *
	 * @var string $decoded_url
	 */
	public readonly string $decoded_url;

	/**
	 * The original filename for the image.
	 *
	 * @var string $file_name
	 */
	public readonly string $original_file_name;

	/**
	 * URL decoded filename for the image.
	 *
	 * @var string $decoded_file_name
	 */
	public readonly string $decoded_file_name;

	/**
	 * The URL that was used to download the image.
	 *
	 * @var string $download_url
	 */
	public readonly string $download_url;

	/**
	 * The URL that was used to download the image, URL decoded.
	 *
	 * @var string $decoded_download_url
	 */
	public readonly string $decoded_download_url;

	/**
	 * Flag to easily determine is filename has dimensions in it.
	 *
	 * @var bool $has_dimensions
	 */
	public readonly bool $has_dimensions;

	/**
	 * A string representing the dimensions of the image.
	 *
	 * @var string $dimensions
	 */
	public readonly string $dimensions;

	/**
	 * The attachment ID for the image
	 *
	 * @var int $attachment_id
	 */
	public int $attachment_id = 0;

	/**
	 * Constructor.
	 *
	 * @param string $image_url The URL of the image to download.
	 */
	public function __construct( string $image_url ) {
		$this->original_url       = $image_url;
		$this->original_file_name = \WP_CLI\Utils\basename( $image_url );
		$this->decoded_url        = urldecode( $image_url );
		$this->decoded_file_name  = \WP_CLI\Utils\basename( $this->decoded_url );

		$static_image_url = strpos( $image_url, 'static.texastribune.org' );

		$matches = [];
		preg_match( '/http[s?]:\/\/.*([-?](\d+x\d+))\..{3,4}/', $image_url, $matches );

		if ( ! empty( $matches ) ) {
			$this->has_dimensions = true;
			$this->dimensions     = $matches[1];
		} else {
			$this->has_dimensions = false;
			$this->dimensions     = '';
		}

		if ( false !== $static_image_url ) {
			$image_url = substr( $image_url, $static_image_url );

			if ( ! str_starts_with( $image_url, 'http' ) ) {
				$image_url = "https://$image_url";
			}

			$this->download_url         = $image_url;
			$this->decoded_download_url = urldecode( $this->download_url );
		} else {
			$this->download_url         = $image_url;
			$this->decoded_download_url = urldecode( $this->download_url );
		}
	}

	/**
	 * Convenience function that strips out dimensions from file name if it's present.
	 *
	 * @return string
	 */
	public function get_download_url_without_dimensions(): string {
		if ( $this->has_dimensions ) {
			return str_replace( $this->dimensions, '', $this->download_url );
		}

		return $this->download_url;
	}
}
