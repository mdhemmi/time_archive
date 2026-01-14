<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Nextcloud File Archive App

An app for Nextcloud to automatically archive files based on file age. Archived files are moved to the `.archive` folder for each user, which is hidden from mobile apps to prevent re-uploading but remains accessible via the web interface.

## Features

- **Automatic Archiving**: Archive files based on age (no tags required)
- **Mobile App Hidden**: Archive folder is prefixed with a dot (`.archive`), making it invisible to mobile apps and preventing re-upload
- **Web Accessible**: Archived files remain accessible through the web interface
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
git clone https://github.com/nextcloud/files_archive.git
```

2. Install PHP dependencies:
```bash
cd files_archive
composer install --no-dev
```

3. Install and build frontend assets:
```bash
npm ci
npm run build
```

4. Enable the app:
   - Via web UI: Go to Apps → Find "File Archive" → Enable
   - Via CLI: `php occ app:enable files_archive`

5. Run database migrations:
```bash
php occ upgrade
```

## Usage

1. Go to **Settings → Administration → Workflow → File Archive**
2. Create an archive rule:
   - Set the archive period (e.g., 1 year)
   - Choose time unit (Days/Weeks/Months/Years)
   - Select date calculation method (Creation date or Last modification date)
3. Files matching the age criteria will be automatically archived to `.archive` folder for each user

## How It Works

- Files are moved (not deleted) to the `.archive` folder in each user's home directory
- The archive folder is hidden from mobile apps (dot-prefixed) to prevent re-uploading
- Background jobs run daily to check and archive files for all users
- Archived files remain accessible via the web interface
- Each user has their own `.archive` folder, ensuring files are organized per user

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
# files_archive
