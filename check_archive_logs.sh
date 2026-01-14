#!/bin/bash
# Script to check archive job logs

echo "=== Checking Archive Job Logs ==="
echo ""

echo "1. Checking Nextcloud application logs for archive activity..."
echo "   (Looking for 'archive', 'files_archive', 'Running time-based', 'Archiving file', etc.)"
echo ""

# Check Docker logs
echo "Recent archive-related log entries:"
docker logs nextcloud_nextcloud_app --tail 100 2>&1 | grep -i -E "archive|files_archive|Running time-based|Archiving file|Skipping file|archive before" | tail -20

echo ""
echo "2. If no output above, checking all recent logs:"
docker logs nextcloud_nextcloud_app --tail 50 2>&1 | tail -20

echo ""
echo "3. To watch logs in real-time, run:"
echo "   docker logs nextcloud_nextcloud_app -f | grep -i archive"
echo ""
echo "4. To check if archive folder exists for a user, run:"
echo "   docker exec nextcloud_nextcloud_app ls -la /var/www/html/data/USERNAME/files/.archive"
echo "   (Replace USERNAME with actual username)"
echo ""
echo "5. To see all users, run:"
echo "   docker exec nextcloud_nextcloud_app ls -la /var/www/html/data/"
