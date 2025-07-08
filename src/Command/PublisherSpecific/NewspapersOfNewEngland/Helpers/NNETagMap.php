<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland\Helpers;

/**
 * Class NNETagMap
 *
 * Provides a static mapping from old tags to new categories, subcategories, and tags
 * based on the old_tag_to_new_heirarchy.csv file.
 */
class NNETagMap {
	/**
	 * Mapping from old tags to new categories and tags
	 *
	 * @var array
	 */
	public static $mapping = [
		'Greenfield MA'                           => [
			'category' => null,
			'tags'     => [
				'Greenfield MA',
			],
		],
		'Amherst MA'                              => [
			'category' => null,
			'tags'     => [
				'Amherst MA',
			],
		],
		'Northampton MA'                          => [
			'category' => null,
			'tags'     => [
				'Northampton MA',
			],
		],
		'Letter to the Editors'                   => [
			'category' => [
				'name'   => 'Letters',
				'parent' => 'Opinion',
			],
			'tags'     => null,
		],
		'Concord NH'                              => [
			'category' => null,
			'tags'     => [
				'Concord NH',
			],
		],
		'Letters'                                 => [
			'category' => [
				'name'   => 'Letters',
				'parent' => 'Opinion',
			],
			'tags'     => null,
		],
		'Peterborough NH'                         => [
			'category' => null,
			'tags'     => [
				'Peterborough NH',
			],
		],
		'Business'                                => [
			'category' => [
				'name'   => 'Business',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'education'                               => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'DEERFIELD MA'                            => [
			'category' => null,
			'tags'     => [
				'Deerfield MA',
			],
		],
		'high schools'                            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Jaffrey NH'                              => [
			'category' => null,
			'tags'     => [
				'Jaffrey NH',
			],
		],
		'UMASS'                                   => [
			'category' => [
				'name'   => 'UMass',
				'parent' => 'Sports',
			],
			'tags'     => null,
		],
		'ATHOL MA'                                => [
			'category' => null,
			'tags'     => [
				'Athol MA',
			],
		],
		'MONTAGUE MA'                             => [
			'category' => null,
			'tags'     => [
				'Montague MA',
			],
		],
		'hadley ma'                               => [
			'category' => null,
			'tags'     => [
				'Hadley MA',
			],
		],
		'ORANGE MA'                               => [
			'category' => null,
			'tags'     => [
				'Orange MA',
			],
		],
		'Easthampton MA'                          => [
			'category' => null,
			'tags'     => [
				'Easthampton MA',
			],
		],
		'leverett MA'                             => [
			'category' => null,
			'tags'     => [
				'Leverett MA',
			],
		],
		'TURNERS FALLS MA'                        => [
			'category' => null,
			'tags'     => [
				'Turners Falls MA',
			],
		],
		'Lebanon NH'                              => [
			'category' => null,
			'tags'     => [
				'Lebanon NH',
			],
		],
		'High school sports'                      => [
			'category' => [
				'name'   => 'High School & Youth',
				'parent' => 'Sports',
			],
			'tags'     => null,
		],
		'Rindge nh'                               => [
			'category' => null,
			'tags'     => [
				'Rindge NH',
			],
		],
		'NORTHFIELD MA'                           => [
			'category' => null,
			'tags'     => [
				'Northfield MA',
			],
		],
		'crime'                                   => [
			'category' => [
				'name'   => 'Police-Fire-Courts',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'shutesbury MA'                           => [
			'category' => null,
			'tags'     => [
				'Shutesbury MA',
			],
		],
		'SOUTH DEERFIELD MA'                      => [
			'category' => null,
			'tags'     => [
				'South Deerfield MA',
			],
		],
		'POLITICS'                                => [
			'category' => [
				'name'   => 'Politics',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Environment'                             => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'Food'                                    => [
			'category' => [
				'name'   => 'Food',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'SUNDERLAND MA'                           => [
			'category' => null,
			'tags'     => [
				'Sunderland MA',
			],
		],
		'Hanover NH'                              => [
			'category' => null,
			'tags'     => [
				'Hanover NH',
			],
		],
		'housing'                                 => [
			'category' => null,
			'tags'     => [
				'Housing',
			],
		],
		'Whately Ma'                              => [
			'category' => null,
			'tags'     => [
				'Whately MA',
			],
		],
		'Granite Geek'                            => [
			'category' => null,
			'tags'     => [
				'Granite Geek',
			],
		],
		'Hancock NH'                              => [
			'category' => null,
			'tags'     => [
				'Hancock NH',
			],
		],
		'dublin nh'                               => [
			'category' => null,
			'tags'     => [
				'Dublin NH',
			],
		],
		'Concord High School'                     => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'music'                                   => [
			'category' => [
				'name'   => 'Entertainment',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'umass amherst'                           => [
			'category' => [
				'name'   => 'Umass',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Health Care'                             => [
			'category' => null,
			'tags'     => [
				'Health',
			],
		],
		'Bow High School'                         => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'wilton nh'                               => [
			'category' => null,
			'tags'     => [
				'Wilton NH',
			],
		],
		'Bernardston MA'                          => [
			'category' => null,
			'tags'     => [
				'Bernardston MA',
			],
		],
		'Pelham MA'                               => [
			'category' => null,
			'tags'     => [
				'Pelham MA',
			],
		],
		'South Hadley MA'                         => [
			'category' => null,
			'tags'     => [
				'South Hadley MA',
			],
		],
		'nature'                                  => [
			'category' => [
				'name'   => 'Outdoors',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Florence MA'                             => [
			'category' => null,
			'tags'     => [
				'Florence MA',
			],
		],
		'New Ipswich NH'                          => [
			'category' => null,
			'tags'     => [
				'New Ipswich NH',
			],
		],
		'Antrim NH'                               => [
			'category' => null,
			'tags'     => [
				'Antrim NH',
			],
		],
		'Hartford VT'                             => [
			'category' => null,
			'tags'     => [
				'Hartford VT',
			],
		],
		'court'                                   => [
			'category' => [
				'name'   => 'Police-Fire-Courts',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'wendell ma'                              => [
			'category' => null,
			'tags'     => [
				'Wendall MA',
			],
		],
		'ConVal'                                  => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'SHELBURNE FALLS MA'                      => [
			'category' => null,
			'tags'     => [
				'Shelburne Falls MA',
			],
		],
		'northamptonma'                           => [
			'category' => null,
			'tags'     => [
				'Northampton MA',
			],
		],
		'Conway MA'                               => [
			'category' => null,
			'tags'     => [
				'Conway NH',
			],
		],
		'Claremont NH'                            => [
			'category' => null,
			'tags'     => [
				'Claremont NH',
			],
		],
		'belchertown ma'                          => [
			'category' => null,
			'tags'     => [
				'Belchertown MA',
			],
		],
		'Charlemont Ma'                           => [
			'category' => null,
			'tags'     => [
				'Charlemont MA',
			],
		],
		'holyoke ma'                              => [
			'category' => null,
			'tags'     => [
				'Holyoke MA',
			],
		],
		'police'                                  => [
			'category' => [
				'name'   => 'Police-Fire-Courts',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Education NH'                            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Hopkinton NH'                            => [
			'category' => null,
			'tags'     => [
				'Hopkinton NH',
			],
		],
		'health'                                  => [
			'category' => null,
			'tags'     => [
				'Health',
			],
		],
		'NORTHAMPTON'                             => [
			'category' => null,
			'tags'     => [
				'Northampton MA',
			],
		],
		'Erving MA'                               => [
			'category' => null,
			'tags'     => [
				'Erving MA',
			],
		],
		'Greenfield nh'                           => [
			'category' => null,
			'tags'     => [
				'Greenfield NH',
			],
		],
		'Dartmouth College'                       => [
			'category' => [
				'name'   => 'Dartmouth',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'CLIMATE CHANGE'                          => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'Legislature'                             => [
			'category' => [
				'name'   => 'Politics',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Shelburne Ma'                            => [
			'category' => null,
			'tags'     => [
				'Shelburne MA',
			],
		],
		'Gill Ma'                                 => [
			'category' => null,
			'tags'     => [
				'Gill MA',
			],
		],
		'temple nh'                               => [
			'category' => null,
			'tags'     => [
				'Temple NH',
			],
		],
		'Merrimack Valley High School'            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'francestown nh'                          => [
			'category' => null,
			'tags'     => [
				'Francestown NH',
			],
		],
		'Ashfield Ma'                             => [
			'category' => null,
			'tags'     => [
				'Ashfield MA',
			],
		],
		'Pembroke Academy'                        => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Buckland Ma'                             => [
			'category' => null,
			'tags'     => [
				'Buckland MA',
			],
		],
		'easthamptonma'                           => [
			'category' => null,
			'tags'     => [
				'Easthampton MA',
			],
		],
		'Greenville NH'                           => [
			'category' => null,
			'tags'     => [
				'Greenville NH',
			],
		],
		'John Stark Regional High School'         => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'town meeting 2023'                       => [
			'category' => null,
			'tags'     => [
				'Town Meeting',
			],
		],
		'Town Meeting 2024'                       => [
			'category' => null,
			'tags'     => [
				'Town Meeting',
			],
		],
		'new salem ma'                            => [
			'category' => null,
			'tags'     => [
				'New Salem MA',
			],
		],
		'EASTHAMPTON'                             => [
			'category' => null,
			'tags'     => [
				'Easthampton MA',
			],
		],
		'Bow NH'                                  => [
			'category' => null,
			'tags'     => [
				'Bow NH',
			],
		],
		'coe-brown northwood academy'             => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'outdoors'                                => [
			'category' => [
				'name'   => 'Outdoors',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Colrain Ma'                              => [
			'category' => null,
			'tags'     => [
				'Colrain MA',
			],
		],
		'Farming'                                 => [
			'category' => null,
			'tags'     => [
				'Agriculture',
			],
		],
		'State House'                             => [
			'category' => [
				'name'   => 'Politics',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Leyden MA'                               => [
			'category' => null,
			'tags'     => [
				'Leyden MA',
			],
		],
		'amherst'                                 => [
			'category' => null,
			'tags'     => [
				'Amherst MA',
			],
		],
		'Real Estate'                             => [
			'category' => null,
			'tags'     => [
				'Real Estate',
			],
		],
		'Kearsarge Regional High School'          => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Royalston MA'                            => [
			'category' => null,
			'tags'     => [
				'Royalston MA',
			],
		],
		'Hatfield MA'                             => [
			'category' => null,
			'tags'     => [
				'Hatfield MA',
			],
		],
		'legislation'                             => [
			'category' => [
				'name'   => 'Politics',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'UMass football'                          => [
			'category' => null,
			'tags'     => [
				'UMass Football',
			],
		],
		'lyndeborough nh'                         => [
			'category' => null,
			'tags'     => [
				'Lyndeborough NH',
			],
		],
		'umass hockey'                            => [
			'category' => null,
			'tags'     => [
				'UMass Hockey',
			],
		],
		'wildlife'                                => [
			'category' => [
				'name'   => 'Outdoors',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'SCHOOLS'                                 => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'UMass basketball'                        => [
			'category' => null,
			'tags'     => [
				'UMass Basketball',
			],
		],
		'mental health'                           => [
			'category' => null,
			'tags'     => [
				'Health',
			],
		],
		'goinggreen'                              => [
			'category' => null,
			'tags'     => [
				'Going Green',
			],
		],
		'Environmental Reporting Lab'             => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'MARIJUANA'                               => [
			'category' => null,
			'tags'     => [
				'Marijuana',
			],
		],
		'Woodstock VT'                            => [
			'category' => null,
			'tags'     => [
				'Woodstock VT',
			],
		],
		'amherst college'                         => [
			'category' => null,
			'tags'     => [
				'Amherst College',
			],
		],
		'Franklin NH'                             => [
			'category' => null,
			'tags'     => [
				'Franklin NH',
			],
		],
		'Town Meeting'                            => [
			'category' => null,
			'tags'     => [
				'Town Meeting',
			],
		],
		'bennington nh'                           => [
			'category' => null,
			'tags'     => [
				'Bennington NH',
			],
		],
		'Norwich VT'                              => [
			'category' => null,
			'tags'     => [
				'Norwich VT',
			],
		],
		'Southampton MA'                          => [
			'category' => null,
			'tags'     => [
				'Southampton MA',
			],
		],
		'Recreation'                              => [
			'category' => [
				'name'   => 'Outdoors',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Granby MA'                               => [
			'category' => null,
			'tags'     => [
				'Granby MA',
			],
		],
		'theater'                                 => [
			'category' => [
				'name'   => 'Entertainment',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Out and About'                           => [
			'category' => [
				'name'   => 'Entertainment',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Concord School District'                 => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Mason NH'                                => [
			'category' => null,
			'tags'     => [
				'Mason NH',
			],
		],
		'White River Junction VT'                 => [
			'category' => null,
			'tags'     => [
				'White River Junction VT',
			],
		],
		'Windsor VT'                              => [
			'category' => null,
			'tags'     => [
				'Windsor VT',
			],
		],
		'courts'                                  => [
			'category' => [
				'name'   => 'Police-Fire-Courts',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Smith College'                           => [
			'category' => null,
			'tags'     => [
				'Smith College',
			],
		],
		'Franklin County Superior Court'          => [
			'category' => [
				'name'   => 'Police-Fire-Courts',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Belmont High School'                     => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'amherstma'                               => [
			'category' => null,
			'tags'     => [
				'Amherst MA',
			],
		],
		'animals'                                 => [
			'category' => null,
			'tags'     => [
				'Wildlife',
			],
		],
		'local government'                        => [
			'category' => [
				'name'   => 'Town-City-Government',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'religion'                                => [
			'category' => [
				'name'   => 'Faith & Religion',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'South Hadley'                            => [
			'category' => null,
			'tags'     => [
				'South Hadley MA',
			],
		],
		'Newport NH'                              => [
			'category' => null,
			'tags'     => [
				'Newport NH',
			],
		],
		'Warwick MA'                              => [
			'category' => null,
			'tags'     => [
				'Warwick MA',
			],
		],
		'speaking of nature'                      => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'Bishop Brady High School'                => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'events'                                  => [
			'category' => [
				'name'   => 'Calendar',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'MonitorMarquee'                          => [
			'category' => null,
			'tags'     => [
				'Monitor Marquee',
			],
		],
		'Valley Calendar'                         => [
			'category' => [
				'name'   => 'Calendar',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'birds'                                   => [
			'category' => null,
			'tags'     => [
				'Birds',
				'Wildlife',
			],
		],
		'Wilmot NH'                               => [
			'category' => null,
			'tags'     => [
				'Wilmot NH',
			],
		],
		'Enfield NH'                              => [
			'category' => null,
			'tags'     => [
				'Enfield NH',
			],
		],
		'Greenfield'                              => [
			'category' => null,
			'tags'     => [
				'Greenfield MA',
			],
		],
		'SPRINGFIELD MA'                          => [
			'category' => null,
			'tags'     => [
				'Springfield MA',
			],
		],
		'On the trail'                            => [
			'category' => [
				'name'   => 'Politics',
				'parent' => 'News',
			],
			'tags'     => [
				'On the Trail',
			],
		],
		'film'                                    => [
			'category' => [
				'name'   => 'Entertainment',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Heath Ma'                                => [
			'category' => null,
			'tags'     => [
				'Heath MA',
			],
		],
		'Bill Danielson'                          => [
			'category' => null,
			'tags'     => [
				'Birds',
				'Wildlife',
			],
		],
		'petersham ma'                            => [
			'category' => null,
			'tags'     => [
				'Petersham MA',
			],
		],
		'hanover high school'                     => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'WESTHAMPTON MA'                          => [
			'category' => null,
			'tags'     => [
				'Westhampton MA',
			],
		],
		'lebanon high'                            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Greenfield Community College'            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'ENTERTAINMENT'                           => [
			'category' => [
				'name'   => 'Entertainment',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'belchertown'                             => [
			'category' => null,
			'tags'     => [
				'Belchertown MA',
			],
		],
		'Climate Change at Home'                  => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'technology'                              => [
			'category' => null,
			'tags'     => [
				'Science & Technology',
			],
		],
		'farms'                                   => [
			'category' => null,
			'tags'     => [
				'Agriculture',
			],
		],
		'birding'                                 => [
			'category' => null,
			'tags'     => [
				'Birds',
				'Wildlife',
			],
		],
		'Phillipston MA'                          => [
			'category' => null,
			'tags'     => [
				'Phillipston MA',
			],
		],
		'State Government'                        => [
			'category' => [
				'name'   => 'Politics',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'West Lebanon NH'                         => [
			'category' => null,
			'tags'     => [
				'West Lebanon NH',
			],
		],
		'Henniker NH'                             => [
			'category' => null,
			'tags'     => [
				'Henniker NH',
			],
		],
		'Hanover High'                            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Thetford Academy'                        => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Hawley Ma'                               => [
			'category' => null,
			'tags'     => [
				'Hawley MA',
			],
		],
		'Northwood NH'                            => [
			'category' => null,
			'tags'     => [
				'Northwood NH',
			],
		],
		'Hartford High School'                    => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Canaan NH'                               => [
			'category' => null,
			'tags'     => [
				'Canaan NH',
			],
		],
		'Williamsburg MA'                         => [
			'category' => null,
			'tags'     => [
				'Williamsburg MA',
			],
		],
		'Pembroke NH'                             => [
			'category' => null,
			'tags'     => [
				'Pembroke NH',
			],
		],
		'Lebanon High School'                     => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'State House 2025'                        => [
			'category' => [
				'name'   => 'Politics',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Hopkinton Middle High School'            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Books'                                   => [
			'category' => null,
			'tags'     => [
				'Books',
			],
		],
		'Penacook NH'                             => [
			'category' => null,
			'tags'     => [
				'Penacook NH',
			],
		],
		'Loudon NH'                               => [
			'category' => null,
			'tags'     => [
				'Loudon NH',
			],
		],
		'Royalton VT'                             => [
			'category' => null,
			'tags'     => [
				'Royalton VT',
			],
		],
		'Hartford High'                           => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'solar'                                   => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'Boscawen NH'                             => [
			'category' => null,
			'tags'     => [
				'Boscawen NH',
			],
		],
		'Bradford VT'                             => [
			'category' => null,
			'tags'     => [
				'Bradford VT',
			],
		],
		'gardening'                               => [
			'category' => [
				'name'   => 'Home & Garden',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Connecticut River'                       => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'Winnisquam High School'                  => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'jones library'                           => [
			'category' => null,
			'tags'     => [
				'Jones Library',
			],
		],
		'Hunting'                                 => [
			'category' => [
				'name'   => 'Outdoors',
				'parent' => 'Arts & Life',
			],
			'tags'     => [
				'Hunting',
				'Wildlife',
			],
		],
		'Woodstock High School'                   => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'sharon nh'                               => [
			'category' => null,
			'tags'     => [
				'Sharon NH',
			],
		],
		'Stevens High School'                     => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Valley Bounty'                           => [
			'category' => null,
			'tags'     => [
				'Valley Bounty',
			],
		],
		'Thetford VT'                             => [
			'category' => null,
			'tags'     => [
				'Thetford VT',
			],
		],
		'White River Valley High School'          => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Deeds'                                   => [
			'category' => null,
			'tags'     => [
				'Real Estate',
			],
		],
		'science'                                 => [
			'category' => null,
			'tags'     => [
				'Science & Technology',
			],
		],
		'Frontier Regional School'                => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Conant High School'                      => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Dartmouth athletics'                     => [
			'category' => [
				'name'   => 'Dartmouth',
				'parent' => 'Sports',
			],
			'tags'     => null,
		],
		'Greenfield District Court'               => [
			'category' => [
				'name'   => 'Police-Fire-Courts',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Concord Christian Academy'               => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Randolph VT'                             => [
			'category' => null,
			'tags'     => [
				'Randolph VT',
			],
		],
		'Warner NH'                               => [
			'category' => null,
			'tags'     => [
				'Warner NH',
			],
		],
		'Weare NH'                                => [
			'category' => null,
			'tags'     => [
				'Weare NH',
			],
		],
		'Windsor High School'                     => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'solid waste'                             => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'Franklin County Technical School'        => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Allenstown NH'                           => [
			'category' => null,
			'tags'     => [
				'Allenstown NH',
			],
		],
		'frontier'                                => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Hartland VT'                             => [
			'category' => null,
			'tags'     => [
				'Hartland VT',
			],
		],
		'Faith Matters'                           => [
			'category' => [
				'name'   => 'Faith & Religion',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Haydenville MA'                          => [
			'category' => null,
			'tags'     => [
				'Haydenville MA',
			],
		],
		'City Council'                            => [
			'category' => [
				'name'   => 'Town-City-Government',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Millers Falls MA'                        => [
			'category' => null,
			'tags'     => [
				'Millers Falls MA',
			],
		],
		'Mascoma High School'                     => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'New London NH'                           => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Canterbury NH'                           => [
			'category' => null,
			'tags'     => [
				'Canterbury NH',
			],
		],
		'Dartmouth Health'                        => [
			'category' => null,
			'tags'     => [
				'Dartmouth Health',
			],
		],
		'Rivendell Academy'                       => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'dartmouth'                               => [
			'category' => [
				'name'   => 'Dartmouth',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Higher Education'                        => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Pittsfield NH'                           => [
			'category' => null,
			'tags'     => [
				'Pittsfield NH',
			],
		],
		'Epsom NH'                                => [
			'category' => null,
			'tags'     => [
				'Epsom NH',
			],
		],
		'Oxbow High School'                       => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'coronavirus'                             => [
			'category' => null,
			'tags'     => [
				'Covid 19',
			],
		],
		'hampshire regional'                      => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Newport High School'                     => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Lyme NH'                                 => [
			'category' => null,
			'tags'     => [
				'Lyme NH',
			],
		],
		'CANNABIS'                                => [
			'category' => null,
			'tags'     => [
				'Marijuana',
			],
		],
		'Fairlee VT'                              => [
			'category' => null,
			'tags'     => [
				'Fairlee VT',
			],
		],
		'Bethel VT'                               => [
			'category' => null,
			'tags'     => [
				'Bethel VT',
			],
		],
		'mascenic'                                => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'christmas'                               => [
			'category' => [
				'name'   => 'Faith & Religion',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'faith'                                   => [
			'category' => [
				'name'   => 'Faith & Religion',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'affordable housing'                      => [
			'category' => null,
			'tags'     => [
				'Housing',
			],
		],
		'granby'                                  => [
			'category' => null,
			'tags'     => [
				'Granby MA',
			],
		],
		'University of Massachusetts Amherst'     => [
			'category' => [
				'name'   => 'UMass',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Cornish NH'                              => [
			'category' => null,
			'tags'     => [
				'Cornish NH',
			],
		],
		'school committee'                        => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'covid-19'                                => [
			'category' => null,
			'tags'     => [
				'Covid 19',
			],
		],
		'southamptonma'                           => [
			'category' => null,
			'tags'     => [
				'Southampton MA',
			],
		],
		'holyoke'                                 => [
			'category' => null,
			'tags'     => [
				'Holyoke MA',
			],
		],
		'graduation'                              => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'HolyokeMA'                               => [
			'category' => null,
			'tags'     => [
				'Holyoke MA',
			],
		],
		'CONSERVATION'                            => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'Pioneer Valley Regional School District' => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Sharon VT'                               => [
			'category' => null,
			'tags'     => [
				'Sharon VT',
			],
		],
		'hunt'                                    => [
			'category' => [
				'name'   => 'Outdoors',
				'parent' => 'Sports',
			],
			'tags'     => [
				'Hunting',
			],
		],
		'Mount Holyoke College'                   => [
			'category' => null,
			'tags'     => [
				'Mount Holyoke College',
			],
		],
		'hampshire college'                       => [
			'category' => null,
			'tags'     => [
				'Hampshire College',
			],
		],
		'Hopkins Academy'                         => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'CISA'                                    => [
			'category' => null,
			'tags'     => [
				'Agriculture',
			],
		],
		'ROWE MA'                                 => [
			'category' => null,
			'tags'     => [
				'Rowe MA',
			],
		],
		'Concord casino'                          => [
			'category' => null,
			'tags'     => [
				'Concord Casino',
			],
		],
		'Fishing'                                 => [
			'category' => [
				'name'   => 'Outdoors',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'CONGRESS'                                => [
			'category' => [
				'name'   => 'Politics',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Strafford VT'                            => [
			'category' => null,
			'tags'     => [
				'Strafford VT',
			],
		],
		'recycling'                               => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'orford nh'                               => [
			'category' => null,
			'tags'     => [
				'Orford NH',
			],
		],
		'hometown heroes'                         => [
			'category' => null,
			'tags'     => [
				'Hometown Heroes',
			],
		],
		'Contoocook NH'                           => [
			'category' => null,
			'tags'     => [
				'Contoocook NH',
			],
		],
		"northwestern district attorney's office" => [
			'category' => [
				'name'   => 'Police-Fire-Courts',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Webster NH'                              => [
			'category' => null,
			'tags'     => [
				'Webster NH',
			],
		],
		'Hillsboro-Deering'                       => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Athol'                                   => [
			'category' => null,
			'tags'     => [
				'Athol MA',
			],
		],
		'Chesterfield MA'                         => [
			'category' => null,
			'tags'     => [
				'Chesterfield MA',
			],
		],
		'Woodstock High'                          => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Winchendon MA'                           => [
			'category' => null,
			'tags'     => [
				'Winchendon MA',
			],
		],
		'Franklin County Fairgrounds'             => [
			'category' => null,
			'tags'     => [
				'Franklin County Fairgrounds',
			],
		],
		'garden'                                  => [
			'category' => [
				'name'   => 'Home & Garden',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'lebanon'                                 => [
			'category' => null,
			'tags'     => [
				'Lebanon NH',
			],
		],
		'Dartmouth Sports'                        => [
			'category' => [
				'name'   => 'Dartmouth',
				'parent' => 'Sports',
			],
			'tags'     => null,
		],
		'Pioneer Valley Regional School'          => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'school notes'                            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Leeds MA'                                => [
			'category' => null,
			'tags'     => [
				'Leeds MA',
			],
		],
		'Greenfield Public Library'               => [
			'category' => null,
			'tags'     => [
				'Greenfield Public Library',
			],
		],
		'Historic pages'                          => [
			'category' => null,
			'tags'     => [
				'MLT 175th Ann',
			],
		],
		'Mohawk Trail Regional School'            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Claremont City Council'                  => [
			'category' => [
				'name'   => 'Town-City-Government',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Windsor High'                            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Earth Matters'                           => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'disasters'                               => [
			'category' => null,
			'tags'     => [
				'Natural Disasters',
			],
		],
		'Hampshire Superior Court'                => [
			'category' => [
				'name'   => 'Police-Fire-Courts',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'food photo contest'                      => [
			'category' => null,
			'tags'     => [
				'Food Photo Contest',
			],
		],
		'Greenfield High School'                  => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'academy of music'                        => [
			'category' => null,
			'tags'     => [
				'Academy of Music',
			],
		],
		'Chichester NH'                           => [
			'category' => null,
			'tags'     => [
				'Chichester NH',
			],
		],
		'poetry'                                  => [
			'category' => null,
			'tags'     => [
				'Books',
			],
		],
		'Turners Falls High School'               => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'spirituality'                            => [
			'category' => [
				'name'   => 'Faith & Religion',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Deerfield Academy'                       => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Cummington MA'                           => [
			'category' => null,
			'tags'     => [
				'Cummington MA',
			],
		],
		'Stevens High'                            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Bishop Brady'                            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'deerfield river'                         => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'A Life'                                  => [
			'category' => null,
			'tags'     => [
				'A Life',
			],
		],
		'landfills'                               => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'Franklin Pierce'                         => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Plainfield NH'                           => [
			'category' => null,
			'tags'     => [
				'Plainfield NH',
			],
		],
		'Mohawk Trail Regional School District'   => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Tunbridge VT'                            => [
			'category' => null,
			'tags'     => [
				'Tunbridge VT',
			],
		],
		'Mahar'                                   => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'farm'                                    => [
			'category' => null,
			'tags'     => [
				'Agriculture',
			],
		],
		'Chelsea VT'                              => [
			'category' => null,
			'tags'     => [
				'Chelsea VT',
			],
		],
		'Dunbarton NH'                            => [
			'category' => null,
			'tags'     => [
				'Dunbarton NH',
			],
		],
		'Haverhill NH'                            => [
			'category' => null,
			'tags'     => [
				'Haverhill NH',
			],
		],
		'Selectboard'                             => [
			'category' => [
				'name'   => 'Town-City-Government',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'trash'                                   => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'hadleyma'                                => [
			'category' => null,
			'tags'     => [
				'Hadley MA',
			],
		],
		'Montague Center MA'                      => [
			'category' => null,
			'tags'     => [
				'Montague MA',
			],
		],
		'sustainability'                          => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'Smith Academy'                           => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Plainfield MA'                           => [
			'category' => null,
			'tags'     => [
				'Plainfield MA',
			],
		],
		'Dartmouth Hitchcock Medical Center'      => [
			'category' => null,
			'tags'     => [
				'Dartmouth Health',
				'Health',
			],
		],
		'flooding'                                => [
			'category' => null,
			'tags'     => [
				'Natural Disasters',
			],
		],
		'Charlestown NH'                          => [
			'category' => null,
			'tags'     => [
				'Charlestown NH',
			],
		],
		'Quabbin Reservoir'                       => [
			'category' => null,
			'tags'     => [
				'Quabbin Reservoir',
			],
		],
		'Greenfield City Council'                 => [
			'category' => [
				'name'   => 'Town-City-Government',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Quechee VT'                              => [
			'category' => null,
			'tags'     => [
				'Quechee VT',
			],
		],
		'HometownHeroes2022'                      => [
			'category' => null,
			'tags'     => [
				'Hometown Heroes',
			],
		],
		'Gill-Montague Regional School District'  => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Salisbury NH'                            => [
			'category' => null,
			'tags'     => [
				'Salisbury NH',
			],
		],
		'Franklin Tech'                           => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Orange Police Department'                => [
			'category' => null,
			'tags'     => [
				'Police Log',
			],
		],
		'goshen ma'                               => [
			'category' => null,
			'tags'     => [
				'Goshen MA',
			],
		],
		'flowers'                                 => [
			'category' => [
				'name'   => 'Home & Garden',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Baystate Health'                         => [
			'category' => null,
			'tags'     => [
				'Baystate Health',
				'Health',
			],
		],
		'Southampton'                             => [
			'category' => null,
			'tags'     => [
				'Southampton MA',
			],
		],
		'Westfield MA'                            => [
			'category' => null,
			'tags'     => [
				'Westfield MA',
			],
		],
		'Ralph C Mahar Regional School'           => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'RESTAURANTS'                             => [
			'category' => [
				'name'   => 'Food',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Williston'                               => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Grafton NH'                              => [
			'category' => null,
			'tags'     => [
				'Grafton NH',
			],
		],
		'Peterborough'                            => [
			'category' => null,
			'tags'     => [
				'Peterborough NH',
			],
		],
		'state legislature'                       => [
			'category' => [
				'name'   => 'Politics',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Mascoma High'                            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Grantham NH'                             => [
			'category' => null,
			'tags'     => [
				'Grantham NH',
			],
		],
		'Frontier Regional'                       => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Merrimack Valley School District'        => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Ralph C. Mahar Regional School'          => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Westhamptonma'                           => [
			'category' => null,
			'tags'     => [
				'Westhampton MA',
			],
		],
		'South Royalton VT'                       => [
			'category' => null,
			'tags'     => [
				'South Royalton VT',
			],
		],
		'PFAS'                                    => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'UNH'                                     => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Monitor Marquee'                         => [
			'category' => null,
			'tags'     => [
				'Monitor Marquee',
			],
		],
		'Sharon Academy'                          => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Green River Festival'                    => [
			'category' => null,
			'tags'     => [
				'Green River Festival',
			],
		],
		'French King Bridge'                      => [
			'category' => null,
			'tags'     => [
				'French King Bridge',
			],
		],
		'Gilford NH'                              => [
			'category' => null,
			'tags'     => [
				'Gilford NH',
			],
		],
		'Worthington MA'                          => [
			'category' => null,
			'tags'     => [
				'Worthington MA',
			],
		],
		'erving elementary school'                => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Baystate Franklin Medical Center'        => [
			'category' => null,
			'tags'     => [
				'Baystate Health',
				'Health',
			],
		],
		'Book review'                             => [
			'category' => null,
			'tags'     => [
				'Books',
			],
		],
		'climate'                                 => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'Bow School District'                     => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'cooley dickinson hospital'               => [
			'category' => null,
			'tags'     => [
				'Cooley Dickinson Hospital',
				'Health',
			],
		],
		'vershire vt'                             => [
			'category' => null,
			'tags'     => [
				'Vershire VT',
			],
		],
		'Springfield VT'                          => [
			'category' => null,
			'tags'     => [
				'Springfield VT',
			],
		],
		'HEALTHCARE'                              => [
			'category' => null,
			'tags'     => [
				'Health',
			],
		],
		'Sunapee NH'                              => [
			'category' => null,
			'tags'     => [
				'Sunapee NH',
			],
		],
		'Concord Planning Board'                  => [
			'category' => [
				'name'   => 'Town-City-Government',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Holyoke Community College'               => [
			'category' => null,
			'tags'     => [
				'Holyoke Community College',
			],
		],
		'ALife'                                   => [
			'category' => null,
			'tags'     => [
				'A Life',
			],
		],
		'statehouse'                              => [
			'category' => [
				'name'   => 'Politics',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'water'                                   => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'175'                                     => [
			'category' => null,
			'tags'     => [
				'MLT 175th Ann',
			],
		],
		'Northampton High School'                 => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Andover NH'                              => [
			'category' => null,
			'tags'     => [
				'Andover NH',
			],
		],
		'West Lebanon'                            => [
			'category' => null,
			'tags'     => [
				'West Lebanon NH',
			],
		],
		'White River Valley High'                 => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'hiking'                                  => [
			'category' => [
				'name'   => 'Outdoors',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Oxbow High'                              => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Addiction'                               => [
			'category' => null,
			'tags'     => [
				'Health',
			],
		],
		'Hanover'                                 => [
			'category' => null,
			'tags'     => [
				'Hanover NH',
			],
		],
		'book'                                    => [
			'category' => null,
			'tags'     => [
				'Books',
			],
		],
		'Northfield Mount Hermon School'          => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'HOME'                                    => [
			'category' => [
				'name'   => 'Home & Garden',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'hartford hs'                             => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'hanover HS'                              => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'florence'                                => [
			'category' => null,
			'tags'     => [
				'Florence MA',
			],
		],
		'dog'                                     => [
			'category' => null,
			'tags'     => [
				'Pets',
			],
		],
		'Hometown heroes 2022'                    => [
			'category' => null,
			'tags'     => [
				'Hometown Heroes',
			],
		],
		'home and garden'                         => [
			'category' => [
				'name'   => 'Home & Garden',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'select board'                            => [
			'category' => [
				'name'   => 'Town-City-Government',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Connecticut River Conservancy'           => [
			'category' => [
				'name'   => 'Outdoors',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'West Fairlee VT'                         => [
			'category' => null,
			'tags'     => [
				'West Fairlee VT',
			],
		],
		'Historic Deerfield'                      => [
			'category' => null,
			'tags'     => [
				'Historic Deerfield',
			],
		],
		'White River Junction'                    => [
			'category' => null,
			'tags'     => [
				'White River Junction VT',
			],
		],
		'Turners Falls'                           => [
			'category' => null,
			'tags'     => [
				'Turners Falls MA',
			],
		],
		'Stevens HS'                              => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Garden Cinemas'                          => [
			'category' => [
				'name'   => 'Entertainment',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Lebanon HS'                              => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Hitchcock Center'                        => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'River Rat Race'                          => [
			'category' => null,
			'tags'     => [
				'RIver Rat Race',
			],
		],
		'Corinth VT'                              => [
			'category' => null,
			'tags'     => [
				'Corinth VT',
			],
		],
		'Belmont NH'                              => [
			'category' => null,
			'tags'     => [
				'Belmont NH',
			],
		],
		'Yankee Candle'                           => [
			'category' => null,
			'tags'     => [
				'Yankee Candle',
			],
		],
		'natural disasters'                       => [
			'category' => null,
			'tags'     => [
				'Natural Disasters',
			],
		],
		'Frontier Regional School District'       => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Piermont NH'                             => [
			'category' => null,
			'tags'     => [
				'Piermont NH',
			],
		],
		'Unity NH'                                => [
			'category' => null,
			'tags'     => [
				'Unity NH',
			],
		],
		'Lebanon School District'                 => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Newbury VT'                              => [
			'category' => null,
			'tags'     => [
				'Newbury VT',
			],
		],
		'Croydon NH'                              => [
			'category' => null,
			'tags'     => [
				'Croydon NH',
			],
		],
		'West Windsor Vt'                         => [
			'category' => null,
			'tags'     => [
				'West Windsor VT',
			],
		],
		'Newport High'                            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'cancer'                                  => [
			'category' => null,
			'tags'     => [
				'Health',
			],
		],
		'Barnstead NH'                            => [
			'category' => null,
			'tags'     => [
				'Barnstead NH',
			],
		],
		'granbyma'                                => [
			'category' => null,
			'tags'     => [
				'Granby MA',
			],
		],
		'Deerfield Elementary School'             => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Hometown hero'                           => [
			'category' => null,
			'tags'     => [
				'Hometown Heroes',
			],
		],
		'Superintendent Search'                   => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Northfield Elementary School'            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Laconia NH'                              => [
			'category' => null,
			'tags'     => [
				'Laconia NH',
			],
		],
		'Smith Vocational'                        => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'live music'                              => [
			'category' => [
				'name'   => 'Entertainment',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Bernardston Elementary School'           => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'church'                                  => [
			'category' => [
				'name'   => 'Faith & Religion',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Athol Police Department'                 => [
			'category' => null,
			'tags'     => [
				'Police Log',
			],
		],
		'NHTI'                                    => [
			'category' => null,
			'tags'     => [
				'NHTI',
			],
		],
		'Orange NH'                               => [
			'category' => null,
			'tags'     => [
				'Orange NH',
			],
		],
		'Bridge of Flowers'                       => [
			'category' => null,
			'tags'     => [
				'Bridge of Flowers',
			],
		],
		'Valley Parents'                          => [
			'category' => null,
			'tags'     => [
				'Valley Parents',
			],
		],
		'Woodstock HS'                            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Warwick Community School'                => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Fisher Hill Elementary School'           => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Bridgewater VT'                          => [
			'category' => null,
			'tags'     => [
				'Bridgewater VT',
			],
		],
		'hartford'                                => [
			'category' => null,
			'tags'     => [
				'Hartford VT',
			],
		],
		'Weathersfield VT'                        => [
			'category' => null,
			'tags'     => [
				'Weathersfield VT',
			],
		],
		'Wilder VT'                               => [
			'category' => null,
			'tags'     => [
				'Wilder VT',
			],
		],
		'Dorchester NH'                           => [
			'category' => null,
			'tags'     => [
				'Dorchester NH',
			],
		],
		'school budget'                           => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Town Meeting 2025'                       => [
			'category' => null,
			'tags'     => [
				'Town Meeting',
			],
		],
		'Vintage Views'                           => [
			'category' => null,
			'tags'     => [
				'Vintage Views',
			],
		],
		'Tree House Brewing Co.'                  => [
			'category' => [
				'name'   => 'Food',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'PVCICS'                                  => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Great Falls Middle School'               => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'HOPKINS'                                 => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Brattleboro VT'                          => [
			'category' => null,
			'tags'     => [
				'Brattleboro VT',
			],
		],
		'RESTAURANT'                              => [
			'category' => [
				'name'   => 'Food',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Hawlemont Regional School District'      => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Deerfield NH'                            => [
			'category' => null,
			'tags'     => [
				'Deerfield NH',
			],
		],
		'keene NH'                                => [
			'category' => null,
			'tags'     => [
				'Keene NH',
			],
		],
		'god'                                     => [
			'category' => [
				'name'   => 'Faith & Religion',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'mass audubon'                            => [
			'category' => [
				'name'   => 'Outdoors',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Union 38 School District'                => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Mascoma HS'                              => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'North Haverhill NH'                      => [
			'category' => null,
			'tags'     => [
				'North Haverhill NH',
			],
		],
		'hospital'                                => [
			'category' => null,
			'tags'     => [
				'Health',
			],
		],
		'amherst cinema'                          => [
			'category' => [
				'name'   => 'Entertainment',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Hadley'                                  => [
			'category' => null,
			'tags'     => [
				'Hadley MA',
			],
		],
		'Northern Stage'                          => [
			'category' => [
				'name'   => 'Entertainment',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Windsor HS'                              => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Four Rivers Charter Public School'       => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Gardner MA'                              => [
			'category' => null,
			'tags'     => [
				'Gardner MA',
			],
		],
		'Sunderland Elementary School'            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Huntington MA'                           => [
			'category' => null,
			'tags'     => [
				'Huntington MA',
			],
		],
		'mount holyoke'                           => [
			'category' => null,
			'tags'     => [
				'Mount Holyoke College',
			],
		],
		'Deering NH'                              => [
			'category' => null,
			'tags'     => [
				'Deering NH',
			],
		],
		'Ware MA'                                 => [
			'category' => null,
			'tags'     => [
				'Deering NH',
			],
		],
		'AMHERST MA'                              => [
			'category' => null,
			'tags'     => [
				'Amherst MA',
			],
		],
		'Barre MA'                                => [
			'category' => null,
			'tags'     => [
				'Barre MA',
			],
		],
		'book bag'                                => [
			'category' => null,
			'tags'     => [
				'Books',
			],
		],
		'Swift River School'                      => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Food Bank of Western Massachusetts'      => [
			'category' => null,
			'tags'     => null,
		],
		'easthampton high school'                 => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Toy Fund'                                => [
			'category' => null,
			'tags'     => [
				'Toy Fund',
			],
		],
		'belchertownma'                           => [
			'category' => null,
			'tags'     => [
				'Belchertown MA',
			],
		],
		'dartmouth-hitchcock'                     => [
			'category' => null,
			'tags'     => [
				'Dartmouth Health',
				'Health',
			],
		],
		'concerts'                                => [
			'category' => [
				'name'   => 'Entertainment',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'Medicine'                                => [
			'category' => null,
			'tags'     => [
				'Health',
			],
		],
		'Tobacco'                                 => [
			'category' => null,
			'tags'     => [
				'Health',
			],
		],
		'nature photography'                      => [
			'category' => [
				'name'   => 'Outdoors',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'gardens'                                 => [
			'category' => [
				'name'   => 'Home & Garden',
				'parent' => 'Arts & Life',
			],
			'tags'     => null,
		],
		'rivendell hs'                            => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Pomfret VT'                              => [
			'category' => null,
			'tags'     => [
				'Pomfret VT',
			],
		],
		'Claremont'                               => [
			'category' => null,
			'tags'     => [
				'Claremont NH',
			],
		],
		'Monroe MA'                               => [
			'category' => null,
			'tags'     => [
				'Monroe MA',
			],
		],
		'Greenfield School Committee'             => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Dunbarton'                               => [
			'category' => null,
			'tags'     => [
				'Dunbarton NH',
			],
		],
		'pets'                                    => [
			'category' => null,
			'tags'     => [
				'Pets',
			],
		],
		'Lake Pleasant MA'                        => [
			'category' => null,
			'tags'     => [
				'Lake Pleasant MA',
			],
		],
		'Sunshine Week'                           => [
			'category' => null,
			'tags'     => [
				'Sunshine Week',
			],
		],
		'farmers market'                          => [
			'category' => null,
			'tags'     => [
				'Agriculture',
			],
		],
		'FARMERS'                                 => [
			'category' => null,
			'tags'     => [
				'Agriculture',
			],
		],
		'cannabis industry'                       => [
			'category' => null,
			'tags'     => [
				'Marijuana',
			],
		],
		'pandemic'                                => [
			'category' => null,
			'tags'     => [
				'Covid 19',
			],
		],
		'Hillsborough'                            => [
			'category' => null,
			'tags'     => [
				'Hillsborough NH',
			],
		],
		'londonderry nh'                          => [
			'category' => null,
			'tags'     => [
				'Londonderry NH',
			],
		],
		'covid'                                   => [
			'category' => null,
			'tags'     => [
				'Covid 19',
			],
		],
		'Westhampton'                             => [
			'category' => null,
			'tags'     => [
				'Westhampton MA',
			],
		],
		'White River'                             => [
			'category' => null,
			'tags'     => [
				'White River Junction VT',
			],
		],
		'Newport HS'                              => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Newport'                                 => [
			'category' => null,
			'tags'     => [
				'Newport NH',
			],
		],
		'Valley Regional Hospital'                => [
			'category' => null,
			'tags'     => [
				'Valley Regional Hospital',
				'Health',
			],
		],
		'Greenfield Planning Board'               => [
			'category' => [
				'name'   => 'Town-City-Government',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'recipes'                                 => [
			'category' => [
				'name'   => 'Home & Garden',
				'parent' => 'Arts & Entertainment',
			],
			'tags'     => null,
		],
		'Newport School District'                 => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Oxbow HS'                                => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Thetford HS'                             => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Peterborugh NH'                          => [
			'category' => null,
			'tags'     => [
				'Peterborough NH',
			],
		],
		'Lebanon Planning Board'                  => [
			'category' => [
				'name'   => 'Town-City-Government',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Turners'                                 => [
			'category' => null,
			'tags'     => [
				'Turners Falls MA',
			],
		],
		'Cannabis Control Commission'             => [
			'category' => null,
			'tags'     => [
				'Marijuana',
			],
		],
		'Bradford NH'                             => [
			'category' => null,
			'tags'     => [
				'Bradford NH',
			],
		],
		'Northampton Public Schools'              => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Plymouth NH'                             => [
			'category' => null,
			'tags'     => [
				'Plymouth NH',
			],
		],
		'Barnard Vt'                              => [
			'category' => null,
			'tags'     => [
				'Barnard VT',
			],
		],
		'Springfield NH'                          => [
			'category' => null,
			'tags'     => [
				'Springfield NH',
			],
		],
		'Greening Greenfield'                     => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'Warm the Children'                       => [
			'category' => null,
			'tags'     => [
				'Warm the Children',
			],
		],
		'Thetford High'                           => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Waste Management'                        => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'bears'                                   => [
			'category' => null,
			'tags'     => [
				'Wildlife',
			],
		],
		'bird'                                    => [
			'category' => null,
			'tags'     => [
				'Birds',
				'Wildlife',
			],
		],
		'Earth Day'                               => [
			'category' => null,
			'tags'     => [
				'Environment',
			],
		],
		'reading'                                 => [
			'category' => null,
			'tags'     => [
				'Books',
			],
		],
		'jaffrey'                                 => [
			'category' => null,
			'tags'     => [
				'Jaffrey NH',
			],
		],
		'Belchertown State School'                => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'Greenfield Middle School'                => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'templeton ma'                            => [
			'category' => null,
			'tags'     => [
				'Templeton MA',
			],
		],
		'Conway Grammar School'                   => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'North Adams MA'                          => [
			'category' => null,
			'tags'     => [
				'North Adams MA',
			],
		],
		'Williston Northampton'                   => [
			'category' => [
				'name'   => 'Education',
				'parent' => 'News',
			],
			'tags'     => null,
		],
		'townmeeting2025'                         => [
			'category' => null,
			'tags'     => [
				'Town Meeting',
			],
		],
		'Concord Insider'                         => [
			'category' => null,
			'tags'     => [
				'Concord Insider',
			],
		],
		'Flood'                                   => [
			'category' => null,
			'tags'     => [
				'Natural Disasters',
			],
		],
		'Monadnock Perspectives'                  => [
			'category' => null,
			'tags'     => [
				'Monadnock Perspectives',
			],
		],
		'Monadnock Profiles'                      => [
			'category' => null,
			'tags'     => [
				'Monadnock Profiles',
			],
		],
	];


	/**
	 * Apply category and tags to a post based on the mapping.
	 *
	 * @param string $legacy_tag The legacy tag value to look up in the mapping.
	 * @param int    $post_id  The post ID to apply the terms to.
	 *
	 * @return bool            True if mapping was found and applied, false otherwise.
	 */
	public static function add_mapped_category_and_tags_from_legacy_tag( string $legacy_tag, int $post_id ): bool {
		// Check if the value exists in our mapping.
		if ( ! isset( static::$mapping[ $legacy_tag ] ) ) {
			return false;
		}

		$mapping          = static::$mapping[ $legacy_tag ];
		$applied_category = null;

		// Apply category if available.
		if ( ! empty( $mapping['category'] ) ) {
			$map_category = $mapping['category'];

			$category = NNECategoryMap::get_term_by_name( $map_category['name'], 'category' );

			if ( null !== $category ) {
				wp_set_post_terms( $post_id, [ $category->term_id ], 'category', true );
				$applied_category = true;
			} else {
				$applied_category = false;
			}
		}

		$applied_tags = null;
		// Apply tags if available.
		if ( ! empty( $mapping['tags'] ) ) {
			$tag_ids = [];

			foreach ( $mapping['tags'] as $tag_name ) {
				$tag_term = NNECategoryMap::get_term_by_name( $tag_name, 'post_tag' );

				if ( null !== $tag_term ) {
					$tag_term = NNECategoryMap::create_taxonomy( $tag_name, 'post_tag' );
				}

				$tag_ids[] = $tag_term->term_id;
			}

			if ( ! empty( $tag_ids ) ) {
				wp_set_post_terms( $post_id, $tag_ids, 'post_tag', true );
				$terms_applied = true;
			}
		}

		$applied_terms = [
			$applied_category,
			$applied_tags,
		];

		return array_reduce( array_filter( $applied_terms, fn ( $applied_term ) => null !== $applied_term ), fn ( $carry, $item ) => $carry && $item, true );
	}
}
