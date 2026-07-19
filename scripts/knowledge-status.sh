#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
php_binary="${PHP_BINARY:-$(command -v php)}"
php_options=(-d 'error_reporting=E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED')
database_path="${KNOWLEDGE_DB_PATH:-${project_dir}/database/knowledge.sqlite}"
category="${KNOWLEDGE_CATEGORY:-rafeeq-library}"

cd "${project_dir}"

env \
    DB_CONNECTION=sqlite \
    DB_DATABASE="${database_path}" \
    QUEUE_CONNECTION=sync \
    "${php_binary}" "${php_options[@]}" artisan knowledge:status --category="${category}" "$@"
