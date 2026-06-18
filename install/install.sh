#!/bin/bash
# Script d'installation des dépendances Python pour le plugin Fluidra Pool

set -e

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
RESOURCES_DIR="$PLUGIN_DIR/resources"

echo "[fluidrapool] Installation des dépendances Python..."

# Vérifie Python3
if ! command -v python3 &> /dev/null; then
    echo "[fluidrapool] Python3 non trouvé, installation..."
    apt-get update -qq && apt-get install -y -qq python3 python3-pip
fi

# Vérifie pip3
if ! command -v pip3 &> /dev/null; then
    apt-get install -y -qq python3-pip
fi

# Installe les dépendances Python
pip3 install --quiet --upgrade requests

echo "[fluidrapool] Installation terminée."
echo "[fluidrapool] Version Python : $(python3 --version)"
echo "[fluidrapool] Version requests : $(pip3 show requests 2>/dev/null | grep Version | cut -d' ' -f2)"
