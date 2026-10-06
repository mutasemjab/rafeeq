#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${project_dir}"
php_binary="${PHP_BINARY:-php}"

# Back up the production database before running this deployment step.
"${php_binary}" artisan migrate --force
"${php_binary}" artisan config:cache
"${php_binary}" artisan queue:restart
