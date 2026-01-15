# Release Process for Nextcloud App Store

This document describes the step-by-step process for releasing the `files_archive` app to the Nextcloud App Store.

## Prerequisites

1. **Nextcloud App Store Account**: Create an account at https://apps.nextcloud.com
2. **App Registration**: Register your app on the App Store (if not already done)
3. **Signing Certificate**: Obtain a signing certificate from the App Store (see "Initial Setup" below)

## Initial Setup (One-Time)

### 1. Register App on Nextcloud App Store

1. Go to https://apps.nextcloud.com
2. Log in or create an account
3. Navigate to "My apps" → "Register new app"
4. Fill in:
   - **App ID**: `files_archive` (must match `appinfo/info.xml`)
   - **App Name**: Files Archive
   - **Description**: Brief description of the app
   - **Category**: Files
5. Submit the registration

### 2. Generate Signing Certificate

1. **Generate a private key** (on a secure machine):
   ```bash
   mkdir -p ~/.nextcloud/certificates
   cd ~/.nextcloud/certificates
   openssl genrsa -out files_archive.key 4096
   ```

2. **Generate a certificate signing request (CSR)**:
   ```bash
   openssl req -new -key files_archive.key -out files_archive.csr
   ```
   Fill in the prompts (Common Name should be your app ID: `files_archive`)

3. **Upload CSR to App Store**:
   - Go to your app page on apps.nextcloud.com
   - Navigate to "Certificates" or "Signing"
   - Upload `files_archive.csr`
   - Download the certificate file (`files_archive.crt`)

4. **Store certificate securely**:
   ```bash
   # Keep these files safe - you'll need them for every release
   ~/.nextcloud/certificates/files_archive.key
   ~/.nextcloud/certificates/files_archive.crt
   ```

## Release Process (For Each Version)

### Step 1: Update Version Numbers

1. **Update `appinfo/info.xml`**:
   ```xml
   <version>1.0.0</version>  <!-- Update to new version -->
   ```

2. **Update `composer.json`** (optional, but recommended):
   ```json
   "version": "1.0.0"
   ```

3. **Update `package.json`** (optional, but recommended):
   ```json
   "version": "1.0.0"
   ```

### Step 2: Prepare Release

1. **Ensure all changes are committed**:
   ```bash
   git status
   git add .
   git commit -m "Release version 1.0.0"
   git tag -a v1.0.0 -m "Release version 1.0.0"
   ```

2. **Clean the workspace**:
   ```bash
   cd /path/to/files_archive
   
   # Remove build artifacts (they'll be regenerated)
   rm -rf js/
   rm -rf node_modules/
   rm -rf vendor/
   ```

### Step 3: Build the App

1. **Install PHP dependencies** (production only):
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

2. **Install and build frontend**:
   ```bash
   npm install
   npm run build
   ```

3. **Verify build output**:
   ```bash
   # Check that js/ directory exists with built files
   ls -la js/
   # Should see files like:
   # - files_archive-main.js
   # - files_archive-navigation.js
   # - files_archive-archive.js
   # - files_archive-archiveLink.js
   ```

### Step 4: Create Release Archive

1. **Copy app to a clean directory** (to avoid including unnecessary files):
   ```bash
   # Create temporary directory
   mkdir -p /tmp/files_archive-release
   cd /tmp/files_archive-release
   
   # Copy app files (exclude .git, node_modules, etc.)
   rsync -av \
     --exclude='.git' \
     --exclude='node_modules' \
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
     /path/to/files_archive/ ./files_archive/
   ```

2. **Or use Nextcloud's built-in packaging** (recommended):
   ```bash
   # On your Nextcloud server where the app is installed
   cd /var/www/html/apps/files_archive
   
   # Use Nextcloud's app:getpath to get the app directory
   # Then create archive manually or use the signing tool
   ```

### Step 5: Sign the App

1. **Sign the app using Nextcloud's `occ` command**:
   ```bash
   # On your Nextcloud server
   php /var/www/html/occ app:sign files_archive \
     --privateKey=~/.nextcloud/certificates/files_archive.key \
     --certificate=~/.nextcloud/certificates/files_archive.crt \
     --path=/tmp
   ```

   This will create a signed tarball like:
   ```
   /tmp/files_archive-1.0.0.tar.gz
   ```

2. **Verify the signature** (optional):
   ```bash
   tar -tzf /tmp/files_archive-1.0.0.tar.gz | grep signature.json
   # Should show: files_archive/signature.json
   ```

### Step 6: Upload to App Store

1. **Log in to Nextcloud App Store**:
   - Go to https://apps.nextcloud.com
   - Log in with your account

2. **Navigate to your app**:
   - Go to "My apps" → "files_archive"

3. **Create new release**:
   - Click "New Release" or "Upload Release"
   - Fill in:
     - **Version**: `1.0.0` (must match `info.xml`)
     - **Changelog**: Brief description of changes
     - **Supported Nextcloud versions**: Select based on your `info.xml` dependencies
   - **Upload the signed tarball**: `/tmp/files_archive-1.0.0.tar.gz`

4. **Submit for review**:
   - Review all information
   - Submit the release
   - Wait for approval (usually automatic for updates, manual review for new apps)

### Step 7: Post-Release

1. **Update GitHub/GitLab** (if using version control):
   ```bash
   git push origin main
   git push origin v1.0.0
   ```

2. **Create GitHub Release** (optional):
   - Go to your repository
   - Create a new release with tag `v1.0.0`
   - Upload the signed tarball as a release asset
   - Add release notes

3. **Update documentation** (if needed):
   - Update `README.md` with new features
   - Update `CHANGELOG.md` (if you maintain one)

## Quick Release Checklist

- [ ] Version numbers updated in `appinfo/info.xml`
- [ ] All changes committed and tagged
- [ ] Frontend built (`npm run build`)
- [ ] PHP dependencies installed (`composer install --no-dev`)
- [ ] App signed with `occ app:sign`
- [ ] Signed tarball uploaded to App Store
- [ ] Release notes added
- [ ] Git tags pushed (if using version control)

## Troubleshooting

### "App signing failed"

- Verify certificate paths are correct
- Ensure certificate and key files are readable
- Check that certificate hasn't expired

### "Build files missing"

- Run `npm run build` before signing
- Verify `js/` directory exists with built files
- Check `webpack.config.js` output path

### "App rejected by store"

- Check `appinfo/info.xml` for required fields
- Ensure all dependencies are correctly specified
- Verify app follows Nextcloud coding standards
- Run `php occ app:check-code files_archive` to check for issues

### "Certificate not found"

- Verify certificate files are in `~/.nextcloud/certificates/`
- Check file permissions (should be readable by your user)
- Ensure certificate hasn't been revoked on App Store

## Version Numbering

Follow [Semantic Versioning](https://semver.org/):
- **MAJOR** (1.0.0): Breaking changes
- **MINOR** (0.1.0): New features, backward compatible
- **PATCH** (0.0.1): Bug fixes, backward compatible

Example progression:
- `1.0.0` → `1.0.1` (bug fix)
- `1.0.1` → `1.1.0` (new feature)
- `1.1.0` → `2.0.0` (breaking change)

## Additional Resources

- [Nextcloud App Store Documentation](https://docs.nextcloud.com/server/latest/developer_manual/app_development/app_store.html)
- [App Signing Guide](https://docs.nextcloud.com/server/latest/developer_manual/app_development/app_signing.html)
- [App Store Guidelines](https://apps.nextcloud.com/developer/apps/guidelines)
