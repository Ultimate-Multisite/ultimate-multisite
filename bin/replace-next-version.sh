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
#   bin/replace-next-version.sh 2.20.0   Replace placeholders with 2.20.0 (stable versions only)
#   bin/replace-next-version.sh --check  List remaining placeholders; exit 1 if any
#   bin/replace-next-version.sh --check-guessed [<base-ref>] [--allow X.Y.Z]
#       List numeric @since/@deprecated tags added since <base-ref> (default: latest
#       v* tag) whose version is not an existing release tag or an --allow version;
#       exit 1 if any. Catches guessed versions on unreleased code.

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
	echo "       $(basename "$0") --check-guessed [<base-ref>] [--allow X.Y.Z]..."
	echo "                                    List numeric @since/@deprecated tags added since <base-ref>"
	echo "                                    (default: latest v* tag) that are not release tags"
	return 0
}

# Runs git grep over PATHSPECS. Exit 1 means "no match" and counts as success;
# any other failure (unreadable index, bad pathspec) is propagated so the
# release gate never passes an incomplete scan.
grep_placeholders() {
	local mode="$1"
	local status=0

	git -C "$ROOT_DIR" grep "$mode" -E "$PLACEHOLDER_REGEX" -- "${PATHSPECS[@]}" || status=$?

	if [[ $status -gt 1 ]]; then
		echo "Error: git grep failed with exit code $status" >&2
		return 1
	fi

	return 0
}

check_placeholders() {
	local matches

	matches=$(grep_placeholders -n) || return 1

	if [[ -n "$matches" ]]; then
		echo "Error: unreplaced n.e.x.t version placeholders found:"
		echo "$matches"
		echo "Run: bin/replace-next-version.sh <version>"
		return 1
	fi

	echo "No n.e.x.t version placeholders found."
	return 0
}

# Prints the latest stable release tag (vX.Y.Z), or nothing when none exists.
latest_release_tag() {
	git -C "$ROOT_DIR" tag -l 'v*' --sort=-v:refname | grep -E -m1 '^v[0-9]+\.[0-9]+\.[0-9]+$' || true
	return 0
}

# Lists @since / @deprecated tags with a numeric version on lines added since
# <base-ref> whose version is neither an existing release tag nor allowed via
# --allow. Guessed versions ship wrong; unreleased code must use n.e.x.t.
# Output lines: file:line: text. Exit 1 when any are found.
check_guessed() {
	local base="$1"
	shift
	local allowed=("$@")
	local found=0
	local diff_output
	local file lineno version text

	if [[ -z "$base" ]]; then
		base=$(latest_release_tag)
	fi

	if [[ -z "$base" ]] || ! git -C "$ROOT_DIR" rev-parse --verify --quiet "$base^{commit}" >/dev/null; then
		echo "Error: base ref '${base:-<none>}' not found; pass one: --check-guessed <base-ref>" >&2
		return 2
	fi

	diff_output=$(git -C "$ROOT_DIR" diff --unified=0 --no-color --no-ext-diff "$base" -- "${PATHSPECS[@]}") || return 2

	while IFS=$'\t' read -r file lineno version text; do
		[[ -z "$file" ]] && continue

		if [[ -n "$(git -C "$ROOT_DIR" tag -l "v$version")" ]]; then
			continue
		fi

		if [[ ${#allowed[@]} -gt 0 ]] && printf '%s\n' "${allowed[@]}" | grep -qxF "$version"; then
			continue
		fi

		if [[ $found -eq 0 ]]; then
			echo "Guessed @since/@deprecated versions (not a release tag) added since $base:"
		fi
		echo "$file:$lineno: $text"
		found=$((found + 1))
	done < <(awk '
		/^\+\+\+ / { file = ($2 == "/dev/null") ? "" : substr($2, 3); next }
		/^@@ / {
			split($3, parts, ",")
			line = substr(parts[1], 2) + 0
			next
		}
		/^\+/ && file != "" {
			text = substr($0, 2)
			rest = text
			while (match(rest, /@(since|deprecated)[ \t]+v?[0-9]+(\.[0-9]+)+/)) {
				tag = substr(rest, RSTART, RLENGTH)
				sub(/^@(since|deprecated)[ \t]+v?/, "", tag)
				gsub(/\t/, " ", text)
				printf "%s\t%d\t%s\t%s\n", file, line, tag, text
				rest = substr(rest, RSTART + RLENGTH)
			}
			line++
		}
	' <<<"$diff_output")

	if [[ $found -gt 0 ]]; then
		echo "Use n.e.x.t for unreleased code (see CONTRIBUTING.md), or correct the version."
		return 1
	fi

	echo "No guessed @since/@deprecated versions found since $base."
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
	local files
	local file

	files=$(grep_placeholders -l) || return 1

	while IFS= read -r file; do
		[[ -z "$file" ]] && continue
		replace_in_file "$ROOT_DIR/$file" "$version"
		echo "  updated $file"
		count=$((count + 1))
	done <<<"$files"

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
		--check-guessed)
			shift
			local base=""
			local allow=()
			if [[ $# -gt 0 && "$1" != --* ]]; then
				base="$1"
				shift
			fi
			while [[ $# -gt 0 ]]; do
				if [[ "$1" == "--allow" && $# -ge 2 ]]; then
					allow+=("$2")
					shift 2
				else
					echo "Error: unexpected argument '$1'" >&2
					usage >&2
					return 1
				fi
			done
			check_guessed "$base" ${allow[@]+"${allow[@]}"}
			return $?
			;;
		'' | -h | --help)
			usage
			[[ -n "$arg" ]] && return 0
			return 1
			;;
	esac

	# Stable versions only: a pre-release would permanently stamp its suffix
	# (e.g. 2.20.0-beta.1) on code that ships in 2.20.0.
	if [[ ! "$arg" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
		echo "Error: version must be a stable X.Y.Z (got '$arg'). Pre-releases keep n.e.x.t."
		return 1
	fi

	replace_placeholders "$arg"
	return $?
}

main "$@"
