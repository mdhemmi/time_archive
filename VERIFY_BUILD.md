# Verify Build and Clear Cache

If the UI still shows the tag section after rebuilding, follow these steps:

## Step 1: Verify Build Output

Check if the `js/` directory exists and has recent files:

```bash
cd /opt/stacks/nextcloud/apps/files_archive
ls -lah js/
```

You should see files like:
- `files_archive-main.js`
- `files_archive-vendors-*.js`

Check the modification time - they should be recent (after your build).

## Step 2: Force Rebuild

Delete old build files and rebuild:

```bash
cd /opt/stacks/nextcloud/apps/files_archive

# Remove old build files
rm -rf js/

# Rebuild
npm run build

# Verify files were created
ls -lah js/
```

## Step 3: Clear Nextcloud Cache Completely

```bash
# Enter maintenance mode
docker exec -u www-data nextcloud_nextcloud_app php /var/www/html/occ maintenance:mode --on

# Clear all caches
docker exec -u www-data nextcloud_nextcloud_app php /var/www/html/occ files:scan --all
docker exec -u www-data nextcloud_nextcloud_app php /var/www/html/occ maintenance:repair

# Exit maintenance mode
docker exec -u www-data nextcloud_nextcloud_app php /var/www/html/occ maintenance:mode --off
```

## Step 4: Clear Browser Cache Completely

1. Open Developer Tools (F12)
2. Right-click the refresh button
3. Select "Empty Cache and Hard Reload"
4. Or use Ctrl+Shift+Delete to clear all browser data

## Step 5: Verify Source Code

Check that the source file doesn't have tag references:

```bash
cd /opt/stacks/nextcloud/apps/files_archive
grep -i "NcSelectTags\|Files tagged with" src/AdminSettings.vue
```

This should return **nothing**. If it returns results, the source file wasn't updated correctly.

## Step 6: Check for Multiple Versions

Make sure you're editing the correct file:

```bash
cd /opt/stacks/nextcloud/apps/files_archive
find . -name "AdminSettings.vue" -type f
```

There should only be one file: `src/AdminSettings.vue`

## Step 7: Verify JavaScript Contains No Tag References

Check the built JavaScript file:

```bash
cd /opt/stacks/nextcloud/apps/files_archive
grep -i "tag\|NcSelectTags" js/files_archive-main.js | head -5
```

If you see tag-related code, the build didn't pick up the changes.

## Step 8: Check File Permissions

Ensure web server can read the files:

```bash
cd /opt/stacks/nextcloud/apps/files_archive
sudo chown -R www-data:www-data js/
sudo chmod -R 755 js/
```

## Step 9: Check Nextcloud Logs

Look for JavaScript errors:

```bash
tail -f /opt/stacks/nextcloud/data/nextcloud.log | grep -i "files_archive\|error\|javascript"
```

## Step 10: Manual Verification

1. Open the page in an incognito/private window
2. Check the browser console (F12 → Console tab) for errors
3. Check the Network tab to see which JavaScript files are being loaded
4. Verify the loaded file has a recent timestamp

## If Still Not Working

If after all these steps the tag section still appears:

1. **Check the actual file on the server** - maybe it wasn't copied correctly
2. **Verify the build actually ran** - check npm build output for errors
3. **Check if there's a CDN or proxy** caching the files
4. **Try accessing from a different browser** to rule out browser-specific caching

## Quick Test

To verify the source is correct, temporarily add a unique string:

```bash
# Add a test comment to AdminSettings.vue
echo "<!-- TEST_NO_TAGS -->" >> src/AdminSettings.vue

# Rebuild
npm run build

# Check if it's in the built file
grep "TEST_NO_TAGS" js/files_archive-main.js
```

If the test string appears in the built file, the build is working. If the UI still shows tags, it's a caching issue.
