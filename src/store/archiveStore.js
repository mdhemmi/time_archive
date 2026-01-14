/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import {
	createArchiveRule,
	deleteArchiveRule,
	getArchiveRules,
} from '../services/archiveService.js'

const state = () => ({
	archiveRules: {},
})

const getters = {
	getArchiveRules: state => () => Object.values(state.archiveRules),
}

const mutations = {
	/**
	 * Adds a rule to the store
	 *
	 * @param {object} state current store state
	 * @param {object} rule the rule
	 */
	addRule(state, rule) {
		state.archiveRules[rule.id] = rule
	},

	/**
	 * Deletes a rule from the store
	 *
	 * @param {object} state current store state
	 * @param {number} id the rule id of the rule to delete
	 */
	deleteRule(state, id) {
		delete state.archiveRules[id]
	},
}

const actions = {
	/**
	 * Load the archive rules from the backend
	 *
	 * @param {object} context default store context
	 */
	async loadArchiveRules(context) {
		try {
			const response = await getArchiveRules()
			console.log('[Files Archive] API response:', response)
			// Handle both OCS and direct response formats
			let rules = []
			if (response.data) {
				if (response.data.ocs && response.data.ocs.data) {
					rules = response.data.ocs.data
				} else if (Array.isArray(response.data)) {
					rules = response.data
				} else if (response.data.data && Array.isArray(response.data.data)) {
					rules = response.data.data
				}
			}
			console.log('[Files Archive] Parsed rules:', rules)
			rules.forEach((rule) => {
				context.commit('addRule', rule)
			})
		} catch (error) {
			console.error('[Files Archive] Failed to load archive rules:', error)
			console.error('[Files Archive] Error response:', error.response)
			throw error
		}
	},

	async deleteArchiveRule(context, ruleId) {
		await deleteArchiveRule(ruleId)
		context.commit('deleteRule', ruleId)
	},

	async createNewRule(context, rule) {
		const response = await createArchiveRule(rule)
		context.commit('addRule', response.data.ocs.data)
	},
}

export default { state, mutations, getters, actions }
