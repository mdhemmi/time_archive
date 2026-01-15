<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Time_Archive\Repair;

use OCA\Time_Archive\Constants;
use Exception;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use Psr\Log\LoggerInterface;

/**
 * Repair step to add existing .archive folders to favorites
 */
class FavoriteArchiveFolders implements IRepairStep {
	public function __construct(
		private readonly IUserManager $userManager,
		private readonly IRootFolder $rootFolder,
		private readonly ISystemTagManager $tagManager,
		private readonly ISystemTagObjectMapper $tagMapper,
		private readonly LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Add existing .archive folders to favorites';
	}

	#[\Override]
	public function run(IOutput $output): void {
		$output->info('Adding existing .archive folders to favorites...');
		error_log('[Files Archive Repair] Starting favorite archive folders repair step');
		
		$processed = 0;
		$added = 0;
		$errors = 0;
		$skipped = 0;
		
		// Iterate through all users
		$this->userManager->callForAllUsers(function (IUser $user) use (&$processed, &$added, &$errors, &$skipped, $output) {
			$userId = $user->getUID();
			$processed++;
			
			try {
				$userFolder = $this->rootFolder->getUserFolder($userId);
				
				// Check if .archive folder exists
				try {
					$archiveNode = $userFolder->get(Constants::ARCHIVE_FOLDER);
					if (!$archiveNode instanceof Folder) {
						// Not a folder, skip
						$output->debug("User {$userId}: .archive exists but is not a folder");
						$skipped++;
						return;
					}
					
					$fileId = $archiveNode->getId();
					error_log("[Files Archive Repair] Found .archive folder for user {$userId} (file ID: {$fileId})");
					
					// Check if already favorited
					if ($this->isFavorited($archiveNode)) {
						$output->info("User {$userId}: .archive folder already favorited");
						error_log("[Files Archive Repair] User {$userId}: .archive folder already favorited");
						$skipped++;
						return;
					}
					
					// Add to favorites
					error_log("[Files Archive Repair] Attempting to add .archive folder to favorites for user {$userId}");
					$result = $this->addToFavorites($archiveNode);
					if ($result) {
						$added++;
						$output->info("✓ Added .archive folder to favorites for user: {$userId}");
						error_log("[Files Archive Repair] ✓ Successfully added .archive folder to favorites for user {$userId}");
					} else {
						$errors++;
						$output->warning("✗ Failed to add .archive folder to favorites for user: {$userId} (check logs)");
						error_log("[Files Archive Repair] ✗ Failed to add .archive folder to favorites for user {$userId}");
					}
					
				} catch (NotFoundException $e) {
					// No .archive folder for this user, skip
					$output->debug("User {$userId}: No .archive folder found");
					$skipped++;
				}
			} catch (Exception $e) {
				$errors++;
				$errorMsg = "Failed to process user {$userId}: " . $e->getMessage();
				$output->warning($errorMsg);
				error_log("[Files Archive Repair] ERROR: {$errorMsg}");
				error_log("[Files Archive Repair] Stack trace: " . $e->getTraceAsString());
				$this->logger->error('Failed to add .archive folder to favorites for user ' . $userId, [
					'exception' => $e,
				]);
			}
		});
		
		$summary = "Processed {$processed} users, added {$added} folders to favorites, {$skipped} skipped, {$errors} errors";
		$output->info($summary);
		error_log("[Files Archive Repair] {$summary}");
	}
	
	/**
	 * Check if a folder is already favorited
	 */
	private function isFavorited(Folder $folder): bool {
		try {
			$fileId = $folder->getId();
			$tags = $this->tagMapper->getTagIdsForObjects([$fileId], 'files');
			
			if (empty($tags[$fileId])) {
				return false;
			}
			
			// Check if any of the tags is a favorite tag
			$allTags = $this->tagManager->getAllTags('files');
			foreach ($allTags as $tag) {
				$tagName = $tag->getName();
				if (($tagName === '$user!favorite' || 
				     $tagName === 'favorite' ||
				     (strpos($tagName, 'favorite') !== false && $tag->isUserVisible() && $tag->isUserAssignable())) &&
				    in_array((string)$tag->getId(), $tags[$fileId])) {
					return true;
				}
			}
			
			return false;
		} catch (Exception $e) {
			// If we can't check, assume not favorited
			return false;
		}
	}
	
	/**
	 * Add the archive folder to favorites
	 * Same logic as in ArchiveJob::addToFavorites()
	 * 
	 * @return bool True if successfully added, false otherwise
	 */
	private function addToFavorites(Folder $archiveFolder): bool {
		try {
			$fileId = $archiveFolder->getId();
			$userId = $archiveFolder->getOwner()->getUID();
			
			// Try to find existing favorite tags
			$allTags = $this->tagManager->getAllTags('files');
			$favoriteTag = null;
			
			foreach ($allTags as $tag) {
				$tagName = $tag->getName();
				// Check for favorite tag patterns used in Nextcloud
				if ($tagName === '$user!favorite' || 
				    $tagName === 'favorite' ||
				    (strpos($tagName, 'favorite') !== false && $tag->isUserVisible() && $tag->isUserAssignable())) {
					$favoriteTag = $tag;
					break;
				}
			}
			
			// If no favorite tag exists, try to create one
			if ($favoriteTag === null) {
				try {
					// Try to create a favorite tag
					error_log("[Files Archive Repair] No favorite tag found, attempting to create one...");
					$favoriteTag = $this->tagManager->createTag('favorite', true, true);
					$this->logger->debug('Created favorite tag for archive folder');
					error_log("[Files Archive Repair] Created favorite tag with ID: " . $favoriteTag->getId());
				} catch (Exception $e) {
					// If we can't create the tag, log and continue
					$errorMsg = 'Could not create favorite tag: ' . $e->getMessage();
					$this->logger->debug($errorMsg);
					error_log("[Files Archive Repair] ERROR: {$errorMsg}");
					error_log("[Files Archive Repair] Stack trace: " . $e->getTraceAsString());
					return false;
				}
			} else {
				error_log("[Files Archive Repair] Found existing favorite tag: ID={$favoriteTag->getId()}, Name={$favoriteTag->getName()}");
			}
			
			// Assign the favorite tag to the archive folder
			error_log("[Files Archive Repair] Assigning favorite tag {$favoriteTag->getId()} to file {$fileId}");
			$this->tagMapper->assignTags($fileId, 'files', [(string)$favoriteTag->getId()]);
			
			// Verify it was assigned
			$tags = $this->tagMapper->getTagIdsForObjects([$fileId], 'files');
			if (!empty($tags[$fileId]) && in_array((string)$favoriteTag->getId(), $tags[$fileId])) {
				$this->logger->info('Added archive folder to favorites (file ID: ' . $fileId . ', user: ' . $userId . ')');
				error_log("[Files Archive Repair] ✓ Verified: Favorite tag successfully assigned to file {$fileId}");
				return true;
			} else {
				error_log("[Files Archive Repair] ✗ WARNING: Tag assignment may have failed - tag not found after assignment");
				return false;
			}
		} catch (Exception $e) {
			// Log but don't fail - favorite assignment is best effort
			$errorMsg = 'Failed to add archive folder to favorites: ' . $e->getMessage();
			$this->logger->warning($errorMsg, [
				'exception' => $e,
			]);
			error_log("[Files Archive Repair] ERROR: {$errorMsg}");
			error_log("[Files Archive Repair] Stack trace: " . $e->getTraceAsString());
			return false;
		}
	}
}
