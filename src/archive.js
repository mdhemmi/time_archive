/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'

import { generateFilePath } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'

import ArchiveView from './ArchiveView.vue'

// Styles
import '@nextcloud/dialogs/style.css'

// eslint-disable-next-line
__webpack_nonce__ = btoa(getRequestToken())

// eslint-disable-next-line
__webpack_public_path__ = generateFilePath('time_archive', '', 'js/')

createApp(ArchiveView)
	.mount('#archive-view')
