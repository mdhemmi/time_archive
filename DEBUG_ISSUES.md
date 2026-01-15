# Debugging Guide for Archive App Issues

## Issue 1: Archive Rules Not Loading

### Check Browser Console
1. Open browser DevTools (F12)
2. Go to Console tab
3. Look for errors starting with `[Files Archive]`
4. Check Network tab for API calls to `/ocs/v2.php/apps/time_archive/api/v1/rules`

### Common Issues:
- **403 Forbidden**: User is not an admin. Archive rules are admin-only.
- **404 Not Found**: Route not registered. Run `php occ app:enable time_archive` and `php occ upgrade`
- **Empty response**: Check if rules exist in database: `SELECT * FROM oc_archive_rules;`

### Verify API Response Format:
The API should return:
```json
{
  "ocs": {
    "meta": {...},
    "data": [
      {
        "id": 1,
        "tagid": null,
        "timeunit": 3,
        "timeamount": 1,
        "timeafter": 1,
        "hasJob": true
      }
    ]
  }
}
```

## Issue 2: Navigation Icon Missing

### Check Logs:
```bash
docker exec nextcloud_nextcloud_app tail -100 /var/www/html/data/nextcloud.log | grep "Files Archive"
```

### Verify Navigation Registration:
1. Check if `NavigationManager` is being called in `Application.php`
2. Check if icon file exists: `ls -la /var/www/html/apps/time_archive/img/app.svg`
3. Check if route is registered: `php occ app:list | grep time_archive`

### Note:
`INavigationManager` might not be the right interface for top navigation bar. In Nextcloud, apps typically appear in top navigation automatically if they have a main route.

## Issue 3: Archiving Flow Not Listed

### Check Background Jobs:
```bash
docker exec -u www-data nextcloud_nextcloud_app php /var/www/html/occ job:list | grep Archive
```

### Verify Jobs Are Registered:
Jobs should be automatically created when rules are loaded. Check logs for:
- "Failed to check/add job for rule"
- "Archive job completed"

### Check Archive Folder:
```bash
ls -la /var/www/html/data/USERNAME/files/.archive
```
