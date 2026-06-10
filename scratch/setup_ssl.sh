#!/bin/bash
# Vértice Acadêmico — Script de Configuração de HTTPS Autoassinado
set -e

if [ "$EUID" -ne 0 ]; then
  echo "Por favor, execute este script como root (usando sudo)."
  exit 1
fi

echo "1. Habilitando módulo SSL no Apache..."
a2enmod ssl

echo "2. Gerando certificado autoassinado para o IP 200.131.43.96..."
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/apache-selfsigned.key \
  -out /etc/ssl/certs/apache-selfsigned.crt \
  -subj "/C=BR/ST=MG/L=Belo Horizonte/O=CEFET-MG/OU=Vertice Academico/CN=200.131.43.96"

echo "3. Copiando configuração do site SSL..."
cp /home/wazuh/Documentos/AppGestaoDiscente/scratch/default-ssl.conf /etc/apache2/sites-available/default-ssl.conf

echo "4. Habilitando o site default-ssl no Apache..."
a2ensite default-ssl

echo "5. Reiniciando o serviço do Apache..."
systemctl restart apache2

echo "--------------------------------------------------"
echo "HTTPS Autoassinado ativado com sucesso no Apache!"
echo "Acesse o sistema por: https://200.131.43.96"
echo "--------------------------------------------------"
