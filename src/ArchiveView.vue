<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -->
<template>
	<div id="archive-view" class="archive-view">
		<div class="archive-view__header">
			<h2 class="archive-view__title">
				<Archive :size="24" />
				{{ t('files_archive', 'Archived Files') }}
			</h2>
			<p class="archive-view__description">
				{{ t('files_archive', 'Files that have been automatically archived based on your archive rules.') }}
			</p>
		</div>

		<div v-if="loading" class="archive-view__loading">
			<p>{{ t('files_archive', 'Loading archived files...') }}</p>
		</div>

		<div v-else-if="error" class="archive-view__error">
			<p class="archive-view__error-text">{{ error }}</p>
		</div>

		<div v-else-if="files.length === 0" class="archive-view__empty">
			<Archive :size="64" />
			<h3>{{ t('files_archive', 'No archived files') }}</h3>
			<p>{{ t('files_archive', 'Files will appear here once they have been archived by your archive rules.') }}</p>
		</div>

		<div v-else class="archive-view__content">
			<div class="archive-view__stats">
				{{ t('files_archive', '{count} archived file', { count: files.length }, files.length) }}
				({{ formatTotalSize() }})
			</div>

			<table class="archive-view__table">
				<thead>
					<tr>
						<th>{{ t('files_archive', 'Name') }}</th>
						<th>{{ t('files_archive', 'Size') }}</th>
						<th>{{ t('files_archive', 'Modified') }}</th>
						<th>{{ t('files_archive', 'Actions') }}</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="file in files" :key="file.id">
						<td class="archive-view__file-name">
							{{ file.path }}
						</td>
						<td>{{ formatFileSize(file.size) }}</td>
						<td>{{ formatDate(file.mtime) }}</td>
						<td>
							<NcButton
								type="tertiary"
								:aria-label="t('files_archive', 'Open {file}', { file: file.name })"
								@click="openFile(file)">
								<template #icon>
									<Download :size="16" />
								</template>
								{{ t('files_archive', 'Open') }}
							</NcButton>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import Archive from 'vue-material-design-icons/Archive.vue'
import Download from 'vue-material-design-icons/Download.vue'
import { getArchivedFiles } from './services/archiveService.js'

export default {
	name: 'ArchiveView',
	components: {
		NcButton,
		Archive,
		Download,
	},
	data() {
		return {
			files: [],
			loading: true,
			error: null,
		}
	},
	mounted() {
		this.loadFiles()
	},
	methods: {
		t,
		async loadFiles() {
			this.loading = true
			this.error = null
			try {
				const response = await getArchivedFiles()
				// Handle both OCS and direct response formats
				this.files = response.data?.ocs?.data?.files || response.data?.files || []
			} catch (e) {
				this.error = t('files_archive', 'Failed to load archived files')
				console.error('Error loading archived files:', e)
				showError(t('files_archive', 'Failed to load archived files'))
			} finally {
				this.loading = false
			}
		},
		formatFileSize(bytes) {
			if (bytes === 0) return '0 B'
			const k = 1024
			const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
			const i = Math.floor(Math.log(bytes) / Math.log(k))
			return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
		},
		formatTotalSize() {
			const total = this.files.reduce((sum, file) => sum + (file.size || 0), 0)
			return this.formatFileSize(total)
		},
		formatDate(timestamp) {
			const date = new Date(timestamp * 1000)
			return date.toLocaleDateString() + ' ' + date.toLocaleTimeString()
		},
		openFile(file) {
			// Open file in Files app
			window.location.href = OC.generateUrl('/apps/files/?dir=/.archive&openfile=' + file.id)
		},
	},
}
</script>

<style scoped>
.archive-view {
	min-height: 100vh;
	background: var(--color-main-background);
	padding: 24px;
}

.archive-view__header {
	margin-bottom: 24px;
	padding-bottom: 16px;
	border-bottom: 1px solid var(--color-border);
}

.archive-view__title {
	display: flex;
	align-items: center;
	gap: 12px;
	font-size: 24px;
	font-weight: 600;
	margin: 0 0 8px 0;
	color: var(--color-main-text);
}

.archive-view__description {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.archive-view__loading,
.archive-view__empty {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 64px 24px;
	gap: 16px;
	text-align: center;
}

.archive-view__empty h3 {
	margin: 0;
	color: var(--color-main-text);
}

.archive-view__empty p {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.archive-view__error {
	padding: 24px;
	text-align: center;
}

.archive-view__error-text {
	color: var(--color-error);
}

.archive-view__content {
	padding: 24px 0;
}

.archive-view__stats {
	margin-bottom: 16px;
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}

.archive-view__table {
	width: 100%;
	border-collapse: collapse;
	background: var(--color-main-background);
}

.archive-view__table thead {
	background: var(--color-background-dark);
	border-bottom: 2px solid var(--color-border);
}

.archive-view__table th {
	padding: 12px;
	text-align: left;
	font-weight: 600;
	color: var(--color-main-text);
}

.archive-view__table td {
	padding: 12px;
	border-bottom: 1px solid var(--color-border);
	color: var(--color-main-text);
}

.archive-view__table tbody tr:hover {
	background: var(--color-background-dark);
}

.archive-view__file-name {
	font-weight: 500;
}
</style>
