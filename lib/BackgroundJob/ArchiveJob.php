<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Time_Archive\BackgroundJob;

use Exception;
use OC\Files\Filesystem;
use OCA\Time_Archive\Constants;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\Config\ICachedMountFileInfo;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\LockedException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Share\IManager as IShareManager;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use Psr\Log\LoggerInterface;

class ArchiveJob extends TimedJob {
	public function __construct(
		ITimeFactory $timeFactory,
		private readonly ISystemTagManager $tagManager,
		private readonly ISystemTagObjectMapper $tagMapper,
		private readonly IUserMountCache $userMountCache,
		private readonly IDBConnection $db,
		private readonly IRootFolder $rootFolder,
		private readonly IJobList $jobList,
		private readonly IUserManager $userManager,
		private readonly IShareManager $shareManager,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
		// Run once a day
		$this->setInterval(24 * 60 * 60);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	#[\Override]
	public function run($argument): void {
		// Determine if this is a tag-based or time-based rule
		$tagId = $argument['tag'] ?? null;
		$ruleId = $argument['rule'] ?? null;

		// Validate if there is an entry in the DB
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('archive_rules');
		
		if ($tagId !== null) {
			// Tag-based rule
			$qb->where($qb->expr()->eq('tag_id', $qb->createNamedParameter($tagId)));
			
			// Validate if tag still exists
			try {
				$this->tagManager->getTagsByIds((string)$tagId);
			} catch (\InvalidArgumentException $e) {
				$this->jobList->remove($this, $argument);
				$this->logger->debug("Background job was removed, because tag $tagId is invalid", [
					'exception' => $e,
				]);
				return;
			} catch (TagNotFoundException $e) {
				$this->jobList->remove($this, $argument);
				$this->logger->debug("Background job was removed, because tag $tagId no longer exists", [
					'exception' => $e,
				]);
				return;
			}
		} else {
			// Time-based rule
			$qb->where($qb->expr()->eq('id', $qb->createNamedParameter($ruleId)))
				->andWhere($qb->expr()->isNull('tag_id'));
		}

		$cursor = $qb->executeQuery();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			$this->jobList->remove($this, $argument);
			$identifier = $tagId ?? $ruleId;
			$this->logger->debug("Background job was removed, because rule $identifier has no archive rule configured");
			return;
		}

		// Calculate archive before date
		$archiveBefore = $this->getBeforeDate((int)$data['time_unit'], (int)$data['time_amount']);
		$timeAfter = (int)$data['time_after'];

		if ($tagId !== null) {
			// Tag-based archiving
			$this->logger->info("Running archive for Tag $tagId with archive before " . $archiveBefore->format(\DateTimeInterface::ATOM));
			error_log("Time Archive: Running archive for Tag $tagId with archive before " . $archiveBefore->format('Y-m-d H:i:s'));
			$this->archiveByTag($tagId, $archiveBefore, $timeAfter);
		} else {
			// Time-based archiving - archive all files for all users
			$this->logger->info("Running time-based archive (Rule $ruleId) with archive before " . $archiveBefore->format(\DateTimeInterface::ATOM));
			error_log("Time Archive: Running time-based archive (Rule $ruleId) with archive before " . $archiveBefore->format('Y-m-d H:i:s'));
			$stats = $this->archiveByTime($archiveBefore, $timeAfter);
			$this->logger->info("Archive job completed: " . json_encode($stats));
			error_log("Time Archive: Archive job completed - " . json_encode($stats));
		}
	}

	/**
	 * Archive files based on tag
	 */
	private function archiveByTag(int $tagId, \DateTime $archiveBefore, int $timeAfter): void {
		$offset = '';
		$limit = 1000;
		while ($offset !== null) {
			$fileIds = $this->tagMapper->getObjectIdsForTags((string)$tagId, 'files', $limit, $offset);
			$this->logger->debug('Checking archive for ' . count($fileIds) . ' files in this chunk');

			foreach ($fileIds as $fileId) {
				$fileId = (int)$fileId;
				try {
					$node = $this->checkFileId($fileId);
				} catch (NotFoundException $e) {
					$this->logger->debug("Node with id $fileId was not found", [
						'exception' => $e,
					]);
					continue;
				}

				$this->archiveNode($node, $archiveBefore, $timeAfter, (string)$tagId);
			}

			if (empty($fileIds) || count($fileIds) < $limit) {
				break;
			}

			$offset = (string)array_pop($fileIds);
		}
	}

	/**
	 * Archive files based on time for all users
	 * 
	 * @return array{usersProcessed: int, filesArchived: int, filesChecked: int, foldersArchived: int}
	 */
	private function archiveByTime(\DateTime $archiveBefore, int $timeAfter): array {
		$stats = [
			'usersProcessed' => 0,
			'filesArchived' => 0,
			'filesChecked' => 0,
			'foldersArchived' => 0,
		];
		
		error_log("Time Archive: Starting to process all users. Archive threshold: " . $archiveBefore->format('Y-m-d H:i:s'));
		
		$this->userManager->callForAllUsers(function ($user) use ($archiveBefore, $timeAfter, &$stats) {
			$userId = $user->getUID();
			try {
				error_log("Time Archive: Processing user: $userId");
				$userFolder = $this->rootFolder->getUserFolder($userId);
				if (!Filesystem::$loaded) {
					Filesystem::init($userId, '/' . $userId . '/files');
				}
				
				$userStats = $this->archiveUserFolder($userFolder, $archiveBefore, $timeAfter, $userId);
				$stats['usersProcessed']++;
				$stats['filesArchived'] += $userStats['filesArchived'];
				$stats['filesChecked'] += $userStats['filesChecked'];
				$stats['foldersArchived'] += $userStats['foldersArchived'];
				
				if ($userStats['filesChecked'] > 0 || $userStats['foldersArchived'] > 0) {
					error_log("Time Archive: User $userId - Checked: {$userStats['filesChecked']}, Files archived: {$userStats['filesArchived']}, Folders archived: {$userStats['foldersArchived']}");
				}
			} catch (Exception $e) {
				$errorMsg = "Failed to archive files for user $userId: " . $e->getMessage();
				error_log("Files Archive ERROR: $errorMsg");
				$this->logger->warning($errorMsg, [
					'exception' => $e,
				]);
			}
		});
		
		error_log("Time Archive: Completed processing. Total users: {$stats['usersProcessed']}, Files checked: {$stats['filesChecked']}, Files archived: {$stats['filesArchived']}, Folders archived: {$stats['foldersArchived']}");
		
		return $stats;
	}

	/**
	 * Recursively archive files in a folder
	 * After archiving files, checks if folder is empty and archives it if it meets criteria
	 * 
	 * @return array{filesArchived: int, filesChecked: int, foldersArchived: int}
	 */
	private function archiveUserFolder(Folder $folder, \DateTime $archiveBefore, int $timeAfter, string $userId): array {
		$stats = [
			'filesArchived' => 0,
			'filesChecked' => 0,
			'foldersArchived' => 0,
		];
		
		// Skip the archive folder itself
		if ($folder->getName() === Constants::ARCHIVE_FOLDER) {
			return $stats;
		}
		
		// Check if this is a protected folder (mobile app upload folder)
		// We still process files inside, but never archive the folder itself
		$isProtected = $this->isProtectedFolder($folder, $userId);
		if ($isProtected) {
			$this->logger->debug('Processing protected folder ' . $folder->getName() . ' (mobile app upload folder) - files will be archived but folder will not');
		}

		$nodes = $folder->getDirectoryListing();
		foreach ($nodes as $node) {
			// Skip if already in archive folder
			if (strpos($node->getPath(), '/' . Constants::ARCHIVE_FOLDER . '/') !== false) {
				continue;
			}

			if ($node instanceof Folder) {
				// Recursively process subfolders
				$subStats = $this->archiveUserFolder($node, $archiveBefore, $timeAfter, $userId);
				$stats['filesArchived'] += $subStats['filesArchived'];
				$stats['filesChecked'] += $subStats['filesChecked'];
				$stats['foldersArchived'] += $subStats['foldersArchived'];
			} else {
				// Check and archive file
				$stats['filesChecked']++;
				$archived = $this->archiveNode($node, $archiveBefore, $timeAfter, null);
				if ($archived) {
					$stats['filesArchived']++;
				}
			}
		}
		
		// After processing all files and subfolders, check if this folder is now empty
		// and should be archived
		// Skip this check for protected folders (mobile app upload folders)
		if (!$isProtected) {
			try {
				// Re-fetch the folder to get current state (files may have been moved)
				$currentNodes = $folder->getDirectoryListing();
				$remainingNodes = array_filter($currentNodes, function($node) {
					// Count only nodes that are not in the archive folder
					return strpos($node->getPath(), '/' . Constants::ARCHIVE_FOLDER . '/') === false;
				});
				
				// If folder is now empty (or only contains .archive folder references)
				if (empty($remainingNodes)) {
					// Check if folder meets archive criteria based on its modification time
					$folderTime = $this->getDateFromNode($folder, $timeAfter);
					
					if ($folderTime < $archiveBefore) {
						$this->logger->debug('Folder ' . $folder->getId() . ' is empty and meets archive criteria, archiving folder');
						try {
							$this->moveToArchive($folder, 3, 2);
							$stats['foldersArchived']++;
							$this->logger->info('Archived empty folder ' . $folder->getId() . ' (' . $folder->getPath() . ')');
						} catch (Exception $e) {
							$this->logger->warning('Failed to archive empty folder ' . $folder->getId() . ': ' . $e->getMessage(), [
								'exception' => $e,
								'folderPath' => $folder->getPath(),
							]);
						}
					} else {
						$this->logger->debug('Folder ' . $folder->getId() . ' is empty but does not meet archive criteria (age: ' . $folderTime->format('Y-m-d') . ', threshold: ' . $archiveBefore->format('Y-m-d') . ')');
					}
				}
			} catch (Exception $e) {
				// If we can't check folder state, log but don't fail
				$this->logger->debug('Could not check if folder ' . $folder->getId() . ' is empty: ' . $e->getMessage());
			}
		}
		
		return $stats;
	}

	/**
	 * Get a node for the given fileid.
	 */
	private function checkFileId(int $fileId): Node {
		$mountPoints = $this->userMountCache->getMountsForFileId($fileId);

		if (empty($mountPoints)) {
			throw new NotFoundException("No mount points found for file $fileId");
		}

		foreach ($mountPoints as $mountPoint) {
			try {
				return $this->getMovableNodeFromMountPoint($mountPoint, $fileId);
			} catch (NotPermittedException $e) {
				$this->logger->debug('Mount point ' . ($mountPoint->getMountId() ?? 'null') . ' has no move permissions for file ' . $fileId);
			} catch (NotFoundException $e) {
				// Already logged explicitly inside
			}
		}

		throw new NotFoundException("No mount point with move permissions found for file $fileId");
	}

	protected function getMovableNodeFromMountPoint(ICachedMountFileInfo $mountPoint, int $fileId): Node {
		try {
			$userId = $mountPoint->getUser()->getUID();
			$userFolder = $this->rootFolder->getUserFolder($userId);
			if (!Filesystem::$loaded) {
				Filesystem::init($userId, '/' . $userId . '/files');
			}
		} catch (Exception $e) {
			$this->logger->debug($e->getMessage(), [
				'exception' => $e,
			]);
			throw new NotFoundException('Could not get user', 0, $e);
		}

		$nodes = $userFolder->getById($fileId);
		if (empty($nodes)) {
			throw new NotFoundException('No node for file ' . $fileId . ' and user ' . $userId);
		}

		foreach ($nodes as $node) {
			// Check if node can be moved (not just deleted)
			if ($node->isDeletable() && $node->isUpdateable()) {
				return $node;
			}
			$this->logger->debug('Mount point ' . ($mountPoint->getMountId() ?? 'null') . ' has access to node ' . $node->getId() . ' but permissions are ' . $node->getPermissions());
		}

		throw new NotPermittedException();
	}

	protected function getDateFromNode(Node $node, int $timeAfter): \DateTime {
		$time = new \DateTime();
		$time->setTimestamp($node->getMTime());

		if ($timeAfter === Constants::MODE_CTIME && $node->getUploadTime() !== 0) {
			$time->setTimestamp($node->getUploadTime());
		} elseif ($timeAfter === Constants::MODE_MTIME && $node->getMTime() < $node->getUploadTime()) {
			$time->setTimestamp($node->getUploadTime());
			$this->logger->debug('Upload time of file ' . $node->getId() . ' is newer than modification time, continuing with that');
		}

		return $time;
	}

	/**
	 * Archive a node if it matches the criteria
	 * 
	 * @return bool True if file was archived, false otherwise
	 */
	private function archiveNode(Node $node, \DateTime $archiveBefore, int $timeAfter, ?string $tagId): bool {
		$time = $this->getDateFromNode($node, $timeAfter);

		if ($time < $archiveBefore) {
			$this->logger->debug('Archiving file ' . $node->getId() . ' (age: ' . $time->format('Y-m-d') . ', threshold: ' . $archiveBefore->format('Y-m-d') . ')');
			
			// Check if file is shared - shared files may have locks from multiple users
			$isShared = $this->isFileShared($node);
			if ($isShared) {
				$this->logger->debug('File ' . $node->getId() . ' is shared, using extended retry delays for locked files');
			}
			
			try {
				// Use longer retry delays for shared files (5s, 10s, 20s vs 2s, 4s, 8s)
				$retryDelay = $isShared ? 5 : 2;
				$this->moveToArchive($node, 3, $retryDelay);
				// Remove tag after archiving to prevent re-archiving (only for tag-based rules)
				if ($tagId !== null) {
					$this->removeTagFromFile($node->getId(), $tagId);
				}
				return true;
			} catch (LockedException $e) {
				// File is locked (being accessed/synced/shared) - will retry in next run
				$shareInfo = $isShared ? ' (shared file)' : '';
				$this->logger->warning('File ' . $node->getId() . $shareInfo . ' is locked and could not be archived. Will retry in next archive run.', [
					'exception' => $e,
					'filePath' => $node->getPath(),
					'isShared' => $isShared,
				]);
				return false;
			} catch (Exception $e) {
				$this->logger->error('Failed to archive file ' . $node->getId() . ': ' . $e->getMessage(), [
					'exception' => $e,
					'filePath' => $node->getPath(),
				]);
				return false;
			}
		} else {
			$this->logger->debug('Skipping file ' . $node->getId() . ' from archiving (age: ' . $time->format('Y-m-d') . ', threshold: ' . $archiveBefore->format('Y-m-d') . ')');
			return false;
		}
	}

	/**
	 * Move a node to the archive folder with retry logic for locked files
	 */
	private function moveToArchive(Node $node, int $maxRetries = 3, int $retryDelay = 2): void {
		$userId = $node->getOwner()->getUID();
		$userFolder = $this->rootFolder->getUserFolder($userId);

		// Get or create archive folder
		$wasCreated = false;
		try {
			$archiveNode = $userFolder->get(Constants::ARCHIVE_FOLDER);
			if (!$archiveNode instanceof Folder) {
				throw new NotPermittedException(Constants::ARCHIVE_FOLDER . ' exists but is not a folder');
			}
			$archiveFolder = $archiveNode;
		} catch (NotFoundException $e) {
			// Create archive folder if it doesn't exist
			$archiveFolder = $userFolder->newFolder(Constants::ARCHIVE_FOLDER);
			$wasCreated = true;
		}
		
		// If the archive folder was just created, add it to favorites
		if ($wasCreated) {
			$this->addToFavorites($archiveFolder);
		}

		// Determine relative path of the node inside the user's files folder
		$userFolderPath = rtrim($userFolder->getPath(), '/');
		$nodePath = $node->getPath();

		// Example:
		//   $userFolderPath = /user/files
		//   $nodePath       = /user/files/Photos/picture.jpg
		//   $relativePath   = Photos/picture.jpg
		if (str_starts_with($nodePath, $userFolderPath . '/')) {
			$relativePath = substr($nodePath, strlen($userFolderPath) + 1);
		} else {
			// Fallback: use just the file name
			$relativePath = $node->getName();
		}

		$isFolder = $node instanceof Folder;
		$relativeDir = trim(\dirname($relativePath), '/');
		$fileName = \basename($relativePath);

		// Ensure the same subfolder structure exists inside the archive folder
		$targetFolder = $archiveFolder;
		if ($relativeDir !== '' && $relativeDir !== '.') {
			$segments = explode('/', $relativeDir);
			foreach ($segments as $segment) {
				$segment = trim($segment);
				if ($segment === '' || $segment === '.') {
					continue;
				}

				if ($targetFolder->nodeExists($segment)) {
					$child = $targetFolder->get($segment);
					if ($child instanceof Folder) {
						$targetFolder = $child;
					} else {
						// Name conflict: a file exists where we want a folder.
						// In this rare case, append a suffix to create a folder.
						$segment .= '_folder';
						if ($targetFolder->nodeExists($segment)) {
							$child = $targetFolder->get($segment);
							if ($child instanceof Folder) {
								$targetFolder = $child;
							} else {
								// Give up on nesting, archive flat for this file.
								$targetFolder = $archiveFolder;
								break;
							}
						} else {
							$targetFolder = $targetFolder->newFolder($segment);
						}
					}
				} else {
					$targetFolder = $targetFolder->newFolder($segment);
				}
			}
		}

		// Generate unique name to avoid conflicts inside the target folder
		$uniqueName = $fileName;
		
		if ($isFolder) {
			// For folders, check if folder with this name already exists
			$counter = 0;
			while ($targetFolder->nodeExists($uniqueName)) {
				$counter++;
				$uniqueName = $fileName . ' (' . $counter . ')';
			}
		} else {
			// For files, use pathinfo to handle extensions
			$baseName = pathinfo($fileName, PATHINFO_FILENAME);
			$extension = pathinfo($fileName, PATHINFO_EXTENSION);
			$counter = 0;
			while ($targetFolder->nodeExists($uniqueName)) {
				$counter++;
				$uniqueName = $baseName . ' (' . $counter . ')' . ($extension ? '.' . $extension : '');
			}
		}

		// Try to move with retry logic for locked files
		$attempt = 0;
		$lastException = null;
		
		while ($attempt < $maxRetries) {
			try {
				$node->move($targetFolder->getPath() . '/' . $uniqueName);
				$nodeType = $isFolder ? 'folder' : 'file';
				$this->logger->debug('Archived ' . $nodeType . ' ' . $node->getId() . ' to ' . $targetFolder->getPath() . '/' . $uniqueName);
				return; // Success, exit the method
			} catch (LockedException $e) {
				$attempt++;
				$lastException = $e;
				
				if ($attempt < $maxRetries) {
					// Calculate exponential backoff delay: 2s, 4s, 8s
					$delay = $retryDelay * (2 ** ($attempt - 1));
					$this->logger->info('File ' . $node->getId() . ' is locked, retrying in ' . $delay . ' seconds (attempt ' . $attempt . '/' . $maxRetries . ')', [
						'exception' => $e,
						'filePath' => $node->getPath(),
					]);
					
					// Wait before retrying
					sleep($delay);
					
					// Re-fetch the node in case it changed
					try {
						$userId = $node->getOwner()->getUID();
						$userFolder = $this->rootFolder->getUserFolder($userId);
						$nodes = $userFolder->getById($node->getId());
						if (!empty($nodes)) {
							$node = $nodes[0];
						}
					} catch (Exception $refreshException) {
						$this->logger->warning('Could not refresh node before retry: ' . $refreshException->getMessage());
					}
				} else {
					// Max retries reached
					$this->logger->warning('File ' . $node->getId() . ' is locked after ' . $maxRetries . ' attempts, skipping. File will be archived in next run.', [
						'exception' => $e,
						'filePath' => $node->getPath(),
					]);
					throw $e; // Re-throw to be caught by archiveNode
				}
			}
		}
		
		// Should not reach here, but just in case
		if ($lastException !== null) {
			throw $lastException;
		}
	}

	/**
	 * Add the archive folder to favorites
	 * In Nextcloud, favorites are implemented using a special system tag
	 */
	private function addToFavorites(Folder $archiveFolder): void {
		try {
			$fileId = $archiveFolder->getId();
			$userId = $archiveFolder->getOwner()->getUID();
			
			// In Nextcloud, favorites use a special system tag
			// The tag name pattern is typically "$user!favorite" where $user is the user ID
			// However, system tags are global, so we need to find the right approach
			
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
					// Note: This might require admin permissions, so we'll try and catch
					$favoriteTag = $this->tagManager->createTag('favorite', true, true);
					$this->logger->debug('Created favorite tag for archive folder');
				} catch (Exception $e) {
					// If we can't create the tag, try using the Files app's built-in mechanism
					// In Nextcloud, favorites might be stored differently
					$this->logger->debug('Could not create favorite tag, trying alternative method: ' . $e->getMessage());
					
					// Alternative: Use WebDAV property or database directly
					// For now, we'll log and continue - the user can manually favorite it
					error_log('Time Archive: Could not add archive folder to favorites automatically. User can manually favorite the .archive folder.');
					return;
				}
			}
			
			// Assign the favorite tag to the archive folder
			$this->tagMapper->assignTags($fileId, 'files', [(string)$favoriteTag->getId()]);
			$this->logger->info('Added archive folder to favorites (file ID: ' . $fileId . ', user: ' . $userId . ')');
			error_log('Time Archive: Added .archive folder to favorites for user ' . $userId);
		} catch (Exception $e) {
			// Log but don't fail - favorite assignment is best effort
			$this->logger->warning('Failed to add archive folder to favorites: ' . $e->getMessage(), [
				'exception' => $e,
			]);
			error_log('Time Archive: Failed to add archive folder to favorites: ' . $e->getMessage());
		}
	}

	/**
	 * Check if a folder is a protected mobile app upload folder
	 * These folders should never be archived to prevent breaking mobile app auto-upload
	 * 
	 * @param Folder $folder The folder to check
	 * @param string $userId The user ID
	 * @return bool True if the folder is protected and should not be archived
	 */
	private function isProtectedFolder(Folder $folder, string $userId): bool {
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			$userFolderPath = rtrim($userFolder->getPath(), '/');
			$folderPath = $folder->getPath();
			
			// Check if this is a top-level folder (direct child of user's files folder)
			// Example: /user/files/Camera -> Camera is top-level
			$relativePath = '';
			if (str_starts_with($folderPath, $userFolderPath . '/')) {
				$relativePath = substr($folderPath, strlen($userFolderPath) + 1);
			} else {
				// Not in user's files folder, not protected
				return false;
			}
			
			// Check if this is a top-level folder (no slashes in relative path)
			// Top-level folders are direct children of /user/files/
			if (strpos($relativePath, '/') === false) {
				// This is a top-level folder, check if it's in the protected list
				$folderName = $folder->getName();
				if (in_array($folderName, Constants::PROTECTED_FOLDERS, true)) {
					return true;
				}
			}
			
			return false;
		} catch (Exception $e) {
			// If we can't check, assume it's not protected to be safe
			$this->logger->debug('Could not check if folder is protected: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Check if a file is shared with other users
	 * Returns true if the file or any parent folder is shared
	 */
	private function isFileShared(Node $node): bool {
		try {
			$owner = $node->getOwner();
			if ($owner === null) {
				return false;
			}
			
			$ownerId = $owner->getUID();
			
			// Check shares on the file itself
			$shares = $this->shareManager->getSharesBy($ownerId, \OCP\Share\IShare::TYPE_USER, $node, false, -1, 0);
			if (!empty($shares)) {
				return true;
			}
			
			$shares = $this->shareManager->getSharesBy($ownerId, \OCP\Share\IShare::TYPE_GROUP, $node, false, -1, 0);
			if (!empty($shares)) {
				return true;
			}
			
			$shares = $this->shareManager->getSharesBy($ownerId, \OCP\Share\IShare::TYPE_LINK, $node, false, -1, 0);
			if (!empty($shares)) {
				return true;
			}
			
			// Check if parent folder is shared (files inherit share status from parent)
			$parent = $node->getParent();
			if ($parent !== null && $parent instanceof Folder) {
				$parentShares = $this->shareManager->getSharesBy($ownerId, \OCP\Share\IShare::TYPE_USER, $parent, false, -1, 0);
				if (!empty($parentShares)) {
					return true;
				}
				
				$parentShares = $this->shareManager->getSharesBy($ownerId, \OCP\Share\IShare::TYPE_GROUP, $parent, false, -1, 0);
				if (!empty($parentShares)) {
					return true;
				}
			}
			
			return false;
		} catch (Exception $e) {
			// If we can't check shares, assume it's not shared to be safe
			$this->logger->debug('Could not check if file ' . $node->getId() . ' is shared: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Remove the archive tag from a file after it has been processed
	 */
	private function removeTagFromFile(int $fileId, string $tagId): void {
		try {
			$this->tagMapper->unassignTags($fileId, 'files', [$tagId]);
			$this->logger->debug('Removed archive tag ' . $tagId . ' from file ' . $fileId . ' to prevent re-archiving');
		} catch (Exception $e) {
			// Log but don't fail - tag removal is best effort
			$this->logger->warning('Failed to remove tag ' . $tagId . ' from file ' . $fileId . ': ' . $e->getMessage(), [
				'exception' => $e,
			]);
		}
	}

	private function getBeforeDate(int $timeunit, int $timeAmount): \DateTime {
		$currentDate = new \DateTime();
		$currentDate->setTimestamp($this->time->getTime());

		if ($timeunit === Constants::UNIT_DAY) {
			$delta = new \DateInterval('P' . $timeAmount . 'D');
		} elseif ($timeunit === Constants::UNIT_WEEK) {
			$delta = new \DateInterval('P' . $timeAmount . 'W');
		} elseif ($timeunit === Constants::UNIT_MONTH) {
			$delta = new \DateInterval('P' . $timeAmount . 'M');
		} elseif ($timeunit === Constants::UNIT_YEAR) {
			$delta = new \DateInterval('P' . $timeAmount . 'Y');
		} elseif ($timeunit === Constants::UNIT_MINUTE) {
			$delta = new \DateInterval('PT' . $timeAmount . 'M');
		} elseif ($timeunit === Constants::UNIT_HOUR) {
			$delta = new \DateInterval('PT' . $timeAmount . 'H');
		} else {
			// Default to days if invalid unit
			$delta = new \DateInterval('P' . $timeAmount . 'D');
		}

		return $currentDate->sub($delta);
	}
}
