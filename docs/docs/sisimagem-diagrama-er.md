# SISIMAGEM — Diagrama de relacionamentos (ER)

Gerado a partir de `sisimagem-ddl.sql`. Renderiza em qualquer visualizador
Markdown com suporte a Mermaid (GitLab renderiza nativamente).

```mermaid
erDiagram
    USERS ||--o{ DOCUMENTOS : "cria (criado_por)"
    ARQUIVOS ||--o{ DOCUMENTOS : "é referenciado por"
    TIPOS_DOCUMENTO ||--o{ DOCUMENTOS : classifica
    LOCAIS_ARQUIVO ||--o{ DOCUMENTOS : arquiva
    SETORES ||--o{ DOCUMENTOS : "origem (setor_origem_id)"
    SETORES ||--o{ DOCUMENTOS : "responsável (setor_responsavel_id)"

    USERS {
        bigint id PK
        text nip UK
        int uri_legado UK
        text perfil "adm | papem40 | sasm | padrao"
        boolean senha_temporaria
    }

    SETORES {
        smallint id PK
        int uri_legado UK
        text nome
    }

    LOCAIS_ARQUIVO {
        smallint id PK
        int uri_legado UK
        text nome
    }

    TIPOS_DOCUMENTO {
        smallint id PK
        text nome UK
        boolean ativo
    }

    ARQUIVOS {
        bigint id PK
        char_64 sha256 UK
        text resid_legado
        text caminho "nulo se conteudo preenchido"
        bytea conteudo "nulo se caminho preenchido"
        boolean sobrescrito
    }

    DOCUMENTOS {
        bigint id PK
        bigint uri_legado UK
        text record_id
        text titulo
        boolean titulo_legado_numerico
        bigint arquivo_id FK
        smallint tipo_documento_id FK
        smallint local_arquivo_id FK
        smallint setor_origem_id FK
        smallint setor_responsavel_id FK
        text cpf
        text nip_matricula
        text protocolo
        boolean urgente
        date data_protocolo
        boolean data_origem_invalida
        bigint criado_por FK
    }

    ETL_REJEITOS {
        bigint id PK
        bigint uri_legado
        text tabela
        text campo
        text valor_bruto
        text motivo
    }
```

## Notas de leitura

- `ETL_REJEITOS` aparece sem seta no diagrama porque não tem chave
  estrangeira de propósito — precisa aceitar registro de log mesmo quando o
  dado de origem é inválido ao ponto de não corresponder a nenhuma linha
  criada nas outras tabelas.
- `SETORES` se relaciona com `DOCUMENTOS` duas vezes (origem e responsável) —
  é o mesmo padrão do `TSRECLOC` do legado, tipos 1 e 2, já separados em
  colunas distintas em vez de linhas.
- `ARQUIVOS` pode ser referenciado por mais de um documento — é a
  deduplicação por `sha256`: o mesmo arquivo físico anexado a processos
  diferentes vira uma linha só em `arquivos`.
- `uri_legado`, presente em quase toda tabela, não aparece como
  relacionamento porque não é FK — é chave de reconciliação com o Oracle
  de origem, usada pelo ETL para idempotência, não para integridade
  referencial dentro do banco novo.
