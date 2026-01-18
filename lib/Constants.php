<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Time_Archive;

class Constants {
	// Time units - keep existing values for backward compatibility
	public const UNIT_DAY = 0;
	public const UNIT_WEEK = 1;
	public const UNIT_MONTH = 2;
	public const UNIT_YEAR = 3;
	// New units added for testing
	public const UNIT_MINUTE = 4;
	public const UNIT_HOUR = 5;

	public const MODE_CTIME = 0;
	public const MODE_MTIME = 1;

	public const ARCHIVE_FOLDER = '.archive';
	
	/**
	 * Folders that should never be archived (used by mobile apps for auto-upload)
	 * These are typically top-level folders in the user's files directory
	 */
	public const PROTECTED_FOLDERS = [
		'Camera',
		'Photos',
		'Documents',
		'Screenshots',
		'Videos',
		'Downloads',
		'DCIM', // Android camera folder
		'Pictures',
		'Images',
	];
}
