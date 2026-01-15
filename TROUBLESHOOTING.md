# Troubleshooting: App Not Appearing in Nextcloud

If the app is not showing up in Nextcloud's app list, follow these steps:

## Step 1: Verify App Directory Location

Ensure the app is in the correct location:
```bash
# Your app should be at:
/opt/stacks/nextcloud/apps/time_archive

# Verify it exists
ls -la /opt/stacks/nextcloud/apps/time_archive
```

## Step 2: Check File Permissions

The web server user must be able to read all files:

```bash
# Find your web server user (usually www-data, nginx, or apache)
ps aux | grep -E 'nginx|apache|httpd' | head -1

# Set correct ownership (replace www-data with your web server user)
sudo chown -R www-data:www-data /opt/stacks/nextcloud/apps/time_archive

# Set correct permissions
sudo find /opt/stacks/nextcloud/apps/time_archive -type d -exec chmod 755 {} \;
sudo find /opt/stacks/nextcloud/apps/time_archive -type f -exec chmod 644 {} \;
```

## Step 3: Validate appinfo/info.xml

Check if the XML file is valid:

```bash
cd /opt/stacks/nextcloud/apps/time_archive
xmllint --noout appinfo/info.xml
```

If `xmllint` is not installed:
```bash
# Ubuntu/Debian
sudo apt-get install libxml2-utils

# Or use PHP to validate
php -r "libxml_use_internal_errors(true); \$xml = simplexml_load_file('appinfo/info.xml'); if (\$xml === false) { foreach (libxml_get_errors() as \$error) { echo \$error->message; } } else { echo 'XML is valid'; }"
```

## Step 4: Clear Nextcloud Cache

Nextcloud caches app information. Clear it:

```bash
cd /opt/stacks/nextcloud

# Enter maintenance mode
sudo -u www-data php occ maintenance:mode --on

# Clear app cache
sudo -u www-data php occ app:list

# Exit maintenance mode
sudo -u www-data php occ maintenance:mode --off
```

## Step 5: Check Nextcloud Logs

Look for errors in the Nextcloud log:

```bash
# Check the log file (location may vary)
tail -f /opt/stacks/nextcloud/data/nextcloud.log

# Or check system logs
journalctl -u nextcloud -f  # if using systemd
```

## Step 6: Verify Required Files Exist

Check that all required files are present:

```bash
cd /opt/stacks/nextcloud/apps/time_archive

# Required files
ls -la appinfo/info.xml
ls -la appinfo/Application.php
ls -la appinfo/routes.php

# Check if lib directory exists
ls -la lib/

# Check if built JS files exist
ls -la js/
```

## Step 7: Enable App via CLI

Try enabling the app directly via command line:

```bash
cd /opt/stacks/nextcloud

# List all apps (should show time_archive if detected)
sudo -u www-data php occ app:list | grep time_archive

# Enable the app
sudo -u www-data php occ app:enable time_archive

# If that fails, check for errors
sudo -u www-data php occ app:enable time_archive -v
```

## Step 8: Check Nextcloud Version Compatibility

Verify your Nextcloud version is compatible:

```bash
cd /opt/stacks/nextcloud
sudo -u www-data php occ status
```

The app requires Nextcloud 28-33. If you're on a different version, you may need to adjust the `min-version` and `max-version` in `appinfo/info.xml`.

## Step 9: Verify PHP Dependencies

Ensure Composer dependencies are installed:

```bash
cd /opt/stacks/nextcloud/apps/time_archive
ls -la vendor/
```

If `vendor/` is missing or empty:
```bash
composer install --no-dev --optimize-autoloader
```

## Step 10: Check for Syntax Errors

Check PHP files for syntax errors:

```bash
cd /opt/stacks/nextcloud/apps/time_archive
find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;
```

## Step 11: Verify App ID

Ensure the app ID matches in all places:

```bash
cd /opt/stacks/nextcloud/apps/time_archive

# Check info.xml
grep '<id>' appinfo/info.xml

# Check Application.php
grep 'APP_ID' appinfo/Application.php

# Both should show: time_archive
```

## Step 12: Check Web Server Configuration

If using a reverse proxy or special web server config, ensure the apps directory is accessible:

```bash
# Test if web server can access the directory
sudo -u www-data ls -la /opt/stacks/nextcloud/apps/time_archive
```

## Common Issues and Solutions

### Issue: "App not found" error
**Solution:** Check file permissions and ensure the app directory is readable by the web server user.

### Issue: "Invalid appinfo/info.xml"
**Solution:** Validate the XML file and check for syntax errors.

### Issue: "Dependencies not met"
**Solution:** Check Nextcloud version compatibility and ensure all PHP dependencies are installed.

### Issue: App appears but can't be enabled
**Solution:** Check Nextcloud logs for specific error messages. Common causes:
- Missing PHP extensions
- Database migration errors
- Permission issues

## Still Not Working?

If none of the above steps work:

1. **Check Nextcloud documentation:** https://docs.nextcloud.com/server/latest/admin_manual/apps_management.html
2. **Check app logs:** Look for specific error messages
3. **Try a minimal test:** Create a simple test app to verify your Nextcloud installation can detect apps
4. **Check SELinux/AppArmor:** If enabled, they might be blocking file access

## Quick Diagnostic Command

Run this to get a quick overview:

```bash
cd /opt/stacks/nextcloud/apps/time_archive && \
echo "=== File Permissions ===" && \
ls -la appinfo/ && \
echo -e "\n=== XML Validation ===" && \
xmllint --noout appinfo/info.xml 2>&1 && \
echo "XML is valid" || echo "XML has errors" && \
echo -e "\n=== Required Files ===" && \
[ -f appinfo/info.xml ] && echo "✓ info.xml exists" || echo "✗ info.xml missing" && \
[ -f appinfo/Application.php ] && echo "✓ Application.php exists" || echo "✗ Application.php missing" && \
[ -d lib/ ] && echo "✓ lib/ directory exists" || echo "✗ lib/ directory missing" && \
[ -d js/ ] && echo "✓ js/ directory exists" || echo "✗ js/ directory missing" && \
echo -e "\n=== Nextcloud Detection ===" && \
cd /opt/stacks/nextcloud && \
sudo -u www-data php occ app:list 2>&1 | grep -i time_archive || echo "App not detected by Nextcloud"
```
