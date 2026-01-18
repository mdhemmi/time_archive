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
	 * Default folders that should never be archived (used by mobile apps for auto-upload)
	 * These are typically top-level folders in the user's files directory
	 * Additional folders can be configured via admin settings
	 */
	public const DEFAULT_PROTECTED_FOLDERS = [
		'Camera',
		'Photos',
		'Documents',
		'Screenshots',
		'Videos',
		'Downloads',
		'DCIM', // Android camera folder
		'Pictures',
		'Images',
		'SofortUpload', // German Nextcloud app folder
	];
	
	/**
	 * Get the list of protected folders, merging defaults with configured values
	 * 
	 * @param \OCP\IConfig|null $config Nextcloud config service (optional)
	 * @return array<string> List of protected folder names
	 */
	public static function getProtectedFolders(?\OCP\IConfig $config = null): array {
		$defaults = self::DEFAULT_PROTECTED_FOLDERS;
		
		if ($config === null) {
			return $defaults;
		}
		
		// Get configured folders from app config (comma-separated list)
		$configured = $config->getAppValue('time_archive', 'protected_folders', '');
		
		if (empty($configured)) {
			return $defaults;
		}
		
		// Parse comma-separated list and merge with defaults
		$configuredFolders = array_map('trim', explode(',', $configured));
		$configuredFolders = array_filter($configuredFolders, function($folder) {
			return !empty($folder);
		});
		
		// Merge defaults with configured folders (remove duplicates, preserve order)
		$allFolders = array_merge($defaults, $configuredFolders);
		return array_values(array_unique($allFolders));
	}
}
