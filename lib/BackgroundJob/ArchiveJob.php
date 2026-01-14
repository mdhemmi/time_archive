<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_Archive\BackgroundJob;

use Exception;
use OC\Files\Filesystem;
use OCA\Files_Archive\Constants;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\Config\ICachedMountFileInfo;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IDBConnection;
use OCP\IUserManager;
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
			error_log("Files Archive: Running archive for Tag $tagId with archive before " . $archiveBefore->format('Y-m-d H:i:s'));
			$this->archiveByTag($tagId, $archiveBefore, $timeAfter);
		} else {
			// Time-based archiving - archive all files for all users
			$this->logger->info("Running time-based archive (Rule $ruleId) with archive before " . $archiveBefore->format(\DateTimeInterface::ATOM));
			error_log("Files Archive: Running time-based archive (Rule $ruleId) with archive before " . $archiveBefore->format('Y-m-d H:i:s'));
			$stats = $this->archiveByTime($archiveBefore, $timeAfter);
			$this->logger->info("Archive job completed: " . json_encode($stats));
			error_log("Files Archive: Archive job completed - " . json_encode($stats));
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
	 * @return array{usersProcessed: int, filesArchived: int, filesChecked: int}
	 */
	private function archiveByTime(\DateTime $archiveBefore, int $timeAfter): array {
		$stats = [
			'usersProcessed' => 0,
			'filesArchived' => 0,
			'filesChecked' => 0,
		];
		
		error_log("Files Archive: Starting to process all users. Archive threshold: " . $archiveBefore->format('Y-m-d H:i:s'));
		
		$this->userManager->callForAllUsers(function ($user) use ($archiveBefore, $timeAfter, &$stats) {
			$userId = $user->getUID();
			try {
				error_log("Files Archive: Processing user: $userId");
				$userFolder = $this->rootFolder->getUserFolder($userId);
				if (!Filesystem::$loaded) {
					Filesystem::init($userId, '/' . $userId . '/files');
				}
				
				$userStats = $this->archiveUserFolder($userFolder, $archiveBefore, $timeAfter, $userId);
				$stats['usersProcessed']++;
				$stats['filesArchived'] += $userStats['filesArchived'];
				$stats['filesChecked'] += $userStats['filesChecked'];
				
				if ($userStats['filesChecked'] > 0) {
					error_log("Files Archive: User $userId - Checked: {$userStats['filesChecked']}, Archived: {$userStats['filesArchived']}");
				}
			} catch (Exception $e) {
				$errorMsg = "Failed to archive files for user $userId: " . $e->getMessage();
				error_log("Files Archive ERROR: $errorMsg");
				$this->logger->warning($errorMsg, [
					'exception' => $e,
				]);
			}
		});
		
		error_log("Files Archive: Completed processing. Total users: {$stats['usersProcessed']}, Files checked: {$stats['filesChecked']}, Files archived: {$stats['filesArchived']}");
		
		return $stats;
	}

	/**
	 * Recursively archive files in a folder
	 * 
	 * @return array{filesArchived: int, filesChecked: int}
	 */
	private function archiveUserFolder(Folder $folder, \DateTime $archiveBefore, int $timeAfter, string $userId): array {
		$stats = [
			'filesArchived' => 0,
			'filesChecked' => 0,
		];
		
		// Skip the archive folder itself
		if ($folder->getName() === Constants::ARCHIVE_FOLDER) {
			return $stats;
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
			} else {
				// Check and archive file
				$stats['filesChecked']++;
				$archived = $this->archiveNode($node, $archiveBefore, $timeAfter, null);
				if ($archived) {
					$stats['filesArchived']++;
				}
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
			try {
				$this->moveToArchive($node);
				// Remove tag after archiving to prevent re-archiving (only for tag-based rules)
				if ($tagId !== null) {
					$this->removeTagFromFile($node->getId(), $tagId);
				}
				return true;
			} catch (Exception $e) {
				$this->logger->error('Failed to archive file ' . $node->getId() . ': ' . $e->getMessage(), [
					'exception' => $e,
				]);
				return false;
			}
		} else {
			$this->logger->debug('Skipping file ' . $node->getId() . ' from archiving (age: ' . $time->format('Y-m-d') . ', threshold: ' . $archiveBefore->format('Y-m-d') . ')');
			return false;
		}
	}

	/**
	 * Move a node to the archive folder
	 */
	private function moveToArchive(Node $node): void {
		$userId = $node->getOwner()->getUID();
		$userFolder = $this->rootFolder->getUserFolder($userId);

		// Get or create archive folder
		try {
			$archiveNode = $userFolder->get(Constants::ARCHIVE_FOLDER);
			if (!$archiveNode instanceof Folder) {
				throw new NotPermittedException(Constants::ARCHIVE_FOLDER . ' exists but is not a folder');
			}
			$archiveFolder = $archiveNode;
		} catch (NotFoundException $e) {
			// Create archive folder if it doesn't exist
			$archiveFolder = $userFolder->newFolder(Constants::ARCHIVE_FOLDER);
		}

		// Generate unique filename to avoid conflicts
		$fileName = $node->getName();
		$baseName = pathinfo($fileName, PATHINFO_FILENAME);
		$extension = pathinfo($fileName, PATHINFO_EXTENSION);
		$uniqueName = $fileName;

		// If file with this name already exists, append counter
		$counter = 0;
		while ($archiveFolder->nodeExists($uniqueName)) {
			$counter++;
			$uniqueName = $baseName . ' (' . $counter . ')' . ($extension ? '.' . $extension : '');
		}

		$node->move($archiveFolder->getPath() . '/' . $uniqueName);
		$this->logger->debug('Archived file ' . $node->getId() . ' to ' . Constants::ARCHIVE_FOLDER . '/' . $uniqueName);
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
