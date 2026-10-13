#!/bin/bash

# Setup script for Git hooks
# Run this script to install the Git hooks for this project

set -e

echo "Setting up Git hooks for Ultimate Multisite..."

# Check if we're in a git repository
if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1 || [ ! -e ".git" ]; then
    echo "Error: This script must be run from the root of the Git repository."
    exit 1
fi

# Check if .githooks directory exists
if [ ! -d ".githooks" ]; then
    echo "Error: .githooks directory not found."
    exit 1
fi

# Configure Git to use our custom hooks directory
git config core.hooksPath .githooks

echo "Git hooks have been installed successfully!"
echo ""
echo "The following hooks are now active:"
echo "  - pre-commit: Runs PHPCS, PHPStan, ESLint, and Stylelint on staged files"
echo "  - post-checkout: Creates a private test database for each new linked worktree"
echo ""
echo "Local tests automatically select that worktree's private database."
echo ""
echo "Make sure to run 'composer install' and 'pnpm install --frozen-lockfile' to have the required tools available."
