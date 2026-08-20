#!/bin/bash

# Setup script for Git hooks
# Run this script to install the Git hooks for this project

set -e

echo "Setting up Git hooks for Multisite Ultimate..."

# Check if we're in a git repository
if [ ! -d ".git" ]; then
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
echo ""
echo "To bypass hooks for a specific commit, use: git commit --no-verify"
echo ""
echo "Make sure to run 'composer install' and 'pnpm install --frozen-lockfile' to have the required tools available."
