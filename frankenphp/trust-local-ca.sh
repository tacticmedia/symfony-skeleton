#!/bin/sh
# Development only; the production image never copies this file.
#
# Chromium reads the NSS database rather than the system bundle, and Caddy stops once
# the CA is trusted by the system, so the browser running the e2e journeys is never
# told about it and every HTTPS page fails with NET::ERR_CERT_AUTHORITY_INVALID.
#
# Caddy creates the authority as it starts, which is after this runs: on a fresh
# checkout the certificate is therefore trusted from the second container start.
set -e

ROOT_CERT=/data/caddy/pki/authorities/local/root.crt
NICKNAME='Local Development CA'

[ -f "$ROOT_CERT" ] || exit 0

certutil -d sql:/root/.pki/nssdb -D -n "$NICKNAME" 2>/dev/null || true
certutil -d sql:/root/.pki/nssdb -A -t 'C,,' -n "$NICKNAME" -i "$ROOT_CERT"
