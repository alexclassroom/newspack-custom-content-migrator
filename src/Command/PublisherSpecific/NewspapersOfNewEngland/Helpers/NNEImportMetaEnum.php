<?php
/** NNEImportMetaEnum
 *
 * @package Newspack Custom Content Migrator
 */

namespace NewspackCustomContentMigrator\Command\PublisherSpecific\NewspapersOfNewEngland\Helpers;

enum NNEImportMetaEnum: string {
	case ARTICLE_ID_KEY               = 'newspack_legacy_article_id';
	case IMAGE_DATA_ID_KEY            = 'newspack_legacy_image_data_id';
	case IMAGE_EDITORIAL_ID_KEY       = 'newspack_legacy_image_editorial_id';
	case IMAGE_CHECKSUM_KEY           = 'newspack_legacy_image_checksum_filename';
	case IMAGE_DOC_NAME_KEY           = 'newspack_legacy_image_doc_name';
	case IMAGE_INCLUDE_IN_GALLERY_KEY = 'newspack_legacy_image_include_in_gallery';
	case IMAGE_INLINE_KEY             = 'newspack_legacy_image_inline';
	case IMAGE_POSITION_KEY           = 'newspack_legacy_image_position';

	case BYLINE_FEATURE_ACTIVE_KEY = '_newspack_byline_active';
	case BYLINE_KEY                = '_newspack_byline';

	case TAG_UPDATE_META_KEY           = '_newspack_update_tag_via_mapping';
	case TAG_UPDATE_SKIPPED_META_VALUE = 'skipped';
	case TAG_UPDATE_UPDATED_META_VALUE = 'updated';
}
