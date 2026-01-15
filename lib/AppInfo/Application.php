<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_Archive\AppInfo;

use OCA\Files_Archive\EventListener;
use OCA\Files_Archive\Navigation\NavigationManager;
use OCA\Files_Archive\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\SystemTag\ManagerEvent;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'files_archive';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	#[\Override]
	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(ManagerEvent::EVENT_DELETE, EventListener::class);
		$context->registerNotifierService(Notifier::class);
	}

	#[\Override]
	public function boot(IBootContext $context): void {
		$container = $context->getAppContainer();
		
		try {
			// Register top navigation entry
			$navigationManager = $container->get(NavigationManager::class);
			$navigationManager->register();
			error_log('[Files Archive] NavigationManager registered in boot()');
		} catch (\Exception $e) {
			error_log('[Files Archive] Error registering navigation in boot(): ' . $e->getMessage());
		}
		
		// Load Files app sidebar navigation script
		// This script will register the Archive entry in the Files app sidebar
		Util::addScript(self::APP_ID, 'files_archive-navigation');
	}
}

