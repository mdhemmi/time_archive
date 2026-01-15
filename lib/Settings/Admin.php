<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Time_Archive\Settings;

use OCA\Time_Archive\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {
	public function __construct(
		protected readonly IInitialState $initialState,
		protected readonly IURLGenerator $url,
	) {
	}

	#[\Override]
	public function getForm(): TemplateResponse {
		// Note: Webpack outputs files with app prefix (time_archive-main.js)
		// So we need to pass the full filename including the prefix
		Util::addScript('time_archive', 'time_archive-main');

		$this->initialState->provideInitialState(
			'doc-url',
			$this->url->linkToDocs('admin-time-archive')
		);

		return new TemplateResponse('time_archive', 'admin', [], '');
	}

	#[\Override]
	public function getSection(): string {
		return 'workflow';
	}

	#[\Override]
	public function getPriority(): int {
		return 80;
	}
}
