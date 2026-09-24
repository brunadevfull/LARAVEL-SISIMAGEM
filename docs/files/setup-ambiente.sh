#!/usr/bin/env bash
set -euo pipefail

# ============================================================
# SISIMAGEM — setup do ambiente de desenvolvimento (Ubuntu)
# Idempotente: pode rodar de novo sem quebrar o que já existe.
# ============================================================

# --- Editar antes de rodar --------------------------------
PROJETO="sisimagem"
DB_NOME="sisimagem"
DB_USUARIO="sisimagem"
DB_SENHA="dev_local"
GITLAB_REMOTE=""   # cole aqui a URL SSH do repo, ex: git@gitlab.com:usuario/sisimagem.git
# ------------------------------------------------------------

echo "== 1. Pacotes do sistema =="
sudo apt update -qq

if ! command -v php >/dev/null; then
  echo "PHP não encontrado — instalando PHP 8.3 e extensões..."
  sudo apt install -y software-properties-common
  sudo add-apt-repository -y ppa:ondrej/php
  sudo apt update -qq
  sudo apt install -y php8.3 php8.3-cli php8.3-pgsql php8.3-mbstring \
      php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-fpm
else
  echo "PHP já instalado: $(php -v | head -n1)"
fi

if ! command -v composer >/dev/null; then
  echo "Composer não encontrado — instalando..."
  curl -sS https://getcomposer.org/installer | php
  sudo mv composer.phar /usr/local/bin/composer
else
  echo "Composer já instalado: $(composer --version)"
fi

if ! command -v psql >/dev/null; then
  echo "PostgreSQL não encontrado — instalando..."
  sudo apt install -y postgresql postgresql-contrib
  sudo systemctl enable --now postgresql
else
  echo "PostgreSQL já instalado: $(psql --version)"
  sudo systemctl start postgresql 2>/dev/null || true
fi

if ! command -v git >/dev/null; then
  sudo apt install -y git
fi

if ! command -v node >/dev/null; then
  echo "Node.js não encontrado — instalando (necessário para o Breeze)..."
  curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
  sudo apt install -y nodejs
else
  echo "Node já instalado: $(node -v)"
fi

echo
echo "== 2. Banco de dados =="
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USUARIO}'" | grep -q 1 \
  || sudo -u postgres psql -c "CREATE USER ${DB_USUARIO} WITH PASSWORD '${DB_SENHA}';"

sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='${DB_NOME}'" | grep -q 1 \
  || sudo -u postgres psql -c "CREATE DATABASE ${DB_NOME} WITH ENCODING 'UTF8' OWNER ${DB_USUARIO};"

sudo -u postgres psql -d "${DB_NOME}" -c "GRANT ALL ON SCHEMA public TO ${DB_USUARIO};"
echo "Banco '${DB_NOME}' pronto, dono '${DB_USUARIO}'."

echo
echo "== 3. Projeto Laravel =="
if [ ! -d "${PROJETO}" ]; then
  composer create-project laravel/laravel "${PROJETO}"
else
  echo "Pasta '${PROJETO}' já existe — pulando criação."
fi

cd "${PROJETO}"

echo
echo "== 4. Configurando .env =="
if [ ! -f .env ]; then
  cp .env.example .env
fi

sed -i \
  -e "s/^DB_CONNECTION=.*/DB_CONNECTION=pgsql/" \
  -e "s/^DB_HOST=.*/DB_HOST=127.0.0.1/" \
  -e "s/^DB_PORT=.*/DB_PORT=5432/" \
  -e "s/^DB_DATABASE=.*/DB_DATABASE=${DB_NOME}/" \
  -e "s/^DB_USERNAME=.*/DB_USERNAME=${DB_USUARIO}/" \
  -e "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_SENHA}/" \
  .env

grep -q "^APP_TIMEZONE=" .env \
  && sed -i "s/^APP_TIMEZONE=.*/APP_TIMEZONE=America\/Sao_Paulo/" .env \
  || echo "APP_TIMEZONE=America/Sao_Paulo" >> .env

php artisan key:generate --ansi

echo
echo "== 5. Git =="
if [ ! -d .git ]; then
  git init
  git add .
  git commit -m "projeto laravel inicial"
  if [ -n "${GITLAB_REMOTE}" ]; then
    git remote add origin "${GITLAB_REMOTE}"
    git branch -M main
    git push -u origin main
  else
    echo "GITLAB_REMOTE vazio — pulei o push. Configure e rode manualmente depois:"
    echo "  git remote add origin <url>"
    echo "  git push -u origin main"
  fi
else
  echo "Repositório git já inicializado — pulando."
fi

echo
echo "== 6. Migrations do SISIMAGEM =="
QTD_MIGRATIONS=$(find database/migrations -maxdepth 1 -name '2026_*' 2>/dev/null | wc -l)
if [ "${QTD_MIGRATIONS}" -ge 8 ]; then
  echo "Migrations do schema novo já presentes (${QTD_MIGRATIONS} arquivos). Rodando migrate..."
  php artisan migrate
else
  echo "Migrations do schema novo NÃO encontradas em database/migrations/."
  echo "Extraia o migrations.zip para dentro de 'database/' e rode:"
  echo "  php artisan migrate"
fi

echo
echo "== Pronto =="
echo "Teste com: php artisan serve"
echo "Depois: composer require laravel/breeze --dev && php artisan breeze:install blade"
