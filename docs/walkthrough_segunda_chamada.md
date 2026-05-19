# Walkthrough: Módulo de Segunda Chamada (Second Call Module)

Este documento detalha o design, arquitetura e implementação do novo módulo de **Segunda Chamada** do sistema **Vértice Acadêmico**. O módulo segue rigorosamente as regras de arquitetura (MVC pragmático e camadas de serviço) e padrões visuais do sistema.

---

## 1. Estrutura de Diretórios e Arquivos Criados

| Arquivo | Descrição | Regra Aplicada |
| :--- | :--- | :--- |
| `scripts/migrate_segunda_chamada.php` | Script de migração para criação da tabela no banco de dados. | Isolamento de alterações de banco em scripts reutilizáveis. |
| `scripts/migrate_add_atividade_nome_to_segunda_chamada.php` | Migration para adicionar a coluna `atividade_nome`. | Schema evolution controlado. |
| `scripts/seed_segunda_chamada_permissions.php` | Script para inserção de permissões RBAC no sistema. | RBAC centralizado por instituição. |
| `src/App/Services/SegundaChamadaService.php` | Classe de serviço encapsulando a lógica de negócios e persistência. | Centralização de escrita em Services com trait `Auditable`. |
| `api/segundachamada.php` | Endpoint AJAX REST para o CRUD, upload de anexos e envio de e-mails. | Centralização de APIs estruturadas sob `/api`. |
| `segundachamada/index.php` | Dashboard do módulo com listagem, filtros e modal fixo de 80% do tamanho da tela. | UI Standards (modals, toasts, e grids sem inline CSS). |

---

## 2. Modelagem do Banco de Dados (`segunda_chamada`)

A tabela foi atualizada e possui a seguinte estrutura final:
```sql
CREATE TABLE IF NOT EXISTS `segunda_chamada` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `aluno_id` int UNSIGNED NOT NULL,
  `telefone_aluno` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_aluno` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_responsavel` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone_responsavel` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disciplina_codigo` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `atividade_nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `justificativa` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `anexo_caminho` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anexo_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anexo_tipo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anexo_tamanho` int UNSIGNED DEFAULT NULL,
  `data_atividade_perdida` date NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `usuario_id` int UNSIGNED NOT NULL,
  `status` enum('Pendente','Deferido','Indeferido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pendente',
  `observacoes_status` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_sc_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sc_disciplina` FOREIGN KEY (`disciplina_codigo`) REFERENCES `disciplinas` (`codigo`) ON DELETE CASCADE,
  CONSTRAINT `fk_sc_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sc_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Máscaras de Telefone e Validação de E-mail

- **Máscaras de Input**: Os campos de *Telefone do Aluno* e *Telefone do Responsável* utilizam o utilitário nativo global do sistema `initPhoneMask()` através da atribuição da classe `mask-phone` e tipo `tel`, formatando automaticamente os inputs no padrão `(99) 99999-9999` ou `(99) 9999-9999`.
- **Validação de E-mail**:
  - **Client-Side (JS)**: O formulário intercepta o envio e valida o formato utilizando expressões regulares antes de disparar a requisição AJAX.
  - **Server-Side (PHP)**: O endpoint `/api/segundachamada.php` sanitiza e valida o e-mail utilizando `filter_var($email, FILTER_VALIDATE_EMAIL)` garantindo que nenhum endereço incorreto seja registrado no banco de dados.

---

## 4. Anexo Opcional e Compactação Automática

- **Anexo Opcional**: O campo de documento comprobatório (anexo) é totalmente opcional tanto na inserção quanto na edição. O indicador visual de obrigatoriedade (*) foi removido e a propriedade HTML/JS `required` foi desabilitada por completo.
- **Upload de PDF e Imagem**:
  - PDF: Limite de 10MB.
  - Imagem (JPG, PNG, WEBP): Otimização inteligente pelo backend (utilizando biblioteca GD) comprimindo a qualidade e redimensionando imagens de alta resolução para manter o arquivo abaixo de 2MB.

---

## 5. Bloqueio de Status e Deferimento (Controle Interno)

- De acordo com as diretrizes de controle, os campos de **Status da Solicitação** e **Observações sobre o Status** foram desabilitados (`disabled`) na interface de preenchimento, impedindo qualquer manipulação indevida pelo solicitante.
- O formulário submete o valor `Pendente` através de um input `hidden` que reflete o estado atual, e o backend força o status `'Pendente'` na criação de novos registros para assegurar robustez à prova de adulterações.

---

## 6. Notificações por E-mail (Enviado pelo Coordenador)

- **Coordenador como Remetente**: O sistema localiza o Coordenador do Curso associado à turma do aluno (`course_coordinators`) utilizando o método robusto `getCoordenadorByTurma()` da camada de Service. O e-mail de notificação sai formatado com o nome e endereço de e-mail do próprio coordenador no header `From` e `Reply-To`. Caso a turma não possua um coordenador cadastrado, o sistema adota um remetente institucional seguro (`noreply@verticeacademico.com.br`).
- **Resolução de Cabeçalhos em Servidores Linux**: Alterados os delimitadores de linha dos headers de `\r\n` para `\n`, eliminando problemas de duplicação de carriage return em servidores Apache/Nginx rodando sobre distribuições Linux (que invalidava a formatação e impedia o disparo correto pelo MTA local).
- **Ação de Reenvio Manual**: Implementado um botão de ação com ícone de envelope (**✉️**) em cada linha da listagem do dashboard. Clicar no botão solicita uma confirmação ao usuário, gerando um token CSRF e disparando um endpoint seguro `/api/segundachamada.php?action=resend_email` que realiza uma nova tentativa de notificação a todos os professores associados à disciplina.
