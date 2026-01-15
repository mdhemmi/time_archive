# Deployment Guide for Nextcloud File Archive App

This guide explains how to deploy the File Archive app into a Nextcloud instance.

## Prerequisites

Before deploying, ensure you have:
- **Nextcloud 28-33** installed and running
- **PHP 8.2 or higher** with required extensions
- **Node.js 24+** and **npm 11+** installed (see [Node.js Version Requirements](#nodejs-version-requirements) below)
- **Composer** installed
- **Command-line access** to your Nextcloud server (SSH or direct access)

### Node.js Version Requirements

**Important:** This app requires Node.js 24+ and npm 11+. If your server has an older version, you have three options:

1. **Upgrade Node.js on the server** (recommended for production)
2. **Use a Node version manager** (nvm) to install the correct version
3. **Build assets on a different machine** and copy them to the server

See the [Troubleshooting](#troubleshooting) section for detailed instructions.

## Deployment Steps

### Method 1: Manual Deployment (Recommended for Production)

#### Step 1: Copy the App to Nextcloud

Copy the entire app directory to your Nextcloud `apps` folder:

```bash
# If deploying from this directory
cp -r /Volumes/MacMiniM4-ext\ 1/Development/time_archive /path/to/nextcloud/apps/time_archive

# Or if you have the app in a different location
# Replace /path/to/nextcloud with your actual Nextcloud installation path
# Common paths:
# - /var/www/nextcloud/apps/
# - /usr/share/nextcloud/apps/
# - ~/nextcloud/apps/
```

**Important:** Ensure the app directory is owned by the web server user (usually `www-data` or `nginx`):

```bash
sudo chown -R www-data:www-data /path/to/nextcloud/apps/time_archive
```

#### Step 2: Install PHP Dependencies

Navigate to the app directory and install Composer dependencies:

```bash
cd /path/to/nextcloud/apps/time_archive
composer install --no-dev --optimize-autoloader
```

**Note:** Use `--no-dev` for production to exclude development dependencies.

#### Step 3: Install and Build Frontend Assets

Install npm dependencies and build the frontend:

```bash
# Install dependencies
# If package-lock.json exists, use npm ci for faster, reliable builds:
npm ci

# If package-lock.json doesn't exist, use npm install instead:
# npm install

# Build for production
npm run build
```

**Note:** 
- `npm ci` requires a `package-lock.json` file. If it doesn't exist, use `npm install` instead, which will generate the lock file.
- If you don't have Node.js on your production server, you can build the assets on your development machine and copy the `dist/` or `js/` directory to the server.

#### Step 4: Set Proper Permissions

Ensure the app directory has correct permissions:

```bash
# Set ownership (adjust user/group as needed)
sudo chown -R www-data:www-data /path/to/nextcloud/apps/time_archive

# Set directory permissions
sudo find /path/to/nextcloud/apps/time_archive -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /path/to/nextcloud/apps/time_archive -type f -exec chmod 644 {} \;
```

#### Step 5: Enable the App

You can enable the app in two ways:

**Option A: Via Command Line (Recommended)**
```bash
cd /path/to/nextcloud
sudo -u www-data php occ app:enable time_archive
```

**Option B: Via Web Interface**
1. Log in as an administrator
2. Go to **Apps** → **Not enabled**
3. Find **"File Archive"** in the list
4. Click **Enable**

#### Step 6: Run Database Migrations

Run the Nextcloud upgrade command to execute database migrations:

```bash
cd /path/to/nextcloud
sudo -u www-data php occ upgrade
```

This will automatically run any pending migrations for the app.

#### Step 7: Verify Installation

Check that the app is enabled:

```bash
cd /path/to/nextcloud
sudo -u www-data php occ app:list | grep time_archive
```

You should see `time_archive` in the enabled apps list.

### Method 2: Development Deployment

For development/testing purposes:

```bash
# 1. Copy app to Nextcloud apps directory
cp -r /path/to/time_archive /path/to/nextcloud/apps/

# 2. Install dependencies (including dev dependencies)
cd /path/to/nextcloud/apps/time_archive
composer install
# Use npm install if package-lock.json doesn't exist, otherwise use npm ci
npm install  # or npm ci if package-lock.json exists

# 3. Build for development (with source maps)
npm run dev

# 4. Enable app
cd /path/to/nextcloud
php occ app:enable time_archive

# 5. Run migrations
php occ upgrade
```

## Post-Deployment Configuration

### Access the Admin Settings

1. Log in as an administrator
2. Navigate to **Settings** → **Administration** → **Workflow** → **File Archive**
3. Create archive rules as needed

### Verify Background Jobs

The app uses background jobs to archive files. Ensure Nextcloud's cron is running:

```bash
# Check cron status
cd /path/to/nextcloud
sudo -u www-data php occ status

# If cron is not running, set it up:
# Option 1: System cron (recommended)
sudo -u www-data crontab -e
# Add: */5 * * * * cd /path/to/nextcloud && php -f cron.php

# Option 2: AJAX cron (less reliable)
# This runs automatically when users access the web interface
```

## Troubleshooting

### Node.js Version Mismatch

If you see warnings like `EBADENGINE Unsupported engine` during `npm install`, your Node.js/npm versions are too old. The app requires Node.js 24+ and npm 11+.

#### Option 1: Upgrade Node.js on the Server (Recommended)

**Using NodeSource repository (Ubuntu/Debian):**
```bash
# Install Node.js 24.x
curl -fsSL https://deb.nodesource.com/setup_24.x | sudo -E bash -
sudo apt-get install -y nodejs

# Verify installation
node --version  # Should show v24.x.x
npm --version   # Should show 11.x.x
```

**Using nvm (Node Version Manager):**
```bash
# Install nvm
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
source ~/.bashrc  # or ~/.zshrc

# Install and use Node.js 24
nvm install 24
nvm use 24
nvm alias default 24

# Verify
node --version
npm --version
```

**Using package manager (varies by OS):**
- **CentOS/RHEL:** Use NodeSource repository similar to Ubuntu
- **macOS:** `brew install node@24` or use nvm
- **Alpine:** `apk add nodejs npm` (check available versions)

#### Option 2: Build Assets on Development Machine

If you can't upgrade Node.js on the production server, build the assets on a machine with Node.js 24+:

**On development machine:**
```bash
cd /path/to/time_archive
npm install
npm run build
```

**Copy built assets to server:**
```bash
# Copy the built JavaScript files
scp -r js/ user@server:/opt/stacks/nextcloud/apps/time_archive/

# Or copy the entire app directory after building
```

**On production server:**
```bash
# Only install PHP dependencies (skip npm)
cd /opt/stacks/nextcloud/apps/time_archive
composer install --no-dev --optimize-autoloader
```

#### Option 3: Use Docker/Container for Building

If you have Docker available:
```bash
# Build using Node.js 24 container
docker run --rm -v $(pwd):/app -w /app node:24 npm install && npm run build
```

**Note:** While npm may show warnings with older Node.js versions, the build might still work. However, it's recommended to use the correct versions to avoid potential runtime issues.

### App Not Appearing in Apps List

1. **Check file permissions:**
   ```bash
   ls -la /path/to/nextcloud/apps/time_archive
   ```

2. **Check appinfo/info.xml syntax:**
   ```bash
   xmllint --noout /path/to/nextcloud/apps/time_archive/appinfo/info.xml
   ```

3. **Clear Nextcloud cache:**
   ```bash
   cd /path/to/nextcloud
   sudo -u www-data php occ maintenance:mode --on
   sudo -u www-data php occ maintenance:mode --off
   ```

### Frontend Assets Not Loading

1. **Rebuild frontend assets:**
   ```bash
   cd /path/to/nextcloud/apps/time_archive
   npm run build
   ```

2. **Clear browser cache** and Nextcloud cache

### Database Migration Errors

1. **Check Nextcloud logs:**
   ```bash
   tail -f /path/to/nextcloud/data/nextcloud.log
   ```

2. **Manually run migrations:**
   ```bash
   cd /path/to/nextcloud
   sudo -u www-data php occ upgrade
   ```

### Permission Errors

Ensure the web server user owns the app directory:
```bash
sudo chown -R www-data:www-data /path/to/nextcloud/apps/time_archive
```

## Updating the App

When updating to a new version:

1. **Backup current installation:**
   ```bash
   cp -r /path/to/nextcloud/apps/time_archive /path/to/backup/time_archive
   ```

2. **Update files** (git pull, or copy new files)

3. **Update dependencies:**
   ```bash
   cd /path/to/nextcloud/apps/time_archive
   composer install --no-dev --optimize-autoloader
   npm install  # or npm ci if package-lock.json exists
   npm run build
   ```

4. **Run migrations:**
   ```bash
   cd /path/to/nextcloud
   sudo -u www-data php occ upgrade
   ```

5. **Clear cache:**
   ```bash
   sudo -u www-data php occ maintenance:mode --on
   sudo -u www-data php occ maintenance:mode --off
   ```

## Security Considerations

1. **File Permissions:** Never make the app directory world-writable
2. **Dependencies:** Always use `--no-dev` in production
3. **Updates:** Keep the app updated with security patches
4. **Backups:** Backup your Nextcloud instance before deploying/updating

## Uninstallation

To remove the app:

```bash
# 1. Disable the app
cd /path/to/nextcloud
sudo -u www-data php occ app:disable time_archive

# 2. Remove the app directory
rm -rf /path/to/nextcloud/apps/time_archive

# 3. Clear cache
sudo -u www-data php occ maintenance:mode --on
sudo -u www-data php occ maintenance:mode --off
```

**Note:** Disabling the app will stop archiving jobs, but archived files will remain in the `.archive` folder.

## Additional Resources

- [Nextcloud App Development Documentation](https://docs.nextcloud.com/server/latest/developer_manual/app/)
- [Nextcloud Administration Manual](https://docs.nextcloud.com/server/latest/admin_manual/)
- [App Repository](https://github.com/nextcloud/time_archive)
