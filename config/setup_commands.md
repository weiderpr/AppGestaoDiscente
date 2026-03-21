# Log de Comandos do Sistema Operacional — Implantação Vértice Acadêmico

Este arquivo registra todos os comandos executados no sistema operacional (Shell/Terminal) para a configuração e funcionamento do sistema. Siga esta sequência para implantar em um novo servidor.

---

### 1. Preparação de Diretórios
Estes comandos criam as pastas necessárias para o armazenamento de uploads (fotos de perfil e logotipos de instituições) e garantem as permissões de escrita para o servidor web.

```bash
# Criação das pastas de upload
mkdir -p assets/uploads/avatars
mkdir -p assets/uploads/institutions

# Ajuste de permissões (garante que o PHP possa salvar arquivos)
chmod -R 777 assets/uploads
```

---

### 2. Configuração do Banco de Dados (MySQL)
Comandos para criação do banco de dados, usuário de desenvolvimento e importação da estrutura inicial (schema).

```bash
# Criar o banco de dados caso não exista
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS vertice_academico CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Criar usuário 'dev' com senha 'devdev' e dar permissões
# (Ajuste conforme sua política de segurança)
mysql -u root -p -e "CREATE USER IF NOT EXISTS 'dev'@'localhost' IDENTIFIED BY 'devdev';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON vertice_academico.* TO 'dev'@'localhost';"
mysql -u root -p -e "FLUSH PRIVILEGES;"

# Importar o schema inicial (tabelas users, institutions, courses, turmas, etapas, course_coordinators)
mysql -u dev -pdevdev vertice_academico < sql/schema.sql
```

---

### 3. Servidor Web (Apache/PHP)
Considerações sobre o ambiente de execução.

- **PHP 8.3+**: Certifique-se de que as extensões `pdo_mysql` e `mbstring` estão instaladas.
- **Apache Mod_Rewrite**: O arquivo `.htaccess` na raiz utiliza reescrita de URL. Certifique-se de que o `AllowOverride All` está configurado no Apache para a pasta do projeto.
- **Servidor Embutido (Desenvolvimento)**:
  ```bash
  php -S 0.0.0.0:8080 -t .
  ```

---

### 4. Atualizações de Estrutura (Histórico)
Sempre que o `sql/schema.sql` for alterado, o comando de importação (item 2) deve ser executado novamente em ambiente de homologação/produção, ou as queries específicas aplicadas.

*Última atualização do log: 2026-03-18*
