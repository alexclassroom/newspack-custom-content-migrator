<?php
/**
 * Class Concrete5XmlFetcher
 *
 * Fetches articles from a Concrete5 XML file or url.
 *
 * It takes care to use very little memory by sipping on only one article at the time.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Logic;

use Exception;
use InvalidArgumentException;
use SimpleXMLElement;
use XMLReader;

class Concrete5Xml {

	/**
	 * The path or url to the XML file to read.
	 *
	 * @var string File path or url.
	 */
	protected string $xml_file_path;

	/**
	 * Constructor.
	 *
	 * @param string $xml_file_path The path or url to the XML file to read.
	 *
	 * @throws InvalidArgumentException If the XML file does not exist or the URL is invalid.
	 */
	public function __construct( string $xml_file_path ) {
		$xml_file_path = trim( $xml_file_path );
		if ( ! str_starts_with( $xml_file_path, 'http' ) ) {
			if ( ! file_exists( $xml_file_path ) ) {
				throw new InvalidArgumentException( 'XML file does not exist: ' . esc_html( $xml_file_path ) );
			}
		} elseif ( ! wp_http_validate_url( $xml_file_path ) ) {
			throw new InvalidArgumentException( 'Invalid URL for XML: ' . esc_html( $xml_file_path ) );
		}
		$this->xml_file_path = $xml_file_path;
	}

	/**
	 * Get each article from the XML file as a sanitized array.
	 *
	 * @return iterable Iterable that yields an array with strings sanitized from each <article> element.
	 * @throws Exception If the file could not be opened by XMLReader.
	 */
	public function get_articles(): iterable {

		$reader = XMLReader::open( $this->xml_file_path );
		if ( ! $reader ) {
			throw new Exception( 'Failed to open XML file: ' . esc_html( $this->xml_file_path ) );
		}

		while ( $reader->read() ) {
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase, WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase, WordPress.NamingConventions.ValidVariableName.InterpolatedVariableNotSnakeCase
			if ( XMLReader::ELEMENT === $reader->nodeType && 'article' === $reader->name ) {
				$articleContent = $reader->readInnerXML();
				yield $this->sanitize_article(
					simplexml_load_string(
						"<article>$articleContent</article>"
					)
				);
			}
			// phpcs:enable
		}

		$reader->close();
	}

	/**
	 * Get the count of articles in the XML file.
	 * 
	 * @throws \Exception If the file could not be opened by XMLReader.
	 * @return int The count of articles in the XML file.
	 */
	public function get_count(): int {
		$reader = XMLReader::open( $this->xml_file_path );
		if ( ! $reader ) {
			throw new Exception( 'Failed to open XML file: ' . esc_html( $this->xml_file_path ) );
		}

		return iterator_count( $this->get_articles() );
	}

	/**
	 * Sanitize an article element from the XML file into an array with the available fields.
	 *
	 * @param SimpleXMLElement $article A single article element from the XML file.
	 *
	 * @return array Keyed array with sanitized strings from the article element.
	 */
	private function sanitize_article( SimpleXMLElement $article ): array {
		// These are the available fields in the Concrete5 XML file.
		$fields = [
			'title',
			'datePublic',
			'author',
			'url',
			'tags',
			'category',
			'image',
			'lead',
			'description',
			'content',
			'gallery',
		];
		
		$sanitized_article = [];
		foreach ( $fields as $field ) {
			if ( 'gallery' === $field ) {
				// For gallery, convert <image> nodes to array.
				$gallery = [];
				foreach ( $article->gallery->image as $image ) {
					$gallery[] = (string) $image;
				}
				$sanitized_article[ $field ] = $gallery;
				continue;
			} else {
				// Sanitize fields by trimming and casting them to strings.
				$sanitized_article[ $field ] = trim( (string) ( $article->{$field} ?? '' ) );
			}
		}

		return $sanitized_article;
	}
}
