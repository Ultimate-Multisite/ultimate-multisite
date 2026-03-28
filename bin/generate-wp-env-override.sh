#!/bin/bash
# Generate .wp-env.override.json that includes discovered addons as plugins.
#
# Usage:
#   bin/generate-wp-env-override.sh [--dir <addons-dir>] [--addon <slug>] [--php <version>]
#
# Options:
#   --dir <path>      Addons directory (default: ../ultimate-multisite-addons)
#   --addon <slug>    Include only this specific addon (plus core)
#   --php <version>   Set PHP version in override (e.g., 8.1)
#
# Output: .wp-env.override.json in the repo root

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Defaults
ADDONS_DIR=""
SINGLE_ADDON=""
PHP_VERSION=""

parse_args() {
	while [[ $# -gt 0 ]]; do
		case "$1" in
		--dir)
			ADDONS_DIR="$2"
			shift 2
			;;
		--addon)
			SINGLE_ADDON="$2"
			shift 2
			;;
		--php)
			PHP_VERSION="$2"
			shift 2
			;;
		-h | --help)
			echo "Usage: $0 [--dir <addons-dir>] [--addon <slug>] [--php <version>]"
			exit 0
			;;
		*)
			echo "Unknown option: $1" >&2
			return 1
			;;
		esac
	done
	return 0
}

find_plugin_dirs() {
	local search_dir="$1"
	local dirs=""

	if [[ -n "$SINGLE_ADDON" ]]; then
		local addon_path="$search_dir/$SINGLE_ADDON"
		if [[ -d "$addon_path" ]]; then
			# Verify it contains a PHP file with Plugin Name header
			if grep -rl "Plugin Name:" "$addon_path"/*.php >/dev/null 2>&1; then
				local rel_path
				rel_path=$(python3 -c "import os.path; print(os.path.relpath('$addon_path', '$REPO_ROOT'))" 2>/dev/null || echo "../ultimate-multisite-addons/$SINGLE_ADDON")
				dirs="$rel_path"
			fi
		fi
	else
		for addon_dir in "$search_dir"/*/; do
			if [[ ! -d "$addon_dir" ]]; then
				continue
			fi
			# Check for a main plugin file with Plugin Name header
			if grep -rl "Plugin Name:" "$addon_dir"*.php >/dev/null 2>&1; then
				local rel_path
				rel_path=$(python3 -c "import os.path; print(os.path.relpath('${addon_dir%/}', '$REPO_ROOT'))" 2>/dev/null || echo "")
				if [[ -n "$rel_path" ]]; then
					if [[ -n "$dirs" ]]; then
						dirs="$dirs"$'\n'"$rel_path"
					else
						dirs="$rel_path"
					fi
				fi
			fi
		done
	fi

	echo "$dirs"
	return 0
}

generate_override() {
	local plugin_paths="$1"

	# Build the plugins JSON array: always starts with "." (core)
	local plugins_json='["."'
	while IFS= read -r path; do
		if [[ -n "$path" ]]; then
			plugins_json="$plugins_json, \"$path\""
		fi
	done <<<"$plugin_paths"
	plugins_json="$plugins_json]"

	# Build the full override JSON
	local override
	if [[ -n "$PHP_VERSION" ]]; then
		override=$(
			cat <<ENDJSON
{
  "config": {
    "phpVersion": "$PHP_VERSION"
  },
  "env": {
    "development": {
      "plugins": $plugins_json
    },
    "tests": {
      "plugins": $plugins_json
    }
  }
}
ENDJSON
		)
	else
		override=$(
			cat <<ENDJSON
{
  "env": {
    "development": {
      "plugins": $plugins_json
    },
    "tests": {
      "plugins": $plugins_json
    }
  }
}
ENDJSON
		)
	fi

	echo "$override"
	return 0
}

main() {
	parse_args "$@"

	if [[ -z "$ADDONS_DIR" ]]; then
		ADDONS_DIR="$(cd "$REPO_ROOT/.." && pwd)/ultimate-multisite-addons"
	fi

	if [[ ! -d "$ADDONS_DIR" ]]; then
		echo "Warning: Addons directory not found at $ADDONS_DIR" >&2
		echo "Run bin/clone-addons.sh first, or use --dir to specify the path." >&2
		# Generate override with just core
		generate_override "" >"$REPO_ROOT/.wp-env.override.json"
		echo "Generated .wp-env.override.json (core only)"
		return 0
	fi

	local plugin_dirs
	plugin_dirs=$(find_plugin_dirs "$ADDONS_DIR")

	local count=0
	if [[ -n "$plugin_dirs" ]]; then
		count=$(echo "$plugin_dirs" | wc -l | tr -d ' ')
	fi

	generate_override "$plugin_dirs" >"$REPO_ROOT/.wp-env.override.json"

	echo "Generated .wp-env.override.json with $count addon(s)"
	if [[ -n "$plugin_dirs" ]]; then
		echo "$plugin_dirs" | while IFS= read -r p; do
			echo "  + $p"
		done
	fi
	return 0
}

main "$@"
