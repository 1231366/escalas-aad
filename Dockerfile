# Produção — Render web service (ADR-0005).
# Multi-stage: assets Vite + vendor composer → imagem final FrankenPHP (Caddy embutido).

# ── Stage 1: assets ──────────────────────────────────────────────────────────
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tsconfig.json components.json ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ── Stage 2: vendor ──────────────────────────────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader --ignore-platform-reqs

# ── Stage 3: runtime ─────────────────────────────────────────────────────────
FROM dunglas/frankenphp:1-php8.4 AS runtime

RUN install-php-extensions pdo_pgsql pgsql intl zip gd opcache bcmath pcntl

# Render bloqueia binários com Linux capabilities (cap_net_bind_service do
# frankenphp, para bind a portas <1024) mesmo a correr como root — não
# precisamos dela porque o $PORT do Render nunca é privilegiado.
RUN setcap -r /usr/local/bin/frankenphp

ENV SERVER_NAME=":${PORT:-8080}"
ENV OCTANE_DISABLED=1

WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN php artisan storage:link || true \
    && chown -R www-data:www-data storage bootstrap/cache

# bootstrap/cache/packages.php não pode vir do dev local (referencia pacotes
# require-dev como o Pail, ausentes aqui por causa do --no-dev acima).
RUN php artisan package:discover --ansi

# Migrations correm no arranque; config/route/view cache para performance.
COPY <<'EOF' /usr/local/bin/start.sh
#!/bin/sh
set -e
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
exec frankenphp php-server --root /app/public --listen :${PORT:-8080}
EOF
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 8080
CMD ["/usr/local/bin/start.sh"]
