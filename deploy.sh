#!/bin/bash

set -e

cd /srv/cryoholdco

echo "Iniciando despliegue..."

# Obtener cambios de GitHub
git fetch origin

# Actualizar main
git pull --ff-only origin main

# Instalar dependencias
docker exec cryoholdco composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Limpiar y reconstruir cachés
docker exec cryoholdco php artisan optimize:clear
docker exec cryoholdco php artisan optimize

echo "Despliegue finalizado correctamente."