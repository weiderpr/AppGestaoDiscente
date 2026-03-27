# Análise de Melhorias e Dívida Técnica — Vértice Acadêmico

Este documento detalha as melhorias necessárias identificadas durante a análise profunda do sistema, categorizadas por grau de criticidade.

---

## 🔴 Criticalidade: CRÍTICA

### 1. Sincronização de Esquema do Banco de Dados
- **Problema:** Tabelas essenciais como `conselho_encaminhamentos` e `atendimentos` estão sendo utilizadas no código (ex: `referrals_ajax.php`, `atendimentos_functions.php`), mas não estão definidas no arquivo principal `sql/schema.sql` nem em arquivos de migração visíveis.
- **Risco:** Impossibilidade de replicar o ambiente de desenvolvimento/produção de forma confiável. Perda de integridade referencial.
- **Melhoria:** Atualizar o `schema.sql` e criar migrações retroativas para todas as tabelas e índices faltantes.

### 2. Ausência de Proteção CSRF (Cross-Site Request Forgery)
- **Problema:** Não foi detectado nenhum mecanismo de validação de tokens CSRF nas rotas de alteração de dados (POST, PUT, DELETE), tanto no novo `Router.php` quanto nos arquivos AJAX legados.
- **Risco:** Um atacante pode induzir um usuário autenticado a executar ações indesejadas (ex: excluir um curso ou alterar dados de um aluno) através de sites maliciosos.
- **Melhoria:** Implementar um middleware ou utilitário global para geração e validação de tokens CSRF em todas as requisições de estado.

---

## 🟠 Criticalidade: ALTA

### 3. Dualidade Arquitetural (Dívida Técnica)
- **Problema:** Existe uma divisão clara entre o novo padrão (Controllers/Services em `src/`) e o padrão legado (arquivos procedurais na raiz e em `courses/`).
- **Risco:** Aumento exponencial no custo de manutenção, duplicação de lógica de negócio e dificuldade em implementar melhorias globais (como segurança ou logs).
- **Melhoria:** Estabelecer um plano de migração para mover a lógica dos arquivos `*_ajax.php` e `*_functions.php` para a estrutura de `Services` e `Controllers`.

### 4. Gestão de Permissões Ad-hoc
- **Problema:** As verificações de perfil (ex: `Admin`, `Coordenador`) são feitas de forma manual e repetitiva em cada método de controller ou bloco de código AJAX.
- **Risco:** Inconsistência na segurança. É fácil esquecer uma verificação em uma nova funcionalidade, levando a falhas de autorização.
- **Melhoria:** Implementar um sistema de ACL (Access Control List) ou RBAC (Role-Based Access Control) centralizado, preferencialmente integrado ao Router via Middleware.

---

## 🟡 Criticalidade: MÉDIA

### 5. Centralização de Lógica de Instituição
- **Problema:** Muitos serviços e funções dependem do `institution_id` passado manualmente por parâmetro.
- **Risco:** Erros de "vazamento de dados" entre instituições se um desenvolvedor esquecer de incluir o filtro em uma query.
- **Melhoria:** Utilizar um "Contexto Global" ou "Global Scope" no banco de dados para que as queries filtrem automaticamente pela instituição ativa na sessão do usuário.

### 6. Ausência de Testes Automatizados
- **Problema:** Não há diretório de testes (`tests/`) nem ferramentas como PHPUnit ou Jest configuradas.
- **Risco:** Regressões frequentes ao alterar partes centrais do sistema, especialmente durante a migração da arquitetura legada.
- **Melhoria:** Configurar PHPUnit para o backend e Vitest/Jest para os componentes JS, iniciando com testes de integração nos serviços críticos.

---

## 🟢 Criticalidade: BAIXA

### 7. Otimização de Assets Frontend
- **Problema:** Alguns arquivos JS (ex: `student_comments.js`) estão crescendo significativamente e não passam por um processo de build/minificação.
- **Risco:** Tempo de carregamento ligeiramente superior em conexões lentas e código exposto sem ofuscação.
- **Melhoria:** Introduzir um bundler simples (como Vite ou esbuild) para gerenciar, minificar e otimizar os assets.

### 8. Padronização de Erros e Logs
- **Problema:** O tratamento de erros é inconsistente (alguns retornam JSON com `success: false`, outros redirecionam com `header`).
- **Risco:** Dificuldade em debugar problemas em produção e UI inconsistente para o usuário.
- **Melhoria:** Criar um Handler de Exceções global que formate erros de acordo com o tipo de requisição (HTML vs API) e registre-os em um log centralizado.
