<?php

namespace NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland\Helpers;

enum NNEImportMetaEnum: string {
	case ARTICLE_ID_KEY               = 'newspack_legacy_article_id';
	case IMAGE_DATA_ID_KEY            = 'newspack_legacy_image_data_id';
	case IMAGE_EDITORIAL_ID_KEY       = 'newspack_legacy_image_editorial_id';
	case IMAGE_DOC_NAME_KEY           = 'newspack_legacy_image_doc_name';
	case IMAGE_INCLUDE_IN_GALLERY_KEY = 'newspack_legacy_image_include_in_gallery';
	case IMAGE_INLINE_KEY             = 'newspack_legacy_image_inline';
	case IMAGE_POSITION_KEY           = 'newspack_legacy_image_position';

	case BYLINE_FEATURE_ACTIVE_KEY = '_newspack_byline_active';
	case BYLINE_KEY                = '_newspack_byline';
}
