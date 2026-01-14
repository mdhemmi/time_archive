<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -->
<template>
	<div class="archive-rule-card">
		<div class="archive-rule-card__header">
			<div class="archive-rule-card__title">
				<ClockOutline :size="20" class="archive-rule-card__title-icon" />
				<span class="archive-rule-card__title-name">{{ t('files_archive', 'Time-based archive rule') }}</span>
			</div>
			<NcButton variant="tertiary"
				:aria-label="deleteLabel"
				@click="onClickDelete">
				<template #icon>
					<Delete :size="20" />
				</template>
			</NcButton>
		</div>

		<div class="archive-rule-card__body">
			<div class="archive-rule-card__detail">
				<ClockOutline :size="18" class="archive-rule-card__detail-icon" />
				<div class="archive-rule-card__detail-content">
					<span class="archive-rule-card__detail-label">{{ t('files_archive', 'Archive after') }}</span>
					<span class="archive-rule-card__detail-value">{{ getAmountAndUnit }}</span>
				</div>
			</div>

			<div class="archive-rule-card__detail">
				<Calendar :size="18" class="archive-rule-card__detail-icon" />
				<div class="archive-rule-card__detail-content">
					<span class="archive-rule-card__detail-label">{{ t('files_archive', 'Calculate from') }}</span>
					<span class="archive-rule-card__detail-value">{{ getAfter }}</span>
				</div>
			</div>

			<div class="archive-rule-card__detail">
				<Archive :size="18" class="archive-rule-card__detail-icon" />
				<div class="archive-rule-card__detail-content">
					<span class="archive-rule-card__detail-label">{{ t('files_archive', 'Destination') }}</span>
					<span class="archive-rule-card__detail-value archive-rule-card__destination">
						{{ t('files_archive', '.archive folder') }}
					</span>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import Delete from 'vue-material-design-icons/TrashCanOutline.vue'
import ClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import Calendar from 'vue-material-design-icons/Calendar.vue'
import Archive from 'vue-material-design-icons/Archive.vue'

import { showSuccess } from '@nextcloud/dialogs'
import { t, n } from '@nextcloud/l10n'

export default {
	name: 'ArchiveRule',

	components: {
		NcButton,
		Delete,
		ClockOutline,
		Calendar,
		Archive,
	},

	props: {
		id: {
			type: Number,
			required: true,
		},
		tagid: {
			type: Number,
			required: false,
			default: null,
		},
		timeunit: {
			type: Number,
			required: true,
		},
		timeamount: {
			type: Number,
			required: true,
		},
		timeafter: {
			type: Number,
			required: true,
		},
		hasJob: {
			type: Boolean,
			required: true,
		},
	},

	computed: {
		getAmountAndUnit() {
			switch (this.timeunit) {
			case 0:
				return n('files_archive', '%n day', '%n days', this.timeamount)
			case 1:
				return n('files_archive', '%n week', '%n weeks', this.timeamount)
			case 2:
				return n('files_archive', '%n month', '%n months', this.timeamount)
			case 3:
				return n('files_archive', '%n year', '%n years', this.timeamount)
			case 4:
				return n('files_archive', '%n minute', '%n minutes', this.timeamount)
			case 5:
				return n('files_archive', '%n hour', '%n hours', this.timeamount)
			default:
				return n('files_archive', '%n day', '%n days', this.timeamount)
			}
		},

		getAfter() {
			switch (this.timeafter) {
			case 0:
				return t('files_archive', 'Creation date')
			default:
				return t('files_archive', 'Last modification date')
			}
		},

		deleteLabel() {
			return t('files_archive', 'Delete archive rule')
		},
	},

	methods: {
		t,
		async onClickDelete() {
			await this.$store.dispatch('deleteArchiveRule', this.id)
			showSuccess(t('files_archive', 'Archive rule has been deleted'))
		},
	},
}
</script>

<style scoped lang="scss">
.archive-rule-card {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 16px;
	transition: box-shadow 0.2s ease;

	&:hover {
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
	}
}

.archive-rule-card__header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 16px;
	padding-bottom: 12px;
	border-bottom: 1px solid var(--color-border);
}

.archive-rule-card__title {
	display: flex;
	align-items: center;
	gap: 8px;
}

.archive-rule-card__title-icon {
	color: var(--color-primary-element);
}

.archive-rule-card__title-name {
	font-weight: 600;
	font-size: 1.05em;
	color: var(--color-main-text);
}

.archive-rule-card__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.archive-rule-card__detail {
	display: flex;
	align-items: flex-start;
	gap: 12px;
}

.archive-rule-card__detail-icon {
	flex-shrink: 0;
	margin-top: 2px;
	color: var(--color-text-maxcontrast);
}

.archive-rule-card__detail-content {
	display: flex;
	flex-direction: column;
	gap: 2px;
	flex: 1;
}

.archive-rule-card__detail-label {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.archive-rule-card__detail-value {
	font-size: 0.95em;
	color: var(--color-main-text);
	font-weight: 500;
}

.archive-rule-card__destination {
	color: var(--color-primary-element);
}
</style>
