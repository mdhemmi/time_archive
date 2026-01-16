/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Auto-open file after navigating to Files app from Archive view
 * This script runs in the Files app and checks if we need to open a file
 */
function autoOpenFile() {
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
	
	// Wait for Files app to be fully loaded
	const tryOpen = (attempts = 0) => {
		if (attempts > 50) {
			console.warn('[Time Archive] Files app did not load in time to open file')
			return
		}
		
		// Check if Files app is loaded
		if (window.OCA && window.OCA.Files && window.OCA.Files.App && window.OCA.Files.App.fileList) {
			const fileList = window.OCA.Files.App.fileList
			
			// Wait a bit more for file list to be populated
			setTimeout(() => {
				console.log('[Time Archive] File list loaded, searching for file ID:', fileInfo.id)
				console.log('[Time Archive] File list contains', fileList.files.length, 'files')
				
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
					}
				} else {
					console.warn('[Time Archive] File not found in file list. ID:', fileInfo.id, 'Name:', fileInfo.name)
					console.log('[Time Archive] Available files:', fileList.files.slice(0, 10).map(f => ({ id: f.id, name: f.name, mimetype: f.mimetype })))
				}
			}, 1500)
		} else {
			// Try again after a short delay
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
