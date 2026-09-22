#!/usr/bin/env bash
# Builds the cPanel deployment package: dist/ev-catalog-cpanel.zip
#   public_html/   ← contents of ev-site/ (without local config, .env or uploaded files)
#   database/      ← ev_catalog.sql
#   docs/          ← deployment guide, API reference, schema, wireframes
set -euo pipefail
cd "$(dirname "$0")"

OUT=dist
STAGE="$OUT/ev-catalog"
rm -rf "$STAGE" "$OUT/ev-catalog-cpanel.zip"
mkdir -p "$STAGE/public_html" "$STAGE/database" "$STAGE/docs"

rsync -a \
  --exclude 'config.php' --exclude '.env' \
  --exclude 'uploads/*' --include 'uploads/.htaccess' \
  ev-site/ "$STAGE/public_html/"
cp ev-site/uploads/.htaccess "$STAGE/public_html/uploads/.htaccess"
cp database/ev_catalog.sql "$STAGE/database/"
cp -r docs/*.md "$STAGE/docs/"
cp README.md "$STAGE/README.md"

cat > "$STAGE/INSTALL.txt" <<'EOF'
EV Catalog - quick install (full guide: docs/DEPLOYMENT.md)

1. cPanel > MySQL Databases: create a database + user, grant ALL PRIVILEGES.
2. cPanel > phpMyAdmin: select the database > Import > database/ev_catalog.sql
3. cPanel > File Manager (enable "Show Hidden Files"): upload the CONTENTS of
   public_html/ into your public_html (or a subfolder).
4. Copy config.sample.php to config.php and enter the database name, user and password.
5. MultiPHP Manager: select PHP 8.1 or newer.
6. Visit https://yourdomain.com/admin and sign in with
      admin@example.com / ChangeMe123!
   You will be asked to choose a new password.
EOF

(cd "$OUT" && zip -qr ev-catalog-cpanel.zip ev-catalog)
rm -rf "$STAGE"
echo "Built $OUT/ev-catalog-cpanel.zip ($(du -h "$OUT/ev-catalog-cpanel.zip" | cut -f1))"
