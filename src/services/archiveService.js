/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * @param {object} rule The archive rule to add
 * @return {object} The axios response
 */
const createArchiveRule = async function(rule) {
	return axios.post(generateOcsUrl('/apps/time_archive/api/v1/rules'), rule)
}

/**
 * @param {number} ruleId The archive rule to delete
 * @return {object} The axios response
 */
const deleteArchiveRule = async function(ruleId) {
	return axios.delete(generateOcsUrl('/apps/time_archive/api/v1/rules/{ruleId}', { ruleId }))
}

/**
 * @return {object} The axios response
 */
const getArchiveRules = async function() {
	return axios.get(generateOcsUrl('/apps/time_archive/api/v1/rules'))
}

/**
 * Manually trigger archive job for all active rules
 * @return {object} The axios response
 */
const runArchiveJob = async function() {
	return axios.post(generateOcsUrl('/apps/time_archive/api/v1/run'))
}

/**
 * Get list of archived files for the current user
 * @return {object} The axios response
 */
const getArchivedFiles = async function() {
	return axios.get(generateOcsUrl('/apps/time_archive/api/v1/files'))
}

export {
	createArchiveRule,
	deleteArchiveRule,
	getArchiveRules,
	runArchiveJob,
	getArchivedFiles,
}
