-- ============================================================
-- SISIMAGEM — DDL do banco novo (PostgreSQL 16+)
-- ============================================================
-- Ordem de criação respeita dependência de chave estrangeira.
-- `users` aqui está com as colunas padrão do Laravel Breeze mais
-- a extensão de perfil. Na implementação real, parte vem da
-- migration padrão do framework e parte de uma migration própria
-- — aqui está unificado por ser documento de referência do schema.
-- ============================================================

-- ------------------------------------------------------------
-- Extensões
-- ------------------------------------------------------------
-- Necessária para busca full-text em português.
CREATE EXTENSION IF NOT EXISTS pg_trgm;
-- Habilitar só se a busca por trecho no meio da palavra (LIKE '%x%')
-- se mostrar necessária no uso real. Custo de escrita maior.
-- (índices trigram ficam comentados na seção de índices)

-- ------------------------------------------------------------
-- users
-- ------------------------------------------------------------
CREATE TABLE users (
    id                BIGSERIAL PRIMARY KEY,
    name              TEXT NOT NULL,
    email             TEXT NOT NULL UNIQUE,
    email_verified_at TIMESTAMPTZ,
    password          TEXT NOT NULL,
    remember_token    VARCHAR(100),
    created_at        TIMESTAMPTZ,
    updated_at        TIMESTAMPTZ,

    -- extensão para os dados e perfis reais do legado
    nip               TEXT UNIQUE,
    uri_legado        INTEGER UNIQUE,
    perfil            TEXT NOT NULL DEFAULT 'padrao'
        CHECK (perfil IN ('adm', 'papem40', 'sasm', 'padrao')),
    tentativas_login  SMALLINT NOT NULL DEFAULT 0,
    bloqueado_em      TIMESTAMPTZ,
    senha_temporaria  BOOLEAN NOT NULL DEFAULT TRUE
);

COMMENT ON COLUMN users.perfil IS
    'Mapeamento do legado: ADM->adm, PAPEM40->papem40, SASM->sasm, qualquer outro->padrao';
COMMENT ON COLUMN users.senha_temporaria IS
    'true força troca de senha no próximo login. Usado na migração dos 235 usuários.';

-- ------------------------------------------------------------
-- setores
-- ------------------------------------------------------------
CREATE TABLE setores (
    id          SMALLSERIAL PRIMARY KEY,
    uri_legado  INTEGER UNIQUE,
    nome        TEXT NOT NULL
);

-- ------------------------------------------------------------
-- locais_arquivo
-- ------------------------------------------------------------
CREATE TABLE locais_arquivo (
    id          SMALLSERIAL PRIMARY KEY,
    uri_legado  INTEGER UNIQUE,
    nome        TEXT NOT NULL
);

-- ------------------------------------------------------------
-- tipos_documento
-- ------------------------------------------------------------
CREATE TABLE tipos_documento (
    id     SMALLSERIAL PRIMARY KEY,
    nome   TEXT NOT NULL UNIQUE,
    ativo  BOOLEAN NOT NULL DEFAULT TRUE
);

-- ------------------------------------------------------------
-- arquivos
-- ------------------------------------------------------------
CREATE TABLE arquivos (
    id            BIGSERIAL PRIMARY KEY,
    sha256        CHAR(64) UNIQUE,
    resid_legado  TEXT,
    extensao      TEXT,
    mime          TEXT,
    bytes         BIGINT,
    caminho       TEXT,       -- preenchido se armazenamento em disco
    conteudo      BYTEA,      -- preenchido se armazenamento em banco
    sobrescrito   BOOLEAN NOT NULL DEFAULT FALSE,
    criado_em     TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON COLUMN arquivos.sobrescrito IS
    'true para os 92 casos confirmados de sobrescrita por colisão de nome no legado';
COMMENT ON COLUMN arquivos.caminho IS
    'Nulo se o conteúdo estiver em `conteudo`. Decisão disco/banco pendente de volume real.';

-- ------------------------------------------------------------
-- documentos
-- ------------------------------------------------------------
CREATE TABLE documentos (
    id                     BIGSERIAL PRIMARY KEY,
    uri_legado             BIGINT UNIQUE,
    record_id              TEXT NOT NULL,
    titulo                 TEXT NOT NULL,
    titulo_legado_numerico BOOLEAN NOT NULL DEFAULT FALSE,
    arquivo_id             BIGINT REFERENCES arquivos(id),

    tipo_documento_texto   TEXT,
    tipo_documento_id      SMALLINT REFERENCES tipos_documento(id),
    local_arquivo_id       SMALLINT REFERENCES locais_arquivo(id),
    setor_origem_id        SMALLINT REFERENCES setores(id),
    setor_responsavel_id   SMALLINT REFERENCES setores(id),

    consignado             TEXT,
    nip_matricula          TEXT,
    origem                 TEXT,
    numero_documento       TEXT,
    beneficiario           TEXT,
    protocolo              TEXT,
    cpf                    CHAR(11),
    entidade_consignataria TEXT,
    oficio_judicial_anexo  TEXT,
    urgente                BOOLEAN NOT NULL DEFAULT FALSE,
    observacoes            TEXT,

    data_protocolo         DATE,
    data_criacao_legado    DATE,
    data_protocolo_legado  DATE,

    criado_em              TIMESTAMPTZ,
    registrado_em          TIMESTAMPTZ,
    data_origem_invalida   BOOLEAN NOT NULL DEFAULT FALSE,

    criado_por             BIGINT REFERENCES users(id),
    atualizado_em          TIMESTAMPTZ
);

COMMENT ON COLUMN documentos.titulo_legado_numerico IS
    'true quando TSRECORD.TITLE original era apenas o ID numérico (bug do legado) — título real foi composto no ETL';
COMMENT ON COLUMN documentos.tipo_documento_texto IS
    'Valor original do legado, preservado mesmo sem normalização (10.578 variações conhecidas)';
COMMENT ON COLUMN documentos.data_origem_invalida IS
    'true quando alguma data de origem não pôde ser convertida — valor real fica em etl_rejeitos';

-- ------------------------------------------------------------
-- etl_rejeitos
-- ------------------------------------------------------------
-- Tabela de log do ETL. Sem FK propositalmente: precisa aceitar
-- registro mesmo quando o dado de origem é inválido ao ponto de
-- não ter contrapartida em nenhuma tabela nova.
CREATE TABLE etl_rejeitos (
    id           BIGSERIAL PRIMARY KEY,
    uri_legado   BIGINT,
    tabela       TEXT,
    campo        TEXT,
    valor_bruto  TEXT,
    motivo       TEXT,
    criado_em    TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ============================================================
-- Índices
-- ============================================================

CREATE INDEX idx_doc_nip       ON documentos(nip_matricula);
CREATE INDEX idx_doc_cpf       ON documentos(cpf);
CREATE INDEX idx_doc_protocolo ON documentos(protocolo);
CREATE INDEX idx_doc_numero    ON documentos(numero_documento);
CREATE INDEX idx_doc_tipo      ON documentos(tipo_documento_id);
CREATE INDEX idx_doc_criado    ON documentos(criado_em DESC);

CREATE INDEX idx_doc_busca ON documentos
  USING GIN (to_tsvector('portuguese',
             coalesce(titulo,'') || ' ' ||
             coalesce(beneficiario,'') || ' ' ||
             coalesce(observacoes,'')));

-- Habilitar só se a busca real exigir trecho no meio da palavra:
-- CREATE INDEX idx_doc_benef_trgm ON documentos USING GIN (beneficiario gin_trgm_ops);

CREATE INDEX idx_arquivo_sha256 ON arquivos(sha256);
CREATE INDEX idx_user_perfil    ON users(perfil);
