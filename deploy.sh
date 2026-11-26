
#!/bin/bash
set -Eeuo pipefail

APP_DIR="/srv/cryoholdco"
CONTAINER="cryoholdco"

echo "======================================"
echo " INICIANDO DESPLIEGUE DE CRYOHOLDCO"
echo "======================================"

cd "$APP_DIR"

echo "[1/5] Actualizando código desde GitHub..."
git fetch origin
git pull --ff-only origin main

echo "[2/5] Instalando dependencias del frontend..."
npm ci

echo "[3/5] Compilando frontend..."
npm run build

echo "[4/5] Instalando dependencias de Laravel..."
docker exec "$CONTAINER" composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-dev

echo "[5/5] Optimizando Laravel..."
docker exec "$CONTAINER" php artisan optimize:clear
docker exec "$CONTAINER" php artisan optimize

echo "======================================"
echo " DESPLIEGUE FINALIZADO CORRECTAMENTE"
echo "======================================"
