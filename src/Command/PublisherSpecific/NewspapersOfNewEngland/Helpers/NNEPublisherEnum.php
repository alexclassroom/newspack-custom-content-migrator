<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland\Helpers;

enum NNEPublisherEnum: string {
	case AMHERST_BULLETIN    = 'amherst-bulletin';
	case ATHOL_DAILY_NEWS    = 'athol-daily-news';
	case CONCORD_MONITOR     = 'concord-monitor';
	case DAILY_HAMPSHIRE     = 'daily-hampshire-gazette';
	case GREENFIELD_RECORDER = 'greenfield-recorder';
	case MONADNOCK_LEDGER    = 'monadnock-ledger-transcript';
	case VALLEY_NEWS         = 'valley-news';
}
