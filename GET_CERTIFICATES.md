# How to Get Signing Certificates for Nextcloud App Store

This guide explains how to obtain signing certificates required to publish your app on the Nextcloud App Store.

## Prerequisites

- A Nextcloud App Store account (create one at https://apps.nextcloud.com)
- Your app registered on the App Store (see Step 1 below)

## Step-by-Step Process

### Step 1: Register Your App on the App Store

1. **Go to the Nextcloud App Store**:
   - Visit https://apps.nextcloud.com
   - Log in or create an account

2. **Register your app**:
   - Click on your profile/account menu
   - Go to "My apps" or "Developer"
   - Click "Register new app" or "Add app"
   - Fill in:
     - **App ID**: `files_archive` (must match `appinfo/info.xml`)
     - **App Name**: Files Archive
     - **Description**: Brief description of your app
     - **Category**: Tools or Files
   - Submit the registration

### Step 2: Generate Private Key and Certificate Signing Request (CSR)

On your local machine (or a secure server), generate both the private key and CSR in one command:

```bash
# Create directory for certificates
mkdir -p ~/.nextcloud/certificates
cd ~/.nextcloud/certificates

# Generate private key and CSR in one command
# Replace APP_ID with your app ID: files_archive
openssl req -nodes -newkey rsa:4096 \
  -keyout files_archive.key \
  -out files_archive.csr \
  -subj "/CN=files_archive"

# Set secure permissions on the private key
chmod 600 files_archive.key
```

**Important**: 
- Keep the private key (`files_archive.key`) secure and private. Never share it or commit it to version control.
- The Common Name (CN) in the CSR must be your app ID: `files_archive`

### Step 3: Submit CSR to Certificate Repository

1. **Go to the certificate repository** (link provided on the registration page)
2. **Follow the README** in that repository for instructions on submitting your CSR
3. **Post your CSR** to the repository (typically via a pull request or issue)

### Step 4: Obtain the Certificate

1. **After your CSR is processed**, you'll receive a certificate file
2. **Save the certificate** to `~/.nextcloud/certificates/files_archive.crt`
3. **The certificate should be in PEM format** (text format with `-----BEGIN CERTIFICATE-----` and `-----END CERTIFICATE-----`)

### Step 5: Generate Signature Over App ID

You need to generate a signature over your app ID using your private key:

```bash
# Generate signature (replace files_archive with your app ID)
echo -n "files_archive" | openssl dgst -sha512 -sign ~/.nextcloud/certificates/files_archive.key | openssl base64
```

**Copy the entire output** - this is your signature that you'll paste into the registration form.

### Step 6: Register on App Store

1. **Go to the registration page** on https://apps.nextcloud.com
2. **Fill in the form**:
   - **Public certificate**: Open `files_archive.crt` and copy the entire contents (including `-----BEGIN CERTIFICATE-----` and `-----END CERTIFICATE-----` lines) into the text area
   - **Signature over your app's ID**: Paste the signature you generated in Step 5
3. **Click "Register"**

**Important Warning**: The form states that "Updating an app certificate will delete all of its already uploaded releases!" - so be careful when updating existing certificates.

### Step 7: Verify Your Certificates

You should now have:
- `~/.nextcloud/certificates/files_archive.key` (private key - keep secret!)
- `~/.nextcloud/certificates/files_archive.crt` (certificate from certificate repository)

Verify they match:

```bash
# Check that the certificate matches the key
openssl x509 -noout -modulus -in files_archive.crt | openssl md5
openssl rsa -noout -modulus -in files_archive.key | openssl md5
```

Both commands should output the same MD5 hash. If they match, your certificate is valid.

### Step 8: Test Signing (Optional)

You can test the signing process:

```bash
# On your Nextcloud server
php /var/www/html/occ app:sign files_archive \
  --privateKey=~/.nextcloud/certificates/files_archive.key \
  --certificate=~/.nextcloud/certificates/files_archive.crt \
  --path=/tmp
```

## Security Best Practices

1. **Backup your private key**:
   ```bash
   # Create a secure backup
   tar -czf files_archive-certificates-backup.tar.gz \
     ~/.nextcloud/certificates/files_archive.*
   # Store this backup in a secure location (encrypted storage, password manager, etc.)
   ```

2. **Never commit certificates to git**:
   - Add to `.gitignore`:
     ```
     *.key
     *.crt
     *.csr
     certificates/
     .nextcloud/
     ```

3. **Use secure storage**:
   - Store certificates on an encrypted drive
   - Use a password manager for backups
   - Limit access to the certificate directory

4. **Rotate if compromised**:
   - If your private key is ever exposed, generate a new one immediately
   - Contact Nextcloud App Store support to revoke the old certificate

## Troubleshooting

### "Certificate not found" error

- Verify the certificate file exists: `ls -la ~/.nextcloud/certificates/`
- Check file permissions: `chmod 600 ~/.nextcloud/certificates/files_archive.key`
- Ensure the certificate matches the key (see Step 6)

### "Invalid certificate" error

- Verify the Common Name in the CSR matches your app ID exactly
- Ensure you downloaded the correct certificate from the App Store
- Check that the certificate hasn't expired

### "Permission denied" error

- Ensure the key file is readable: `chmod 600 files_archive.key`
- Check that you own the files: `chown $USER:$USER files_archive.*`

## Alternative: Using the Signing Script

Once you have the certificates, you can use the provided signing script:

```bash
# Make sure certificates are in the expected location
ls ~/.nextcloud/certificates/files_archive.*

# Run the signing script
./sign-release.sh files_archive-1.0.0.tar.gz
```

The script will automatically find the certificates if they're in:
- `~/.nextcloud/certificates/`
- `/root/.nextcloud/certificates/`
- `./certificates/` (current directory)
- Or specify with `CERT_DIR=/path/to/certs`

## Next Steps

After obtaining certificates:

1. **Test signing** a release archive
2. **Sign your first release** using the script or manually
3. **Upload to App Store** at https://apps.nextcloud.com

For the complete release process, see [RELEASE.md](RELEASE.md).
