<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -->
<template>
	<NcSettingsSection :name="t('time_archive', 'File Archive')"
		:doc-url="docUrl"
		:description="t('time_archive', 'Automatically archive files based on age. Archived files are moved to the .archive folder, which is hidden from mobile apps but accessible via the web interface.')">
		
		<!-- Info about viewing archived files -->
		<div class="archive-info">
			<div class="archive-info__card">
				<strong>{{ t('time_archive', 'Accessing archived files') }}</strong>
				<p>{{ t('time_archive', 'The .archive folder is hidden from mobile apps but accessible via the web interface. An "Archive" link is available in the Files app sidebar navigation for easy access.') }}</p>
			</div>
		</div>

		<!-- Manual Archive Button -->
		<div class="archive-actions">
			<NcButton variant="secondary"
				type="button"
				:disabled="loading || runningArchive"
				:aria-label="t('time_archive', 'Run archive job now')"
				@click="onClickRunArchive">
				<template #icon>
					<Play :size="20" />
				</template>
				{{ runningArchive ? t('time_archive', 'Running...') : t('time_archive', 'Run archive now') }}
			</NcButton>
			<p class="archive-actions__hint">
				{{ t('time_archive', 'Manually trigger the archive job to process files immediately instead of waiting for the scheduled run.') }}
			</p>
		</div>

		<!-- Global include / exclude path configuration -->
		<div class="archive-path-settings">
			<h3 class="archive-section-title">
				{{ t('time_archive', 'Archive path filters') }}
			</h3>
			<p class="archive-path-settings__description">
				{{ t('time_archive', 'Limit archiving to specific folders or exclude folders and files globally. Paths are relative to the user files root (e.g. Projects, Projects/Archived, Shared/Reports).') }}
			</p>

			<div class="archive-path-settings__grid">
				<div class="archive-path-settings__field">
					<label class="archive-form__label" for="includePaths">
						{{ t('time_archive', 'Only archive from these paths') }}
					</label>
					<textarea id="includePaths"
						v-model="includePathsText"
						:disabled="loadingSettings"
						class="archive-path-settings__textarea"
						:placeholder="t('time_archive', 'One path per line, leave empty to allow all folders')"></textarea>
					<p class="archive-form__hint">
						{{ t('time_archive', 'If set, only files whose path starts with one of these entries will be considered for archiving.') }}
					</p>
				</div>

				<div class="archive-path-settings__field">
					<label class="archive-form__label" for="excludePaths">
						{{ t('time_archive', 'Never archive these paths') }}
					</label>
					<textarea id="excludePaths"
						v-model="excludePathsText"
						:disabled="loadingSettings"
						class="archive-path-settings__textarea"
						:placeholder="excludePathsPlaceholder"></textarea>
					<p class="archive-form__hint">
						{{ t('time_archive', 'Files and folders under these paths are always excluded from archiving, regardless of the rule.') }}
					</p>
				</div>

				<div class="archive-path-settings__field archive-path-settings__field--full">
					<label class="archive-form__label" for="excludedUsers">
						{{ t('time_archive', 'Excluded users') }}
					</label>
					<textarea id="excludedUsers"
						v-model="excludedUsersText"
						:disabled="loadingSettings"
						class="archive-path-settings__textarea"
						:placeholder="excludedUsersPlaceholder"></textarea>
					<p class="archive-form__hint">
						{{ t('time_archive', 'Users listed here are never processed by the archive job. One user ID per line.') }}
					</p>
				</div>
			</div>

			<div class="archive-path-settings__actions">
				<NcButton variant="secondary"
					type="button"
					:disabled="loadingSettings || savingSettings"
					@click="onClickSavePathSettings">
					<template #icon>
						<Archive :size="18" />
					</template>
					{{ savingSettings ? t('time_archive', 'Saving…') : t('time_archive', 'Save archive settings') }}
				</NcButton>
				<p v-if="settingsHint" class="archive-path-settings__hint">
					{{ settingsHint }}
				</p>
			</div>
		</div>

		<!-- Existing Rules -->
		<div v-if="archiveRules.length > 0" class="archive-rules-list">
			<h3 class="archive-section-title">
				{{ t('time_archive', 'Active archive rules') }}
			</h3>
			<div class="archive-rules-grid">
				<ArchiveRule v-for="rule in archiveRules"
					:key="rule.id"
					v-bind="rule" />
			</div>
		</div>

		<!-- Create New Rule Form -->
		<div class="archive-form-container">
			<h3 class="archive-section-title">
				{{ archiveRules.length > 0 ? t('time_archive', 'Create new archive rule') : t('time_archive', 'Create your first archive rule') }}
			</h3>
			
			<div class="archive-form">
				<!-- Archive Time -->
				<div class="archive-form__field-group">
					<div class="archive-form__field archive-form__field--time">
						<label class="archive-form__label">
							{{ t('time_archive', 'Archive after') }}
						</label>
						<div class="archive-form__time-inputs">
							<NcTextField v-model="newAmount"
								:disabled="loading"
								type="number"
								min="1"
								:label="t('time_archive', 'Amount')"
								:aria-label="t('time_archive', 'Number of time units')"
								class="archive-form__time-amount" />
							<NcSelect v-model="newUnit"
								:disabled="loading"
								:options="unitOptions"
								:allow-empty="false"
								:clearable="false"
								track-by="id"
								label="label"
								class="archive-form__time-unit" />
						</div>
						<p class="archive-form__hint">
							{{ t('time_archive', 'How long to keep files before archiving them.') }}
						</p>
					</div>

					<div class="archive-form__field">
						<label class="archive-form__label">
							{{ t('time_archive', 'Calculate from') }}
						</label>
						<NcSelect v-model="newAfter"
							:disabled="loading"
							:options="afterOptions"
							:allow-empty="false"
							:clearable="false"
							track-by="id"
							label="label"
							class="archive-form__input" />
						<p class="archive-form__hint">
							{{ t('time_archive', 'The date to use as the starting point for the archive period.') }}
						</p>
					</div>
				</div>

				<!-- Info Box -->
				<div class="archive-form__info-box">
					<Archive :size="20" class="archive-form__info-icon" />
					<p class="archive-form__info-text">
						{{ t('time_archive', 'All files matching the age criteria will be moved to the .archive folder for each user. This folder is hidden from mobile apps to prevent re-uploading. You can access archived files through the web interface by enabling "Show hidden files" in Files app settings.') }}
					</p>
				</div>

				<!-- Submit Button -->
				<div class="archive-form__actions">
					<NcButton variant="primary"
						type="button"
						:disabled="loading"
						:aria-label="createLabel"
						@click="onClickCreate">
						<template #icon>
							<Plus :size="20" />
						</template>
						{{ t('time_archive', 'Create archive rule') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcSettingsSection>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import Plus from 'vue-material-design-icons/Plus.vue'
import Archive from 'vue-material-design-icons/Archive.vue'
import Play from 'vue-material-design-icons/Play.vue'

import ArchiveRule from './Components/ArchiveRule.vue'
import { runArchiveJob, getArchiveSettings, updateArchiveSettings, getArchiveStats } from './services/archiveService.js'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

export default {
	name: 'AdminSettings',

	components: {
		NcButton,
		NcSelect,
		ArchiveRule,
		NcSettingsSection,
		NcTextField,
		Plus,
		Archive,
		Play,
	},

		data() {
		const unitOptions = [
			{ id: 0, label: t('time_archive', 'Days') },
			{ id: 1, label: t('time_archive', 'Weeks') },
			{ id: 2, label: t('time_archive', 'Months') },
			{ id: 3, label: t('time_archive', 'Years') },
			{ id: 4, label: t('time_archive', 'Minutes') },
			{ id: 5, label: t('time_archive', 'Hours') },
		]
		
		const afterOptions = [
			{ id: 0, label: t('time_archive', 'Creation date') },
			{ id: 1, label: t('time_archive', 'Last modification date') },
		]

		return {
			loading: true,
			runningArchive: false,
			docUrl: loadState('time_archive', 'doc-url'),

			unitOptions,
			newUnit: unitOptions[3], // Default to years

			afterOptions,
			newAfter: afterOptions[1], // Default to modification date

			newAmount: '365',

			// Global path filter settings
			includePathsText: loadState('time_archive', 'include-paths') || '',
			excludePathsText: loadState('time_archive', 'exclude-paths') || '',
			excludedUsersText: loadState('time_archive', 'excluded-users') || '',
			loadingSettings: false,
			savingSettings: false,
			settingsHint: '',

			// Statistics
			statsLoading: false,
			statsError: '',
			statsOverall: null,
			statsPerUser: [],
		}
	},

	computed: {
		archiveRules() {
			return this.$store.getters.getArchiveRules()
		},

		createLabel() {
			return t('time_archive', 'Create new archive rule')
		},

		excludePathsPlaceholder() {
			return t('time_archive', 'One path per line (e.g. .archive-temp or Projects/Drafts)')
		},

		excludedUsersPlaceholder() {
			return t('time_archive', 'One user ID per line')
		},
	},

	async mounted() {
		try {
			console.log('[Files Archive] Loading archive rules...')
			await this.$store.dispatch('loadArchiveRules')
			console.log('[Files Archive] Archive rules loaded:', this.archiveRules)
		} catch (e) {
			console.error('[Files Archive] Error loading archive rules:', e)
			console.error('[Files Archive] Error details:', e.response || e.message)
			const errorMsg = e.response?.data?.message || e.message || t('time_archive', 'An error occurred while loading the existing archive rules')
			showError(errorMsg)
		} finally {
			this.loading = false
		}

		// Load latest archive settings from backend (in case they changed server-side)
		this.loadPathSettings()
		// Load statistics for administrators
		this.loadStats()
	},

	methods: {
		t,

		async onClickCreate() {
			const newUnit = this.newUnit?.id ?? this.newUnit
			const newAfter = this.newAfter?.id ?? this.newAfter
			const newAmount = parseInt(this.newAmount, 10)

			if (newUnit < 0 || newUnit > 5) {
				showError(t('time_archive', 'Invalid time unit'))
				return
			}

			if (newAfter < 0 || newAfter > 1) {
				showError(t('time_archive', 'Invalid date option'))
				return
			}

			if (isNaN(newAmount) || newAmount < 1) {
				showError(t('time_archive', 'Please enter a valid archive period (at least 1)'))
				return
			}

			try {
				const ruleData = {
					tagid: null,
					timeamount: newAmount,
					timeunit: newUnit,
					timeafter: newAfter,
				}

				await this.$store.dispatch('createNewRule', ruleData)

				showSuccess(t('time_archive', 'Archive rule has been created'))
				this.resetForm()
			} catch (e) {
				showError(t('time_archive', 'Failed to create archive rule'))
				console.error(e)
			}
		},

		resetForm() {
			this.newAmount = '365'
			this.newUnit = this.unitOptions[3] // Default to years
			this.newAfter = this.afterOptions[1] // Default to modification date
		},

		async onClickRunArchive() {
			if (this.runningArchive) {
				return
			}

			this.runningArchive = true
			try {
				const response = await runArchiveJob()
				const data = response.data?.ocs?.data || response.data || {}
				const rulesProcessed = data.rulesProcessed || 0
				const message = data.message || t('time_archive', 'Archive job completed')
				const hint = data.hint || ''
				
				if (rulesProcessed > 0) {
					showSuccess(message + (hint ? ' ' + hint : ''))
				} else {
					showSuccess(message + (hint ? ' ' + hint : ''))
				}
			} catch (e) {
				showError(t('time_archive', 'Failed to run archive job: {error}', { error: e.message || 'Unknown error' }))
				console.error('Archive job error:', e)
			} finally {
				this.runningArchive = false
			}
		},

		async loadPathSettings() {
			this.loadingSettings = true
			this.settingsHint = ''
			try {
				const response = await getArchiveSettings()
				const data = response.data?.ocs?.data || response.data || {}
				this.includePathsText = data.includePaths ?? ''
				this.excludePathsText = data.excludePaths ?? ''
				this.excludedUsersText = data.excludedUsers ?? ''
			} catch (e) {
				console.error('[Files Archive] Failed to load archive path settings', e)
				this.settingsHint = t('time_archive', 'Failed to load archive path filters from the server.')
			} finally {
				this.loadingSettings = false
			}
		},

		async onClickSavePathSettings() {
			if (this.savingSettings) {
				return
			}

			this.savingSettings = true
			this.settingsHint = ''
			try {
				const payload = {
					includePaths: this.includePathsText,
					excludePaths: this.excludePathsText,
					excludedUsers: this.excludedUsersText,
				}
				const response = await updateArchiveSettings(payload)
				const data = response.data?.ocs?.data || response.data || {}
				this.includePathsText = data.includePaths ?? this.includePathsText
				this.excludePathsText = data.excludePaths ?? this.excludePathsText
				this.excludedUsersText = data.excludedUsers ?? this.excludedUsersText
				this.settingsHint = t('time_archive', 'Archive settings have been saved.')
				showSuccess(t('time_archive', 'Archive settings have been saved.'))
			} catch (e) {
				console.error('[Files Archive] Failed to save archive path settings', e)
				this.settingsHint = t('time_archive', 'Failed to save archive path filters.')
				showError(t('time_archive', 'Failed to save archive path filters.'))
			} finally {
				this.savingSettings = false
			}
		},

		async loadStats() {
			this.statsLoading = true
			this.statsError = ''
			try {
				const response = await getArchiveStats()
				const data = response.data?.ocs?.data || response.data || {}
				this.statsOverall = data.overall || null
				this.statsPerUser = Array.isArray(data.perUser) ? data.perUser : []
			} catch (e) {
				console.error('[Files Archive] Failed to load archive statistics', e)
				this.statsError = t('time_archive', 'Failed to load archive statistics.')
			} finally {
				this.statsLoading = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.archive-actions {
	margin-bottom: 32px;
	padding: 16px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.archive-actions__hint {
	font-size: 0.9em;
	color: var(--color-text-maxcontrast);
	margin: 8px 0 0 0;
	line-height: 1.4;
}

.archive-section-title {
	font-size: 1.2em;
	font-weight: 600;
	margin: 24px 0 16px 0;
	color: var(--color-main-text);
	
	&:first-child {
		margin-top: 0;
	}
}

.archive-rules-list {
	margin-bottom: 32px;
}

.archive-rules-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
	gap: 16px;
	margin-bottom: 8px;
}

.archive-form-container {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 24px;
	margin-bottom: 24px;
}

.archive-form {
	display: flex;
	flex-direction: column;
	gap: 24px;
}

.archive-form__field {
	display: flex;
	flex-direction: column;
	gap: 8px;

	&--time {
		flex: 1;
	}
}

.archive-form__field-group {
	display: grid;
	grid-template-columns: 2fr 1fr;
	gap: 16px;

	@media (max-width: 768px) {
		grid-template-columns: 1fr;
	}
}

.archive-form__label {
	font-weight: 600;
	color: var(--color-main-text);
	font-size: 0.95em;
}

.archive-form__input {
	width: 100%;
}

.archive-form__time-inputs {
	display: grid;
	grid-template-columns: 100px 1fr;
	gap: 12px;
	align-items: end;
}

.archive-form__time-amount {
	:deep(.input-field__input) {
		text-align: right;
	}
}

.archive-form__hint {
	font-size: 0.9em;
	color: var(--color-text-maxcontrast);
	margin: 0;
	line-height: 1.4;
}

.archive-form__info-box {
	display: flex;
	gap: 12px;
	align-items: flex-start;
	padding: 16px;
	background: var(--color-primary-element-light);
	border-radius: var(--border-radius);
	margin-top: 8px;
}

.archive-form__info-icon {
	flex-shrink: 0;
	margin-top: 2px;
	color: var(--color-primary-element);
}

.archive-form__info-text {
	margin: 0;
	font-size: 0.9em;
	color: var(--color-main-text);
	line-height: 1.5;
}

.archive-form__actions {
	display: flex;
	justify-content: flex-end;
	margin-top: 8px;
}

.archive-info {
	margin-bottom: 24px;
}

.archive-info__card {
	background: var(--color-primary-element-light);
	border: 1px solid var(--color-primary-element);
	border-radius: var(--border-radius);
	padding: 16px;
	
	strong {
		display: block;
		margin-bottom: 8px;
		color: var(--color-primary-element-text-dark);
		font-size: 0.95em;
	}
	
	p {
		margin: 0;
		font-size: 0.9em;
		color: var(--color-main-text);
		line-height: 1.5;
	}
}

.archive-path-settings {
	margin-bottom: 32px;
	padding: 20px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.archive-path-settings__description {
	margin: 0 0 12px 0;
	font-size: 0.9em;
	color: var(--color-text-maxcontrast);
}

.archive-path-settings__grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
	gap: 16px;
	margin-top: 8px;
}

.archive-path-settings__field {
	display: flex;
	flex-direction: column;
	gap: 8px;

	&--full {
		grid-column: 1 / -1;
	}
}

.archive-path-settings__textarea {
	min-height: 96px;
	max-height: 180px;
	resize: vertical;
	padding: 8px;
	border-radius: var(--border-radius);
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-family: var(--font-family-monospace);
}

.archive-path-settings__actions {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-top: 16px;
}

.archive-path-settings__hint {
	margin: 0;
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
}
</style>
