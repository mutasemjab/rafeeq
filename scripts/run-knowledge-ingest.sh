#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source_dir="${1:-/Users/tajawal/Downloads/rafiq files}"

if [[ $# -gt 0 ]]; then
    shift
fi

php_binary="${PHP_BINARY:-$(command -v php)}"
php_options=(-d 'error_reporting=E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED')
database_path="${KNOWLEDGE_DB_PATH:-${project_dir}/database/knowledge.sqlite}"
category="${KNOWLEDGE_CATEGORY:-rafeeq-library}"
report_path="${KNOWLEDGE_REPORT_PATH:-${project_dir}/storage/app/knowledge_reports/rafiq-files.json}"
bundle_path="${KNOWLEDGE_BUNDLE_PATH:-${project_dir}/storage/app/knowledge_exports/rafeeq-knowledge.ndjson.gz}"
log_path="${KNOWLEDGE_LOG_PATH:-${project_dir}/storage/logs/knowledge-local-ingest.log}"

if [[ ! -d "${source_dir}" && ! -f "${source_dir}" ]]; then
    echo "Knowledge source does not exist: ${source_dir}" >&2
    exit 1
fi

mkdir -p "$(dirname "${database_path}")" "$(dirname "${report_path}")" "$(dirname "${bundle_path}")" "$(dirname "${log_path}")"
touch "${database_path}"

runtime_env=(
    env
    DB_CONNECTION=sqlite
    DB_DATABASE="${database_path}"
    QUEUE_CONNECTION=sync
)

cd "${project_dir}"

"${runtime_env[@]}" "${php_binary}" "${php_options[@]}" artisan migrate --force

if command -v caffeinate >/dev/null 2>&1; then
    caffeinate -dimsu -w $$ >/dev/null 2>&1 &
fi

ingest_command=(
    "${runtime_env[@]}"
    "${php_binary}"
    "${php_options[@]}"
    -d memory_limit=2048M
    artisan
    knowledge:ingest
    "${source_dir}"
    --category="${category}"
    --process
    --reembed
    --link
    --report="${report_path}"
)

if [[ $# -gt 0 ]]; then
    ingest_command+=("$@")
fi

echo "Starting resumable knowledge ingestion. Log: ${log_path}"
set +e
"${ingest_command[@]}" 2>&1 | tee -a "${log_path}"
exit_code=${PIPESTATUS[0]}
set -e

if [[ ${exit_code} -ne 0 ]]; then
    echo "Ingestion completed with failures. Exporting all successfully processed documents; run the same command again after fixing failures." >&2
fi

"${runtime_env[@]}" "${php_binary}" "${php_options[@]}" artisan knowledge:export "${bundle_path}" --category="${category}" 2>&1 | tee -a "${log_path}"

echo "Knowledge bundle ready: ${bundle_path}"
echo "Upload the bundle and its .sha256 file to the server, then run knowledge:import-index."

if [[ ${exit_code} -ne 0 ]]; then
    exit "${exit_code}"
fi
