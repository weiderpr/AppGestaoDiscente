# Análise de Melhorias e Dívida Técnica — Vértice Acadêmico
> [!NOTE]
> Última Atualização: 27/03/2026 - 16:30hs

Este documento reflete o estado atual das melhorias implementadas e o novo roteiro de evolução do sistema após a conclusão da Refatoração Mobile e Segurança (CSRF/RBAC).

---

## ✅ CONCLUÍDO E ESTÁVEL
- [x] **Proteção CSRF Global:** Implementadas validações em todas as rotas de alteração de estado (Mobile e Desktop).
- [x] **RBAC (Role-Based Access Control) Dinâmico:** Substituição de perfis "hardcoded" pela nova Matriz de Permissões no banco de dados.
- [x] **Refinamento Mobile Premium:** Interface mobile 100% responsiva, com alto contraste e baixo tempo de carregamento.
- [x] **Sincronização de Esquema:** O arquivo `sql/schema.sql` agora contém todas as 33 tabelas e os dados de permissões iniciais.

---

## 🔴 PRIORIDADE: CRÍTICA (Próximos Passos)

### 1. Centralização da Sessão e Sessão Multi-Instituição
- **Problema:** A seleção de instituição ainda depende de redirecionamentos manuais em páginas individuais (ex: `courses.php`, `turmas.php`).
- **Risco:** Inconsistência ao navegar entre módulos se o usuário perder o contexto de qual instituição está ativa.
- **Melhoria:** Implementar um `InstitutionMiddleware` que verifique automaticamente se a instituição está selecionada e injete o contexto em todas as requisições.

### 2. Auditoria de Alterações (Audit Logs)
- **Problema:** Com o aumento de observações pedagógicas e alterações de permissões via interface, não há um rastro de "quem alterou o quê".
- **Risco:** Dificuldade em resolver conflitos de dados ou identificar acessos indevidos.
- **Melhoria:** Criar uma tabela `audit_logs` e um helper global para registrar ações críticas (Save/Update/Delete).

---

## 🟠 PRIORIDADE: ALTA

### 3. Consolidação Arquitetural (Migração Legada)
- **Problema:** Fragmentação entre arquivos procedurais na raiz e `Services/Controllers` em `src/`.
- **Risco:** Duplicação de lógica e aumento do custo de manutenção.
- **Melhoria:** Continuar movendo a lógica de negócios para a pasta `src/`, transformando arquivos procedurais em apenas "pontos de entrada" (wrappers) que chamam os Controllers.

### 4. Gestão de Notificações Mobile
- **Problema:** Registros de observações pedagógicas são silenciosos; o coordenador só vê o comentário se acessar o aluno especificamente.
- **Risco:** Perda de agilidade no acompanhamento pedagógico.
- **Melhoria:** Implementar um sistema de alerta/sina (via email ou dashboard administrativo) para novas observações críticas.

---

## 🟡 PRIORIDADE: MÉDIA

### 5. Implementação de Testes Automatizados
- **Problema:** O sistema não possui cobertura de testes para os serviços críticos (`AlunoService`, `AuthService`).
- **Risco:** Introdução de regressões durante as próximas fases de refatoração.
- **Melhoria:** Instalar **PHPUnit** e criar os primeiros testes de integração para as rotas de Permissões e Segurança.

### 6. Relatório Mobile de Turma
- **Problema:** O professor vê um aluno por vez, mas não tem um resumo de "quantos comentários foram feitos na Turma X esta semana".
- **Risco:** Visão fragmentada do desempenho da turma.
- **Melhoria:** Adicionar uma nova aba ou tela de "Resumo de Turma" na interface mobile.

---

## 🟢 PRIORIDADE: BAIXA

### 7. Otimização de Imagens e Cache
- **Problema:** Fotos de alunos são carregadas sem redimensionamento no mobile.
- **Melhoria:** Implementar redimensionamento dinâmico (Thumbnailer) para economizar dados móveis.

### 8. Documentação Técnica Abrangente
- **Melhoria:** Evoluir o `rbac_guide.md` para um Manual Geral de Desenvolvimento do Vértice Acadêmico.
