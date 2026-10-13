#!/bin/bash

# Verify bin/check-env.sh detects incomplete Composer development dependencies.

set -euo pipefail

REPO_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
TEST_ROOT="$(mktemp -d)"
REPOSITORY_DIR="${TEST_ROOT}/repository"
WORDPRESS_DIR="${TEST_ROOT}/wordpress"
COMMAND_DIR="${TEST_ROOT}/commands"
WP_TESTS_DIR="${TEST_ROOT}/wp-tests"

trap 'rm -rf "${TEST_ROOT}"' EXIT

mkdir -p "${REPOSITORY_DIR}/bin" "${REPOSITORY_DIR}/vendor/bin" "${REPOSITORY_DIR}/node_modules/.bin" "${WORDPRESS_DIR}" "${COMMAND_DIR}" "${WP_TESTS_DIR}/includes"
cp "${REPO_ROOT}/bin/check-env.sh" "${REPOSITORY_DIR}/bin/check-env.sh"
touch "${REPOSITORY_DIR}/vendor/autoload.php" "${REPOSITORY_DIR}/node_modules/.bin/eslint" "${WORDPRESS_DIR}/wp-config.php" "${WP_TESTS_DIR}/includes/bootstrap.php"

for command in php pnpm wp; do
	cat > "${COMMAND_DIR}/${command}" <<'SCRIPT'
#!/bin/bash
exit 0
SCRIPT
	chmod +x "${COMMAND_DIR}/${command}"
done

if missing_output=$(PATH="${COMMAND_DIR}:${PATH}" WP_TESTS_DIR="${WP_TESTS_DIR}" bash "${REPOSITORY_DIR}/bin/check-env.sh" 2>&1); then
	printf 'Expected incomplete Composer development dependencies to fail.\n'
	exit 1
fi

case "${missing_output}" in
	*'composer development tools missing: vendor/bin/phpunit vendor/bin/phpcs vendor/bin/phpstan'*) ;;
	*)
		printf 'Missing Composer development tools were not reported.\n'
		exit 1
		;;
esac

case "${missing_output}" in
	*'Fix: composer install'*) ;;
	*)
		printf 'Composer recovery guidance was not reported.\n'
		exit 1
		;;
esac

touch "${REPOSITORY_DIR}/vendor/bin/phpunit" "${REPOSITORY_DIR}/vendor/bin/phpcs" "${REPOSITORY_DIR}/vendor/bin/phpstan"
chmod +x "${REPOSITORY_DIR}/vendor/bin/phpunit" "${REPOSITORY_DIR}/vendor/bin/phpcs" "${REPOSITORY_DIR}/vendor/bin/phpstan"

if ! complete_output=$(PATH="${COMMAND_DIR}:${PATH}" WP_TESTS_DIR="${WP_TESTS_DIR}" bash "${REPOSITORY_DIR}/bin/check-env.sh" 2>&1); then
	printf 'Expected complete Composer development dependencies to pass.\n'
	printf '%s\n' "${complete_output}"
	exit 1
fi

case "${complete_output}" in
	*'composer install — vendor/autoload.php and development tools found'*) ;;
	*)
		printf 'Composer development readiness was not reported.\n'
		exit 1
		;;
esac

printf 'check-env Composer development dependency tests passed.\n'
