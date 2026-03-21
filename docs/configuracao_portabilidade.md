# Portabilidade do Repositório Configuradada com Sucesso

O projeto `AppGestaoDiscente` agora está adaptado para um fluxo de trabalho portátil utilizando **Git**. Suas senhas e o conteúdo que os usuários geram na aplicação (como fotos) ficarão apenas na máquina em que foram criados, evitando conflitos.

## O Que Foi Feito:

1. **Configuração Desacoplada (`config/database.php`)**:
   - O arquivo original foi reescrito para procurar por um arquivo `config.local.php`.
   - Se ele existir, lê dele; se não existir, usa um modelo padrão.
   - Foi criado o arquivo `config.local.php` na sua máquina contendo seus acessos já preenchidos (esse **NÃO** foi enviado pro GitHub).
   - Foi enviado um modelo: `config.local.php.example` para auxiliar quando você baixar em outras máquinas.

2. **Gerenciamento Flexível (`.gitignore`)**:
   - Foram adicionadas regras eficientes para projetos em PHP ao `.gitignore`.
   - Incluímos a proteção de pastas de cache, histórico do seu VSCode e do Sistema Operacional.
   - Foram configuradas as pastas de mídia dos usuários para que o conteúdo dinâmico não sobreponha a configuração de outras máquinas (foram ignoradas `assets/uploads/alunos/`, `assets/uploads/institutions/` e `assets/uploads/avatars/`).

3. **Versão Oficializada**:
   - Todo o código foi anexado em um *Commit* inicial e enviado ( pushed ) com segurança usando sua autenticação direto para o ramo `main` do GitHub.

## Manual: Como Desenvolver em Uma Nova Máquina?

Quando você for para a **Outra Máquina**, faça apenas o seguinte:

1. Clone o seu projeto normal:
   ```bash
   git clone https://github.com/weiderpr/AppGestaoDiscente.git
   cd AppGestaoDiscente
   ```

2. Crie uma cópia do arquivo exemplo e abra para inserir as permissões do banco da outra máquina:
   ```bash
   cp config/config.local.php.example config/config.local.php
   ```
   *Edite esse arquivo copiado colocando o `user` e `password` do banco MySQL dessa máquina em específico.*

3. Seu repositório agora vai rodar exatamente como precisa em ambas as máquinas! O que você alterar no código, você irá `add`, `commit` e `push`. E na outra máquina `git pull`. Simples!
