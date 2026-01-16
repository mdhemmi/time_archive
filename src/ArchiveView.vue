<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -->
<template>
	<div id="archive-view" class="archive-view">
		<div class="archive-view__header">
			<h2 class="archive-view__title">
				<Archive :size="24" />
				{{ t('time_archive', 'Archived Files') }}
			</h2>
			<p class="archive-view__description">
				{{ t('time_archive', 'Files that have been automatically archived based on your archive rules.') }}
			</p>
		</div>

		<div v-if="loading" class="archive-view__loading">
			<p>{{ t('time_archive', 'Loading archived files...') }}</p>
		</div>

		<div v-else-if="error" class="archive-view__error">
			<p class="archive-view__error-text">{{ error }}</p>
		</div>

		<div v-else-if="files.length === 0" class="archive-view__empty">
			<Archive :size="64" />
			<h3>{{ t('time_archive', 'No archived files') }}</h3>
			<p>{{ t('time_archive', 'Files will appear here once they have been archived by your archive rules.') }}</p>
		</div>

		<div v-else class="archive-view__content">
			<div class="archive-view__stats">
				{{ t('time_archive', '{count} archived file', { count: files.length }, files.length) }}
				({{ formatTotalSize() }})
			</div>

			<div class="archive-view__tree">
				<ArchiveTreeNode
					v-for="node in folderTree"
					:key="node.path"
					:node="node"
					@open-file="openFile"
				/>
			</div>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import Archive from 'vue-material-design-icons/Archive.vue'
import ArchiveTreeNode from './Components/ArchiveTreeNode.vue'
import { getArchivedFiles } from './services/archiveService.js'

export default {
	name: 'ArchiveView',
	components: {
		NcButton,
		Archive,
		ArchiveTreeNode,
	},
	data() {
		return {
			files: [],
			loading: true,
			error: null,
		}
	},
	computed: {
		folderTree() {
			// Build folder tree structure from file paths
			const tree = {}
			
			this.files.forEach(file => {
				const pathParts = file.path.split('/').filter(p => p)
				if (pathParts.length === 0) return
				
				let current = tree
				
				// Build folder structure
				for (let i = 0; i < pathParts.length - 1; i++) {
					const part = pathParts[i]
					if (!current[part]) {
						current[part] = {
							type: 'folder',
							name: part,
							path: pathParts.slice(0, i + 1).join('/'),
							children: {},
							files: [],
						}
					}
					current = current[part].children
				}
				
				// Add file to the last folder
				const fileName = pathParts[pathParts.length - 1]
				let parent = tree
				for (const part of pathParts.slice(0, -1)) {
					parent = parent[part].children
				}
				if (!parent[fileName]) {
					parent[fileName] = {
						type: 'file',
						...file,
						name: fileName,
					}
				}
			})
			
			// Convert to array format
			const convertToArray = (obj) => {
				return Object.values(obj).map(item => {
					if (item.type === 'folder') {
						return {
							...item,
							children: convertToArray(item.children),
						}
					}
					return item
				}).sort((a, b) => {
					// Folders first, then files, both alphabetically
					if (a.type === 'folder' && b.type !== 'folder') return -1
					if (a.type !== 'folder' && b.type === 'folder') return 1
					return a.name.localeCompare(b.name)
				})
			}
			
			return convertToArray(tree)
		},
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
				this.error = t('time_archive', 'Failed to load archived files')
				console.error('Error loading archived files:', e)
				showError(t('time_archive', 'Failed to load archived files'))
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

<style scoped lang="scss">
.archive-view {
	min-height: 100vh;
	width: 100%;
	max-width: 100%;
	background: var(--color-main-background);
	padding: 0;
	margin: 0;
	box-sizing: border-box;
}

.archive-view__header {
	margin: 0;
	padding: 24px;
	padding-bottom: 16px;
	border-bottom: 1px solid var(--color-border);
	background: var(--color-main-background);
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
	padding: 24px;
	width: 100%;
	max-width: 100%;
	box-sizing: border-box;
	background: var(--color-main-background);
}

.archive-view__tree {
	width: 100%;
	max-width: 100%;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 8px;
	box-sizing: border-box;
}

.archive-view__stats {
	margin-bottom: 16px;
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}

.archive-view__stats {
	margin-bottom: 16px;
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}
</style>
