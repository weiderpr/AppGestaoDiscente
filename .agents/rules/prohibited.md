# Proibições Estritas (Restrições de Sistema)

## 1. Segurança e Dados
- **PROIBIDO:** Concatenar variáveis em strings SQL (Prevenir SQL Injection).
- **PROIBIDO:** Uso de `mysqli_query` ou funções puras de mysql (usar apenas a nossa classe de conexão PDO).
- **PROIBIDO:** Imprimir variáveis diretas no HTML sem escape (Prevenir XSS).

## 2. Tecnologias e Dependências
- **PROIBIDO:** Sugerir bibliotecas que não estão no projeto (ex: Axios, React, SweetAlert) a menos que solicitado.
- **PROIBIDO:** Uso de funções de versões do PHP superiores à [INSIRA SUA VERSÃO EX: 7.4/8.1].
- **PROIBIDO:** Uso de Composer para instalar novos pacotes sem autorização prévia.

## 3. Interface e Estilo
- **PROIBIDO:** Uso de estilos inline (`style="..."`) nos elementos HTML.
- **PROIBIDO:** Criação de novos arquivos CSS para ajustes pequenos; use o `style.css` existente.
- **PROIBIDO:** Uso de JavaScript nativo (`alert()`, `confirm()`) para feedback; use o sistema de Toasts do projeto.
