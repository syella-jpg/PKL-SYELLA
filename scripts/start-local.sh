#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.."
echo "Buka http://127.0.0.1:8000/menu/page.html"
exec php -S 127.0.0.1:8000 -t .
