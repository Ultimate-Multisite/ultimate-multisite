# GitHub Workflows

This directory contains GitHub Actions workflows for automating various tasks in the repository.

## Release Workflow

The `release.yml` workflow automatically builds and releases the plugin when a new tag is pushed to the repository.

### How it works

1. When a new tag is pushed (in the format `v*.*.*`), the workflow is triggered
2. The workflow checks out the code, sets up PHP and Node.js
3. It verifies that the version in the tag matches the version in the plugin files
4. It runs the build process using `pnpm run build`
5. It creates a ZIP file of the plugin
6. It creates a GitHub release with the ZIP file attached

### Usage

To create a new release:

1. Update the version number in:
   - `wp-multisite-waas.php` (the `Version:` header)
   - `inc/class-wp-ultimo.php` (the `VERSION` class constant)
   - `readme.txt` (the `Stable tag:` field)
   - `package.json` (the `version` field)

2. Commit these changes

3. Create and push a new tag:
   ```
   git tag v1.2.3
   git push origin v1.2.3
   ```

4. The workflow will automatically create a release with the built plugin

### Requirements

- The repository must have a `package.json`, `pnpm-lock.yaml`, and a `build` script
- The plugin must have consistent version numbers across all files

## Sync Wiki Workflow

The `sync-wiki.yml` workflow automatically syncs the content of the `.wiki` directory to the GitHub wiki whenever changes are pushed to the main branch.

### How it works

1. When changes are pushed to the `.wiki` directory on the main branch, the workflow is triggered
2. The workflow clones the GitHub wiki repository
3. It removes all existing files from the wiki repository (except the `.git` directory)
4. It copies all files from the `.wiki` directory to the wiki repository
5. It commits and pushes the changes to the wiki repository

### Benefits

- Documentation is automatically synced to the GitHub wiki
- No manual steps required to update the wiki
- Documentation changes can be reviewed alongside code changes
- Documentation is version-controlled with the code

### Requirements

- The GitHub wiki must be enabled for the repository
- The workflow uses the `GITHUB_TOKEN` secret, which is automatically provided by GitHub Actions
