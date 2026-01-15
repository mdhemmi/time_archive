<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Time_Archive\Controller;

use OCA\Time_Archive\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index(): TemplateResponse {
		// Note: Webpack outputs files with app prefix (time_archive-archive.js)
		// So we need to pass the full filename including the prefix
		Util::addScript(Application::APP_ID, 'time_archive-archive');

		return new TemplateResponse(Application::APP_ID, 'archive', [], 'user');
	}
}
