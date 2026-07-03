#!/usr/bin/env bash
set -euo pipefail

# Ambiente de desenvolvimento local — sem Docker (ADR-0005).
# Sobe: Laravel (artisan serve :8000) + Vite (HMR) + solver FastAPI (:8001).
# Requisitos: PHP 8.3+, Composer, Node 20+, uv, PostgreSQL a correr com a BD escalas_aad.

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

command -v php >/dev/null || { echo "erro: falta PHP" >&2; exit 1; }
command -v composer >/dev/null || { echo "erro: falta Composer" >&2; exit 1; }
command -v npm >/dev/null || { echo "erro: falta Node/npm" >&2; exit 1; }
command -v uv >/dev/null || { echo "erro: falta uv (brew install uv)" >&2; exit 1; }

[ -d vendor ] || composer install
[ -d node_modules ] || npm install
[ -f .env ] || { cp .env.example .env && php artisan key:generate; }
(cd solver && uv sync -q)

php artisan migrate --force

trap 'kill 0' EXIT INT TERM

(cd solver && uv run uvicorn app.main:app --reload --port 8001) &
npm run dev &
php artisan serve --port 8000 &
# QUEUE_CONNECTION=sync em dev (.env) — jobs (gerar escala, emails) correm
# na hora, sem precisar de um worker à parte. Nada a arrancar aqui.

echo
echo "▶ web:    http://localhost:8000"
echo "▶ solver: http://localhost:8001/docs"
wait
