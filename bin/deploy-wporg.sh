#!/usr/bin/env bash
# Deploy an already-built release ZIP without changing its contents.
set -euo pipefail

: "${VERSION:?VERSION is required}"
: "${SVN_USERNAME:?SVN_USERNAME is required}"
: "${SVN_PASSWORD:?SVN_PASSWORD is required}"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || exit 1
ARCHIVE="$(realpath "${1:?Release ZIP is required}")"
SOURCE_DIR="$(realpath "${2:-.}")"
DEPLOY_DIR="$(mktemp -d "${RUNNER_TEMP:-${TMPDIR:-/tmp}}/wporg-deploy.XXXXXX")"
SVN_URL='https://plugins.svn.wordpress.org/ultimate-multisite'

# Refuse to update trunk for a version that is already tagged.
if svn info "${SVN_URL}/tags/${VERSION}" >/dev/null 2>&1; then
	printf 'SVN tag %s already exists; refusing to overwrite it\n' "$VERSION" >&2
	exit 1
fi

python3 - "$ARCHIVE" "$DEPLOY_DIR/package" "$VERSION" <<'PY'
import pathlib
import re
import sys
import zipfile

archive, destination, version = sys.argv[1:]
with zipfile.ZipFile(archive) as package:
    for name in package.namelist():
        path = pathlib.PurePosixPath(name)
        if path.is_absolute() or '..' in path.parts or not path.parts or path.parts[0] != 'ultimate-multisite':
            raise SystemExit('Unexpected release ZIP path')
    header = package.read('ultimate-multisite/ultimate-multisite.php').decode()
    readme = package.read('ultimate-multisite/readme.txt').decode()
    if re.search(r'^ \* Version:\s*(\S+)', header, re.M)[1] != version:
        raise SystemExit('Release ZIP plugin version mismatch')
    if re.search(r'^Stable tag:\s*(\S+)', readme, re.M)[1] != version:
        raise SystemExit('Release ZIP stable tag mismatch')
    package.extractall(destination)
PY

svn checkout --depth=immediates "$SVN_URL" "$DEPLOY_DIR/svn"
svn update --set-depth=infinity "$DEPLOY_DIR/svn/trunk" "$DEPLOY_DIR/svn/assets"
rsync -rc --delete "$DEPLOY_DIR/package/ultimate-multisite/" "$DEPLOY_DIR/svn/trunk/" \
	--exclude='.svn' --exclude='.git' --exclude='.github'
mkdir -p "$DEPLOY_DIR/svn/assets/blueprints"
cp "$SOURCE_DIR/assets/blueprints/blueprint.json" "$DEPLOY_DIR/svn/assets/blueprints/blueprint.json"
cd "$DEPLOY_DIR/svn"

# XML preserves whitespace and quotes, unlike awk/xargs over text status.
# Subprocess argument arrays also prevent filenames from becoming options.
python3 - <<'PY'
import subprocess
import xml.etree.ElementTree as ET

status = ET.fromstring(subprocess.check_output(['svn', 'status', '--xml', 'trunk', 'assets']))
for state, operation in [('unversioned', ['add', '--parents', '-q']), ('missing', ['delete', '--force', '-q'])]:
    # A trailing @ escapes SVN's peg-revision syntax in literal filenames.
    paths = [entry.attrib['path'] + '@' for entry in status.findall('.//entry')
             if entry.find('wc-status').attrib['item'] == state]
    for offset in range(0, len(paths), 500):
        subprocess.run(['svn', *operation, '--', *paths[offset:offset + 500]], check=True)
PY

if [[ -n "$(svn status trunk assets)" ]]; then
	svn commit trunk assets --username "$SVN_USERNAME" --password "$SVN_PASSWORD" \
		--no-auth-cache --non-interactive -m "Update trunk to ${VERSION}"
fi
svn copy "${SVN_URL}/trunk" "${SVN_URL}/tags/${VERSION}" \
	--username "$SVN_USERNAME" --password "$SVN_PASSWORD" \
	--no-auth-cache --non-interactive -m "Tag ${VERSION}"
printf 'Successfully deployed and tagged %s on WordPress.org\n' "$VERSION"
