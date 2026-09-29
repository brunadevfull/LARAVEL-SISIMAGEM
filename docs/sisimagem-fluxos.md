# SISIMAGEM — Fluxos

Os fluxos do sistema novo e do ETL. Quando algo veio do legado, está dito.

## 1. Login

```mermaid
flowchart TD
    A["Usuário informa NIP ou CPF e a senha"] --> B["Remove a pontuação: 8 ou 9 dígitos é NIP, 11 é CPF"]
    B --> C{"Tamanho válido?"}
    C -->|não| E["Mensagem de erro genérica"]
    C -->|sim| D{"Usuário existe e não está bloqueado?"}
    D -->|não| E
    D -->|sim| F{"Senha confere?"}
    F -->|não| G["Soma 1 em tentativas_login"]
    G --> H{"Chegou a 5?"}
    H -->|sim| I["Grava bloqueado_em"]
    I --> E
    H -->|não| E
    F -->|sim| J["Zera tentativas_login"]
    J --> K{"senha_temporaria?"}
    K -->|sim| L["Obriga a trocar a senha"]
    K -->|não| M["Cria a sessão"]
    L --> M
```

No legado o contador ficava na sessão e não era gravado; aqui fica no banco.
A mensagem de erro é a mesma para usuário inexistente, bloqueado ou senha
errada.

## 2. Busca

```mermaid
flowchart TD
    A["Usuário abre a busca"] --> B["Preenche filtros, todos opcionais"]
    B --> C["O servidor monta a consulta com parâmetros"]
    C --> D{"Perfil é admin?"}
    D -->|sim| E["Sem filtro de setor"]
    D -->|não| F["Filtra documentos.setor_id = setor do usuário"]
    E --> G["Resultado paginado, mais recente primeiro"]
    F --> G
    G --> H["Usuário escolhe um documento"]
    H --> I["Tela do documento: dados, anexos e respostas"]
```

O filtro de setor é escopo do Model, não depende do formulário.

## 3. Download

```mermaid
flowchart TD
    A["Pedido de download com o id do documento"] --> B{"Usuário autenticado?"}
    B -->|não| X["Negado"]
    B -->|sim| C{"Documento existe e está no setor do usuário?"}
    C -->|não| Y["Resposta 404"]
    C -->|sim| D{"Tem arquivo?"}
    D -->|não| F["Aviso: documento sem arquivo"]
    D -->|sim| G["Lê arquivos.conteudo e envia com o mime guardado e o nome original do documento"]
```

O pedido nunca traz caminho. No legado o cliente mandava o caminho do
arquivo e o servidor o abria sem conferir.

## 4. Inclusão de documento

```mermaid
flowchart TD
    A["Usuário abre a inclusão"] --> B{"Perfil pode incluir?"}
    B -->|não| X["Acesso negado"]
    B -->|sim| C["Lista só os tipos do próprio setor; admin vê todos"]
    C --> D{"Anexo ou resposta de outro documento?"}
    D -->|sim| E["Escolhe o documento pai, do mesmo setor"]
    D -->|não| F["Natureza: documento ou processo"]
    E --> G["Preenche os campos e anexa o arquivo"]
    F --> G
    G --> H["Validação no servidor de todos os campos"]
    H --> I{"Válido?"}
    I -->|não| G
    I -->|sim| J["Calcula sha256, mime e tamanho e guarda o nome original"]
    J --> K["Abre a transação"]
    K --> L["Grava o arquivo, ou reaproveita a linha com o mesmo sha256"]
    L --> M["Grava o documento: setor e criado_por do usuário logado"]
    M --> N["Confirma a transação"]
    K -.->|erro em qualquer passo| R["Desfaz tudo: nada fica gravado"]
```

No legado o arquivo era gravado em disco antes do banco e fora da transação:
uma falha deixava arquivo órfão, e o nome sequencial gerado por contagem
causou os 92 arquivos sobrescritos.

## 5. ETL de metadados

```mermaid
flowchart TD
    A["Exporta CSV do Oracle, na rede da PAPEM"] --> B["Carrega no staging com COPY, sem transformar"]
    B --> C["setores (duas linhas, à mão) e locais_arquivo, de TSRECLOC tipo 0"]
    C --> D["tipos_documento: seed dos 24 tipos oficiais"]
    D --> E["users: a partir da planilha do PAPEM-40"]
    E --> F["documentos com setor_id e natureza vindos do tipo de registro, documento_pai_id vazio"]
    F --> G{"O valor converte?"}
    G -->|não| H["Vazio e registro em etl_rejeitos"]
    G -->|sim| I["Grava"]
    H --> I
    I --> J["Segunda passada: documento_pai_id casando RCCONTAINERURI com uri_legado"]
    J --> K["Validação: contagens, amostra de 50, rejeitos"]
```

## 6. ETL de arquivos

```mermaid
flowchart TD
    A["Copia o repositório do servidor Windows"] --> B["Para cada TSRECELEC, monta o caminho a partir do RESID"]
    B --> C{"O arquivo existe?"}
    C -->|não| D["Registro em etl_rejeitos"]
    C -->|sim| E["Lê os bytes, calcula sha256, mime e tamanho"]
    E --> F{"sha256 já existe em arquivos?"}
    F -->|sim| G["Reaproveita a linha"]
    F -->|não| H["Insere em arquivos.conteudo"]
    G --> I["Preenche documentos.arquivo_id"]
    H --> I
    I --> J{"RESID entre os 92 duplicados?"}
    J -->|sim| K["sobrescrito = true"]
    J -->|não| L["Segue"]
    K --> L
    L --> M["Conferir: 426.383 documentos com arquivo"]
```

A regra do caminho vem do código do legado e ainda não foi confirmada no
servidor de arquivos.
