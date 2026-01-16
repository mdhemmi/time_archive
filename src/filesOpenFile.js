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
	
	// Try to find file in DOM first (simpler approach)
	const tryFindInDOM = () => {
		if (!fileInfo.id) return null
		
		// Look for file element with matching data-file-id or id attribute
		const fileElement = document.querySelector(`[data-file-id="${fileInfo.id}"]`) ||
		                   document.querySelector(`[data-id="${fileInfo.id}"]`) ||
		                   document.querySelector(`[data-fileid="${fileInfo.id}"]`)
		
		if (fileElement) {
			console.log('[Time Archive] Found file element in DOM:', fileElement)
			return fileElement
		}
		
		// Also try finding by file name if available
		if (fileInfo.name) {
			const nameElement = Array.from(document.querySelectorAll('[data-file-name], [data-name]')).find(el => {
				const name = el.getAttribute('data-file-name') || el.getAttribute('data-name')
				return name === fileInfo.name
			})
			if (nameElement) {
				console.log('[Time Archive] Found file element by name:', nameElement)
				return nameElement
			}
		}
		
		return null
	}
	
	// Wait for Files app to be fully loaded
	const tryOpen = (attempts = 0) => {
		if (attempts > 200) {
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
		const fileElement = tryFindInDOM()
		if (fileElement) {
			console.log('[Time Archive] Attempting to open file via DOM element')
			try {
				// Try double-click first
				const dblClickEvent = new MouseEvent('dblclick', {
					bubbles: true,
					cancelable: true,
					view: window
				})
				fileElement.dispatchEvent(dblClickEvent)
				console.log('[Time Archive] Dispatched double-click event on file element')
				return
			} catch (e) {
				console.warn('[Time Archive] Failed to dispatch double-click:', e)
				try {
					fileElement.click()
					console.log('[Time Archive] Clicked file element')
					return
				} catch (e2) {
					console.warn('[Time Archive] Failed to click file element:', e2)
				}
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
			// Files app not loaded yet, try again
			if (attempts % 10 === 0) {
				console.log('[Time Archive] Waiting for Files app to load... attempt', attempts)
				console.log('[Time Archive] window.OCA:', !!window.OCA)
				console.log('[Time Archive] window.OCA.Files:', !!(window.OCA && window.OCA.Files))
				console.log('[Time Archive] window.OCA.Files.App:', !!(window.OCA && window.OCA.Files && window.OCA.Files.App))
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
