#!/usr/bin/env bash
#
# Persist Laravel storage across Forge zero-downtime releases.
#
# Why: MEDIA_DISK=local writes to storage/app/private. If each release keeps
# its own storage/, PDFs disappear on the next deploy (DB row remains, file 404).
#
# Paste this into the Forge Deploy Script AFTER composer install / npm, and
# BEFORE php artisan migrate / optimize / reload (while still inside the release):
#
#   bash scripts/forge-persist-storage.sh
#
# Or inline the body below. Site path defaults to the parent of /releases.
#
set -euo pipefail

RELEASE_DIR="$(pwd)"
SITE_DIR="$(cd "${RELEASE_DIR}/../.." && pwd)"

# Prefer Forge env when available
if [ -n "${FORGE_SITE_PATH:-}" ]; then
  SITE_DIR="${FORGE_SITE_PATH}"
fi

SHARED_STORAGE="${SITE_DIR}/storage"

mkdir -p \
  "${SHARED_STORAGE}/app/private" \
  "${SHARED_STORAGE}/app/public" \
  "${SHARED_STORAGE}/framework/cache/data" \
  "${SHARED_STORAGE}/framework/sessions" \
  "${SHARED_STORAGE}/framework/testing" \
  "${SHARED_STORAGE}/framework/views" \
  "${SHARED_STORAGE}/logs"

# If this release shipped a real storage/ tree (from git), merge any files first
if [ -d "${RELEASE_DIR}/storage" ] && [ ! -L "${RELEASE_DIR}/storage" ]; then
  if [ -d "${RELEASE_DIR}/storage/app/private" ]; then
    rsync -a "${RELEASE_DIR}/storage/app/private/" "${SHARED_STORAGE}/app/private/" || true
  fi
  if [ -d "${RELEASE_DIR}/storage/app/public" ]; then
    rsync -a "${RELEASE_DIR}/storage/app/public/" "${SHARED_STORAGE}/app/public/" || true
  fi
  rm -rf "${RELEASE_DIR}/storage"
fi

ln -sfn "${SHARED_STORAGE}" "${RELEASE_DIR}/storage"

echo "Linked ${RELEASE_DIR}/storage -> ${SHARED_STORAGE}"
