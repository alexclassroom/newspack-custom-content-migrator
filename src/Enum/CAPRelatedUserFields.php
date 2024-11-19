<?php

namespace NewspackCustomContentMigrator\Enum;

enum CAPRelatedUserFields: string {
	case LOGIN     = 'user_login';
	case NICE_NAME = 'user_nicename';
	case EMAIL     = 'user_email';
}
