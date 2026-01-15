#!/bin/bash

# SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

# Script to generate the signature required for App Store registration
# Usage: ./generate-signature.sh [app-id] [key-path]

set -e

APP_ID="${1:-time_archive}"
CERT_KEY="${2:-$HOME/.nextcloud/certificates/${APP_ID}.key}"

# Check if key exists
if [ ! -f "$CERT_KEY" ]; then
    echo "ERROR: Private key not found: $CERT_KEY"
    echo ""
    echo "Usage: $0 [app-id] [key-path]"
    echo ""
    echo "Examples:"
    echo "  $0                                    # Uses default: time_archive"
    echo "  $0 time_archive                     # Specify app ID"
    echo "  $0 time_archive /path/to/key.key    # Specify app ID and key path"
    exit 1
fi

# Generate signature
echo "Generating signature for app ID: $APP_ID"
echo "Using key: $CERT_KEY"
echo ""
echo "Signature (copy this to the registration form):"
echo "----------------------------------------"
echo -n "$APP_ID" | openssl dgst -sha512 -sign "$CERT_KEY" | openssl base64
echo "----------------------------------------"
echo ""
echo "Copy the entire signature above (between the lines) to the"
echo "'Signature over your app's ID' field in the registration form."
