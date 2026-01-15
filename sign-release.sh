#!/bin/bash

# SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

# Script to sign a files_archive release for Nextcloud App Store
# Usage: ./sign-release.sh <version> [archive-file]
# Example: ./sign-release.sh 1.0.0 files_archive-1.0.0.tar.gz

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
APP_NAME="files_archive"
DOCKER_CONTAINER="nextcloud_nextcloud_app"
CERT_DIR="$HOME/.nextcloud/certificates"
CERT_KEY="$CERT_DIR/${APP_NAME}.key"
CERT_CRT="$CERT_DIR/${APP_NAME}.crt"

# Functions
print_error() {
    echo -e "${RED}ERROR:${NC} $1" >&2
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_info() {
    echo -e "${NC}$1"
}

# Check arguments
if [ $# -lt 1 ]; then
    print_error "Usage: $0 <version> [archive-file]"
    echo ""
    echo "Or: $0 <archive-file>  (version will be extracted from filename)"
    echo ""
    echo "Examples:"
    echo "  $0 1.0.0                                    # Sign from GitHub release"
    echo "  $0 1.0.0 files_archive-1.0.0.tar.gz        # Sign local file"
    echo "  $0 files_archive-1.0.0.tar.gz              # Auto-detect version from filename"
    echo "  $0 files_archive-1.0.0.zip                 # Works with .zip files too"
    exit 1
fi

# Smart argument detection
if [ -f "$1" ]; then
    # First argument is a file - extract version from filename
    ARCHIVE_FILE="$1"
    FILENAME=$(basename "$ARCHIVE_FILE")
    
    # Try to extract version from filename (e.g., files_archive-1.0.0.tar.gz -> 1.0.0)
    if [[ "$FILENAME" =~ ${APP_NAME}-([0-9]+\.[0-9]+\.[0-9]+) ]]; then
        VERSION="${BASH_REMATCH[1]}"
        print_info "Auto-detected version: $VERSION from filename"
    else
        print_error "Could not extract version from filename: $FILENAME"
        echo ""
        echo "Please specify version explicitly:"
        echo "  $0 <version> $ARCHIVE_FILE"
        exit 1
    fi
else
    # First argument is version
    VERSION="$1"
    ARCHIVE_FILE="${2:-}"
    
    # Determine archive file
    if [ -z "$ARCHIVE_FILE" ]; then
        # Try to find archive in current directory (try both .tar.gz and .zip)
        for ext in tar.gz zip; do
            if [ -f "${APP_NAME}-${VERSION}.${ext}" ]; then
                ARCHIVE_FILE="${APP_NAME}-${VERSION}.${ext}"
                break
            fi
        done
        
        if [ -z "$ARCHIVE_FILE" ] || [ ! -f "$ARCHIVE_FILE" ]; then
            print_error "Archive file not found for version $VERSION"
            echo ""
            echo "Tried:"
            echo "  ${APP_NAME}-${VERSION}.tar.gz"
            echo "  ${APP_NAME}-${VERSION}.zip"
            echo ""
            echo "Please either:"
            echo "  1. Download the archive from GitHub Actions artifacts"
            echo "  2. Specify the archive file as second argument"
            echo "  3. Place the archive in the current directory"
            exit 1
        fi
    fi
fi

# Check if archive file exists
if [ ! -f "$ARCHIVE_FILE" ]; then
    print_error "Archive file not found: $ARCHIVE_FILE"
    exit 1
fi

print_info "=== Signing Files Archive Release ==="
print_info "Version: $VERSION"
print_info "Archive: $ARCHIVE_FILE"
echo ""

# Check if Docker container is running
if ! docker ps --format '{{.Names}}' | grep -q "^${DOCKER_CONTAINER}$"; then
    print_error "Docker container '$DOCKER_CONTAINER' is not running"
    echo ""
    echo "Please start your Nextcloud container first:"
    echo "  docker start $DOCKER_CONTAINER"
    exit 1
fi

print_success "Docker container found: $DOCKER_CONTAINER"

# Check if certificates exist
if [ ! -f "$CERT_KEY" ]; then
    print_error "Signing key not found: $CERT_KEY"
    echo ""
    echo "Please ensure your signing certificate is at:"
    echo "  $CERT_KEY"
    echo "  $CERT_CRT"
    exit 1
fi

if [ ! -f "$CERT_CRT" ]; then
    print_error "Signing certificate not found: $CERT_CRT"
    exit 1
fi

print_success "Signing certificates found"

# Determine archive extension and handle accordingly
ARCHIVE_EXT="${ARCHIVE_FILE##*.}"
ARCHIVE_BASE=$(basename "$ARCHIVE_FILE" ".${ARCHIVE_EXT}")

# Copy archive to container
print_info "Copying archive to container..."
if [ "$ARCHIVE_EXT" = "zip" ]; then
    # If it's a zip, we need to extract and re-tar it, or handle it differently
    print_warning "ZIP file detected. Converting to tar.gz..."
    
    # Extract zip to temp directory
    TEMP_DIR=$(mktemp -d)
    unzip -q "$ARCHIVE_FILE" -d "$TEMP_DIR"
    
    # Create tar.gz from extracted contents
    cd "$TEMP_DIR"
    tar -czf "/tmp/${APP_NAME}-${VERSION}.tar.gz" "${APP_NAME}" 2>/dev/null || tar -czf "/tmp/${APP_NAME}-${VERSION}.tar.gz" *
    cd - > /dev/null
    
    # Copy to container
    docker cp "/tmp/${APP_NAME}-${VERSION}.tar.gz" "${DOCKER_CONTAINER}:/tmp/${APP_NAME}-${VERSION}.tar.gz"
    rm -rf "$TEMP_DIR" "/tmp/${APP_NAME}-${VERSION}.tar.gz"
    print_success "ZIP converted and copied to container"
else
    # It's already a tar.gz
    docker cp "$ARCHIVE_FILE" "${DOCKER_CONTAINER}:/tmp/${APP_NAME}-${VERSION}.tar.gz"
    print_success "Archive copied to container"
fi

# Copy certificates to container
print_info "Copying certificates to container..."
docker exec -u root "${DOCKER_CONTAINER}" mkdir -p /root/.nextcloud/certificates
docker cp "$CERT_KEY" "${DOCKER_CONTAINER}:/root/.nextcloud/certificates/${APP_NAME}.key"
docker cp "$CERT_CRT" "${DOCKER_CONTAINER}:/root/.nextcloud/certificates/${APP_NAME}.crt"
docker exec -u root "${DOCKER_CONTAINER}" chmod 600 "/root/.nextcloud/certificates/${APP_NAME}.key"
print_success "Certificates copied to container"

# Extract archive in container
print_info "Extracting archive in container..."
docker exec -u www-data "${DOCKER_CONTAINER}" sh -c "
    cd /tmp
    rm -rf ${APP_NAME}
    tar -xzf ${APP_NAME}-${VERSION}.tar.gz
"
print_success "Archive extracted"

# Copy app to Nextcloud apps directory (temporary)
print_info "Installing app in container (temporary)..."
docker exec -u root "${DOCKER_CONTAINER}" sh -c "
    rm -rf /var/www/html/apps/${APP_NAME}
    cp -r /tmp/${APP_NAME}/${APP_NAME} /var/www/html/apps/
    chown -R www-data:www-data /var/www/html/apps/${APP_NAME}
"
print_success "App installed in container"

# Sign the app
print_info "Signing app..."
SIGN_OUTPUT=$(docker exec -u www-data "${DOCKER_CONTAINER}" php /var/www/html/occ app:sign "${APP_NAME}" \
    --privateKey="/root/.nextcloud/certificates/${APP_NAME}.key" \
    --certificate="/root/.nextcloud/certificates/${APP_NAME}.crt" \
    --path="/tmp" 2>&1)

if [ $? -eq 0 ]; then
    print_success "App signed successfully"
    echo "$SIGN_OUTPUT"
else
    print_error "Signing failed"
    echo "$SIGN_OUTPUT"
    exit 1
fi

# Find the signed archive
SIGNED_ARCHIVE=$(docker exec "${DOCKER_CONTAINER}" find /tmp -name "${APP_NAME}-${VERSION}.tar.gz" -type f | head -1)

if [ -z "$SIGNED_ARCHIVE" ]; then
    print_error "Signed archive not found in container"
    exit 1
fi

# Copy signed archive back
SIGNED_OUTPUT="${APP_NAME}-${VERSION}-signed.tar.gz"
print_info "Copying signed archive from container..."
docker cp "${DOCKER_CONTAINER}:${SIGNED_ARCHIVE}" "$SIGNED_OUTPUT"
print_success "Signed archive copied: $SIGNED_OUTPUT"

# Verify signature
print_info "Verifying signature..."
if tar -tzf "$SIGNED_OUTPUT" | grep -q "signature.json"; then
    print_success "Signature verified: signature.json found in archive"
else
    print_warning "signature.json not found - signing may have failed"
fi

# Cleanup
print_info "Cleaning up..."
docker exec -u root "${DOCKER_CONTAINER}" sh -c "
    rm -rf /tmp/${APP_NAME}
    rm -f /tmp/${APP_NAME}-${VERSION}.tar.gz
    rm -rf /var/www/html/apps/${APP_NAME}
    rm -f /root/.nextcloud/certificates/${APP_NAME}.key
    rm -f /root/.nextcloud/certificates/${APP_NAME}.crt
"
print_success "Cleanup completed"

echo ""
print_info "=== Signing Complete ==="
print_success "Signed archive: $SIGNED_OUTPUT"
echo ""
print_info "Next steps:"
echo "  1. Upload $SIGNED_OUTPUT to https://apps.nextcloud.com"
echo "  2. Go to your app → New Release"
echo "  3. Upload the signed tarball"
echo "  4. Fill in version ($VERSION) and release notes"
echo ""
