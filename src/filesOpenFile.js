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
	// Check URL parameters first
	const urlParams = new URLSearchParams(window.location.search)
	const openFileId = urlParams.get('openfile') || urlParams.get('fileid')
	
	// Check if we have a file to open from Archive view (sessionStorage or URL)
	let fileInfo = null
	const fileInfoStr = sessionStorage.getItem('time_archive_open_file')
	
	if (fileInfoStr) {
		// Remove the flag so it doesn't trigger again
		sessionStorage.removeItem('time_archive_open_file')
		fileInfo = JSON.parse(fileInfoStr)
	} else if (openFileId) {
		// Use file ID from URL parameter
		fileInfo = { id: parseInt(openFileId) }
	} else {
		return
	}
	
	console.log('[Time Archive] Attempting to open file:', fileInfo)
	
	// Try to find file in DOM (simpler and more reliable approach)
	const tryFindInDOM = () => {
		if (!fileInfo.id) return null
		
		console.log('[Time Archive] Searching DOM for file ID:', fileInfo.id)
		
		// Try multiple selectors to find the file element
		const selectors = [
			`[data-file-id="${fileInfo.id}"]`,
			`[data-id="${fileInfo.id}"]`,
			`[data-fileid="${fileInfo.id}"]`,
			`[data-file="${fileInfo.id}"]`,
			`tr[data-id="${fileInfo.id}"]`,
			`tr[data-fileid="${fileInfo.id}"]`,
			`.file[data-id="${fileInfo.id}"]`,
			`.file[data-fileid="${fileInfo.id}"]`,
		]
		
		for (const selector of selectors) {
			const element = document.querySelector(selector)
			if (element) {
				console.log('[Time Archive] Found file element with selector:', selector, element)
				return element
			}
		}
		
		// Also try finding by file name if available
		if (fileInfo.name) {
			console.log('[Time Archive] Searching DOM for file name:', fileInfo.name)
			const nameSelectors = [
				`[data-file-name="${fileInfo.name}"]`,
				`[data-name="${fileInfo.name}"]`,
				`tr[data-file="${fileInfo.name}"]`,
			]
			
			for (const selector of nameSelectors) {
				const element = document.querySelector(selector)
				if (element) {
					console.log('[Time Archive] Found file element by name with selector:', selector, element)
					return element
				}
			}
			
			// Try finding by text content (file name in table row)
			const allRows = document.querySelectorAll('tbody tr, .files-fileList tr, table tr')
			for (const row of allRows) {
				const text = row.textContent || row.innerText
				if (text && text.includes(fileInfo.name)) {
					console.log('[Time Archive] Found file element by text content:', row)
					return row
				}
			}
		}
		
		console.log('[Time Archive] File element not found in DOM')
		return null
	}
	
	// Wait for Files app to be fully loaded
	const tryOpen = (attempts = 0) => {
		if (attempts > 300) {
			console.warn('[Time Archive] Files app did not load in time to open file after', attempts, 'attempts')
			// Last resort: try direct download
			if (fileInfo.id) {
				console.log('[Time Archive] Attempting direct file download as last resort')
				const downloadUrl = OC.generateUrl('/apps/files/ajax/download.php?files=' + fileInfo.id)
				window.open(downloadUrl, '_blank')
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
						
						// Last resort: try to open file via direct download URL
						if (fileInfo.id) {
							console.log('[Time Archive] Attempting direct file download as fallback')
							const downloadUrl = OC.generateUrl('/apps/files/ajax/download.php?files=' + fileInfo.id)
							window.open(downloadUrl, '_blank')
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
			if (attempts % 20 === 0) {
				console.log('[Time Archive] Waiting for file to appear in DOM... attempt', attempts)
				console.log('[Time Archive] DOM ready state:', document.readyState)
				console.log('[Time Archive] File table rows found:', document.querySelectorAll('tbody tr, .files-fileList tr').length)
			}
			setTimeout(() => tryOpen(attempts + 1), 100)
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
