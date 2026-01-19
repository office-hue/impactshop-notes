#!/usr/bin/env bash
set -euo pipefail

DB_NAME=${1:-wp_test}
DB_USER=${2:-root}
DB_PASS=${3:-}
DB_HOST=${4:-localhost}
WP_VERSION=${5:-latest}

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP_TESTS_DIR="${ROOT}/tests/wordpress-tests-lib"
WP_CORE_DIR="${ROOT}/tests/wordpress"

download() {
  local url=$1
  local dest=$2
  if command -v curl >/dev/null 2>&1; then
    curl -sSL "$url" -o "$dest"
  else
    wget -q "$url" -O "$dest"
  fi
}

install_wp() {
  mkdir -p "$WP_CORE_DIR"
  if [[ ! -f "${WP_CORE_DIR}/wp-load.php" ]]; then
    wp core download --path="$WP_CORE_DIR" --skip-content --version="$WP_VERSION"
  fi
}

install_tests() {
  if [[ ! -d "$WP_TESTS_DIR" ]]; then
    if command -v svn >/dev/null 2>&1; then
      svn export --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit "$WP_TESTS_DIR"
    else
      local tmpdir
      tmpdir=$(mktemp -d)
      local zip="${tmpdir}/wp-develop.zip"
      download "https://github.com/WordPress/wordpress-develop/archive/refs/heads/trunk.zip" "$zip"
      unzip -q "$zip" -d "$tmpdir"
      mkdir -p "$WP_TESTS_DIR"
      rsync -a "${tmpdir}/wordpress-develop-trunk/tests/phpunit/" "$WP_TESTS_DIR/"
      rm -rf "$tmpdir"
    fi
  fi
  if [[ ! -f "${WP_TESTS_DIR}/wp-tests-config.php" ]]; then
    if [[ -f "${WP_TESTS_DIR}/wp-tests-config-sample.php" ]]; then
      cp "${WP_TESTS_DIR}/wp-tests-config-sample.php" "${WP_TESTS_DIR}/wp-tests-config.php"
      sed -i '' "s/yourdbnamehere/${DB_NAME}/" "${WP_TESTS_DIR}/wp-tests-config.php"
      sed -i '' "s/yourusernamehere/${DB_USER}/" "${WP_TESTS_DIR}/wp-tests-config.php"
      sed -i '' "s/yourpasswordhere/${DB_PASS}/" "${WP_TESTS_DIR}/wp-tests-config.php"
      sed -i '' "s/localhost/${DB_HOST}/" "${WP_TESTS_DIR}/wp-tests-config.php"
    else
      cat > "${WP_TESTS_DIR}/wp-tests-config.php" <<EOF
<?php
define( 'DB_NAME', '${DB_NAME}' );
define( 'DB_USER', '${DB_USER}' );
define( 'DB_PASSWORD', '${DB_PASS}' );
define( 'DB_HOST', '${DB_HOST}' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
\$table_prefix = 'wptests_';
define( 'WP_DEBUG', true );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'ImpactShop Tests' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'ABSPATH', '${WP_CORE_DIR}/' );
EOF
    fi
  fi
}

install_wp
install_tests

echo "WordPress test suite ready:"
echo "  WP core: ${WP_CORE_DIR}"
echo "  WP tests: ${WP_TESTS_DIR}"
