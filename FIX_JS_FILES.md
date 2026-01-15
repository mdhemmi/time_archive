# Fix: Missing JavaScript Files

## Problem
The error `Could not find resource time_archive/js/time_archive-main.js to load` indicates that the JavaScript files haven't been built or are in the wrong location.

## Solution

Run these commands to rebuild the frontend:

```bash
cd /opt/stacks/nextcloud/apps/time_archive

# 1. Remove old build files (if any)
rm -rf js/

# 2. Rebuild frontend
npm run build

# 3. Verify files were created
ls -la js/

# You should see files like:
# - time_archive-main.js
# - time_archive-main.js.map
# - time_archive-navigation.js
# - time_archive-archive.js
# etc.

# 4. Set correct permissions (if needed)
chown -R www-data:www-data js/
chmod -R 755 js/

# 5. Clear Nextcloud cache
docker exec -u www-data nextcloud_nextcloud_app php /var/www/html/occ maintenance:mode --on
docker exec -u www-data nextcloud_nextcloud_app php /var/www/html/occ maintenance:mode --off
```

## Alternative: Use the fix_ui.sh script

```bash
cd /opt/stacks/nextcloud/apps/time_archive
./fix_ui.sh
```

This script will:
1. Check source files
2. Remove old build files
3. Rebuild frontend
4. Verify build output
5. Set permissions
6. Clear Nextcloud cache

## After Fixing

After running the build:
1. **Refresh your browser** (hard refresh: Ctrl+Shift+R or Cmd+Shift+R)
2. **Check the admin settings page** - Archive rules should now load
3. **Check the top navigation** - Archive icon should appear (if navigation registration works)
4. **Check browser console** - No more "Could not find resource" errors
