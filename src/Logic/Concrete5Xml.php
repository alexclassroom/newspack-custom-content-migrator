<?php

namespace NewspackCustomContentMigrator\Logic;

use Exception;
use InvalidArgumentException;
use SimpleXMLElement;
use XMLReader;

/**
 * Class Concrete5XmlFetcher
 *
 * Fetches articles from a Concrete5 XML file or url.
 *
 * It takes care to use very little memory by sipping on only one article at the time.
 */
class Concrete5Xml {

	protected string $xml_file_path;

	public function __construct( string $xml_file_path ) {
		$xml_file_path = trim( $xml_file_path );
		if ( ! str_starts_with( $xml_file_path, 'http' ) ) {
			if ( ! file_exists( $xml_file_path ) ) {
				throw new InvalidArgumentException( 'XML file does not exist: ' . $xml_file_path );
			}
		} elseif ( ! wp_http_validate_url( $xml_file_path ) ) {
			throw new InvalidArgumentException( 'Invalid URL for XML: ' . $xml_file_path );
		}
		$this->xml_file_path = $xml_file_path;
	}

	/**
	 * Returns an iterable generator that yields the content of each <article> element.
	 *
	 * @return iterable
	 * @throws Exception
	 */
	public function get_articles(): iterable {

		$reader = XMLReader::open( $this->xml_file_path );
		if ( ! $reader ) {
			throw new Exception( 'Failed to open XML file: ' . $this->xml_file_path );
		}

		while ( $reader->read() ) {
			// Check if the current node is an <article> element
			if ( $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'article' ) {
				$articleContent = $reader->readInnerXML();
				yield $this->sanitize_article(
					simplexml_load_string(
						"<article>$articleContent</article>"
					)
				);
			}
		}

		// Close the XMLReader after processing.
		$reader->close();
	}

	private function sanitize_article( SimpleXMLElement $article ): array {
		// These are the available fields in the Concrete5 XML file.
		// We sanitize them by trimming and casting them to strings.
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
		];
		$sanitized_article = [];
		foreach ( $fields as $field ) {
			$sanitized_article[ $field ] = trim( (string) ( $article->{$field} ?? '' ) );
		}
		return $sanitized_article;
	}

}
