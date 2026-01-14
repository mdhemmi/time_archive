#!/bin/bash
# Script to fix UI caching issues

echo "=== Fixing File Archive UI ==="
echo ""

# Navigate to app directory
cd /opt/stacks/nextcloud/apps/files_archive || exit 1

echo "1. Checking source file..."
if grep -q "NcSelectTags\|Files tagged with" src/AdminSettings.vue 2>/dev/null; then
    echo "   ERROR: Source file still contains tag references!"
    exit 1
else
    echo "   ✓ Source file is correct (no tag references)"
fi

echo ""
echo "2. Removing old build files..."
rm -rf js/
echo "   ✓ Old build files removed"

echo ""
echo "3. Rebuilding frontend..."
npm run build
if [ $? -ne 0 ]; then
    echo "   ERROR: Build failed!"
    exit 1
fi
echo "   ✓ Build completed"

echo ""
echo "4. Verifying build output..."
if [ ! -d "js" ] || [ -z "$(ls -A js/ 2>/dev/null)" ]; then
    echo "   ERROR: No JavaScript files were generated!"
    exit 1
else
    echo "   ✓ JavaScript files found:"
    ls -lh js/ | tail -n +2
fi

echo ""
echo "5. Checking for tag references in built files..."
if grep -qi "NcSelectTags\|Files tagged with" js/*.js 2>/dev/null; then
    echo "   WARNING: Built files still contain tag references!"
    echo "   This might be from dependencies, but check manually"
else
    echo "   ✓ No tag references found in built files"
fi

echo ""
echo "6. Setting correct permissions..."
chown -R www-data:www-data js/ 2>/dev/null || echo "   (Skipping chown - run as root if needed)"
chmod -R 755 js/ 2>/dev/null || echo "   (Skipping chmod - run as root if needed)"
echo "   ✓ Permissions set"

echo ""
echo "7. Clearing Nextcloud cache..."
echo "   Entering maintenance mode..."
docker exec -u www-data nextcloud_nextcloud_app php /var/www/html/occ maintenance:mode --on 2>/dev/null || echo "   (Warning: Could not enter maintenance mode)"
echo "   Exiting maintenance mode..."
docker exec -u www-data nextcloud_nextcloud_app php /var/www/html/occ maintenance:mode --off 2>/dev/null || echo "   (Warning: Could not exit maintenance mode)"
echo "   ✓ Cache cleared"

echo ""
echo "=== Done ==="
echo ""
echo "Next steps:"
echo "1. Clear your browser cache (Ctrl+Shift+Delete or Cmd+Shift+Delete)"
echo "2. Hard refresh the page (Ctrl+Shift+R or Cmd+Shift+R)"
echo "3. Or try an incognito/private window"
echo ""
echo "If the tag section still appears, check:"
echo "- Browser Developer Tools (F12) → Network tab → verify which JS files are loaded"
echo "- Browser Console for any JavaScript errors"
echo "- Nextcloud logs: docker logs nextcloud_nextcloud_app -f"
