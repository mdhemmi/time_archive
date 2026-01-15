#!/bin/bash
# Script to check PHP errors

echo "=== Checking PHP Errors ==="
echo ""

echo "1. Checking PHP error log:"
docker exec nextcloud_nextcloud_app tail -50 /var/log/php*-fpm.log 2>/dev/null | tail -20 || echo "   No PHP-FPM log found"

echo ""
echo "2. Checking Apache error log:"
docker exec nextcloud_nextcloud_app tail -50 /var/log/apache2/error.log 2>/dev/null | tail -20 || echo "   No Apache error log found"

echo ""
echo "3. Checking Nextcloud log:"
docker exec nextcloud_nextcloud_app tail -50 /var/www/html/data/nextcloud.log 2>/dev/null | tail -20 || echo "   No Nextcloud log found"

echo ""
echo "4. Testing PHP syntax of key files:"
echo "   Checking APIController.php..."
docker exec nextcloud_nextcloud_app php -l /var/www/html/apps/time_archive/lib/Controller/APIController.php 2>&1

echo ""
echo "   Checking Admin.php..."
docker exec nextcloud_nextcloud_app php -l /var/www/html/apps/time_archive/lib/Settings/Admin.php 2>&1

echo ""
echo "5. To see real-time errors, run:"
echo "   docker logs nextcloud_nextcloud_app -f | grep -i error"
