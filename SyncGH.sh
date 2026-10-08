#!/bin/bash
# Actualiza el repositorio local desde main sin forzar ni mezclar historiales
set -e
git pull --ff-only origin main
