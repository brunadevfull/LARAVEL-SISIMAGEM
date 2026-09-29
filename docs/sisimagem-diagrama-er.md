# SISIMAGEM: Diagrama de entidade-relacionamento (DER)

Todas as tabelas e todas as colunas do banco novo. O significado de cada
coluna está no dicionário de dados.

Legenda: PK = chave primária. FK = chave estrangeira. UK = valor único. Nas
ligações, o círculo indica que a ligação é opcional e o pé de galinha indica
"muitos".

```mermaid
erDiagram
    ETL_REJEITOS {
        bigint id PK
        bigint uri_legado
        text tabela
        text campo
        text valor_bruto
        text motivo
        timestamp criado_em
    }

    SETORES |o--o{ USERS : "setor do usuário"
    SETORES ||--o{ TIPOS_DOCUMENTO : "tipos do setor"
    SETORES |o--o{ DOCUMENTOS : "setor dono"
    USERS |o--o{ DOCUMENTOS : "criado_por"
    ARQUIVOS |o--o{ DOCUMENTOS : "arquivo_id"
    TIPOS_DOCUMENTO |o--o{ DOCUMENTOS : "tipo_documento_id"
    LOCAIS_ARQUIVO |o--o{ DOCUMENTOS : "local_arquivo_id"
    DOCUMENTOS |o--o{ DOCUMENTOS : "documento_pai_id"

    SETORES {
        smallint id PK "1 = PAPEM-41, 2 = PAPEM-42"
        int uri_legado UK
        varchar nome UK
    }

    LOCAIS_ARQUIVO {
        smallint id PK
        int uri_legado UK
        varchar nome
    }

    TIPOS_DOCUMENTO {
        smallint id PK
        smallint setor_id FK
        varchar nome "único dentro do setor"
        boolean ativo
    }

    USERS {
        bigint id PK
        varchar name
        varchar password
        varchar remember_token
        timestamp created_at
        timestamp updated_at
        varchar nip UK
        char_11 cpf UK
        int uri_legado UK
        varchar perfil "admin, gestor_setor, operador_setor, padrao"
        smallint setor_id FK "padrao sempre 1"
        smallint tentativas_login
        timestamp bloqueado_em
        boolean senha_temporaria
    }

    ARQUIVOS {
        bigint id PK
        char_64 sha256 UK
        bytea conteudo
        text resid_legado
        varchar extensao
        varchar mime
        bigint bytes
        boolean sobrescrito
        timestamp criado_em
    }

    DOCUMENTOS {
        bigint id PK
        bigint uri_legado UK
        varchar record_id
        text titulo
        boolean titulo_legado_numerico
        varchar natureza "documento, processo, resposta, anexo"
        varchar criado_por_legado
        text nome_arquivo_original
        smallint setor_id FK
        bigint documento_pai_id FK
        bigint arquivo_id FK
        smallint tipo_documento_id FK
        smallint local_arquivo_id FK
        bigint criado_por FK
        text tipo_documento_texto
        text beneficiario
        text consignado
        char_11 cpf
        text nip_matricula
        text origem
        text numero_documento
        text protocolo
        text entidade_consignataria
        boolean oficio_judicial_anexo
        boolean urgente
        text observacoes
        date data_protocolo
        date data_criacao_legado
        date data_protocolo_legado
        timestamp criado_em
        timestamp registrado_em
        timestamp atualizado_em
        boolean data_origem_invalida
    }
```

## Quem vê o quê

| Perfil | Setor | Vê | Inclui | Edita e exclui |
|---|---|---|---|---|
| `admin` | nenhum | todos os setores | qualquer tipo | tudo |
| `gestor_setor` | PAPEM-41 ou PAPEM-42 | só o próprio setor | tipos do próprio setor | só o próprio setor |
| `operador_setor` | PAPEM-41 ou PAPEM-42 | só o próprio setor | tipos do próprio setor | não |
| `padrao` | sempre PAPEM-41 | só o PAPEM-41 | não | não |

Regra de visibilidade: `documentos.setor_id = users.setor_id`, exceto para o
admin. O documento filho tem o mesmo setor do pai.
