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
			// Handle both OCS and direct response formats
			const rules = response.data?.ocs?.data || response.data || []
			rules.forEach((rule) => {
				context.commit('addRule', rule)
			})
		} catch (error) {
			console.error('Failed to load archive rules:', error)
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
