/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'

let registered = false
let attempts = 0
const MAX_ATTEMPTS = 100 // Try for up to 20 seconds (100 * 200ms)

/**
 * Register Archive navigation entry in Files app
 */
function initFilesNavigation() {
	// Don't register multiple times
	if (registered) {
		return
	}

	attempts++
	
	// Debug logging on first few attempts
	if (attempts <= 3) {
		console.log(`[Files Archive] Attempt ${attempts}: Checking for Files navigation API...`)
		console.log('[Files Archive] window.OCA:', typeof window.OCA)
		console.log('[Files Archive] window.OCA.Files:', typeof window.OCA?.Files)
		console.log('[Files Archive] window.OCA.Files.Navigation:', typeof window.OCA?.Files?.Navigation)
		if (window.OCA?.Files?.Navigation) {
			console.log('[Files Archive] Navigation object:', window.OCA.Files.Navigation)
			console.log('[Files Archive] Navigation methods:', Object.keys(window.OCA.Files.Navigation))
		}
	}

	// Wait for Files app navigation to be available
	let Navigation = null
	
	// Try multiple possible locations for the Navigation API
	if (typeof window.OCA?.Files?.Navigation !== 'undefined') {
		Navigation = window.OCA.Files.Navigation
	} else if (typeof window.OC?.Files?.Navigation !== 'undefined') {
		Navigation = window.OC.Files.Navigation
	} else if (typeof window.OCA?.Files?.Sidebar?.Navigation !== 'undefined') {
		Navigation = window.OCA.Files.Sidebar.Navigation
	} else if (typeof window.OCA?.Files?.App?.Navigation !== 'undefined') {
		Navigation = window.OCA.Files.App.Navigation
	}

	if (!Navigation) {
		// Files app not loaded yet, try again
		if (attempts < MAX_ATTEMPTS) {
			setTimeout(initFilesNavigation, 200)
		} else {
			console.warn('[Files Archive] Navigation API not found after', attempts, 'attempts')
			console.warn('[Files Archive] Available OCA.Files properties:', Object.keys(window.OCA?.Files || {}))
		}
		return
	}

	try {
		// Generate the URL to the .archive folder in Files app
		const archiveUrl = generateUrl('/apps/files/?dir=/.archive')
		
		// Try different navigation config formats
		const navConfigs = [
			// Format 1: Standard format
			{
				id: 'archive',
				appName: 'files',
				name: t('time_archive', 'Archive'),
				icon: 'icon-archive',
				order: 10,
				href: archiveUrl,
			},
			// Format 2: Alternative format
			{
				id: 'time_archive',
				name: t('time_archive', 'Archive'),
				icon: 'icon-archive',
				order: 10,
				href: archiveUrl,
			},
			// Format 3: Minimal format
			{
				id: 'archive',
				name: t('time_archive', 'Archive'),
				href: archiveUrl,
			},
		]

		console.log('[Files Archive] Navigation object found:', Navigation)
		console.log('[Files Archive] Navigation type:', typeof Navigation)
		console.log('[Files Archive] Navigation methods:', Object.keys(Navigation))
		console.log('[Files Archive] Is array?', Array.isArray(Navigation))

		// Try .add() method (older API)
		if (typeof Navigation.add === 'function') {
			for (const config of navConfigs) {
				try {
					Navigation.add(config)
					console.log('[Files Archive] ✓ Navigation entry registered via .add() with config:', config)
					registered = true
					return
				} catch (e) {
					console.log('[Files Archive] .add() failed with config:', config, 'Error:', e.message)
				}
			}
		}

		// Try .register() method (newer API)
		if (typeof Navigation.register === 'function') {
			for (const config of navConfigs) {
				try {
					Navigation.register(config)
					console.log('[Files Archive] ✓ Navigation entry registered via .register() with config:', config)
					registered = true
					return
				} catch (e) {
					console.log('[Files Archive] .register() failed with config:', config, 'Error:', e.message)
				}
			}
		}

		// Try .push() if it's an array
		if (Array.isArray(Navigation)) {
			for (const config of navConfigs) {
				try {
					Navigation.push(config)
					console.log('[Files Archive] ✓ Navigation entry added to array with config:', config)
					registered = true
					return
				} catch (e) {
					console.log('[Files Archive] .push() failed with config:', config, 'Error:', e.message)
				}
			}
		}

		// Try direct property assignment
		if (Navigation.entries && Array.isArray(Navigation.entries)) {
			for (const config of navConfigs) {
				try {
					Navigation.entries.push(config)
					console.log('[Files Archive] ✓ Navigation entry added to .entries array with config:', config)
					registered = true
					return
				} catch (e) {
					console.log('[Files Archive] .entries.push() failed with config:', config, 'Error:', e.message)
				}
			}
		}

		// Try using Files app's registerNavigation method if available
		if (typeof window.OCA?.Files?.registerNavigation === 'function') {
			for (const config of navConfigs) {
				try {
					window.OCA.Files.registerNavigation(config)
					console.log('[Files Archive] ✓ Navigation entry registered via OCA.Files.registerNavigation() with config:', config)
					registered = true
					return
				} catch (e) {
					console.log('[Files Archive] OCA.Files.registerNavigation() failed with config:', config, 'Error:', e.message)
				}
			}
		}

		console.warn('[Files Archive] Navigation API found but no supported registration method worked')
		console.warn('[Files Archive] Navigation object structure:', JSON.stringify(Navigation, null, 2))
	} catch (error) {
		console.error('[Files Archive] Failed to register navigation:', error)
		console.error('[Files Archive] Error stack:', error.stack)
	}
}

// Only run if we're in the Files app or on a page that might load it
function shouldRunNavigation() {
	// Check if we're on a Files app page
	const isFilesApp = window.location.pathname.includes('/apps/files') || 
	                   window.location.pathname.includes('/index.php/apps/files') ||
	                   document.querySelector('[data-app="files"]') !== null ||
	                   document.querySelector('#app-content-files') !== null
	
	// Also run on main pages where Files might be loaded
	const isMainPage = window.location.pathname === '/' || 
	                  window.location.pathname === '/index.php' ||
	                  window.location.pathname.includes('/index.php/apps/')
	
	return isFilesApp || isMainPage
}

// Initialize when DOM is ready
if (shouldRunNavigation()) {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => {
			// Wait a bit for Files app to initialize
			setTimeout(initFilesNavigation, 1000)
		})
	} else {
		// Start trying immediately
		setTimeout(initFilesNavigation, 1000)
	}

	// Also listen for Files app load event if available
	if (typeof window.addEventListener === 'function') {
		window.addEventListener('OCA.Files.App.loaded', initFilesNavigation)
		window.addEventListener('OCA.Files.loaded', initFilesNavigation)
		window.addEventListener('files:app:loaded', initFilesNavigation)
		
		// Listen for route changes in case Files app loads dynamically
		const originalPushState = history.pushState
		history.pushState = function(...args) {
			originalPushState.apply(history, args)
			if (shouldRunNavigation() && !registered) {
				setTimeout(initFilesNavigation, 500)
			}
		}
	}
} else {
	console.log('[Files Archive] Skipping navigation registration - not on Files app page')
}
