# Complete Guide: Building and Releasing the Time Archive App

This comprehensive guide covers everything you need to know to build, sign, and release the Time Archive app to the Nextcloud App Store.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Building the App](#building-the-app)
3. [Creating a Release](#creating-a-release)
4. [Signing the App](#signing-the-app)
5. [Uploading to App Store](#uploading-to-app-store)
6. [Automated Release with GitHub Actions](#automated-release-with-github-actions)
7. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Required Software

- **Node.js 24+** and **npm 11+**
- **PHP 8.2+** with Composer
- **Git** (for version control)
- **Nextcloud server** (for signing, can be local or remote)
- **Docker** (optional, for signing via script)

### Required Accounts and Certificates

1. **Nextcloud App Store Account**
   - Create account at https://apps.nextcloud.com
   - Register your app (if not already done)

2. **Signing Certificate** (one-time setup)
   - Private key: `~/.nextcloud/certificates/time_archive.key`
   - Certificate: `~/.nextcloud/certificates/time_archive.crt`
   - See [Initial Certificate Setup](#initial-certificate-setup) below

### Initial Certificate Setup

If you don't have signing certificates yet:

1. **Generate a private key**:
   ```bash
   mkdir -p ~/.nextcloud/certificates
   cd ~/.nextcloud/certificates
   openssl genrsa -out time_archive.key 4096
   ```

2. **Generate a certificate signing request (CSR)**:
   ```bash
   openssl req -new -key time_archive.key -out time_archive.csr
   ```
   Fill in the prompts (Common Name should be your app ID: `time_archive`)

3. **Upload CSR to App Store**:
   - Go to https://apps.nextcloud.com
   - Navigate to your app → "Certificates" or "Signing"
   - Upload `time_archive.csr`
   - Download the certificate file (`time_archive.crt`)

4. **Store certificates securely**:
   ```bash
   # Keep these files safe - you'll need them for every release
   ~/.nextcloud/certificates/time_archive.key
   ~/.nextcloud/certificates/time_archive.crt
   ```

---

## Building the App

### Development Build

For local development and testing:

```bash
# Navigate to app directory
cd /path/to/time_archive

# Install PHP dependencies (development)
composer install

# Install npm dependencies
npm ci

# Build for development (with source maps, etc.)
npm run build

# Or use watch mode for automatic rebuilding
npm run watch
```

**Output**: Built files in `js/` directory:
- `time_archive-main.js`
- `time_archive-archive.js`
- `time_archive-archiveLink.js`
- `time_archive-openFile.js`
- Source maps (`.map` files)

### Production Build

For release and production deployment:

```bash
# Navigate to app directory
cd /path/to/time_archive

# Install PHP dependencies (production, no dev dependencies)
composer install --no-dev --optimize-autoloader

# Install npm dependencies
npm ci

# Build for production (optimized, minified)
npm run build
```

**Verify build output**:
```bash
# Check that js/ directory exists with built files
ls -la js/
# Should see:
# - time_archive-main.js
# - time_archive-archive.js
# - time_archive-archiveLink.js
# - time_archive-openFile.js
# - *.map files (source maps)
```

### Build Without Node.js on Server

If your production server doesn't have Node.js:

1. **Build on development machine**:
   ```bash
   npm ci
   npm run build
   ```

2. **Copy built files to server**:
   ```bash
   # Copy the js/ directory
   scp -r js/ user@server:/path/to/nextcloud/apps/time_archive/
   
   # Or use rsync
   rsync -av js/ user@server:/path/to/nextcloud/apps/time_archive/js/
   ```

---

## Creating a Release

### Step 1: Update Version Number

Update the version in `appinfo/info.xml`:

```xml
<version>1.0.1</version>  <!-- Update to new version -->
```

**Version Numbering** (follow Semantic Versioning):
- **MAJOR** (1.0.0): Breaking changes
- **MINOR** (0.1.0): New features, backward compatible
- **PATCH** (0.0.1): Bug fixes, backward compatible

Example progression:
- `1.0.0` → `1.0.1` (bug fix)
- `1.0.1` → `1.1.0` (new feature)
- `1.1.0` → `2.0.0` (breaking change)

### Step 2: Commit and Tag

```bash
# Ensure all changes are committed
git status
git add appinfo/info.xml
git commit -m "Release version 1.0.1"

# Create version tag
git tag -a v1.0.1 -m "Release version 1.0.1"

# Push to remote
git push origin main
git push origin v1.0.1
```

### Step 3: Build the App

Follow the [Production Build](#production-build) steps above.

### Step 4: Create Release Archive

#### Option A: Manual Archive Creation

```bash
# Create temporary directory
mkdir -p /tmp/time_archive-release
cd /tmp/time_archive-release

# Copy app files (exclude development files)
rsync -av \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='.gitignore' \
  --exclude='.editorconfig' \
  --exclude='.eslintrc.js' \
  --exclude='.stylelintrc.json' \
  --exclude='babel.config.js' \
  --exclude='tsconfig.json' \
  --exclude='webpack.config.js' \
  --exclude='package-lock.json' \
  --exclude='composer.lock' \
  --exclude='*.md' \
  --exclude='*.sh' \
  --exclude='tests' \
  --exclude='.phpunit.result.cache' \
  /path/to/time_archive/ ./time_archive/

# Create tarball (tar.gz format required by Nextcloud)
cd /tmp/time_archive-release
tar -czf time_archive-1.0.1.tar.gz time_archive/

# Verify archive
file time_archive-1.0.1.tar.gz
# Should show: "gzip compressed data"

# Verify archive structure
tar -tzf time_archive-1.0.1.tar.gz | head -5
# Should show: time_archive/...
```

#### Option B: Using GitHub Actions (Recommended)

The GitHub Actions workflow automatically creates the archive when you push a tag:

```bash
# Push tag to trigger workflow
git push origin v1.0.1
```

Then download the archive from:
- GitHub Releases: https://github.com/your-username/time_archive/releases
- Or GitHub Actions artifacts

**Important**: Download from the **Release page**, not artifacts (artifacts are wrapped in ZIP).

---

## Signing the App

The app **must be signed** before uploading to the App Store. You can sign it manually or using the provided script.

### Option A: Using the Signing Script (Recommended)

The `sign-release.sh` script automates signing using Docker:

```bash
# Basic usage (script will find archive in current directory)
./sign-release.sh 1.0.1

# Or specify the archive file explicitly
./sign-release.sh 1.0.1 time_archive-1.0.1.tar.gz
```

**Prerequisites**:
- Docker container named `nextcloud_nextcloud_app` must be running
- Certificates in `~/.nextcloud/certificates/`:
  - `time_archive.key`
  - `time_archive.crt`

**What the script does**:
1. Copies archive to Docker container
2. Extracts archive
3. Signs using `occ app:sign`
4. Copies signed archive back as `time_archive-1.0.1-signed.tar.gz`
5. Cleans up temporary files

### Option B: Manual Signing

#### On Nextcloud Server

```bash
# On your Nextcloud server
cd /var/www/html/apps

# Extract the archive (if not already extracted)
tar -xzf /path/to/time_archive-1.0.1.tar.gz

# Sign the app
php occ app:sign time_archive \
  --privateKey=~/.nextcloud/certificates/time_archive.key \
  --certificate=~/.nextcloud/certificates/time_archive.crt \
  --path=/tmp

# The signed archive will be created at:
# /tmp/time_archive-1.0.1.tar.gz
```

#### Using Docker

```bash
# Copy archive to container
docker cp time_archive-1.0.1.tar.gz nextcloud_nextcloud_app:/tmp/

# Extract in container
docker exec nextcloud_nextcloud_app tar -xzf /tmp/time_archive-1.0.1.tar.gz -C /tmp/

# Copy certificates to container (if not already there)
docker cp ~/.nextcloud/certificates/time_archive.key nextcloud_nextcloud_app:/root/.nextcloud/certificates/
docker cp ~/.nextcloud/certificates/time_archive.crt nextcloud_nextcloud_app:/root/.nextcloud/certificates/

# Sign the app
docker exec -u www-data nextcloud_nextcloud_app php /var/www/html/occ app:sign time_archive \
  --privateKey=/root/.nextcloud/certificates/time_archive.key \
  --certificate=/root/.nextcloud/certificates/time_archive.crt \
  --path=/tmp

# Copy signed archive back
docker cp nextcloud_nextcloud_app:/tmp/time_archive-1.0.1.tar.gz ./time_archive-1.0.1-signed.tar.gz
```

### Verify Signature

After signing, verify the signature is present:

```bash
# Check for signature.json in archive
tar -tzf time_archive-1.0.1.tar.gz | grep signature.json
# Should show: time_archive/signature.json

# Extract and view signature (optional)
tar -xzf time_archive-1.0.1.tar.gz
cat time_archive/signature.json
```

---

## Uploading to App Store

### Step 1: Prepare Release Information

Before uploading, prepare:
- **Version number** (must match `appinfo/info.xml`)
- **Changelog** (list of changes, new features, bug fixes)
- **Supported Nextcloud versions** (from `appinfo/info.xml` dependencies)
- **Signed archive file** (`.tar.gz` format)

### Step 2: Upload to App Store

1. **Log in to Nextcloud App Store**:
   - Go to https://apps.nextcloud.com
   - Log in with your account

2. **Navigate to your app**:
   - Go to "My apps" → "time_archive"

3. **Create new release**:
   - Click "New Release" or "Upload Release"
   - Fill in the form:
     - **Version**: `1.0.1` (must match `info.xml`)
     - **Changelog**: 
       ```
       - Fixed issue with locked files
       - Added support for archiving empty folders
       - Improved handling of shared files
       ```
     - **Supported Nextcloud versions**: Select based on your `info.xml` dependencies
     - **Download link**: Leave empty (you'll upload the file)
   
4. **Upload the signed archive**:
   - Click "Choose File" or drag-and-drop
   - Select `time_archive-1.0.1.tar.gz` (the signed archive)
   - **Important**: Use the signed archive, not the unsigned one

5. **Submit for review**:
   - Review all information
   - Check that version number matches
   - Verify archive is signed (should show signature info)
   - Click "Submit" or "Upload"

### Step 3: Wait for Approval

- **Updates**: Usually approved automatically within minutes
- **New apps**: May require manual review (can take 1-3 days)
- **Check status**: Go to "My apps" → "time_archive" → "Releases"

### Common Upload Issues

**"No possible app folder found"**:
- You're using the wrong download URL (GitHub archive URL instead of release asset)
- Solution: Download from GitHub Release page, not the archive URL
- Correct URL format: `https://github.com/user/repo/releases/download/v1.0.1/time_archive-1.0.1.tar.gz`

**"Invalid signature"**:
- Archive is not signed or signature is invalid
- Solution: Re-sign the archive using `occ app:sign`

**"Version already exists"**:
- This version was already uploaded
- Solution: Use a new version number

---

## Automated Release with GitHub Actions

The repository includes GitHub Actions workflows that automate most of the release process.

### Workflow Overview

**`.github/workflows/release.yml`**:
- Triggers on version tag push (e.g., `v1.0.1`)
- Builds the app (composer + npm)
- Creates release archive (`.tar.gz`)
- Creates GitHub Release
- Uploads archive as release asset

### Setting Up Automated Releases

1. **Ensure workflow is enabled**:
   - Go to GitHub repository → Settings → Actions → General
   - Ensure "Allow all actions and reusable workflows" is enabled

2. **Set workflow permissions**:
   - Go to Settings → Actions → General
   - Under "Workflow permissions", select "Read and write permissions"
   - Check "Allow GitHub Actions to create and approve pull requests"

3. **Add Personal Access Token (for releases)**:
   - Go to https://github.com/settings/tokens
   - Create new token (classic) with `repo` scope
   - Go to repository → Settings → Secrets and variables → Actions
   - Add secret: `RELEASE_TOKEN` (paste your token)

4. **Create a release**:
   ```bash
   # Update version in appinfo/info.xml
   git add appinfo/info.xml
   git commit -m "Bump version to 1.0.1"
   git tag -a v1.0.1 -m "Release version 1.0.1"
   git push origin main
   git push origin v1.0.1  # This triggers the workflow
   ```

5. **Download signed archive**:
   - Go to GitHub → Releases
   - Download `time_archive-1.0.1.tar.gz` from the release
   - **Note**: If signing is not automated, download and sign manually

### Manual Workflow Trigger

You can also trigger the workflow manually:

1. Go to GitHub → Actions → "Release" workflow
2. Click "Run workflow"
3. Enter version number (e.g., `1.0.1`)
4. Click "Run workflow"

### Automated Signing (Optional)

To enable automated signing in GitHub Actions:

1. **Add signing secrets**:
   - Go to repository → Settings → Secrets and variables → Actions
   - Add secrets:
     - `APP_SIGNING_CERTIFICATE`: Contents of `time_archive.crt`
     - `APP_SIGNING_KEY`: Contents of `time_archive.key`

2. **Note**: Automated signing requires a Nextcloud server environment. The workflow can create unsigned archives that you sign manually, or you can set up a self-hosted runner with Nextcloud installed.

---

## Troubleshooting

### Build Issues

**"npm: command not found"**:
```bash
# Install Node.js and npm
# On Ubuntu/Debian:
curl -fsSL https://deb.nodesource.com/setup_24.x | sudo -E bash -
sudo apt-get install -y nodejs

# On macOS:
brew install node
```

**"Module not found" errors**:
```bash
# Clean install
rm -rf node_modules package-lock.json
npm install
npm run build
```

**"Webpack errors"**:
- Check Node.js version: `node --version` (should be 24+)
- Check npm version: `npm --version` (should be 11+)
- Try: `npm ci` instead of `npm install`

### Signing Issues

**"Certificate not found"**:
```bash
# Verify certificate files exist
ls -la ~/.nextcloud/certificates/
# Should see: time_archive.key and time_archive.crt

# Check permissions
chmod 600 ~/.nextcloud/certificates/time_archive.key
chmod 644 ~/.nextcloud/certificates/time_archive.crt
```

**"App signing failed"**:
- Verify certificate hasn't expired
- Check certificate and key match (they were generated together)
- Ensure Nextcloud server has access to certificates

**"Docker container not found"**:
```bash
# List running containers
docker ps

# If container has different name, update sign-release.sh script
# Or use manual signing method
```

### Archive Issues

**"Archive is ZIP instead of TAR.GZ"**:
- GitHub Actions artifacts are always ZIP-wrapped
- Solution: Download from GitHub Release page, not artifacts
- Or create archive manually using `tar -czf`

**"Archive structure incorrect"**:
```bash
# Verify archive structure
tar -tzf time_archive-1.0.1.tar.gz | head -5
# Should show: time_archive/...

# If it shows: time_archive-1.0.1/... (wrong)
# Fix by extracting and re-archiving:
tar -xzf time_archive-1.0.1.tar.gz
cd time_archive-1.0.1
tar -czf ../time_archive-1.0.1-fixed.tar.gz time_archive/
```

### Upload Issues

**"No possible app folder found"**:
- Using wrong download URL (GitHub archive URL)
- Solution: Use release asset URL or upload file directly

**"Invalid signature"**:
- Archive not signed or signature corrupted
- Solution: Re-sign the archive

**"Version already exists"**:
- Version was already uploaded
- Solution: Use new version number

### GitHub Actions Issues

**"Too many retries"**:
- Update `softprops/action-gh-release` to v2
- Check workflow permissions
- Verify Personal Access Token has correct permissions

**"403 Forbidden" when creating release**:
- Add `permissions` section to workflow
- Add Personal Access Token as `RELEASE_TOKEN` secret
- Check repository workflow permissions

**"Workflow not triggered"**:
- Verify tag format: `v1.0.1` (with 'v' prefix)
- Check workflow file syntax
- Verify workflow is in `.github/workflows/` directory

---

## Quick Reference Checklist

### Before Release

- [ ] Update version in `appinfo/info.xml`
- [ ] Update CHANGELOG.md (if maintained)
- [ ] Test the app thoroughly
- [ ] Commit all changes
- [ ] Create version tag

### Building

- [ ] Install PHP dependencies: `composer install --no-dev`
- [ ] Install npm dependencies: `npm ci`
- [ ] Build frontend: `npm run build`
- [ ] Verify `js/` directory exists with built files

### Creating Archive

- [ ] Create clean archive (exclude dev files)
- [ ] Verify archive is `.tar.gz` format
- [ ] Verify archive structure (starts with `time_archive/`)
- [ ] Test archive extraction

### Signing

- [ ] Certificates available (`~/.nextcloud/certificates/`)
- [ ] Sign archive using `occ app:sign` or script
- [ ] Verify signature exists (`signature.json` in archive)
- [ ] Keep signed archive safe

### Uploading

- [ ] Log in to https://apps.nextcloud.com
- [ ] Navigate to app → "New Release"
- [ ] Fill in version, changelog, supported versions
- [ ] Upload signed archive
- [ ] Submit for review

### Post-Release

- [ ] Push git tag to remote
- [ ] Create GitHub Release (if using GitHub)
- [ ] Update documentation
- [ ] Announce release (if applicable)

---

## Additional Resources

- [Nextcloud App Store Documentation](https://docs.nextcloud.com/server/latest/developer_manual/app_development/app_store.html)
- [App Signing Guide](https://docs.nextcloud.com/server/latest/developer_manual/app_development/app_signing.html)
- [App Store Guidelines](https://apps.nextcloud.com/developer/apps/guidelines)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Semantic Versioning](https://semver.org/)

---

## Support

If you encounter issues not covered in this guide:

1. Check the [Troubleshooting](#troubleshooting) section
2. Review Nextcloud App Store documentation
3. Check GitHub Issues for similar problems
4. Ask for help in Nextcloud community forums
