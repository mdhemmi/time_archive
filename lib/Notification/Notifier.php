<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Time_Archive\Notification;

use OCA\Time_Archive\AppInfo\Application;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {
	public function __construct(
		private readonly IFactory $l10Factory,
		private readonly IRootFolder $rootFolder,
		private readonly IURLGenerator $url,
	) {
	}

	#[\Override]
	public function getID(): string {
		return Application::APP_ID;
	}

	#[\Override]
	public function getName(): string {
		return $this->l10Factory->get(Application::APP_ID)->t('File Archive');
	}

	#[\Override]
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException();
		}

		$userFolder = $this->rootFolder->getUserFolder($notification->getUser());

		$subject = $notification->getSubjectParameters();
		$fileId = (int)$subject['fileId'];

		$nodes = $userFolder->getById($fileId);
		if (empty($nodes)) {
			throw new AlreadyProcessedException();
		}
		/** @var Node $node */
		$node = array_pop($nodes);

		$l = $this->l10Factory->get(Application::APP_ID, $languageCode);
		$notification->setRichSubject(
			$l->t('{file} will be archived in 24 hours'),
			[
				'file' => [
					'type' => 'file',
					'id' => (string)$node->getId(),
					'name' => $node->getName(),
					'path' => (string)$userFolder->getRelativePath($node->getPath()),
					'mimetype' => $node->getMimetype(),
					'link' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $fileId]),
				],
			])
			->setParsedSubject(str_replace('{file}', $node->getName(), $l->t('{file} will be archived in 24 hours')))
			->setRichMessage(
				$l->t('Your file will be moved to the .archive folder, which is hidden from mobile apps but accessible via the web interface.')
			)
			->setParsedMessage($l->t('Your file will be moved to the .archive folder, which is hidden from mobile apps but accessible via the web interface.'))
			->setIcon($this->url->getAbsoluteURL($this->url->imagePath('time_archive', 'app-dark.svg')));

		return $notification;
	}
}
