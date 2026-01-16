<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -->
<template>
	<div class="archive-tree-node">
		<div
			v-if="node.type === 'folder'"
			class="archive-tree-node__folder"
			:class="{ 'archive-tree-node__folder--expanded': isExpanded }"
		>
			<button
				class="archive-tree-node__folder-toggle"
				@click="toggleExpanded"
				:aria-label="isExpanded ? t('time_archive', 'Collapse folder') : t('time_archive', 'Expand folder')"
			>
				<ChevronRight :size="16" :class="{ 'archive-tree-node__chevron--expanded': isExpanded }" />
			</button>
			<Folder :size="20" class="archive-tree-node__icon" />
			<span class="archive-tree-node__name">{{ node.name }}</span>
			<span class="archive-tree-node__count">({{ getItemCount() }})</span>
		</div>
		
		<div
			v-else
			class="archive-tree-node__file"
		>
			<File :size="20" class="archive-tree-node__icon" />
			<span class="archive-tree-node__name">{{ node.name }}</span>
			<span class="archive-tree-node__size">{{ formatFileSize(node.size) }}</span>
			<span class="archive-tree-node__date">{{ formatDate(node.mtime) }}</span>
			<NcButton
				type="tertiary"
				:aria-label="t('time_archive', 'Open {file}', { file: node.name })"
				@click="$emit('open-file', node)"
			>
				<template #icon>
					<Download :size="16" />
				</template>
				{{ t('time_archive', 'Open') }}
			</NcButton>
		</div>
		
		<div v-if="node.type === 'folder' && isExpanded" class="archive-tree-node__children">
			<ArchiveTreeNode
				v-for="child in node.children"
				:key="child.path || child.id"
				:node="child"
				@open-file="$emit('open-file', $event)"
			/>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import Folder from 'vue-material-design-icons/Folder.vue'
import File from 'vue-material-design-icons/File.vue'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import Download from 'vue-material-design-icons/Download.vue'

export default {
	name: 'ArchiveTreeNode',
	components: {
		NcButton,
		Folder,
		File,
		ChevronRight,
		Download,
	},
	props: {
		node: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			isExpanded: false, // Start with folders collapsed
		}
	},
	methods: {
		t,
		toggleExpanded() {
			this.isExpanded = !this.isExpanded
		},
		getItemCount() {
			const countFiles = (node) => {
				if (node.type === 'file') return 1
				return node.children.reduce((sum, child) => sum + countFiles(child), 0)
			}
			return countFiles(this.node)
		},
		formatFileSize(bytes) {
			if (bytes === 0) return '0 B'
			const k = 1024
			const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
			const i = Math.floor(Math.log(bytes) / Math.log(k))
			return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
		},
		formatDate(timestamp) {
			const date = new Date(timestamp * 1000)
			return date.toLocaleDateString() + ' ' + date.toLocaleTimeString()
		},
	},
}
</script>

<style scoped lang="scss">
.archive-tree-node {
	margin-left: 0;
	
	&__folder,
	&__file {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 8px 12px;
		border-radius: var(--border-radius);
		transition: background-color 0.2s;
		
		&:hover {
			background-color: var(--color-background-hover);
		}
	}
	
	&__folder {
		font-weight: 500;
		cursor: pointer;
		user-select: none;
	}
	
	&__file {
		font-weight: 400;
	}
	
	&__folder-toggle {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 20px;
		height: 20px;
		padding: 0;
		margin: 0;
		background: none;
		border: none;
		cursor: pointer;
		color: var(--color-text-maxcontrast);
		transition: transform 0.2s;
		
		&:hover {
			color: var(--color-main-text);
		}
	}
	
	&__chevron {
		transition: transform 0.2s;
		
		&--expanded {
			transform: rotate(90deg);
		}
	}
	
	&__icon {
		flex-shrink: 0;
		color: var(--color-text-maxcontrast);
	}
	
	&__name {
		flex: 1;
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	
	&__count {
		color: var(--color-text-maxcontrast);
		font-size: 0.9em;
		margin-left: auto;
	}
	
	&__size {
		color: var(--color-text-maxcontrast);
		font-size: 0.9em;
		min-width: 80px;
		text-align: right;
	}
	
	&__date {
		color: var(--color-text-maxcontrast);
		font-size: 0.9em;
		min-width: 180px;
		text-align: right;
	}
	
	&__children {
		margin-left: 24px;
		border-left: 1px solid var(--color-border);
		padding-left: 8px;
	}
}
</style>
