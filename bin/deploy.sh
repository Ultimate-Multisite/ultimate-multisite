#!/usr/bin/env bash

set -euo pipefail

# Deploy script for Ultimate Multisite
# Usage: bin/deploy.sh 2.4.6          (stable release)
#        bin/deploy.sh 2.4.6-beta.1   (pre-release)
#
# This script:
# 1) Updates versions in plugin header, WP_Ultimo::VERSION, composer.json, package.json
#    (and readme.txt Stable tag for stable releases only)
# 2) For stable releases: ensures changelog entry exists; replaces XX-XX date; syncs to README.md
#    For pre-releases: skips changelog sync
# 3) Commits and pushes to main
# 4) Runs npm build to generate ultimate-multisite.zip
# 5) Tags and creates a GitHub release, attaching the ZIP and including changelog + PR log
# 6) For stable releases only: deploys to WordPress.org SVN trunk and tags/<version>
#
# Pre-release suffixes: alpha, beta, rc, dev, preview (e.g. 2.4.6-beta.1)
#
# Requirements:
# - Run from repository root or anywhere; script will cd into ultimate-multisite directory automatically
# - Tools: git, npm, sed, awk, unzip, rsync, svn, gh (GitHub CLI) or env GITHUB_TOKEN for gh
# - Git remote 'origin' configured and write access
# - Environment variables for release/SVN:
#   - GH_TOKEN or GITHUB_TOKEN (for gh)
#   - WPORG_USERNAME and WPORG_PASSWORD (for svn if using --username/--password)
#   - WPORG_SLUG (defaults to 'ultimate-multisite')
#

ROOT_DIR=$(cd "$(dirname "$0")/.." && pwd)
PLUGIN_DIR="$ROOT_DIR"

# If script is invoked from the monorepo root, adjust PLUGIN_DIR
if [[ -f "$ROOT_DIR/package.json" && -f "$ROOT_DIR/ultimate-multisite.php" ]]; then
  # already in plugin dir
  :
elif [[ -d "$ROOT_DIR/ultimate-multisite" && -f "$ROOT_DIR/ultimate-multisite/ultimate-multisite.php" ]]; then
  PLUGIN_DIR="$ROOT_DIR/ultimate-multisite"
else
  echo "Error: Could not locate ultimate-multisite plugin directory."
  exit 1
fi

cd "$PLUGIN_DIR"

VERSION="${1:-}"
if [[ -z "$VERSION" ]]; then
  echo "Usage: $(basename "$0") <version>"
  echo "       $(basename "$0") <version>-beta.1"
  echo ""
  echo "Pre-release versions (alpha, beta, rc, dev, preview) skip:"
  echo "  - readme.txt Stable tag update"
  echo "  - Changelog date replacement and README.md sync"
  echo "  - WordPress.org SVN deployment"
  exit 1
fi

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-(alpha|beta|rc|dev|preview)\.[0-9]+)?$ ]]; then
  echo "Error: Version must be in form X.Y.Z or X.Y.Z-beta.N (e.g., 2.4.6 or 2.4.6-beta.1)"
  exit 1
fi

# Detect pre-release versions
IS_PRERELEASE=false
if [[ "$VERSION" =~ -(alpha|beta|rc|dev|preview)\. ]]; then
  IS_PRERELEASE=true
  echo "==> Pre-release detected: $VERSION"
  echo "    Skipping: readme.txt Stable tag, changelog sync, WordPress.org SVN"
fi

DATE_TODAY=$(date +%Y-%m-%d)
SVN_SLUG=${WPORG_SLUG:-ultimate-multisite}
ZIP_NAME="ultimate-multisite.zip"
ZIP_PATH="$PLUGIN_DIR/$ZIP_NAME"

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || { echo "Error: '$1' is required."; exit 1; }
}

echo "==> Checking required tools"
require_cmd git
require_cmd sed
require_cmd awk
require_cmd npm
require_cmd unzip
require_cmd rsync
require_cmd svn

# 'gh' is optional – only needed for GitHub release creation
if ! command -v gh >/dev/null 2>&1; then
  echo "Warning: 'gh' CLI not found. Will attempt to use it if available; otherwise, please create the release manually."
fi

ensure_clean_git() {
  if [[ -n $(git status --porcelain) ]]; then
    echo "Error: Working tree is not clean. Commit or stash changes before deploying."
    git status --porcelain
    exit 1
  fi
  CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
  if [[ "$CURRENT_BRANCH" != "main" ]]; then
    echo "Error: Must be on 'main' branch (current: $CURRENT_BRANCH)"
    exit 1
  fi
}

update_versions() {
  echo "==> Updating versions to $VERSION"

  # readme.txt Stable tag (skip for pre-releases)
  if [[ "$IS_PRERELEASE" == "false" ]]; then
    sed -i.bak -E "s/^(Stable tag:[[:space:]]*).*/\\1$VERSION/" readme.txt
    rm -f readme.txt.bak
  else
    echo "    Skipping readme.txt Stable tag (pre-release)"
  fi

  # ultimate-multisite.php header Version and @version
  sed -i.bak -E "s/^( \* Version:[[:space:]]*).*/\\1$VERSION/" ultimate-multisite.php
  sed -i.bak -E "s/^( \* @version).*/\\1 $VERSION/" ultimate-multisite.php

  # WP_Ultimo const VERSION
  sed -i.bak -E "s/(const VERSION = ')[^']+(';)/\\1$VERSION\\2/" inc/class-wp-ultimo.php

  # composer.json version
  sed -i.bak -E '0,/"version"[[:space:]]*:[[:space:]]*"[^"]+"/ s//"version": '"\"$VERSION\""'/' composer.json

  # package.json version
  sed -i.bak -E '0,/"version"[[:space:]]*:[[:space:]]*"[^"]+"/ s//"version": '"\"$VERSION\""'/' package.json

  rm -f readme.txt.bak ultimate-multisite.php.bak inc/class-wp-ultimo.php.bak composer.json.bak package.json.bak
}

sync_changelog_and_date() {
  if [[ "$IS_PRERELEASE" == "true" ]]; then
    echo "==> Skipping changelog sync (pre-release)"
    return
  fi

  echo "==> Updating changelog date and syncing to README.md"

  # Ensure changelog entry exists and replace XX-XX with today's date
  if ! grep -q "^Version \[$VERSION\] - Released on" readme.txt; then
    echo "Error: Changelog entry for $VERSION not found in readme.txt"
    exit 1
  fi

  # Replace any placeholders like YYYY-MM-XX or XXXX-XX-XX ending with XX with today
  sed -i.bak -E "s/^(Version \[$VERSION\] - Released on )([0-9]{4}-[0-9]{2}-)XX/\\1$DATE_TODAY/" readme.txt
  sed -i.bak -E "s/^(Version \[$VERSION\] - Released on )([0-9]{4}-)XX-XX/\\1$DATE_TODAY/" readme.txt

  rm -f readme.txt.bak

  # Extract changelog block for version from readme.txt
  CHANGELOG_BLOCK=$(awk -v ver="$VERSION" '
    /^== Changelog ==/ { inlog=1; next }
    inlog && $0 ~ ("^Version [[]" ver "[]] - Released on") { on=1; print; next }
    inlog && on {
      if ($0 ~ /^Version [[]/) exit
      print
    }
  ' readme.txt)

  if [[ -z "$CHANGELOG_BLOCK" ]]; then
    echo "Error: Failed to extract changelog block for $VERSION"
    exit 1
  fi

  # Normalize to README.md format (prefix header with ### )
  CHANGELOG_MD=$(echo "$CHANGELOG_BLOCK" | awk 'NR==1{print "### "$0; next} {print}')

  # Insert into README.md under "## 📝 Recent Changes" if not already present
  if grep -q "^### Version \[$VERSION\]" README.md; then
    echo "README.md already contains changelog for $VERSION; updating header date if needed."
    # Update the header line date
    sed -i.bak -E "s|^### Version \[$VERSION\] - Released on .*|### Version [$VERSION] - Released on $DATE_TODAY|" README.md
    rm -f README.md.bak
  else
    awk -v block="$CHANGELOG_MD\n" '
      BEGIN { inserted=0 }
      /^## 📝 Recent Changes/ { print; print ""; printf "%s", block; inserted=1; next }
      { print }
      END { if (!inserted) exit 1 }
    ' README.md > README.md.tmp || {
      echo "Error: Could not find '## 📝 Recent Changes' section in README.md"
      exit 1
    }
    mv README.md.tmp README.md
  fi
}

commit_and_push() {
  echo "==> Committing and pushing changes to main"
  if [[ "$IS_PRERELEASE" == "true" ]]; then
    git add ultimate-multisite.php inc/class-wp-ultimo.php composer.json package.json
  else
    git add readme.txt README.md ultimate-multisite.php inc/class-wp-ultimo.php composer.json package.json
  fi
  git commit -m "chore(release): v$VERSION" || true
  git push origin main
}

build_zip() {
  echo "==> Building plugin ZIP"
  # Ensure dev dependencies (like wp-cli) exist before prebuild (makepot)
  composer install --no-interaction >/dev/null 2>&1 || composer install --no-interaction
  MU_CLIENT_ID="${MU_CLIENT_ID:-dummy}" MU_CLIENT_SECRET="${MU_CLIENT_SECRET:-dummy}" npm run build
  if [[ ! -f "$ZIP_PATH" ]]; then
    echo "Error: Build did not produce $ZIP_PATH"
    exit 1
  fi
}

previous_tag() {
  git describe --tags --abbrev=0 2>/dev/null || true
}

generate_release_notes() {
  echo "==> Generating release notes"

  local prev
  prev=$(previous_tag)

  # Extract markdown changelog block already prepared earlier
  # nothing; we generate notes from block below

  # Gather PR log
  local pr_log
  if [[ -n "$prev" ]]; then
    # Try to collect merged PRs between prev and HEAD
    pr_log=$(git log --pretty='%s' "$prev"..HEAD | grep -Eo '#[0-9]+' | sort -u | sed 's/^/- PR /') || true
    if [[ -z "$pr_log" ]]; then
      pr_log=$(git log --merges --pretty='- %s (%h)' "$prev"..HEAD || true)
    fi
  else
    pr_log="(no previous tag found)"
  fi

  RELEASE_NOTES_FILE=$(mktemp)
  # For pre-releases, use the base version (X.Y.Z) to find changelog entries
  local changelog_ver="$VERSION"
  if [[ "$IS_PRERELEASE" == "true" ]]; then
    changelog_ver="${VERSION%%-*}"
  fi

  {
    echo "## Release v$VERSION"
    echo
    echo "### Highlights"
    # Take the bullet lines from the readme.txt changelog block
    awk -v ver="$changelog_ver" '
      $0 ~ ("^Version [[]" ver "[]] - Released on") { on=1; next }
      on {
        if ($0 ~ /^Version [[]/) exit
        if (NF==0) next
        print
      }
    ' readme.txt
    echo
    echo "### Pull Requests"
    if [[ -n "$prev" ]]; then
      echo "From $prev to v$VERSION"
    fi
    if [[ -n "$pr_log" ]]; then
      echo "$pr_log"
    else
      echo "(no PRs detected)"
    fi
  } > "$RELEASE_NOTES_FILE"

  echo "Release notes prepared at $RELEASE_NOTES_FILE"
}

tag_and_github_release() {
  echo "==> Tagging repo and creating GitHub release"
  if git rev-parse -q --verify "refs/tags/v$VERSION" >/dev/null; then
    echo "Tag v$VERSION already exists; skipping tag creation."
  else
    git tag "v$VERSION"
  fi
  git push origin "v$VERSION" || true

  if command -v gh >/dev/null 2>&1 && [[ -n "${GH_TOKEN:-${GITHUB_TOKEN:-}}" ]]; then
    export GH_TOKEN=${GH_TOKEN:-${GITHUB_TOKEN:-}}
    if gh release view "v$VERSION" >/dev/null 2>&1; then
      echo "GitHub release v$VERSION exists; updating notes and asset."
      gh release edit "v$VERSION" --notes-file "$RELEASE_NOTES_FILE" || true
      gh release upload "v$VERSION" "$ZIP_PATH" --clobber || true
    else
      local prerelease_flag=""
      if [[ "$IS_PRERELEASE" == "true" ]]; then
        prerelease_flag="--prerelease"
      fi
      gh release create "v$VERSION" "$ZIP_PATH" \
        --title "v$VERSION" \
        --notes-file "$RELEASE_NOTES_FILE" \
        $prerelease_flag
    fi
  else
    echo "Skipping GitHub release step: 'gh' missing or GH_TOKEN not set."
  fi
}

deploy_to_wporg_svn() {
  echo "==> Deploying to WordPress.org SVN for slug '$SVN_SLUG'"

  local tmpdir
  tmpdir=$(mktemp -d)
  local svn_dir="$tmpdir/$SVN_SLUG"
  local unzip_dir="$tmpdir/unzip"

  mkdir -p "$svn_dir" "$unzip_dir"
  echo "Checking out SVN repo..."
  svn checkout "https://plugins.svn.wordpress.org/$SVN_SLUG" "$svn_dir"

  echo "Unpacking ZIP..."
  unzip -q "$ZIP_PATH" -d "$unzip_dir"
  # The zip contains a top-level directory (expected to be the slug)
  local src_dir
  src_dir=$(find "$unzip_dir" -maxdepth 1 -mindepth 1 -type d | head -n1)
  if [[ -z "$src_dir" ]]; then
    echo "Error: Could not find unpacked directory inside ZIP."
    exit 1
  fi

  echo "Syncing files to trunk..."
  mkdir -p "$svn_dir/trunk"
  # Remove everything in trunk except .svn metadata, then copy new files
  find "$svn_dir/trunk" -mindepth 1 -not -path '*/.svn*' -exec rm -rf {} + 2>/dev/null || true
  rsync -a --delete --exclude='.svn' "$src_dir/" "$svn_dir/trunk/"

  # Add new files and remove deleted ones
  (cd "$svn_dir" && { svn add --force trunk/* >/dev/null 2>&1 || true; })
  (cd "$svn_dir" && svn status | awk '/^!/ {print $2}' | xargs -r svn rm)

  echo "Committing trunk..."
  if [[ -n "${WPORG_USERNAME:-}" && -n "${WPORG_PASSWORD:-}" ]]; then
    svn commit "$svn_dir/trunk" -m "Release $VERSION" --username "$WPORG_USERNAME" --password "$WPORG_PASSWORD" --non-interactive || true
  else
    svn commit "$svn_dir/trunk" -m "Release $VERSION" --non-interactive || true
  fi

  echo "Tagging $VERSION..."
  (cd "$svn_dir" && svn copy "trunk" "tags/$VERSION")
  if [[ -n "${WPORG_USERNAME:-}" && -n "${WPORG_PASSWORD:-}" ]]; then
    (cd "$svn_dir" && svn commit -m "Tagging version $VERSION" --username "$WPORG_USERNAME" --password "$WPORG_PASSWORD" --non-interactive) || true
  else
    (cd "$svn_dir" && svn commit -m "Tagging version $VERSION" --non-interactive) || true
  fi

  echo "SVN deployment complete."
}

main() {
  ensure_clean_git
  update_versions
  sync_changelog_and_date
  commit_and_push
  build_zip
  generate_release_notes
  tag_and_github_release
  if [[ "$IS_PRERELEASE" == "false" ]]; then
    deploy_to_wporg_svn
  else
    echo "==> Skipping WordPress.org SVN deployment (pre-release)"
  fi
  echo ""
  echo "✅ Deployment of v$VERSION completed."
}

main "$@"
