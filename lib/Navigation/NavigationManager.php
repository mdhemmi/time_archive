<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Time_Archive\Navigation;

use OCA\Time_Archive\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Navigation\INavigationManager;

class NavigationManager {
	public function __construct(
		private INavigationManager $navigationManager,
		private IURLGenerator $urlGenerator,
		private IFactory $l10nFactory,
	) {
	}

	public function register(): void {
		$l = $this->l10nFactory->get(Application::APP_ID);

		// Register navigation entry in top navigation bar
		// Following the same pattern as Files and Activity apps
		try {
			// Generate URL to our own archive view
			// Try using linkToRoute first, fallback to linkTo
			try {
				$archiveUrl = $this->urlGenerator->linkToRoute('time_archive.Page.index');
			} catch (\Exception $e) {
				$archiveUrl = $this->urlGenerator->linkTo('time_archive', 'index.php');
			}

			// Get icon path (relative - Nextcloud will make it absolute)
			$iconPath = $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg');

			error_log('[Time Archive] Registering navigation entry...');
			error_log('[Time Archive] Archive URL: ' . $archiveUrl);
			error_log('[Time Archive] Icon path: ' . $iconPath);

			// Simple navigation entry array (like Files/Activity apps)
			// Note: INavigationManager might be for app navigation, not top bar
			// But we'll try it anyway
			$entry = [
				'id' => Application::APP_ID,
				'order' => 10,
				'href' => $archiveUrl,
				'icon' => $iconPath,
				'name' => $l->t('Archive'),
				'type' => 'link',
				'app' => Application::APP_ID,
			];
			
			error_log('[Time Archive] Navigation entry: ' . json_encode($entry, JSON_UNESCAPED_SLASHES));
			error_log('[Time Archive] NavigationManager class: ' . get_class($this->navigationManager));
			
			// Try registering directly first
			try {
				$this->navigationManager->add($entry);
				error_log('[Time Archive] Navigation entry added directly (non-closure)');
			} catch (\Exception $e) {
				error_log('[Time Archive] Direct add failed, trying closure: ' . $e->getMessage());
				// Register using closure (standard Nextcloud pattern)
				// The closure is called when navigation is rendered
				$this->navigationManager->add(function () use ($entry) {
					error_log('[Time Archive] Navigation closure called, returning entry');
					return $entry;
				});
			}
			
			error_log('[Time Archive] Navigation entry registered successfully');
		} catch (\Exception $e) {
			error_log('[Time Archive] Failed to register navigation: ' . $e->getMessage());
			error_log('[Time Archive] Stack trace: ' . $e->getTraceAsString());
		} catch (\Throwable $e) {
			error_log('[Time Archive] Fatal error: ' . $e->getMessage());
		}
	}
}
