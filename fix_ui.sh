#!/bin/bash
# Script to fix UI caching issues

echo "=== Fixing Time Archive UI ==="
echo ""

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR" || exit 1

echo "Working directory: $(pwd)"

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
    echo "   Current directory: $(pwd)"
    echo "   Checking for js directory..."
    ls -la | grep js || echo "   No js directory found"
    echo "   Checking webpack output..."
    find . -name "*.js" -path "*/js/*" 2>/dev/null | head -5 || echo "   No JS files found in js/ directory"
    exit 1
else
    echo "   ✓ JavaScript files found:"
    ls -lh js/ | tail -n +2
    echo ""
    echo "   Verifying required files exist:"
    # Check for files with correct naming (after webpack prefix)
    if [ -f "js/time_archive-main.js" ]; then
        echo "   ✓ time_archive-main.js exists"
    elif [ -f "js/time_archive-time_archive-main.js" ]; then
        echo "   ⚠ time_archive-time_archive-main.js exists (wrong name - will be fixed on next build)"
    else
        echo "   ✗ time_archive-main.js MISSING"
    fi
    if [ -f "js/time_archive-navigation.js" ]; then
        echo "   ✓ time_archive-navigation.js exists"
    elif [ -f "js/time_archive-time_archive-navigation.js" ]; then
        echo "   ⚠ time_archive-time_archive-navigation.js exists (wrong name - will be fixed on next build)"
    else
        echo "   ✗ time_archive-navigation.js MISSING"
    fi
    if [ -f "js/time_archive-archive.js" ]; then
        echo "   ✓ time_archive-archive.js exists"
    elif [ -f "js/time_archive-time_archive-archive.js" ]; then
        echo "   ⚠ time_archive-time_archive-archive.js exists (wrong name - will be fixed on next build)"
    else
        echo "   ✗ time_archive-archive.js MISSING"
    fi
    if [ -f "js/time_archive-archiveLink.js" ]; then
        echo "   ✓ time_archive-archiveLink.js exists"
    elif [ -f "js/time_archive-time_archive-archiveLink.js" ]; then
        echo "   ⚠ time_archive-time_archive-archiveLink.js exists (wrong name - will be fixed on next build)"
    else
        echo "   ✗ time_archive-archiveLink.js MISSING"
    fi
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
