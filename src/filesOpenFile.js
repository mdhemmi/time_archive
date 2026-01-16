/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Auto-open file after navigating to Files app from Archive view
 * This script runs in the Files app and checks if we need to open a file
 */
console.log('[Time Archive] filesOpenFile.js loaded')

function autoOpenFile() {
	console.log('[Time Archive] autoOpenFile() called')
	
	// Prevent infinite loops - check if we've already tried to open this file
	const urlParams = new URLSearchParams(window.location.search)
	const openFileId = urlParams.get('openfile') || urlParams.get('fileid')
	const alreadyTriedKey = 'time_archive_already_tried_' + (openFileId || 'unknown')
	
	if (sessionStorage.getItem(alreadyTriedKey)) {
		console.log('[Time Archive] Already attempted to open this file, skipping to prevent loop')
		sessionStorage.removeItem(alreadyTriedKey) // Clean up
		return
	}
	
	// Check if we have a file to open from Archive view (sessionStorage or URL)
	let fileInfo = null
	const fileInfoStr = sessionStorage.getItem('time_archive_open_file')
	
	if (fileInfoStr) {
		// Remove the flag so it doesn't trigger again
		sessionStorage.removeItem('time_archive_open_file')
		fileInfo = JSON.parse(fileInfoStr)
		console.log('[Time Archive] File info from sessionStorage:', fileInfo)
		// Mark that we're trying this file
		if (fileInfo.id) {
			sessionStorage.setItem('time_archive_already_tried_' + fileInfo.id, 'true')
		}
	} else if (openFileId) {
		// Use file ID from URL parameter
		fileInfo = { id: parseInt(openFileId) }
		console.log('[Time Archive] File ID from URL parameter:', fileInfo.id)
		// Mark that we're trying this file
		sessionStorage.setItem(alreadyTriedKey, 'true')
	} else {
		console.log('[Time Archive] No file to open - no fileid in URL or sessionStorage')
		return
	}
	
	console.log('[Time Archive] Attempting to open file:', fileInfo)
	
	// First, check if Nextcloud's file preview is already open
	// If fileid is in URL, Nextcloud might handle it automatically
	// But we'll still try to help it along
	
	// Try to find file in DOM - check all table rows, not just filtered ones
	const tryFindInDOM = () => {
		if (!fileInfo.id) return null
		
		// Get ALL table rows (Files app uses various structures)
		const allTableRows = document.querySelectorAll('tbody tr, table tr, .files-list tr')
		console.log('[Time Archive] Found', allTableRows.length, 'total table rows in DOM')
		
		// Filter out headers and non-file rows - be more aggressive
		const fileRows = Array.from(allTableRows).filter(row => {
			// Skip header rows (check class, role, or if it's in thead)
			if (row.classList.contains('files-list_row-head') || 
			    row.classList.contains('header') || 
			    row.classList.contains('notification') ||
			    row.getAttribute('role') === 'columnheader' ||
			    row.closest('thead') !== null) {
				return false
			}
			// Skip if it's clearly not a file row (empty, loading, etc.)
			if (row.classList.contains('empty') || 
			    row.classList.contains('loading') ||
			    row.textContent.trim() === '') {
				return false
			}
			// Include all other rows (they might be file rows)
			return true
		})
		
		console.log('[Time Archive] Found', fileRows.length, 'potential file rows (after filtering headers)')
		
		// Log structure of first few ACTUAL file rows (not headers)
		if (fileRows.length > 0) {
			// Find the first row that's NOT a header
			const firstFileRow = fileRows.find(row => !row.classList.contains('files-list_row-head'))
			if (firstFileRow) {
				console.log('[Time Archive] Sample file row:', firstFileRow)
				console.log('[Time Archive] Sample file row classes:', Array.from(firstFileRow.classList))
				console.log('[Time Archive] Sample file row attributes:', Array.from(firstFileRow.attributes).map(a => `${a.name}="${a.value}"`))
				if (firstFileRow.dataset) {
					console.log('[Time Archive] Sample file row dataset:', Object.keys(firstFileRow.dataset).map(k => `${k}=${firstFileRow.dataset[k]}`))
				}
				// Check if there are any child elements with file info
				const fileLink = firstFileRow.querySelector('a[href*="fileid"], a[data-file], .file-name, [data-fileid]')
				if (fileLink) {
					console.log('[Time Archive] Found file link in row:', fileLink)
					console.log('[Time Archive] File link attributes:', Array.from(fileLink.attributes).map(a => `${a.name}="${a.value}"`))
				}
			}
		}
		
		// Try multiple selectors to find the file element
		const selectors = [
			`[data-file-id="${fileInfo.id}"]`,
			`[data-fileid="${fileInfo.id}"]`,
			`[data-file="${fileInfo.id}"]`,
			`[data-id="${fileInfo.id}"]`,
			`tr[data-file-id="${fileInfo.id}"]`,
			`tr[data-fileid="${fileInfo.id}"]`,
			`tr[data-file="${fileInfo.id}"]`,
			`tr[data-id="${fileInfo.id}"]`,
		]
		
		for (const selector of selectors) {
			try {
				const element = document.querySelector(selector)
				if (element && !element.classList.contains('notification')) {
					console.log('[Time Archive] Found file element with selector:', selector, element)
					return element
				}
			} catch (e) {
				// Invalid selector, skip
			}
		}
		
		// Try finding by file ID in all rows (check row itself and child elements)
		for (const row of fileRows) {
			// Skip headers
			if (row.classList.contains('files-list_row-head') || 
			    row.classList.contains('header') ||
			    row.closest('thead') !== null) continue
			
			// Check row's own attributes
			const rowId = row.getAttribute('data-fileid') || 
			             row.getAttribute('data-file-id') || 
			             row.getAttribute('data-file') ||
			             row.getAttribute('data-id') ||
			             row.getAttribute('id') ||
			             (row.dataset && (row.dataset.fileid || row.dataset.fileId || row.dataset.file || row.dataset.id))
			
			// Try to match the ID (as string or number)
			if (rowId) {
				const rowIdNum = parseInt(rowId)
				if (rowIdNum === fileInfo.id || String(rowId) === String(fileInfo.id)) {
					console.log('[Time Archive] Found file element by row ID attribute. Row ID:', rowId, 'Target ID:', fileInfo.id, 'Row:', row)
					return row
				}
			}
			
			// Check child elements for file ID (links, buttons, etc.)
			const childElements = row.querySelectorAll('a, button, [data-fileid], [data-file-id], [data-file], [data-id]')
			for (const child of childElements) {
				const childId = child.getAttribute('data-fileid') || 
				               child.getAttribute('data-file-id') || 
				               child.getAttribute('data-file') ||
				               child.getAttribute('data-id') ||
				               child.getAttribute('href')?.match(/fileid=(\d+)/)?.[1] ||
				               (child.dataset && (child.dataset.fileid || child.dataset.fileId || child.dataset.file || child.dataset.id))
				
				if (childId) {
					const childIdNum = parseInt(childId)
					if (childIdNum === fileInfo.id || String(childId) === String(fileInfo.id)) {
						console.log('[Time Archive] Found file element by child element ID. Child ID:', childId, 'Target ID:', fileInfo.id, 'Child:', child, 'Row:', row)
						// Return the row (not the child) so we can click the whole row
						return row
					}
				}
			}
			
			// Also check if the file ID appears anywhere in the row text (last resort)
			const rowText = row.textContent || row.innerText || ''
			if (rowText.includes(String(fileInfo.id))) {
				console.log('[Time Archive] Found file element by ID in text content. Row:', row)
				return row
			}
		}
		
		// Also try finding by file name if available
		if (fileInfo.name) {
			console.log('[Time Archive] Searching for file by name:', fileInfo.name)
			for (const row of fileRows) {
				// Skip headers
				if (row.classList.contains('files-list_row-head') || row.classList.contains('header')) continue
				
				const text = row.textContent || row.innerText || ''
				// Check if the file name appears in the row text
				if (text && text.trim().includes(fileInfo.name)) {
					console.log('[Time Archive] Found file element by name in text content. Row:', row)
					return row
				}
			}
		}
		
		console.log('[Time Archive] File element not found in DOM after checking', fileRows.length, 'file rows')
		console.log('[Time Archive] File ID:', fileInfo.id, 'File name:', fileInfo.name)
		return null
	}
	
	// Check if file preview is already open (Nextcloud might handle fileid automatically)
	const checkPreviewOpen = () => {
		// Check if there's a file preview/viewer open
		const preview = document.querySelector('.viewer-container, .file-viewer, .preview-container, #viewer')
		if (preview) {
			console.log('[Time Archive] File preview appears to be open:', preview)
			return true
		}
		return false
	}
	
	// Wait for Files app to be fully loaded
	const tryOpen = (attempts = 0) => {
		// Check if preview is already open (Nextcloud might have handled it)
		if (checkPreviewOpen()) {
			console.log('[Time Archive] File preview is already open - Nextcloud handled fileid parameter')
			return
		}
		
		if (attempts > 50) {
			console.warn('[Time Archive] Could not open file after', attempts, 'attempts')
			console.warn('[Time Archive] File ID:', fileInfo.id, 'Name:', fileInfo.name)
			console.warn('[Time Archive] Please manually open the file from the Files app')
			// Don't navigate anywhere - that would cause an infinite loop
			// Just log the error and let the user manually open the file
			// Clean up the flag
			if (fileInfo.id) {
				sessionStorage.removeItem('time_archive_already_tried_' + fileInfo.id)
			}
			return
		}
		
		// First, try to find file in DOM (simpler and more reliable)
		// This should work even if the Files app API isn't available
		const fileElement = tryFindInDOM()
		if (fileElement) {
			console.log('[Time Archive] Found file element, attempting to open via DOM')
			try {
				// Scroll element into view first
				fileElement.scrollIntoView({ behavior: 'smooth', block: 'center' })
				
				// Wait a bit for scroll, then try to open
				setTimeout(() => {
					try {
						// Try double-click first (most reliable for opening files)
						const dblClickEvent = new MouseEvent('dblclick', {
							bubbles: true,
							cancelable: true,
							view: window,
							detail: 2
						})
						fileElement.dispatchEvent(dblClickEvent)
						console.log('[Time Archive] Dispatched double-click event on file element')
					} catch (e) {
						console.warn('[Time Archive] Failed to dispatch double-click:', e)
						try {
							// Try single click
							const clickEvent = new MouseEvent('click', {
								bubbles: true,
								cancelable: true,
								view: window
							})
							fileElement.dispatchEvent(clickEvent)
							console.log('[Time Archive] Dispatched click event on file element')
						} catch (e2) {
							console.warn('[Time Archive] Failed to dispatch click:', e2)
							try {
								// Last resort: direct click method
								fileElement.click()
								console.log('[Time Archive] Called click() on file element')
							} catch (e3) {
								console.warn('[Time Archive] All DOM click methods failed:', e3)
							}
						}
					}
				}, 300)
				return
			} catch (e) {
				console.warn('[Time Archive] Error opening file via DOM:', e)
			}
		}
		
		// Check multiple possible locations for Files app API
		let fileList = null
		if (window.OCA && window.OCA.Files && window.OCA.Files.App && window.OCA.Files.App.fileList) {
			fileList = window.OCA.Files.App.fileList
		} else if (window.OCA && window.OCA.Files && window.OCA.Files.fileList) {
			fileList = window.OCA.Files.fileList
		} else if (window.OC && window.OC.Files && window.OC.Files.fileList) {
			fileList = window.OC.Files.fileList
		} else if (window.Files && window.Files.fileList) {
			fileList = window.Files.fileList
		}
		
		if (fileList && fileList.files) {
			
			// Wait a bit more for file list to be populated
			setTimeout(() => {
				console.log('[Time Archive] File list found! Searching for file ID:', fileInfo.id)
				console.log('[Time Archive] File list contains', fileList.files ? fileList.files.length : 0, 'files')
				
				if (!fileList.files || fileList.files.length === 0) {
					console.warn('[Time Archive] File list is empty, waiting for files to load...')
					// Try again after a longer delay
					setTimeout(() => tryOpen(attempts + 1), 500)
					return
				}
				
				// Try to find the file by ID first, then by name
				let fileModel = null
				if (fileInfo.id) {
					fileModel = fileList.files.find(f => {
						const match = f.id === fileInfo.id || String(f.id) === String(fileInfo.id)
						if (match) {
							console.log('[Time Archive] Found file by ID:', f.name, 'ID:', f.id)
						}
						return match
					})
				}
				if (!fileModel && fileInfo.name) {
					fileModel = fileList.files.find(f => {
						const match = f.name === fileInfo.name
						if (match) {
							console.log('[Time Archive] Found file by name:', f.name)
						}
						return match
					})
				}
				if (!fileModel && fileInfo.path) {
					fileModel = fileList.files.find(f => f.path === fileInfo.path)
				}
				
				if (fileModel) {
					console.log('[Time Archive] File model found:', fileModel)
					// Try multiple methods to open the file
					let opened = false
					
					// Method 1: Use Files app's default file opening mechanism
					if (window.OCA && window.OCA.Files && window.OCA.Files.FileActions) {
						try {
							const fileActions = window.OCA.Files.FileActions
							// Try to get the default action
							const defaultAction = fileActions.getDefaultFileAction(fileModel.mimetype || 'application/octet-stream')
							if (defaultAction) {
								fileActions.triggerAction(defaultAction.name, fileModel)
								opened = true
								console.log('[Time Archive] Opened file via default FileAction:', defaultAction.name)
							} else {
								// Fallback to 'Open' action
								fileActions.triggerAction('Open', fileModel)
								opened = true
								console.log('[Time Archive] Opened file via FileActions Open')
							}
						} catch (e) {
							console.warn('[Time Archive] FileActions failed:', e)
						}
					}
					
					// Method 2: Use openFile method with file name
					if (!opened && typeof fileList.openFile === 'function') {
						try {
							fileList.openFile(fileModel.name)
							opened = true
							console.log('[Time Archive] Opened file via openFile():', fileModel.name)
						} catch (e) {
							console.warn('[Time Archive] openFile() failed:', e)
						}
					}
					
					// Method 3: Use open method
					if (!opened && typeof fileList.open === 'function') {
						try {
							fileList.open(fileModel.name)
							opened = true
							console.log('[Time Archive] Opened file via open():', fileModel.name)
						} catch (e) {
							console.warn('[Time Archive] open() failed:', e)
						}
					}
					
					// Method 4: Trigger double-click on file element (simulates user action)
					if (!opened && fileModel.$el) {
						try {
							// Try double-click which is more likely to open
							const event = new MouseEvent('dblclick', {
								bubbles: true,
								cancelable: true,
								view: window
							})
							fileModel.$el.dispatchEvent(event)
							opened = true
							console.log('[Time Archive] Opened file via double-click event')
						} catch (e) {
							console.warn('[Time Archive] double-click failed:', e)
							// Fallback to single click
							try {
								fileModel.$el.click()
								opened = true
								console.log('[Time Archive] Opened file via click()')
							} catch (e2) {
								console.warn('[Time Archive] click() failed:', e2)
							}
						}
					}
					
					if (!opened) {
						console.warn('[Time Archive] Could not open file - no working method found')
						console.warn('[Time Archive] File model:', fileModel)
						console.warn('[Time Archive] Available methods:', {
							hasFileActions: !!(window.OCA && window.OCA.Files && window.OCA.Files.FileActions),
							hasOpenFile: typeof fileList.openFile === 'function',
							hasOpen: typeof fileList.open === 'function',
							hasElement: !!fileModel.$el
						})
						
						// Last resort: log error and stop (don't navigate to avoid loop)
						if (fileInfo.id) {
							console.warn('[Time Archive] Could not open file - all methods failed')
							console.warn('[Time Archive] File ID:', fileInfo.id, 'Name:', fileInfo.name)
							console.warn('[Time Archive] Please manually open the file from the Files app')
							// Clean up the flag
							sessionStorage.removeItem('time_archive_already_tried_' + fileInfo.id)
						}
					}
				} else {
					console.warn('[Time Archive] File not found in file list. ID:', fileInfo.id, 'Name:', fileInfo.name)
					console.log('[Time Archive] Available files:', fileList.files.slice(0, 10).map(f => ({ id: f.id, name: f.name, mimetype: f.mimetype })))
				}
			}, 1500)
		} else {
			// Files app API not available, but we can still try DOM approach
			// Continue trying to find file in DOM
			if (attempts % 10 === 0) {
				console.log('[Time Archive] Waiting for file to appear in DOM... attempt', attempts)
				console.log('[Time Archive] Current URL:', window.location.href)
				console.log('[Time Archive] File table rows found:', document.querySelectorAll('tbody tr, .files-fileList tr').length)
				
				// Check if we're in the right directory
				if (fileInfo.dir) {
					const currentDir = new URLSearchParams(window.location.search).get('dir')
					if (currentDir !== fileInfo.dir) {
						console.log('[Time Archive] Not in correct directory. Current:', currentDir, 'Expected:', fileInfo.dir)
						// Navigate to correct directory
						const correctUrl = OC.generateUrl('/apps/files/?dir=' + encodeURIComponent(fileInfo.dir) + '&fileid=' + fileInfo.id)
						window.location.href = correctUrl
						return
					}
				}
			}
			setTimeout(() => tryOpen(attempts + 1), 200)
		}
	}
	
	// Start trying after a short delay to let Files app initialize
	setTimeout(() => tryOpen(), 500)
}

// Run when DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', autoOpenFile)
} else {
	autoOpenFile()
}

// Also listen for Files app load events
if (typeof window.addEventListener === 'function') {
	window.addEventListener('OCA.Files.App.loaded', autoOpenFile)
	window.addEventListener('OCA.Files.loaded', autoOpenFile)
	window.addEventListener('files:app:loaded', autoOpenFile)
}
