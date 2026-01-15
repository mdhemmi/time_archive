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
		// Try multiple locations to add the Archive link
		
		// Method 1: Add to Files app header/toolbar
		const filesHeader = document.querySelector('.files-controls') || 
		                   document.querySelector('.files-list__header') ||
		                   document.querySelector('.files-list-header') ||
		                   document.querySelector('.app-files-header') ||
		                   document.querySelector('[class*="files-header"]') ||
		                   document.querySelector('[class*="app-content"]')
		
		if (filesHeader) {
			// Check if link already exists
			if (filesHeader.querySelector('.files-archive-link')) {
				return
			}
			
			const archiveLink = document.createElement('a')
			archiveLink.className = 'files-archive-link'
			archiveLink.href = generateUrl('/apps/time_archive/')
			archiveLink.title = t('time_archive', 'View archived files')
			archiveLink.innerHTML = `
				<span class="icon-archive" style="margin-right: 4px;"></span>
				${t('time_archive', 'Archive')}
			`
			archiveLink.style.cssText = `
				display: inline-flex;
				align-items: center;
				padding: 8px 16px;
				margin: 8px;
				background-color: var(--color-primary-element, #0082c9);
				color: var(--color-primary-element-text, #ffffff);
				border-radius: var(--border-radius, 3px);
				text-decoration: none;
				font-weight: 500;
				transition: opacity 0.2s;
			`
			archiveLink.addEventListener('mouseenter', () => {
				archiveLink.style.opacity = '0.8'
			})
			archiveLink.addEventListener('mouseleave', () => {
				archiveLink.style.opacity = '1'
			})
			
			// Try to insert at the beginning or after existing controls
			const controls = filesHeader.querySelector('.files-controls') || 
			                filesHeader.querySelector('.files-list__header-actions') ||
			                filesHeader
			
			if (controls) {
				// Insert at the beginning if possible, otherwise append
				if (controls.firstChild) {
					controls.insertBefore(archiveLink, controls.firstChild)
				} else {
					controls.appendChild(archiveLink)
				}
				console.log('[Files Archive] Archive link added to Files app header')
				return
			}
		}
		
		// Method 2: Add to sidebar if available
		const sidebar = document.querySelector('.app-sidebar') ||
		               document.querySelector('.app-navigation') ||
		               document.querySelector('[class*="sidebar"]')
		
		if (sidebar && !sidebar.querySelector('.files-archive-link')) {
			const archiveLink = document.createElement('a')
			archiveLink.className = 'files-archive-link'
			archiveLink.href = generateUrl('/apps/time_archive/')
			archiveLink.title = t('time_archive', 'View archived files')
			archiveLink.innerHTML = `
				<span class="icon-archive" style="margin-right: 8px;"></span>
				${t('time_archive', 'Archive')}
			`
			archiveLink.style.cssText = `
				display: flex;
				align-items: center;
				padding: 12px 16px;
				color: var(--color-main-text, #000000);
				text-decoration: none;
				border-bottom: 1px solid var(--color-border, #e5e5e5);
				font-weight: 500;
			`
			archiveLink.addEventListener('mouseenter', () => {
				archiveLink.style.backgroundColor = 'var(--color-background-hover, #f5f5f5)'
			})
			archiveLink.addEventListener('mouseleave', () => {
				archiveLink.style.backgroundColor = 'transparent'
			})
			
			sidebar.insertBefore(archiveLink, sidebar.firstChild)
			console.log('[Files Archive] Archive link added to sidebar')
			return
		}
		
		// Method 3: Add as a floating button (last resort)
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
		if (!document.querySelector('.files-archive-link') && 
		    !document.querySelector('.files-archive-floating-link')) {
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
