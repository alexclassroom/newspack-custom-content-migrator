<?php

namespace NewspackCustomContentMigrator\Enum;

enum CAPPostMetaKeys: string {
	case LOGIN          = 'cap-user_login';
	case EMAIL          = 'cap-user_email';
	case FIRST_NAME     = 'cap-first_name';
	case LAST_NAME      = 'cap-last_name';
	case DISPLAY_NAME   = 'cap-display_name';
	case DESCRIPTION    = 'cap-description';
	case LINKED_ACCOUNT = 'cap-linked_account';
}
