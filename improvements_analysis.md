# Análise de Melhorias e Dívida Técnica — Vértice Acadêmico
> [!NOTE]
> Última Atualização: 28/03/2026 - 10:35hs
> Estado: Análise Reconfirmada e Roadmap Refinado.

Este documento reflete o estado atual das melhorias implementadas e o roteiro de evolução do sistema. A análise realizada hoje confirma que, embora a base mobile e de segurança esteja sólida, os próximos passos arquiteturais são fundamentais para a escalabilidade.

---

## ✅ CONCLUÍDO E ESTÁVEL
- [x] **Proteção CSRF Global:** Implementadas validações em todas as rotas de alteração de estado.
- [x] **RBAC (Role-Based Access Control) Dinâmico:** Matriz de permissões operacional no banco de dados.
- [x] **Refinamento Mobile Premium:** Interface mobile responsiva e de alto contraste funcional.
- [x] **Sincronização de Esquema:** `sql/schema.sql` atualizado com as 33 tabelas base.

---

## 🔴 PRIORIDADE: CRÍTICA (Pendente)

### 1. Centralização da Sessão e Sessão Multi-Instituição
- **Status:** ⏳ Pendente.
- **Problema:** A seleção de instituição ainda depende de redirecionamentos manuais.
- **Melhoria:** Implementar o `InstitutionMiddleware` em `src/App/Middleware` e registrá-lo no `Router`. Isso removerá a necessidade de verificar a instituição manualmente em cada arquivo PHP da raiz.

### 2. Auditoria de Alterações (Audit Logs)
- **Status:** ⏳ Pendente.
- **Problema:** Sem rastro de alterações em registros pedagógicos.
- **Melhoria:** Criar a tabela `audit_logs` e integrar um trait de log nos Services de `src/App/Services`.

---

## 🟠 PRIORIDADE: ALTA (Em Progresso)

### 3. Consolidação Arquitetural (Migração Legada)
- **Status:** 🔄 Em Progresso / Refatoração Contínua.
- **Detalhe:** As rotas em `src/routes.php` e os Services em `src/App/Services` (como `AlunoService` e `TurmaService`) já existem, mas módulos como `/atendimentos`, `/avaliacoes` e `/subjects` ainda operam inteiramente fora desse novo padrão.
- **Ação:** Priorizar a migração da lógica de "Lançamento de Notas" para serviços.

### 4. Gestão de Notificações Mobile
- **Status:** ⏳ Pendente.
- **Melhoria:** Integrar um serviço de despacho de notificações no `AlunoService` para quando observações críticas forem registradas.

---

## 🟡 PRIORIDADE: MÉDIA

### 5. Implementação de Testes Automatizados
- **Status:** ⏳ Pendente.
- **Ação:** Criar diretório `tests/` e configurar o PHPUnit para validar o `PermissionService` e o middleware de RBAC.

### 6. Relatório Mobile de Turma (Dashboard do Professor)
- **Status:** ⏳ Pendente.
- **Melhoria:** Evoluir o `mobile/turmas.php` para incluir um "snapshot" de engajamento (contagem de observações e pendências da semana).

---

## 🟢 PRIORIDADE: BAIXA

### 7. Otimização de Imagens e Cache
- **Status:** ⏳ Pendente.
- **Melhoria:** Implementar processamento de thumbnails nas fotos dos alunos exibidas em `mobile/aluno_detalhe.php`.

### 8. Gestão da Matriz de Permissões via Interface
- **Status:** ➕ Novo Item.
- **Ação:** Criar uma tela administrativa para gerenciar a Matriz de Permissões sem necessidade de manipulação direta via SQL.

---

## 💡 INSIGHTS DA REVISÃO ATUAL
1. **Integração do RBAC:** O projeto possui um `RoleMiddleware`, mas muitos arquivos na raiz ainda usam verificações `hasDbPermission()` manuais. O objetivo deve ser centralizar isso no `Router`.
2. **Padrão de Resposta API:** Padronizar as respostas JSON dos arquivos em `/api/` para usar um formato consistente (`{ success: boolean, data: any, error: string }`).

