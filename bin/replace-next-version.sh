#!/usr/bin/env bash
#
# Replace the "n.e.x.t" version placeholder with a release version.
#
# New code is tagged with `@since n.e.x.t` (and `@deprecated n.e.x.t`) instead
# of guessing the next version number. At release time, this script rewrites
# every placeholder to the real version, following the WordPress Performance
# Lab convention (WordPress/performance bin/plugin/commands/since.js).
#
# Replaced in tracked PHP and JS files (vendored code, tests, bin/ and scripts/
# are skipped):
#   @since n.e.x.t         -> @since 2.20.0
#   @deprecated n.e.x.t    -> @deprecated 2.20.0
#   'n.e.x.t'              -> '2.20.0'   e.g. _deprecated_function( __METHOD__, 'n.e.x.t' )
# And in readme.txt:
#   Version [n.e.x.t] - Released on ...  -> Version [2.20.0] - Released on ...
#
# Usage:
#   bin/replace-next-version.sh 2.20.0   Replace placeholders with 2.20.0
#   bin/replace-next-version.sh --check  List remaining placeholders; exit 1 if any

set -euo pipefail

ROOT_DIR=$(cd "$(dirname "$0")/.." && pwd)

# Tracked files that may carry placeholders. Excludes vendored code, tests and
# the release tooling itself (which documents the placeholder).
PATHSPECS=(
	'*.php'
	'*.js'
	'readme.txt'
	':(exclude)vendor'
	':(exclude)node_modules'
	':(exclude)assets/js/lib'
	':(exclude)inc/site-exporter/mu-migration'
	':(exclude)bin'
	':(exclude)scripts'
	':(exclude)tests'
)

# Extended regex matching any placeholder this script replaces.
PLACEHOLDER_REGEX="@(since|deprecated)[[:space:]]+n\.e\.x\.t|'[^']*n\.e\.x\.t'|^Version \[n\.e\.x\.t\]"

usage() {
	echo "Usage: $(basename "$0") <version>   Replace n.e.x.t placeholders with <version>"
	echo "       $(basename "$0") --check     List remaining n.e.x.t placeholders"
	return 0
}

check_placeholders() {
	local matches

	matches=$(git -C "$ROOT_DIR" grep -n -E "$PLACEHOLDER_REGEX" -- "${PATHSPECS[@]}" || true)

	if [[ -n "$matches" ]]; then
		echo "Error: unreplaced n.e.x.t version placeholders found:"
		echo "$matches"
		echo "Run: bin/replace-next-version.sh <version>"
		return 1
	fi

	echo "No n.e.x.t version placeholders found."
	return 0
}

replace_in_file() {
	local file="$1"
	local version="$2"

	if [[ "$(basename "$file")" == "readme.txt" ]]; then
		sed -i.bak -E "s/^Version \[n\.e\.x\.t\]/Version [$version]/" "$file"
	else
		sed -i.bak -E \
			-e "s/(@(since|deprecated)[[:space:]]+)n\.e\.x\.t/\\1$version/g" \
			-e "s/('[^']*)n\.e\.x\.t'/\\1$version'/g" \
			"$file"
	fi

	rm -f "$file.bak"
	return 0
}

replace_placeholders() {
	local version="$1"
	local count=0
	local file

	while IFS= read -r file; do
		[[ -z "$file" ]] && continue
		replace_in_file "$ROOT_DIR/$file" "$version"
		echo "  updated $file"
		count=$((count + 1))
	done < <(git -C "$ROOT_DIR" grep -l -E "$PLACEHOLDER_REGEX" -- "${PATHSPECS[@]}" || true)

	echo "Replaced n.e.x.t with $version in $count file(s)."

	# Anything left is a placeholder form the patterns above do not cover.
	check_placeholders
	return $?
}

main() {
	local arg="${1:-}"

	case "$arg" in
		--check)
			check_placeholders
			return $?
			;;
		'' | -h | --help)
			usage
			[[ -n "$arg" ]] && return 0
			return 1
			;;
	esac

	if [[ ! "$arg" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-(alpha|beta|rc|dev|preview)\.[0-9]+)?$ ]]; then
		echo "Error: version must be in form X.Y.Z or X.Y.Z-beta.N (got '$arg')"
		return 1
	fi

	replace_placeholders "$arg"
	return $?
}

main "$@"
