#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────
# start_local.sh — Servidor de desarrollo local Centro Automotriz Arley
# Uso: ./start_local.sh
# URL: http://localhost:8082
# BD:  taller_arley_local (MySQL Homebrew, root/litosiac_local)
# ─────────────────────────────────────────────────────────────

PORT=8082
APP_DIR="$(cd "$(dirname "$0")" && pwd)"

lsof -ti :$PORT | xargs kill -9 2>/dev/null || true

echo "──────────────────────────────────────────"
echo "  Centro Automotriz Arley — Servidor local"
echo "  http://localhost:$PORT"
echo "  Directorio: $APP_DIR"
echo "  BD: taller_arley_local"
echo "  Ctrl+C para detener"
echo "──────────────────────────────────────────"

cd "$APP_DIR"
php -S 0.0.0.0:$PORT -t . router.php 2>&1
