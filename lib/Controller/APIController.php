<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_Archive\Controller;

use OCA\Files_Archive\BackgroundJob\ArchiveJob;
use OCA\Files_Archive\Constants;
use OCA\Files_Archive\ResponseDefinitions;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\TagNotFoundException;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use OCP\SystemTag\ISystemTagObjectMapper;

/**
 * @psalm-import-type Files_ArchiveRule from ResponseDefinitions
 */
class APIController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IDBConnection $db,
		private readonly ISystemTagManager $tagManager,
		private readonly IJobList $jobList,
		private readonly LoggerInterface $logger,
		private readonly ITimeFactory $timeFactory,
		private readonly ISystemTagObjectMapper $tagMapper,
		private readonly IUserMountCache $userMountCache,
		private readonly IRootFolder $rootFolder,
		private readonly IUserManager $userManager,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List archive rules
	 *
	 * @return DataResponse<Http::STATUS_OK, list<Files_ArchiveRule>, array{}>
	 *
	 * 200: List archive rules
	 */
	public function getArchiveRules(): DataResponse {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('archive_rules')
			->orderBy('id');

		$cursor = $qb->executeQuery();

		$result = $tagIds = [];
		try {
			while ($data = $cursor->fetch()) {
				// Handle tag_id - it might be null, empty string, or an integer
				$tagIdValue = $data['tag_id'];
				$tagId = ($tagIdValue !== null && $tagIdValue !== '' && $tagIdValue !== '0') ? (int)$tagIdValue : null;
				$ruleId = (int)$data['id'];
				
				// For time-based rules (no tag), use rule ID as job identifier
				$jobKey = $tagId !== null ? ['tag' => $tagId] : ['rule' => $ruleId];
				try {
					$hasJob = $this->jobList->has(ArchiveJob::class, $jobKey);
					if (!$hasJob) {
						$this->jobList->add(ArchiveJob::class, $jobKey);
					}
				} catch (\Exception $e) {
					$this->logger->warning('Failed to check/add job for rule ' . $ruleId . ': ' . $e->getMessage());
				}

				if ($tagId !== null) {
					$tagIds[] = (string)$tagId;
				}

				$result[] = [
					'id' => $ruleId,
					'tagid' => $tagId,
					'timeunit' => (int)$data['time_unit'],
					'timeamount' => (int)$data['time_amount'],
					'timeafter' => (int)$data['time_after'],
					'hasJob' => true,
				];
			}
		} finally {
			$cursor->closeCursor();
		}

		// Only validate tags if there are any
		if (!empty($tagIds)) {
			try {
				$this->tagManager->getTagsByIds($tagIds);
			} catch (TagNotFoundException $e) {
				$missingTags = array_map('intval', $e->getMissingTags());

				$result = array_values(array_filter($result, static function (array $rule) use ($missingTags): bool {
					return $rule['tagid'] === null || !in_array($rule['tagid'], $missingTags, true);
				}));
			} catch (\InvalidArgumentException $e) {
				// If tag IDs are invalid, filter out rules with those tags
				$this->logger->warning('Invalid tag IDs found in archive rules: ' . $e->getMessage());
				$result = array_values(array_filter($result, static function (array $rule): bool {
					return $rule['tagid'] === null;
				}));
			}
		}

		return new DataResponse($result);
	}

	/**
	 * Create an archive rule
	 *
	 * @param int|null $tagid Tag the archive rule is based on (null for time-based archiving)
	 * @param 0|1|2|3 $timeunit Time unit (days, weeks, months, years)
	 * @psalm-param Constants::UNIT_* $timeunit
	 * @param positive-int $timeamount Amount of time units
	 * @param 0|1 $timeafter Whether archive time is based on creation time (0) or modification time (1)
	 * @psalm-param Constants::MODE_* $timeafter
	 * @return DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'tagid'|'timeunit'|'timeamount'|'timeafter'}, array{}>|DataResponse<Http::STATUS_CREATED, Files_ArchiveRule, array{}>
	 *
	 * 201: Archive rule created
	 * 400: At least one of the parameters was invalid
	 */
	public function createArchiveRule(?int $tagid, int $timeunit, int $timeamount, int $timeafter = Constants::MODE_CTIME): DataResponse {
		// Validate tag if provided
		if ($tagid !== null) {
			try {
				$this->tagManager->getTagsByIds((string)$tagid);
			} catch (\InvalidArgumentException) {
				return new DataResponse(['error' => 'tagid'], Http::STATUS_BAD_REQUEST);
			}
		}

		if ($timeunit < 0 || $timeunit > 3) {
			return new DataResponse(['error' => 'timeunit'], Http::STATUS_BAD_REQUEST);
		}
		if ($timeamount < 1) {
			return new DataResponse(['error' => 'timeamount'], Http::STATUS_BAD_REQUEST);
		}
		if ($timeafter < 0 || $timeafter > 1) {
			return new DataResponse(['error' => 'timeafter'], Http::STATUS_BAD_REQUEST);
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert('archive_rules')
			->setValue('tag_id', $qb->createNamedParameter($tagid))
			->setValue('time_unit', $qb->createNamedParameter($timeunit))
			->setValue('time_amount', $qb->createNamedParameter($timeamount))
			->setValue('time_after', $qb->createNamedParameter($timeafter));

		$qb->executeStatement();
		$id = $qb->getLastInsertId();

		// Insert background job - use rule ID for time-based, tag ID for tag-based
		$jobKey = $tagid !== null ? ['tag' => $tagid] : ['rule' => $id];
		$this->jobList->add(ArchiveJob::class, $jobKey);

		return new DataResponse([
			'id' => $id,
			'tagid' => $tagid,
			'timeunit' => $timeunit,
			'timeamount' => $timeamount,
			'timeafter' => $timeafter,
			'hasJob' => true,
		], Http::STATUS_CREATED);
	}

	/**
	 * Delete an archive rule
	 *
	 * @param int $id Archive rule to delete
	 * @return DataResponse<Http::STATUS_NO_CONTENT|Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 204: Archive rule deleted
	 * 404: Archive rule not found
	 */
	public function deleteArchiveRule(int $id): DataResponse {
		$qb = $this->db->getQueryBuilder();

		// Fetch tag_id and rule info
		$qb->select('tag_id')
			->from('archive_rules')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->setMaxResults(1);
		$cursor = $qb->executeQuery();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		// Remove from archive_rules db
		$qb = $this->db->getQueryBuilder();
		$qb->delete('archive_rules')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$qb->executeStatement();

		// Remove background job - use appropriate key
		$tagId = $data['tag_id'];
		$jobKey = $tagId !== null ? ['tag' => (int)$tagId] : ['rule' => $id];
		$this->jobList->remove(ArchiveJob::class, $jobKey);

		return new DataResponse([], Http::STATUS_NO_CONTENT);
	}

	/**
	 * Manually trigger archive job for all active rules
	 *
	 * @return DataResponse<Http::STATUS_OK, array{message: string, rulesProcessed: int}, array{}>
	 *
	 * 200: Archive job triggered
	 */
	public function runArchiveJob(): DataResponse {
		// Get all active archive rules
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('archive_rules')
			->orderBy('id');

		$cursor = $qb->executeQuery();
		$rules = [];
		while ($data = $cursor->fetch()) {
			$tagIdValue = $data['tag_id'];
			$tagId = ($tagIdValue !== null && $tagIdValue !== '' && $tagIdValue !== '0') ? (int)$tagIdValue : null;
			$ruleId = (int)$data['id'];
			
			$rules[] = [
				'id' => $ruleId,
				'tag_id' => $tagId,
				'time_unit' => (int)$data['time_unit'],
				'time_amount' => (int)$data['time_amount'],
				'time_after' => (int)$data['time_after'],
			];
		}
		$cursor->closeCursor();

		if (empty($rules)) {
			return new DataResponse([
				'message' => 'No archive rules configured',
				'rulesProcessed' => 0,
			], Http::STATUS_OK);
		}

		// Create and run archive job for each rule
		$rulesProcessed = 0;
		$errors = [];
		
		foreach ($rules as $rule) {
			try {
				$jobKey = $rule['tag_id'] !== null ? ['tag' => $rule['tag_id']] : ['rule' => $rule['id']];
				
				// Calculate archive before date for logging
				$archiveBefore = $this->calculateArchiveBeforeDate($rule['time_unit'], $rule['time_amount']);
				
				$this->logger->info('Manually triggered archive job for rule ' . $rule['id'] . ' (archive files older than ' . $archiveBefore->format('Y-m-d H:i:s') . ')');
				error_log('Files Archive: Starting archive job for rule ' . $rule['id'] . ' - archive files older than ' . $archiveBefore->format('Y-m-d H:i:s'));
				
				// Create a new ArchiveJob instance and run it immediately
				$job = new ArchiveJob(
					$this->timeFactory,
					$this->tagManager,
					$this->tagMapper,
					$this->userMountCache,
					$this->db,
					$this->rootFolder,
					$this->jobList,
					$this->userManager,
					$this->logger
				);
				
				$job->run($jobKey);
				$rulesProcessed++;
				
				$this->logger->info('Archive job completed for rule ' . $rule['id']);
				error_log('Files Archive: Archive job completed for rule ' . $rule['id']);
			} catch (\Exception $e) {
				$errorMsg = 'Failed to run archive job for rule ' . $rule['id'] . ': ' . $e->getMessage();
				$errors[] = $errorMsg;
				$this->logger->error($errorMsg, [
					'exception' => $e,
				]);
				error_log('Files Archive ERROR: ' . $errorMsg);
				error_log('Files Archive ERROR Stack: ' . $e->getTraceAsString());
			}
		}

		$message = 'Archive job completed';
		if ($rulesProcessed === 0) {
			$message = 'No rules were processed';
		} elseif ($rulesProcessed === 1) {
			$message = 'Archive job completed for 1 rule. Check logs for details about archived files.';
		} else {
			$message = "Archive job completed for $rulesProcessed rules. Check logs for details about archived files.";
		}

		return new DataResponse([
			'message' => $message,
			'rulesProcessed' => $rulesProcessed,
			'hint' => 'If no files were archived, they may not meet the age criteria. Check Nextcloud logs for details.',
		], Http::STATUS_OK);
	}

	/**
	 * Calculate archive before date (helper for logging)
	 */
	private function calculateArchiveBeforeDate(int $timeUnit, int $timeAmount): \DateTime {
		$spec = 'P' . $timeAmount;

		if ($timeUnit === Constants::UNIT_DAY) {
			$spec .= 'D';
		} elseif ($timeUnit === Constants::UNIT_WEEK) {
			$spec .= 'W';
		} elseif ($timeUnit === Constants::UNIT_MONTH) {
			$spec .= 'M';
		} elseif ($timeUnit === Constants::UNIT_YEAR) {
			$spec .= 'Y';
		}

		$delta = new \DateInterval($spec);
		$currentDate = new \DateTime();
		$currentDate->setTimestamp($this->timeFactory->getTime());

		return $currentDate->sub($delta);
	}
}
