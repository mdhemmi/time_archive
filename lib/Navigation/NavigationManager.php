<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_Archive\Navigation;

use OCA\Files_Archive\AppInfo\Application;
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
		try {
			// Generate URL to Files app with .archive directory
			// Use direct linkTo instead of linkToRoute for more reliability
			$archiveUrl = $this->urlGenerator->linkTo('', 'index.php/apps/files') . '?dir=/.archive';

			// Get icon path (relative is fine, Nextcloud will make it absolute)
			$iconPath = $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg');

			error_log('[Files Archive] Attempting to register navigation...');
			error_log('[Files Archive] Archive URL: ' . $archiveUrl);
			error_log('[Files Archive] Icon path: ' . $iconPath);
			error_log('[Files Archive] NavigationManager type: ' . get_class($this->navigationManager));

			// Create navigation entry array
			$entry = [
				'id' => Application::APP_ID,
				'order' => 10,
				'href' => $archiveUrl,
				'icon' => $iconPath,
				'name' => $l->t('Archive'),
				'app' => Application::APP_ID,
			];
			
			error_log('[Files Archive] Navigation entry array: ' . json_encode($entry));
			
			// Try both closure and direct array registration
			// First try closure (preferred method)
			$this->navigationManager->add(function () use ($entry) {
				error_log('[Files Archive] Navigation entry closure called');
				return $entry;
			});
			
			// Also try direct array (some Nextcloud versions prefer this)
			try {
				$this->navigationManager->add($entry);
				error_log('[Files Archive] Also registered navigation entry directly');
			} catch (\Exception $e) {
				// Ignore if direct registration fails, closure should work
				error_log('[Files Archive] Direct registration failed (expected): ' . $e->getMessage());
			}
			
			error_log('[Files Archive] Navigation entry registered successfully via add()');
		} catch (\Exception $e) {
			error_log('[Files Archive] Failed to register navigation: ' . $e->getMessage());
			error_log('[Files Archive] Stack trace: ' . $e->getTraceAsString());
		} catch (\Throwable $e) {
			error_log('[Files Archive] Fatal error registering navigation: ' . $e->getMessage());
			error_log('[Files Archive] Stack trace: ' . $e->getTraceAsString());
		}
	}
}
