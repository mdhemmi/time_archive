<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Nextcloud File Archive App

An app for Nextcloud to automatically archive files based on file age. Archived files are moved to the `.archive` folder for each user, which is hidden from mobile apps to prevent re-uploading but remains accessible via the web interface.

## Features

- **Automatic Archiving**: Archive files based on age (no tags required)
- **Mobile App Hidden**: Archive folder is prefixed with a dot (`.archive`), making it invisible to mobile apps and preventing re-upload
- **Web Accessible**: Archived files remain accessible through the web interface (enable "Show hidden files" in Files app settings)
- **Time-Based Rules**: Create archive rules based on file age
- **Flexible Time Periods**: Configure archive periods in days, weeks, months, or years
- **Date Calculation**: Choose to calculate from creation date or last modification date
- **Per-User Archives**: Each user gets their own `.archive` folder

## Installation

### Prerequisites

- Nextcloud 28-33
- PHP 8.2 or higher
- Node.js 24+ and npm 11+ (for building frontend assets)
- Composer (for PHP dependencies)

### Manual Installation

1. Clone or download the app:
```bash
cd /path/to/nextcloud/apps
git clone https://github.com/mdhemmi/time_archive.git
```

2. Install PHP dependencies:
```bash
cd time_archive
composer install --no-dev
```

3. Install and build frontend assets:
```bash
npm ci
npm run build
```

4. Enable the app:
   - Via web UI: Go to Apps → Find "File Archive" → Enable
   - Via CLI: `php occ app:enable time_archive`

5. Run database migrations:
```bash
php occ upgrade
```

## Usage

### For Administrators

1. Go to **Settings → Administration → Workflow → File Archive**
2. Create an archive rule:
   - Set the archive period (e.g., 1 year)
   - Choose time unit (Minutes/Hours/Days/Weeks/Months/Years)
   - Select date calculation method (Creation date or Last modification date)
3. Files matching the age criteria will be automatically archived to `.archive` folder for each user
4. You can manually trigger archive jobs using the "Run archive now" button

**Note**: Archive rule configuration is restricted to administrators only. Regular users cannot create, modify, or delete archive rules.

### For Regular Users

Regular users can:
- View their archived files via the Archive view (accessible from the top navigation bar)
- Access archived files through the Files app (enable "Show hidden files" in settings)

Regular users cannot:
- Create, modify, or delete archive rules
- Manually trigger archive jobs

## How It Works

- Files are moved (not deleted) to the `.archive` folder in each user's home directory
- The archive folder is hidden from mobile apps (dot-prefixed) to prevent re-uploading
- Background jobs run daily to check and archive files for all users
- Archived files remain accessible via the web interface
- Each user has their own `.archive` folder, ensuring files are organized per user
- **Protected folders**: Top-level folders used by mobile apps (Camera, Photos, SofortUpload, etc.) are never archived to prevent breaking auto-upload functionality. Files inside these folders can still be archived, but the folders themselves remain.

## Configuration

### Protected Folders

To configure additional protected folders (folders that should never be archived), you can set them via Nextcloud's `occ` command:

```bash
php occ config:app:set time_archive protected_folders --value "MyCustomFolder,AnotherFolder"
```

The default protected folders include: `Camera`, `Photos`, `Documents`, `Screenshots`, `Videos`, `Downloads`, `DCIM`, `Pictures`, `Images`, `SofortUpload`.

**Note**: Only top-level folders (direct children of `/user/files/`) are protected. Subfolders can still be archived.

## Viewing Archived Files

The `.archive` folder is hidden from mobile apps but accessible via the web interface:

- **Floating Archive Button**: When viewing the Files app, a floating **Archive** button appears in the bottom-right corner. Click it to access the dedicated Archive view listing all your archived files.
- **Dedicated Archive View**: You can also access the Archive view directly via: `https://your-nextcloud.com/index.php/apps/time_archive/`
- **Show Hidden Files**: Enable "Show hidden files" in Files app settings to see the `.archive` folder in the folder tree and Favorites section.
- **Direct URL to Archive Folder**: You can also access the archive folder directly via: `https://your-nextcloud.com/index.php/apps/files/?dir=/.archive`

**Important Note**: The `.archive` folder is dot-prefixed (hidden) to prevent mobile/desktop clients from syncing it. Due to Nextcloud's Files app behavior, **dot-prefixed folders do not appear in the Favorites section** when "Show hidden files" is disabled, even if they are favorited. To see it in Favorites, you must enable "Show hidden files" in Files app settings. Alternatively, use the dedicated **Archive** app view for the best experience.

**Note**: For existing `.archive` folders created before the favorite feature, run `php occ maintenance:repair` to add them to favorites (they will appear in Favorites only when "Show hidden files" is enabled).

## Development

```bash
# Install dependencies
composer install
npm ci

# Build for development
npm run dev

# Build for production
npm run build

# Watch for changes
npm run watch
```

## License

AGPL-3.0-or-later
# time_archive
