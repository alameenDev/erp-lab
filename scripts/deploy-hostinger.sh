#!/usr/bin/env bash
set -euo pipefail
repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
php_bin=/opt/alt/php84/usr/bin/php
public_dir="${1:?Pass the full public_html path}"
site_url="${2:?Pass the HTTPS website URL}"
cd "$repo_dir"
git fetch origin hostinger-build
source_commit="$(git show origin/hostinger-build:SOURCE_COMMIT)"
if [[ "$source_commit" != "$(git rev-parse HEAD)" ]]; then
  echo 'The frontend build does not match this checkout. Wait for Build Hostinger frontend to succeed, then retry.'
  exit 1
fi
stage_dir="$(mktemp -d)"
trap 'rm -rf "$stage_dir"' EXIT
git archive origin/hostinger-build dist | tar -x -C "$stage_dir"
"$php_bin" scripts/publish-hostinger.php "$public_dir" "$site_url" "$stage_dir/dist"
cd backend
"$php_bin" artisan config:clear
echo "Published. Open $site_url and $site_url/up to verify web serving."
