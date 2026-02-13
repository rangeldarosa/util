#!/usr/bin/env bash
set -e

echo "[+] Atualizando pacotes..."
sudo apt-get update -y
sudo apt-get install -y \
    ca-certificates \
    curl \
    gnupg \
    lsb-release

echo "[+] Criando diretório de keyrings..."
sudo install -m 0755 -d /etc/apt/keyrings

echo "[+] Baixando GPG key do Docker..."
curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
  | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg

sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo "[+] Adicionando repositório Docker..."
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu \
  $(lsb_release -cs) stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

echo "[+] Atualizando índices..."
sudo apt-get update -y

echo "[+] Instalando Docker Engine..."
sudo apt-get install -y \
  docker-ce \
  docker-ce-cli \
  containerd.io \
  docker-buildx-plugin \
  docker-compose-plugin

echo "[+] Habilitando Docker no boot..."
sudo systemctl enable docker
sudo systemctl start docker

echo "[+] Verificando versão..."
docker --version
docker compose version

echo "[+] Docker instalado com sucesso."

sudo usermod -aG docker $USER
newgrp docker

echo "[+] Permissões dadas ao usuário {$USER}."
