# 🔄 Workflow Git para Múltiplos Servidores

Para desenvolver sua aplicação sincronizada entre dois servidores sem que um quebre as configurações do outro, precisamos isolar o que é código do que é configuração. Eu já comecei esse processo por você.

## O que eu preparei para você:
1. Criei um arquivo **`.gitignore`** no diretório do projeto. Ele instrui o Git a **ignorar arquivos locais**, como as imagens de upload dos usuários (`assets/uploads/*`) e os arquivos de configuração do banco de dados únicos de cada ambiente (`config/database.php`).
2. Criei um arquivo **`config/database.example.php`**. Este será o arquivo rastreado pelo Git contendo a estrutura de conexão, mas sem as senhas reais.

---

## 🚀 Passo a Passo: Configurando o Servidor Atual

Percebi que o Git foi acidentalmente inicializado na pasta principal do seu usuário ubuntu (`~/.git`), o que gera bastante confusão. 

Abra o terminal neste servidor e execute estes comandos exatamente nesta ordem:

```bash
# 1. Remova o git incorreto (Se houver) que foi criado acidentalmente antes
rm -rf ~/.git

# 2. Acesse a pasta do projeto
cd /home/ubuntu/weiderrodrigues/AppGestaoDiscente

# 3. Inicialize o Git na pasta correta
git init

# 4. Adicione seu repositório do GitHub como origem
git remote add origin git@github.com:weiderpr/AppGestaoDiscente.git

# 5. Adicione as exclusões e a base inicial
git add .
git commit -m "Configuração inicial de versionamento multi-ambiente"

# 6. Envie o código principal para o GitHub (Forçando para sobrescrever a versão anterior)
git push -u origin main --force
```

---

## 💻 Configurando no "Outro" Servidor

Quando você for para a sua **outra máquina**, você deve baixar o repositório fresco do GitHub, mas precisará criar a configuração de banco de dados dela:

```bash
# 1. Baixe o repositório atualizado
git clone git@github.com:weiderpr/AppGestaoDiscente.git
cd AppGestaoDiscente

# 2. Configure o banco de dados daquele servidor
cp config/database.example.php config/database.php
```
Agora, abra o `config/database.php` e altere a senha/login para bater com as configurações do banco de dados **dessa outra máquina**. Como o `database.php` está no `.gitignore`, as senhas não serão enviadas para o GitHub e não vão causar conflito com o primeiro servidor!

---

## ♻️ Como trabalhar no dia-a-dia

Sempre que terminar de desenvolver uma funcionalidade em **qualquer uma das máquinas**:

```bash
# Salvar o trabalho
git add .
git commit -m "Descrição do que você fez"
git push
```

Quando for continuar o trabalho na **outra máquina**:

```bash
# Baixar o trabalho que você acabou de enviar
git pull origin main
```

### 💡 Dica sobre o Banco de Dados (Schema)
Se você criar novas tabelas (como as `disciplinas`), lembre-se sempre de exportar o novo `sql/schema.sql`, enviar pelo **git push** e, na outra máquina, abrir seu próprio banco de dados e rodar essas novas alterações SQL manualmente ou readicioná-las via terminal.
