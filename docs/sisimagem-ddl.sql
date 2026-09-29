-- ============================================================
-- SISIMAGEM — DDL de referência (PostgreSQL 16+)
-- Estado alvo: migrations 1 a 15 aplicadas.
-- Esta é a fonte única do schema. O que o Laravel executa de fato são as
-- migrations; se houver divergência, corrija este arquivo.
-- As tabelas internas do Laravel (cache, jobs, sessions, migrations,
-- password_reset_tokens) ficam de fora.
-- ============================================================

CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- ------------------------------------------------------------
-- setores: PAPEM-41 e PAPEM-42
-- ------------------------------------------------------------
CREATE TABLE setores (
    id          SMALLSERIAL PRIMARY KEY,
    uri_legado  INTEGER UNIQUE,
    nome        VARCHAR(255) NOT NULL UNIQUE
);

-- Os dois setores tem id fixo: o perfil padrao depende do id 1.
INSERT INTO setores (id, nome) VALUES (1, 'PAPEM-41'), (2, 'PAPEM-42');
SELECT setval(pg_get_serial_sequence('setores', 'id'), 2);

-- ------------------------------------------------------------
-- locais_arquivo: onde o documento está guardado (TSRECLOC tipo 0)
-- ------------------------------------------------------------
CREATE TABLE locais_arquivo (
    id          SMALLSERIAL PRIMARY KEY,
    uri_legado  INTEGER UNIQUE,
    nome        VARCHAR(255) NOT NULL
);

-- ------------------------------------------------------------
-- users: sem e-mail; login por NIP ou CPF
-- ------------------------------------------------------------
CREATE TABLE users (
    id                BIGSERIAL PRIMARY KEY,
    name              VARCHAR(255) NOT NULL,
    password          VARCHAR(255) NOT NULL,
    remember_token    VARCHAR(100),
    created_at        TIMESTAMP,
    updated_at        TIMESTAMP,

    nip               VARCHAR(255) UNIQUE,
    cpf               CHAR(11) UNIQUE,
    uri_legado        INTEGER UNIQUE,
    perfil            VARCHAR(255) NOT NULL DEFAULT 'padrao',
    setor_id          SMALLINT REFERENCES setores(id) ON DELETE RESTRICT,
    tentativas_login  SMALLINT NOT NULL DEFAULT 0,
    bloqueado_em      TIMESTAMPTZ,
    senha_temporaria  BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT users_perfil_check
        CHECK (perfil IN ('admin', 'gestor_setor', 'operador_setor', 'padrao')),
    -- quem nao e admin precisa de setor (inclui o perfil padrao)
    CONSTRAINT users_setor_perfil_check
        CHECK (perfil = 'admin' OR setor_id IS NOT NULL),
    -- perfil padrao sempre no PAPEM-41 (id 1)
    CONSTRAINT users_padrao_papem41_check
        CHECK (perfil <> 'padrao' OR setor_id = 1)
);
-- Regra na aplicacao: pelo menos um entre nip e cpf.

-- ------------------------------------------------------------
-- tipos_documento: pertencem a um setor
-- ------------------------------------------------------------
CREATE TABLE tipos_documento (
    id        SMALLSERIAL PRIMARY KEY,
    setor_id  SMALLINT NOT NULL REFERENCES setores(id) ON DELETE RESTRICT,
    nome      VARCHAR(255) NOT NULL,
    ativo     BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE (setor_id, nome)
);

-- ------------------------------------------------------------
-- arquivos: o conteudo fica dentro do banco
-- ------------------------------------------------------------
CREATE TABLE arquivos (
    id            BIGSERIAL PRIMARY KEY,
    sha256        CHAR(64) NOT NULL UNIQUE,
    conteudo      BYTEA NOT NULL,
    resid_legado  TEXT,
    extensao      VARCHAR(255),
    mime          VARCHAR(255),
    bytes         BIGINT,
    sobrescrito   BOOLEAN NOT NULL DEFAULT FALSE,
    criado_em     TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------
-- documentos
-- ------------------------------------------------------------
CREATE TABLE documentos (
    id                     BIGSERIAL PRIMARY KEY,
    uri_legado             BIGINT UNIQUE,
    record_id              VARCHAR(255) NOT NULL,
    titulo                 TEXT NOT NULL,
    titulo_legado_numerico BOOLEAN NOT NULL DEFAULT FALSE,

    setor_id               SMALLINT REFERENCES setores(id) ON DELETE RESTRICT,
    documento_pai_id       BIGINT REFERENCES documentos(id) ON DELETE RESTRICT,
    natureza               VARCHAR(20) NOT NULL DEFAULT 'documento',
    arquivo_id             BIGINT REFERENCES arquivos(id) ON DELETE SET NULL,
    tipo_documento_id      SMALLINT REFERENCES tipos_documento(id) ON DELETE SET NULL,
    local_arquivo_id       SMALLINT REFERENCES locais_arquivo(id) ON DELETE SET NULL,
    criado_por             BIGINT REFERENCES users(id) ON DELETE SET NULL,
    criado_por_legado      VARCHAR(255),
    nome_arquivo_original  TEXT,

    tipo_documento_texto   TEXT,
    consignado             TEXT,
    nip_matricula          TEXT,
    origem                 TEXT,
    numero_documento       TEXT,
    beneficiario           TEXT,
    protocolo              TEXT,
    cpf                    CHAR(11),
    entidade_consignataria TEXT,
    oficio_judicial_anexo  BOOLEAN NOT NULL DEFAULT FALSE,
    urgente                BOOLEAN NOT NULL DEFAULT FALSE,
    observacoes            TEXT,

    data_protocolo         DATE,
    data_criacao_legado    DATE,
    data_protocolo_legado  DATE,

    criado_em              TIMESTAMPTZ,
    registrado_em          TIMESTAMPTZ,
    atualizado_em          TIMESTAMPTZ,
    data_origem_invalida   BOOLEAN NOT NULL DEFAULT FALSE,

    CONSTRAINT documentos_natureza_check
        CHECK (natureza IN ('documento', 'processo', 'resposta', 'anexo'))
);

COMMENT ON COLUMN documentos.setor_id IS
    'Setor dono do documento; define quem o ve. Vazio: so o admin ve.';
COMMENT ON COLUMN documentos.documento_pai_id IS
    'Documento que contem este (anexo, resposta, documento dentro de processo). Vazio na maioria.';
COMMENT ON COLUMN documentos.titulo_legado_numerico IS
    'true quando o titulo antigo era so o numero do registro (defeito do legado).';
COMMENT ON COLUMN documentos.tipo_documento_texto IS
    'Tipo como digitado no legado, sem correcao. tipo_documento_id recebe o normalizado.';
COMMENT ON COLUMN documentos.criado_em IS
    'Data de inclusao (TSRECORD.CREATIONDATETIME). No legado vinha do relogio do navegador.';
COMMENT ON COLUMN documentos.registrado_em IS
    'Data do documento (TSRECORD.REGDATETIME). No legado vinha do relogio do navegador.';
COMMENT ON COLUMN documentos.data_origem_invalida IS
    'true quando alguma data do legado nao pode ser convertida; o valor fica em etl_rejeitos.';
COMMENT ON COLUMN documentos.criado_por_legado IS
    'Nome de usuario do legado que incluiu o documento (TSRECELEC.RENAMEURI, nome em TSLOCATION). Fica mesmo quando a conta antiga nao virou usuario novo.';
COMMENT ON COLUMN documentos.nome_arquivo_original IS
    'Nome do arquivo como o usuario o enviou (TSRECELEC.REFILENAME). Fica no documento porque o mesmo arquivo pode servir a mais de um. So da nome ao download, nunca e caminho.';

-- ------------------------------------------------------------
-- etl_rejeitos: sem FK de proposito
-- ------------------------------------------------------------
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
-- Indices
-- ============================================================
CREATE INDEX idx_user_perfil ON users(perfil);

CREATE INDEX idx_doc_nip         ON documentos(nip_matricula);
CREATE INDEX idx_doc_cpf         ON documentos(cpf);
CREATE INDEX idx_doc_protocolo   ON documentos(protocolo);
CREATE INDEX idx_doc_numero      ON documentos(numero_documento);
CREATE INDEX idx_doc_tipo        ON documentos(tipo_documento_id);
CREATE INDEX idx_doc_criado      ON documentos(criado_em);
CREATE INDEX idx_doc_setor_criado ON documentos(setor_id, criado_em DESC);
CREATE INDEX idx_doc_pai         ON documentos(documento_pai_id);

CREATE INDEX idx_doc_busca ON documentos
  USING GIN (to_tsvector('portuguese',
             coalesce(titulo,'') || ' ' ||
             coalesce(beneficiario,'') || ' ' ||
             coalesce(observacoes,'')));

-- So se a busca real exigir trecho no meio da palavra:
-- CREATE INDEX idx_doc_benef_trgm ON documentos USING GIN (beneficiario gin_trgm_ops);
