#!/bin/bash
# Clone or update all Ultimate Multisite addons into a sibling directory.
#
# Usage:
#   bin/clone-addons.sh [--active-only] [--dir <path>] [--addon <slug>]
#
# Options:
#   --active-only   Only clone addons marked "active": true in addons.json
#   --dir <path>    Target directory (default: ../ultimate-multisite-addons)
#   --addon <slug>  Clone/update a single addon by slug
#
# Requires: gh (GitHub CLI), jq

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
MANIFEST="$REPO_ROOT/addons.json"
ORG="Ultimate-Multisite"

# Defaults
ADDONS_DIR=""
ACTIVE_ONLY=false
SINGLE_ADDON=""

usage() {
	echo "Usage: $0 [--active-only] [--dir <path>] [--addon <slug>]"
	echo ""
	echo "Options:"
	echo "  --active-only   Only clone addons marked active in addons.json"
	echo "  --dir <path>    Target directory (default: ../ultimate-multisite-addons)"
	echo "  --addon <slug>  Clone/update a single addon by slug"
	return 0
}

parse_args() {
	while [[ $# -gt 0 ]]; do
		case "$1" in
		--active-only)
			ACTIVE_ONLY=true
			shift
			;;
		--dir)
			ADDONS_DIR="$2"
			shift 2
			;;
		--addon)
			SINGLE_ADDON="$2"
			shift 2
			;;
		-h | --help)
			usage
			exit 0
			;;
		*)
			echo "Unknown option: $1" >&2
			usage >&2
			return 1
			;;
		esac
	done
	return 0
}

check_dependencies() {
	local missing=false
	if ! command -v gh >/dev/null 2>&1; then
		echo "Error: gh (GitHub CLI) is required but not installed." >&2
		missing=true
	fi
	if ! command -v jq >/dev/null 2>&1; then
		echo "Error: jq is required but not installed." >&2
		missing=true
	fi
	if [[ "$missing" == "true" ]]; then
		return 1
	fi
	return 0
}

get_addon_slugs() {
	local filter='.addons[].slug'
	if [[ "$ACTIVE_ONLY" == "true" ]]; then
		filter='.addons[] | select(.active == true) | .slug'
	fi
	if [[ -n "$SINGLE_ADDON" ]]; then
		filter=".addons[] | select(.slug == \"$SINGLE_ADDON\") | .slug"
	fi
	jq -r "$filter" "$MANIFEST"
}

clone_or_update() {
	local slug="$1"
	local target="$ADDONS_DIR/$slug"

	if [[ -d "$target/.git" ]]; then
		echo "  Updating $slug..."
		git -C "$target" fetch --quiet origin 2>/dev/null || true
		local default_branch
		default_branch=$(git -C "$target" symbolic-ref refs/remotes/origin/HEAD 2>/dev/null | sed 's|refs/remotes/origin/||' || echo "main")
		local current_branch
		current_branch=$(git -C "$target" branch --show-current 2>/dev/null || echo "")
		if [[ "$current_branch" == "$default_branch" ]]; then
			git -C "$target" pull --quiet --ff-only origin "$default_branch" 2>/dev/null || true
		fi
		echo "  OK: $slug (existing)"
	else
		echo "  Cloning $slug..."
		if gh repo clone "$ORG/$slug" "$target" -- --quiet 2>/dev/null; then
			echo "  OK: $slug (cloned)"
		else
			echo "  SKIP: $slug (clone failed — repo may be empty)" >&2
		fi
	fi
	return 0
}

main() {
	parse_args "$@"
	check_dependencies

	if [[ -z "$ADDONS_DIR" ]]; then
		ADDONS_DIR="$(cd "$REPO_ROOT/.." && pwd)/ultimate-multisite-addons"
	fi

	if [[ ! -f "$MANIFEST" ]]; then
		echo "Error: Addon manifest not found at $MANIFEST" >&2
		return 1
	fi

	echo "Addon directory: $ADDONS_DIR"
	mkdir -p "$ADDONS_DIR"

	local slugs
	slugs=$(get_addon_slugs)

	if [[ -z "$slugs" ]]; then
		echo "No addons found matching criteria."
		return 0
	fi

	local count=0
	local total
	total=$(echo "$slugs" | wc -l | tr -d ' ')
	echo "Processing $total addon(s)..."
	echo ""

	while IFS= read -r slug; do
		count=$((count + 1))
		echo "[$count/$total] $slug"
		clone_or_update "$slug"
	done <<<"$slugs"

	echo ""
	echo "Done. $count addon(s) processed in $ADDONS_DIR"
	return 0
}

main "$@"
