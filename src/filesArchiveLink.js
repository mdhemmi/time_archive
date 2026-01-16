/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'

/**
 * Add a visible Archive link to the Files app
 * This creates a prominent button/link that users can easily find
 */
function addArchiveLink() {
	// Only run in Files app
	if (!window.location.pathname.startsWith('/apps/files')) {
		return
	}

	// Wait for Files app to load
	const init = () => {
		// Only add the floating button (bottom-right corner)
		// Skip header and sidebar methods to avoid duplicate buttons
		if (!document.querySelector('.files-archive-floating-link')) {
			const floatingLink = document.createElement('a')
			floatingLink.className = 'files-archive-floating-link'
			floatingLink.href = generateUrl('/apps/time_archive/')
			floatingLink.title = t('time_archive', 'View archived files')
			floatingLink.innerHTML = `
				<span class="icon-archive"></span>
				<span class="files-archive-floating-text">${t('time_archive', 'Archive')}</span>
			`
			floatingLink.style.cssText = `
				position: fixed;
				bottom: 20px;
				right: 20px;
				z-index: 9999;
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 8px;
				padding: 12px 20px;
				background-color: var(--color-primary-element, #0082c9);
				color: var(--color-primary-element-text, #ffffff);
				border-radius: 50px;
				text-decoration: none;
				font-weight: 500;
				box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
				transition: transform 0.2s, box-shadow 0.2s;
			`
			floatingLink.addEventListener('mouseenter', () => {
				floatingLink.style.transform = 'scale(1.05)'
				floatingLink.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.3)'
			})
			floatingLink.addEventListener('mouseleave', () => {
				floatingLink.style.transform = 'scale(1)'
				floatingLink.style.boxShadow = '0 2px 8px rgba(0, 0, 0, 0.2)'
			})
			
			// Hide text on small screens
			const textSpan = floatingLink.querySelector('.files-archive-floating-text')
			if (textSpan) {
				const mediaQuery = window.matchMedia('(max-width: 768px)')
				const handleResize = (e) => {
					textSpan.style.display = e.matches ? 'none' : 'inline'
				}
				mediaQuery.addEventListener('change', handleResize)
				handleResize(mediaQuery)
			}
			
			document.body.appendChild(floatingLink)
			console.log('[Files Archive] Archive link added as floating button')
		}
	}

	// Try immediately
	init()

	// Also try after DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init)
	}

	// Try again after a delay (Files app might load dynamically)
	setTimeout(init, 1000)
	setTimeout(init, 3000)

	// Listen for Files app navigation changes
	const observer = new MutationObserver(() => {
		if (!document.querySelector('.files-archive-floating-link')) {
			init()
		}
	})

	observer.observe(document.body, {
		childList: true,
		subtree: true,
	})
}

// Initialize
addArchiveLink()
