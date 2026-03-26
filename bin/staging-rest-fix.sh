#!/usr/bin/env bash
set -euo pipefail

SSH_HOST="sharityh@s59.tarhely.com"
REMOTE_ROOT="/home/sharityh/public_html/impactshop-staging"
BASE_URL="https://app.sharity.hu/impactshop-staging"

echo "🔧 STAGING REST FIX (safe mode)"
echo "📅 $(date '+%Y-%m-%d %H:%M:%S')"

# — 0) csendes login-próba —
ssh -o BatchMode=yes -o RequestTTY=no "$SSH_HOST" 'echo ok' >/dev/null

# — 1) current valódi útvonal —
CURRENT_REAL=$(ssh -o BatchMode=yes "$SSH_HOST" "readlink -f '$REMOTE_ROOT/current' 2>/dev/null || echo '$REMOTE_ROOT'")
echo "📁 CURRENT_REAL = $CURRENT_REAL"

# — 2) WP URL → subdir + permalink FLUSH (mindig cd a WP-hez!) —
ssh -o BatchMode=yes "$SSH_HOST" "cd '$CURRENT_REAL' && \
  wp --skip-plugins --skip-themes option update siteurl '$BASE_URL' && \
  wp --skip-plugins --skip-themes option update home    '$BASE_URL' && \
  wp --skip-plugins --skip-themes rewrite structure '/%postname%/' && \
  wp --skip-plugins --skip-themes rewrite flush --hard"

# — 3) .htaccess SUBDIR kompatibilis újraírás —
ssh -o BatchMode=yes "$SSH_HOST" "cd '$CURRENT_REAL' && \
  cp .htaccess .htaccess.bak.\$(date +%Y%m%d%H%M%S) 2>/dev/null || true; \
  printf '%s\n' \
  '# BEGIN WordPress (ImpactShop staging, subdir)' \
  '<IfModule mod_rewrite.c>' \
  'RewriteEngine On' \
  'RewriteBase /impactshop-staging/' \
  'RewriteRule ^index\\.php$ - [L]' \
  '' \
  '# /wp-admin redirect' \
  'RewriteRule ^wp-admin$ wp-admin/ [R=301,L]' \
  '' \
  '# if not file/dir → index.php (subdir)' \
  'RewriteCond %{REQUEST_FILENAME} !-f' \
  'RewriteCond %{REQUEST_FILENAME} !-d' \
  'RewriteRule . /impactshop-staging/index.php [L]' \
  '</IfModule>' \
  '# END WordPress' > .htaccess"

# — 4) Kritikus pluginek (ha már aktívak, nem gond) —
ssh -o BatchMode=yes "$SSH_HOST" "cd '$CURRENT_REAL' && \
  wp plugin activate impact-bridge-local impact-mini-shortcodes || true"

# — 5) Redirection: wp-json kivétel (ha van ilyen plugin) —
ssh -o BatchMode=yes "$SSH_HOST" "cd '$CURRENT_REAL' && \
  wp redirection regex --activate 2>/dev/null || true; \
  wp redirection add '^wp-json/.*' '/' --regex --match=url --ignore --position=top 2>/dev/null || true"

# — 6) Gyors REST teszt (2 forma) —
ssh -o BatchMode=yes "$SSH_HOST" "
  echo 'CORE /wp-json/               →' ; curl -s -I -L -o /dev/null -w '%{http_code} %{url_effective}\n' '$BASE_URL/wp-json/' ;
  echo 'CORE alt (?rest_route=/)     →' ; curl -s -I -L -o /dev/null -w '%{http_code}\n' '$BASE_URL/index.php?rest_route=/' ;
  echo 'IMPACT /impact/v1/ticker     →' ; curl -s -I -L -o /dev/null -w '%{http_code} %{url_effective}\n' '$BASE_URL/wp-json/impact/v1/ticker' ;
  echo 'IMPACT alt (?rest_route=...) →' ; curl -s -I -L -o /dev/null -w '%{http_code}\n' '$BASE_URL/index.php?rest_route=/impact/v1/ticker' ;
"
echo "✅ REST fix futott."
