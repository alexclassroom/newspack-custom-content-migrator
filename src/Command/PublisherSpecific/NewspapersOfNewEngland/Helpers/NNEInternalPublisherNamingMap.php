<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland\Helpers;

class NNEInternalPublisherNamingMap {
	/**
	 * Path to the Newspapers of New England migration materials. Environment dependent.
	 *
	 * @var string $base_path Path to the Newspapers of New England migration materials.
	 */
	protected string $base_path = '/var/www/html/newspapers_of_new_england';

	/**
	 * Map of internal publisher names to directory names.
	 *
	 * @var array|string[] $directory_name_map Map of directory names to publisher IDs.
	 */
	protected array $directory_name_map = [
		NNEPublisherEnum::AMHERST_BULLETIN->value    => 'amherst_bulletin',
		NNEPublisherEnum::ATHOL_DAILY_NEWS->value    => 'athol_daily_news',
		NNEPublisherEnum::CONCORD_MONITOR->value     => 'concord_monitor',
		NNEPublisherEnum::DAILY_HAMPSHIRE->value     => 'daily_hampshire_gazette',
		NNEPublisherEnum::GREENFIELD_RECORDER->value => 'greenfield_recorder',
		NNEPublisherEnum::MONADNOCK_LEDGER->value    => 'monadnock_ledger_transcript',
		NNEPublisherEnum::VALLEY_NEWS->value         => 'valley_news',
	];

	/**
	 * Map of internal publisher names to nice names.
	 *
	 * @var array|string[] $nice_name_map Map of nice names to publisher IDs.
	 */
	protected array $nice_name_map = [
		NNEPublisherEnum::AMHERST_BULLETIN->value    => 'Amherst Bulletin',
		NNEPublisherEnum::ATHOL_DAILY_NEWS->value    => 'Athol Daily News',
		NNEPublisherEnum::CONCORD_MONITOR->value     => 'Concord Monitor',
		NNEPublisherEnum::DAILY_HAMPSHIRE->value     => 'Daily Hampshire Gazette',
		NNEPublisherEnum::GREENFIELD_RECORDER->value => 'Greenfield Recorder',
		NNEPublisherEnum::MONADNOCK_LEDGER->value    => 'Monadnock Ledger Transcript',
		NNEPublisherEnum::VALLEY_NEWS->value         => 'Valley News',
	];

	/**
	 * Map of internal publisher names to hosts.
	 *
	 * @var array|string[] $host_map Map of hosts to publisher IDs.
	 */
	protected array $host_map = [
		NNEPublisherEnum::AMHERST_BULLETIN->value    => 'https://www.amherstbulletin.com',
		NNEPublisherEnum::ATHOL_DAILY_NEWS->value    => 'https://www.atholdailynews.com',
		NNEPublisherEnum::CONCORD_MONITOR->value     => 'https://www.concordmonitor.com',
		NNEPublisherEnum::DAILY_HAMPSHIRE->value     => 'https://www.gazettenet.com',
		NNEPublisherEnum::GREENFIELD_RECORDER->value => 'https://www.recorder.com',
		NNEPublisherEnum::MONADNOCK_LEDGER->value    => 'https://www.ledgertranscript.com',
		NNEPublisherEnum::VALLEY_NEWS->value         => 'https://www.vnews.com/',
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( defined( 'ATOMIC_SITE_ID' ) && ATOMIC_SITE_ID ) {
			$this->base_path = getcwd();
		}
	}

	/**
	 * Get the directory path for a given publisher.
	 *
	 * @param string|NNEPublisherEnum $publisher The publisher.
	 *
	 * @return string
	 */
	public function get_directory_path( string|NNEPublisherEnum $publisher ): string {
		if ( is_string( $publisher ) ) {
			$publisher = NNEPublisherEnum::tryFrom( $publisher );
		}

		return $this->base_path . '/' . $this->directory_name_map[ $publisher->value ];
	}

	/**
	 * Get the nice name for a given publisher.
	 *
	 * @param string|NNEPublisherEnum $publisher The publisher.
	 *
	 * @return string The nice name for the publisher.
	 */
	public function get_nice_name( string|NNEPublisherEnum $publisher ): string {
		if ( is_string( $publisher ) ) {
			$publisher = NNEPublisherEnum::tryFrom( $publisher );
		}

		return $this->nice_name_map[ $publisher->value ];
	}

	/**
	 * Get the host URL for a given publisher.
	 *
	 * @param string|NNEPublisherEnum $publisher The publisher.
	 *
	 * @return string The host URL for the publisher.
	 */
	public function get_host_url( string|NNEPublisherEnum $publisher ): string {
		if ( is_string( $publisher ) ) {
			$publisher = NNEPublisherEnum::tryFrom( $publisher );
		}

		return $this->host_map[ $publisher->value ];
	}
}
