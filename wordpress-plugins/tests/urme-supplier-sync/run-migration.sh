#!/usr/bin/env bash
# Migration test 1.0.0 -> 1.1.0 on a throwaway WordPress + WooCommerce site.
# Usage: run-migration.sh <wordpress-root> <plugin-1.0.0-dir> <plugin-1.1.0-dir> [wp-cli command]
# The fake feed server must be running (see README.md).
set -euo pipefail
WP_ROOT=$1; OLD=$2; NEW=$3; WP=${4:-wp}
HERE=$(cd "$(dirname "$0")" && pwd)
export URME_MIGRATION_SNAPSHOT=$(mktemp)
LINK="$WP_ROOT/wp-content/plugins/urme-supplier-sync"
cd "$WP_ROOT"
ln -sfn "$OLD" "$LINK"
$WP plugin activate urme-supplier-sync >/dev/null
$WP eval-file "$HERE/migration-populate.php"
ln -sfn "$NEW" "$LINK"
$WP eval-file "$HERE/migration-verify.php"
