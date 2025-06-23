<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland\Helpers;

use Exception;
use WP_Term;

/**
 * The NNECategoryMap class provides methods for mapping categories and tags.
 */
class NNECategoryMap {

	/**
	 * Map of internal category names to new category names and tags.
	 *
	 * @var array[] $amherst_bulletin_map Map of legacy category names to new category names and tags.
	 */
	protected array $amherst_bulletin_map = [
		[
			'name'       => 'News',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Arts-Leisure',
			'categories' => [
				[
					'name'     => 'Arts & Life',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Commentary',
			'categories' => [
				[
					'name'     => 'Opinion',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'For-the-Record',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => [
				'Police Log',
			],
		],
		[
			'name'       => 'The-Lehrer-Report',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => [
				'Lehrer Report',
			],
		],
	];

	/**
	 * Map of internal category names to new category names and tags.
	 *
	 * @var array[] $amherst_weeklies_map Map of legacy category names to new category names and tags.
	 */
	protected array $athol_daily_news_map = [
		[
			'name'       => 'Arts',
			'categories' => [
				[
					'name'     => 'Arts & Life',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Community',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => [
						[
							'name' => 'Community Briefs',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Living',
			'categories' => [
				[
					'name'     => 'Arts & Life',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => [
						[
							'name' => 'Community Briefs',
						],
						[
							'name' => 'Police-Fire-Courts',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Local',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Police-Courts',
			'categories' => [
				[
					'name'     => 'Police-Fire-Courts',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Obituaries',
			'categories' => [
				[
					'name'     => 'Obituaries',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion',
			'categories' => [
				[
					'name'     => 'Opinion',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Police Logs',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => [
				'Police Log',
			],
		],
		[
			'name'       => 'Seniors',
			'categories' => [
				[
					'name'     => 'Community Briefs',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
	];

	/**
	 * Map of internal category names to new category names and tags.
	 *
	 * @var array[] $amherst_weeklies_map Map of legacy category names to new category names and tags.
	 */
	protected array $concord_monitor_map = [
		[
			'name'       => 'News',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => [
						[
							'name' => 'Business',
						],
						[
							'name' => 'Community Briefs',
						],
						[
							'name' => 'Town-City-Government',
						],
						[
							'name' => 'Police-Fire-Courts',
						],
						[
							'name' => 'Politics',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Arts-Life',
			'categories' => [
				[
					'name'     => 'Arts & Life',
					'parent'   => null,
					'children' => [
						[
							'name' => 'Entertainment',
						],
						[
							'name' => 'Food',
						],
						[
							'name' => 'Home & Garden',
						],
						[
							'name' => 'Outdoors',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Arts-Life/Books',
			'parent'     => null,
			'categories' => [
				[
					'name'     => 'Arts & Life',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => [
				'Books',
			],
		],
		[
			'name'       => 'Arts-Life/Entertainment',
			'categories' => [
				[
					'name'     => 'Entertainment',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Arts-Life/Food',
			'categories' => [
				[
					'name'     => 'Food',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Arts-Life/Health',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => [
				'Health',
			],
		],
		[
			'name'       => 'Arts-Life/Home-Garden',
			'categories' => [
				[
					'name'     => 'Home & Garden',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Arts-Life/Milestones',
			'categories' => [
				[
					'name'     => 'Community Briefs',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Arts-Life/Outdoors',
			'categories' => [
				[
					'name'     => 'Outdoors',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Business',
			'categories' => [
				[
					'name'     => 'Business',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Community',
			'categories' => [
				[
					'name'     => 'Community Briefs',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Local',
			'categories' => [
				[
					'name'     => 'Town-City-Government',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Nation-World',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Police-Fire',
			'categories' => [
				[
					'name'     => 'Police-Fire-Courts',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Science',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => [
				'Granite Geek',
				'Science & Technology',
			],
		],
		[
			'name'       => 'News/State',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion',
			'categories' => [
				[
					'name'     => 'Opinion',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion/Cartoons',
			'categories' => [
				[
					'name'     => 'Opinion',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion/Columns',
			'categories' => [
				[
					'name'     => 'Columns',
					'parent'   => 'Opinion',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion/Editorials',
			'categories' => [
				[
					'name'     => 'Editorials',
					'parent'   => 'Opinion',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion/Letters',
			'categories' => [
				[
					'name'     => 'Letters',
					'parent'   => 'Opinion',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Politics',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Politics/Elections',
			'categories' => [
				[
					'name'     => 'Politics',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Politics/Federal',
			'categories' => [
				[
					'name'     => 'Politics',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Politics/Podcast',
			'categories' => [
				[
					'name'     => 'Politics',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Politics/State-House',
			'categories' => [
				[
					'name'     => 'Politics',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Real Estate',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => [
				'Real Estate',
				'Housing',
			],
		],
		[
			'name'       => 'Sports',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports/College',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports/Columns',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports/High-School',
			'categories' => [
				[
					'name'     => 'High School & Youth',
					'parent'   => 'Sports',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports/Professional',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
	];

	/**
	 * Map of internal category names to new category names and tags.
	 *
	 * @var array[] $daily_hampshire_gazette_map Map of legacy category names to new category names and tags.
	 */
	protected array $daily_hampshire_gazette_map = [
		[
			'name'       => 'Arts',
			'categories' => [
				[
					'name'     => 'Arts & Life',
					'parent'   => null,
					'children' => [
						[
							'name' => 'Food',
						],
						[
							'name' => 'Home & Garden',
						],
						[
							'name' => 'Best Of',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => [
						[
							'name' => 'Business',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion',
			'categories' => [
				[
					'name'     => 'Opinion',
					'parent'   => null,
					'children' => [
						[
							'name' => 'Columns',
						],
						[
							'name' => 'Editorials',
						],
						[
							'name' => 'Letters',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => [
						[
							'name' => 'High School & Youth',
						],
						[
							'name' => 'UMass',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Arts/Books',
			'categories' => [
				[
					'name'     => 'Arts & Life',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => [
				'Books',
			],
		],
		[
			'name'       => 'Life',
			'categories' => [
				[
					'name'     => 'Arts & Life',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Life/Announcements',
			'categories' => [
				[
					'name'     => 'Arts & Life',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Life/Food',
			'categories' => [
				[
					'name'     => 'Food',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Life/Health',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => [
				'Health',
			],
		],
		[
			'name'       => 'Life/Home-Garden',
			'categories' => [
				[
					'name'     => 'Home & Garden',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Life/Readers-Choice',
			'categories' => [
				[
					'name'     => 'Best Of',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Business',
			'categories' => [
				[
					'name'     => 'Business',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Local',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Nation-World',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/State-Region',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion/Columns',
			'categories' => [
				[
					'name'     => 'Columns',
					'parent'   => 'Opinion',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion/Editorials',
			'categories' => [
				[
					'name'     => 'Editorials',
					'parent'   => 'Opinion',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion/Letters',
			'categories' => [
				[
					'name'     => 'Letters',
					'parent'   => 'Opinion',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports/College',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports/High-School',
			'categories' => [
				[
					'name'     => 'High School & Youth',
					'parent'   => 'Sports',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports/Pro',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports/UMass',
			'categories' => [
				[
					'name'     => 'UMass',
					'parent'   => 'Sports',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'UMass-Sports-Blog',
			'categories' => [
				[
					'name'     => 'UMass',
					'parent'   => 'Sports',
					'children' => null,
				],
			],
			'tags'       => null,
		],
	];

	/**
	 * Map of internal category names to new category names and tags.
	 *
	 * @var array[] $greenfield_recorder_map Map of legacy category names to new category names and tags.
	 */
	protected array $greenfield_recorder_map = [
		[
			'name'       => 'Arts',
			'categories' => [
				[
					'name'     => 'Arts & Life',
					'parent'   => null,
					'children' => [
						[
							'name' => 'Faith & Religion',
						],
						[
							'name' => 'Food',
						],
						[
							'name' => 'Home & Garden',
						],
						[
							'name' => 'Outdoors',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => [
						[
							'name' => 'Business',
						],
						[
							'name' => 'Community Briefs',
						],
						[
							'name' => 'Police-Fire-Courts',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion',
			'categories' => [
				[
					'name'     => 'Opinion',
					'parent'   => null,
					'children' => [
						[
							'name' => 'Columns',
						],
						[
							'name' => 'Editorials',
						],
						[
							'name' => 'Letters',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => [
						[
							'name' => 'High School & Youth',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Books',
			'categories' => [
				[
					'name'     => 'Arts & Life',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => [
				'Books',
			],
		],
		[
			'name'       => 'Life',
			'categories' => [
				[
					'name'     => 'Arts & Life',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Life/Faith',
			'categories' => [
				[
					'name'     => 'Faith & Religion',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Life/Food',
			'categories' => [
				[
					'name'     => 'Food',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Life/Home-Garden',
			'categories' => [
				[
					'name'     => 'Home & Garden',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Life/Milestones',
			'categories' => [
				[
					'name'     => 'Community Briefs',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Life/Outdoors',
			'categories' => [
				[
					'name'     => 'Outdoors',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Business',
			'categories' => [
				[
					'name'     => 'Business',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Community-Bulletin',
			'categories' => [
				[
					'name'     => 'Community Briefs',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Local',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Police-Courts',
			'categories' => [
				[
					'name'     => 'Police-Fire-Courts',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/State',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/World',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion/Cartoons',
			'categories' => [
				[
					'name'     => 'Opinion',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion/Columns',
			'categories' => [
				[
					'name'     => 'Columns',
					'parent'   => 'Opinion',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion/Editorials',
			'categories' => [
				[
					'name'     => 'Editorials',
					'parent'   => 'Opinion',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion/Letters',
			'categories' => [
				[
					'name'     => 'Letters',
					'parent'   => 'Opinion',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Richies-Top-40',
			'categories' => [
				[
					'name'     => 'Columns',
					'parent'   => 'Opinion',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports/College',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports/Columns',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports/Community',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports/High-School',
			'categories' => [
				[
					'name'     => 'High School & Youth',
					'parent'   => 'Sports',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports/Professional',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
	];

	/**
	 * Map of internal category names to new category names and tags.
	 *
	 * @var array[] $monadnock_ledger_transcript_map Map of legacy category names to new category names and tags.
	 */
	protected array $monadnock_ledger_transcript_map = [
		[
			'name'       => 'Arts-Living',
			'categories' => [
				[
					'name'     => 'Arts & Life',
					'parent'   => null,
					'children' => [
						[
							'name' => 'Food',
						],
						[
							'name' => 'Faith & Religion',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => [
						[
							'name' => 'Business',
						],
						[
							'name' => 'Police-Fire-Courts',
						],
						[
							'name' => 'Education',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion',
			'categories' => [
				[
					'name'     => 'Opinion',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Arts-Living/Environment',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => [
				'Environment',
			],
		],
		[
			'name'       => 'Arts-Living/Food',
			'categories' => [
				[
					'name'     => 'Food',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Arts-Living/Health',
			'categories' => [
				[
					'name'     => 'Arts & Life',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => [
				'Health',
			],
		],
		[
			'name'       => 'Arts-Living/Spirituality',
			'categories' => [
				[
					'name'     => 'Faith & Religion',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Business',
			'categories' => [
				[
					'name'     => 'Business',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Crime',
			'categories' => [
				[
					'name'     => 'Police-Fire-Courts',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Schools',
			'categories' => [
				[
					'name'     => 'Education',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
	];

	/**
	 * Map of internal category names to new category names and tags.
	 *
	 * @var array[] $valley_news_map Map of legacy category names to new category names and tags.
	 */
	protected array $valley_news_map = [
		[
			'name'       => 'Features',
			'categories' => [
				[
					'name'     => 'Arts & Life',
					'parent'   => null,
					'children' => [
						[
							'name' => 'Entertainment',
						],
						[
							'name' => 'Eating Out',
						],
						[
							'name' => 'Faith & Religion',
						],
						[
							'name' => 'Home & Garden',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => [
						[
							'name' => 'Community Briefs',
						],
						[
							'name' => 'Business',
						],
						[
							'name' => 'Education',
						],
						[
							'name' => 'Politics',
						],
						[
							'name' => 'Police-Fire-Courts',
						],
						[
							'name' => 'Town-City-Government',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion',
			'categories' => [
				[
					'name'     => 'Opinion',
					'parent'   => null,
					'children' => [
						[
							'name' => 'Columns',
						],
						[
							'name' => 'Editorials',
						],
						[
							'name' => 'Letters',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => [
						[
							'name' => 'Dartmouth',
						],
						[
							'name' => 'High School & Youth',
						],
						[
							'name' => 'Outdoors',
						],
					],
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Obituaries',
			'categories' => [
				[
					'name'     => 'Obituaries',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Announcements',
			'categories' => [
				[
					'name'     => 'Community Briefs',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Announcements/Births',
			'categories' => [
				[
					'name'     => 'Community Briefs',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Announcements/Engagements',
			'categories' => [
				[
					'name'     => 'Community Briefs',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Announcements/Listings',
			'categories' => [
				[
					'name'     => 'Community Briefs',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Announcements/Weddings',
			'categories' => [
				[
					'name'     => 'Community Briefs',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Features/Arts-Entertainment-Theater',
			'categories' => [
				[
					'name'     => 'Entertainment',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Features/Books-TV-Movies',
			'categories' => [
				[
					'name'     => 'Entertainment',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Features/Cooking-Dining',
			'categories' => [
				[
					'name'     => 'Eating Out',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Features/Essays',
			'categories' => [
				[
					'name'     => 'Arts & Life',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Features/Faith',
			'categories' => [
				[
					'name'     => 'Faith & Religion',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Features/Home-Garden',
			'categories' => [
				[
					'name'     => 'Home & Garden',
					'parent'   => 'Arts & Life',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Features/Science',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => [
				'Science & Technology',
			],
		],
		[
			'name'       => 'News/Business',
			'categories' => [
				[
					'name'     => 'Business',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Cops-Fires-Courts',
			'categories' => [
				[
					'name'     => 'Police-Fire-Courts',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Education',
			'categories' => [
				[
					'name'     => 'Education',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Health-Care',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => [
				'Health',
			],
		],
		[
			'name'       => 'News/Local-Government',
			'categories' => [
				[
					'name'     => 'Town-City-Government',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'News/Politics',
			'categories' => [
				[
					'name'     => 'Politics',
					'parent'   => 'News',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion/Columns',
			'categories' => [
				[
					'name'     => 'Columns',
					'parent'   => 'Opinion',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion/Editorials',
			'categories' => [
				[
					'name'     => 'Editorials',
					'parent'   => 'Opinion',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Opinion/Letters-to-the-editor',
			'categories' => [
				[
					'name'     => 'Letters',
					'parent'   => 'Opinion',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Photos',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Photos/Valley-Visual',
			'categories' => [
				[
					'name'     => 'News',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => [
				'Valley Visual',
			],
		],
		[
			'name'       => 'Sports/College',
			'categories' => [
				[
					'name'     => 'Sports',
					'parent'   => null,
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports/Dartmouth',
			'categories' => [
				[
					'name'     => 'Dartmouth',
					'parent'   => 'Sports',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports/High-School',
			'categories' => [
				[
					'name'     => 'High School & Youth',
					'parent'   => 'Sports',
					'children' => null,
				],
			],
			'tags'       => null,
		],
		[
			'name'       => 'Sports/Outdoors-Recreation',
			'categories' => [
				[
					'name'     => 'Outdoors',
					'parent'   => 'Sports',
					'children' => null,
				],
			],
			'tags'       => null,
		],
	];

	/**
	 * Helper function to add categories and tags to a post based on the mapping provided by the publisher.
	 *
	 * @param string|NNEPublisherEnum $publisher The publisher from which to retrieve the category map.
	 * @param int                     $post_id The ID of the post to which to add the categories and tags.
	 * @param string                  $original_category The original category name from the publisher.
	 *
	 * @return bool|null
	 * @throws Exception If the original category does not exist in the mapping, or if the mapped category does not exist.
	 */
	public static function add_mapped_categories_and_tags( string|NNEPublisherEnum $publisher, int $post_id, string $original_category ): ?bool {
		if ( is_string( $publisher ) ) {
			$publisher = NNEPublisherEnum::tryFrom( $publisher );
		}

		$category_map = self::get_category_map( $publisher );

		if ( empty( $category_map ) ) {
			return null;
		}

		$target_item = null;
		foreach ( $category_map as $item ) {
			if ( $item['name'] === $original_category ) {
				$target_item = $item;
				break;
			}
		}

		if ( null === $target_item ) {
			return null;
		}

		$category      = $target_item['categories'][0];
		$category_term = static::get_term_by_name( $category['name'], 'category' );

		if ( null === $category_term ) {

			$categories = $target_item['categories'];

			do {
				$category      = array_shift( $categories );
				$category_term = static::get_term_by_name( $category['name'], 'category' );

				if ( null === $category_term ) {
					$parent = 0;

					if ( ! empty( $category['parent'] ) ) {
						if ( ! is_numeric( $category['parent'] ) ) {
							$parent_term = static::get_term_by_name( $category['parent'], 'category' );

							if ( null === $parent_term ) {
								$parent_term = static::create_taxonomy( $category['parent'], 'category' );
							}

							$parent = $parent_term->term_taxonomy_id;
						} else {
							$parent = $category['parent'];
						}
					}

					$category_term = static::create_taxonomy( $category['name'], 'category', $parent );
				}

				if ( ! empty( $category['children'] ) ) {
					$categories = array_merge(
						array_map(
							fn( $child ) => $child + [ 'parent' => $category_term->term_taxonomy_id ],
							$category['children']
						),
						$categories
					);
				}
			} while ( ! empty( $categories ) );

			$category_term = static::get_term_by_name( $target_item['categories'][0]['name'], 'category' );
		}

		$maybe_categories_added = wp_set_post_terms( $post_id, [ $category_term->term_id ], 'category', true );

		if ( false === $maybe_categories_added || is_wp_error( $maybe_categories_added ) ) {
			throw new Exception(
				sprintf(
					'Failed to add category "%s" (%s) to post ID %d.',
					$target_item['name'], // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$category['name'], // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$post_id // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				)
			);
		}

		$tags    = $target_item['tags'] ?? [];
		$tag_ids = [];
		foreach ( $tags as $tag ) {
			$tag_term = static::get_term_by_name( $tag, 'post_tag' );

			if ( null === $tag_term ) {
				$tag_term = static::create_taxonomy( $tag, 'post_tag' );
			}

			$tag_ids[] = $tag_term->term_id;
		}

		$maybe_tags_added = wp_set_post_terms( $post_id, $tag_ids, 'post_tag', true );

		return ! empty( $maybe_categories_added ) && ! empty( $maybe_tags_added );
	}


	/**
	 * Returns the category map for the specified publisher.
	 *
	 * @param string|NNEPublisherEnum $publisher The publisher for which to retrieve the category map.
	 *
	 * @return array<array{ name: string, categories: array< array{ name: string, parent: ?string, children: ?array } >, tags: string[] }>
	 */
	public static function get_category_map( string|NNEPublisherEnum $publisher ): array {
		if ( is_string( $publisher ) ) {
			$publisher = NNEPublisherEnum::tryFrom( $publisher );
		}

		return match ( $publisher ) {
			NNEPublisherEnum::AMHERST_BULLETIN => ( new self() )->amherst_bulletin_map,
			NNEPublisherEnum::ATHOL_DAILY_NEWS => ( new self() )->athol_daily_news_map,
			NNEPublisherEnum::CONCORD_MONITOR => ( new self() )->concord_monitor_map,
			NNEPublisherEnum::DAILY_HAMPSHIRE => ( new self() )->daily_hampshire_gazette_map,
			NNEPublisherEnum::GREENFIELD_RECORDER => ( new self() )->greenfield_recorder_map,
			NNEPublisherEnum::MONADNOCK_LEDGER => ( new self() )->monadnock_ledger_transcript_map,
			NNEPublisherEnum::VALLEY_NEWS => ( new self() )->valley_news_map,
			default => [],
		};
	}

	/**
	 * `get_term_by` does some things to $name that cause issues. Instead, we're just using a direct SQL query to get the term.
	 *
	 * @param string $name The name of the term.
	 * @param string $taxonomy The taxonomy.
	 *
	 * @return WP_Term|null
	 */
	public static function get_term_by_name( string $name, string $taxonomy ): ?WP_Term {
		global $wpdb;

		$name_as_string = sanitize_title( $name );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$term = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->terms t 
                        INNER JOIN $wpdb->term_taxonomy tt ON t.term_id = tt.term_id 
                     WHERE tt.taxonomy = %s AND (t.name = %s OR t.slug = %s)",
				$taxonomy,
				$name_as_string,
				$name_as_string
			)
		);

		return $term ? new WP_Term( $term ) : null;
	}

	/**
	 * Helper function to create a taxonomy term, using Migration Scaffolding classes.
	 *
	 * @param string $name The name of the term.
	 * @param string $taxonomy The taxonomy.
	 * @param int    $parent_term_tax_id The parent term ID, or 0 for no parent.
	 *
	 * @return WP_Term
	 * @throws Exception If the term already exists, or some other error during creation occurs.
	 */
	public static function create_taxonomy( string $name, string $taxonomy, int $parent_term_tax_id = 0 ): WP_Term {
		$maybe_term_data = wp_insert_term(
			$name,
			$taxonomy,
			[
				'parent' => $parent_term_tax_id,
				'slug'   => sanitize_title( $name ),
			]
		);

		if ( is_wp_error( $maybe_term_data ) ) {
			throw new Exception(
				sprintf(
					'Failed to create term "%s" in taxonomy "%s": %s',
					$name, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$taxonomy, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$maybe_term_data->get_error_message() // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				)
			);
		}

		return new WP_Term( (object) $maybe_term_data );
	}
}
