# SISIMAGEM — Fluxos

Diagramas para orientar a construção. Os fluxos 1 a 3 são o comportamento do
legado, inferido do código e do banco — a confirmar com o bloco de extração
do Claude Code e a entrevista com o PAPEM-40. Onde há incerteza, está
marcado.

---

## 1. Inclusão de documento

```mermaid
flowchart TD
    A[Usuário abre tela de inclusão] --> B[Preenche campos: beneficiário, CPF, protocolo, tipo, etc.]
    B --> C[Anexa arquivo digitalizado]
    C --> D{Validação client-side}
    D -->|falha| B
    D -->|ok| E[Envia formulário]
    E --> F{Validação server-side}
    F -->|falha| G[Retorna erro, campos preenchidos]
    G --> B
    F -->|ok| H[Grava registro em TSRECORD]
    H --> I[Grava valores em TSEXFIELDV]
    I --> J[Salva arquivo em disco, nome sequencial]
    J --> K{Arquivo salvo com sucesso?}
    K -->|não| L["⚠ Registro existe sem arquivo<br/>(possível origem dos 682 casos)"]
    K -->|sim| M[Grava referência em TSRECELEC]
    M --> N[Documento incluído]

    style L fill:#fee,stroke:#c00
```

**Ponto de risco identificado:** a gravação do registro (H) e a gravação do
arquivo (J) não são atômicas. Se o passo J falhar depois do H ter sido
confirmado, o resultado é um documento sem arquivo — provável explicação dos
682 casos.

**No sistema novo:** envolver os dois em transação de banco, e só liberar o
upload do arquivo como parte da mesma operação. Se usar fila para o
processamento do arquivo, marcar o documento como "pendente" até a
confirmação.

---

## 2. Busca de documento

```mermaid
flowchart TD
    A[Usuário abre tela de pesquisa] --> B[Preenche um ou mais campos de busca]
    B --> C[Envia pesquisa]
    C --> D[Consulta TSRECORD + TSEXFIELDV]
    D --> E{Algum resultado?}
    E -->|não| F[Mensagem: nenhum documento encontrado]
    F --> B
    E -->|sim| G[Lista de resultados]
    G --> H[Usuário seleciona um documento]
    H --> I[Abre tela de visualização]
    I --> J[Carrega metadados de TSRECORD/TSEXFIELDV]
    I --> K[Carrega arquivo via caminho derivado do RESID]
    K --> L{Arquivo encontrado no disco?}
    L -->|não| M["⚠ Erro ao abrir<br/>(possível caso dos 92 sobrescritos)"]
    L -->|sim| N[Exibe/baixa o documento]
```

**Pendente de confirmação com o Militar 1:** quais campos são realmente
usados na busca. O diagrama assume que qualquer campo pode ser critério —
isso deve ser restringido aos campos que os usuários de fato utilizam
(seção 4 do roteiro de requisitos).

---

## 3. Autenticação (legado)

```mermaid
flowchart TD
    A[Usuário acessa o sistema] --> B{Existe sessão válida?}
    B -->|sim| C[Acessa página solicitada]
    B -->|não| D[Redireciona para login.jsp]
    D --> E[Usuário informa credencial]
    E --> F[Hash Whirlpool da senha informada]
    F --> G{Confere com TSLOCLOGIN?}
    G -->|não| H[Mensagem de erro, volta ao login]
    G -->|sim| I[Cria sessão]
    I --> C
```

**Não migra:** o hash Whirlpool. No sistema novo, ver fluxo 3-B.

## 3-B. Autenticação (novo, local)

```mermaid
flowchart TD
    A[Usuário acessa o sistema] --> B{Sessão Laravel válida?}
    B -->|sim| C[Acessa página solicitada]
    B -->|não| D[Redireciona para /login]
    D --> E[Usuário informa e-mail/matrícula e senha]
    E --> F{Laravel Auth confere hash bcrypt}
    F -->|não| G[Mensagem de erro]
    G --> D
    F -->|sim| H{Primeiro acesso após migração?}
    H -->|sim| I[Força redefinição de senha]
    I --> J[Cria sessão]
    H -->|não| J
    J --> C
```

Os 235 usuários migrados entram com senha temporária e são obrigados a
trocar no primeiro acesso — não há como reaproveitar a senha do legado.

---

## 4. ETL de metadados (visão geral)

```mermaid
flowchart TD
    A[Início] --> B[Migrar TSLOCATION → setores + locais_arquivo]
    B --> C[Migrar lista canônica → tipos_documento]
    C --> D[Ler TSRECORD em lotes, paginado por URI]
    D --> E[Pivotar TSEXFIELDV por documento, no próprio Oracle]
    E --> F{Converter datas}
    F -->|válida| G[Grava DATE]
    F -->|inválida/placeholder| H[Grava NULL + log em etl_rejeitos]
    G --> I[Insere em documentos, com uri_legado]
    H --> I
    I --> J{Mais lotes?}
    J -->|sim| D
    J -->|não| K[Validação pós-carga: contagens, duplicidade]
    K --> L[Relatório final]
```

## 5. ETL de arquivos (visão geral)

```mermaid
flowchart TD
    A[Início] --> B[Ler TSRECELEC: URI, RESID]
    B --> C[Derivar caminho do RESID pela regra extraída]
    C --> D{Arquivo existe no caminho previsto?}
    D -->|não| E[Log em etl_rejeitos: arquivo não encontrado]
    D -->|sim| F[Calcular SHA-256 do conteúdo]
    F --> G{Hash já existe em arquivos?}
    G -->|sim| H[Reaproveita registro existente, deduplica]
    G -->|não| I[Grava em arquivos: caminho ou conteúdo, conforme decisão]
    H --> J[Vincula documentos.arquivo_id]
    I --> J
    J --> K{RESID está na lista dos 92 duplicados?}
    K -->|sim| L[Marca arquivos.sobrescrito = true]
    K -->|não| M{Mais registros?}
    L --> M
    M -->|sim| B
    M -->|não| N[Comparar contagem migrada com 426.383 esperados]
```

**Bloqueado até:** validação da regra de caminho contra o servidor real, e
acesso de leitura ao diretório.

---

## Como usar isto ao programar

- Fluxos 1 e 2 orientam os métodos dos Controllers/Livewire das fatias 1 e 2
  do plano técnico.
- Fluxo 3-B é a especificação da autenticação, já pronta para implementar.
- Fluxos 4 e 5 são o roteiro dos Commands de ETL, na ordem em que devem ser
  escritos e executados.
- Os pontos marcados com ⚠ são exatamente os três riscos já reportados
  (documento sem arquivo, arquivo sobrescrito, arquivo órfão) — cada um
  aparece no diagrama no momento exato em que o problema se origina ou se
  manifesta.
