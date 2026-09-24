# SISIMAGEM — Plano técnico

Escopo deste documento: modelo de dados, arquitetura da aplicação e ETL.
Gestão, divisão de tarefas e comunicação institucional estão em documento
separado.

---

## 1. Stack

| Camada | Escolha | Observação |
|---|---|---|
| Banco | PostgreSQL 16+ | encoding UTF8, locale pt_BR |
| Backend | PHP 8.3+, Laravel | |
| Interface | Blade + Livewire + Alpine | um codebase, sem API separada |
| CSS | Bootstrap 5 | |
| Servidor web | Apache + PHP-FPM | `mod_proxy_fcgi`, não `mod_php` |
| Arquivos | `Storage` do Laravel | abstrai destino |
| Fila | driver `database` | evita depender de Redis |
| Autenticação | local, Laravel Breeze com Blade | hash Whirlpool do legado não migra |
| Ambiente local | Docker | |

### Convenções

- Tabelas e colunas em português, código em inglês.
  `class Documento` com `protected $table = 'documentos'`.
- Um repositório, monolito.
- `main` protegida, branch por tarefa, merge via pull request.
- ETL isolado em `app/Console/Commands/Etl/`, sem usar os Models da aplicação,
  com a conexão Oracle declarada apenas ali.

---

## Segurança — achados do legado e requisitos não-funcionais

Levantamento de código encontrou falhas concretas, com linha localizada. Não
são comportamento a replicar — são requisitos de correção obrigatória no
sistema novo.

### SQL Injection confirmado

`DAOTrim.listaDocumento` monta a cláusula `WHERE` por concatenação direta de
string, em pelo menos três métodos de busca:

```java
sql = sql + " and " + chave + " like '%" + mCamposPesquisa.get(chave) + "%' ";
```

Qualquer campo de pesquisa aceita SQL arbitrário. **Requisito:** todo acesso a
dado no sistema novo passa por Eloquent ou Query Builder com binding de
parâmetro. Nunca concatenar valor de usuário em SQL, nem em raw query.

### Path traversal em download de arquivo

`OperacaoAbrirDocumento.doPost()` recebe o parâmetro `nomeDocumento` do
cliente e abre esse caminho diretamente no disco, sem checar se pertence à
pasta configurada. Um usuário autenticado pode ler qualquer arquivo acessível
ao processo do servidor.

**Requisito:** a tela de abrir/baixar documento nunca recebe caminho do
cliente. Recebe só `documento_id` (ou `arquivo_id`), resolve o caminho no
servidor a partir da FK, e serve via `Storage`. Isso já era a direção do
modelo (`arquivo_id` como referência), e agora tem motivo de segurança
explícito para nunca abrir exceção.

### Controle de acesso hoje é só cosmético

Nenhuma classe `Operacao*` do legado valida o grupo do usuário antes de
executar uma ação, com exceção do filtro de pesquisa do grupo SASM. O menu
apenas esconde botões — qualquer usuário autenticado pode chamar
`cmd=incluirUsuario`, `cmd=excluirUsuario` ou `cmd=incluirDocumento`
diretamente, independente do perfil mostrado na tela.

**Requisito:** autorização enforced no servidor via Laravel Policies/Gates,
nunca só ocultando elemento de interface. Ver seção de perfis abaixo.

### Senha de reset não deve seguir a convenção do legado

O legado usa a senha literal `"marinha"` como valor de reset hardcoded — se o
usuário digita esse valor e autentica, é forçado a trocar. É previsível e
está exposta no código-fonte.

**Requisito:** gerar senha temporária aleatória por usuário na migração dos
235 usuários. Preservar o comportamento de "senha temporária força troca no
primeiro acesso", mas nunca com valor fixo ou previsível.

### Ausência quase total de validação server-side no legado

`validacoes-server-side.md` confirma: fora da checagem de usuário duplicado
em `OperacaoIncluirUsuario` e do filtro de perfil do SASM, nenhuma operação
valida formato, tamanho ou obrigatoriedade no servidor. Mesmo campos
marcados com `*` na tela podem chegar vazios ao banco. Isso explica parte da
sujeira de dado encontrada na análise (títulos numéricos, tipos com 10 mil
variações, datas fora de formato) — o servidor sempre aceitou o que chegasse.

**Requisito:** todo campo do sistema novo é validado no servidor (Form
Request do Laravel), independente de validação client-side. O ETL trata
todo valor vindo do legado como não confiável por padrão, não só os campos
já identificados como problemáticos.

---

## 2. Modelo de dados

### DDL

```sql
CREATE TABLE setores (
    id          SMALLSERIAL PRIMARY KEY,
    uri_legado  INTEGER UNIQUE,
    nome        TEXT NOT NULL
);

CREATE TABLE locais_arquivo (
    id          SMALLSERIAL PRIMARY KEY,
    uri_legado  INTEGER UNIQUE,
    nome        TEXT NOT NULL
);

CREATE TABLE tipos_documento (
    id     SMALLSERIAL PRIMARY KEY,
    nome   TEXT NOT NULL UNIQUE,
    ativo  BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE arquivos (
    id            BIGSERIAL PRIMARY KEY,
    sha256        CHAR(64) UNIQUE,
    resid_legado  TEXT,
    extensao      TEXT,
    mime          TEXT,
    bytes         BIGINT,
    caminho       TEXT,
    conteudo      BYTEA,
    sobrescrito   BOOLEAN NOT NULL DEFAULT FALSE,
    criado_em     TIMESTAMPTZ NOT NULL DEFAULT now()
);

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

-- Extensão da tabela `users` (criada pelo Laravel Breeze)
ALTER TABLE users
    ADD COLUMN nip              TEXT UNIQUE,
    ADD COLUMN uri_legado       INTEGER UNIQUE,
    ADD COLUMN perfil           TEXT NOT NULL DEFAULT 'padrao'
        CHECK (perfil IN ('adm', 'papem40', 'sasm', 'padrao')),
    ADD COLUMN tentativas_login SMALLINT NOT NULL DEFAULT 0,
    ADD COLUMN bloqueado_em     TIMESTAMPTZ,
    ADD COLUMN senha_temporaria BOOLEAN NOT NULL DEFAULT TRUE;

CREATE TABLE etl_rejeitos (
    id           BIGSERIAL PRIMARY KEY,
    uri_legado   BIGINT,
    tabela       TEXT,
    campo        TEXT,
    valor_bruto  TEXT,
    motivo       TEXT,
    criado_em    TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### Índices

```sql
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
```

Para busca por trecho no meio da palavra (`LIKE '%texto%'`), o índice GIN de
texto não serve. Se for necessário, habilitar `pg_trgm`:

```sql
CREATE EXTENSION pg_trgm;
CREATE INDEX idx_doc_benef_trgm ON documentos USING GIN (beneficiario gin_trgm_ops);
```

Avaliar só se o uso real exigir. Índice trigram é grande e encarece escrita.

### Decisões de projeto

**`uri_legado` único em todas as tabelas.** Torna o ETL idempotente
(`ON CONFLICT ... DO UPDATE`) e permite reconciliar depois da migração.

**Os 15 campos customizados viram colunas.** O EAV de 3.465.921 linhas colapsa
em 427.065 linhas. Todos os campos são pesquisáveis e nenhum é raro o
suficiente para justificar JSON.

**`tipo_documento` em dois campos.** `tipo_documento_texto` preserva o original
(10.578 variações); `tipo_documento_id` recebe o valor canônico quando houver
mapeamento. Permite migrar sem perda e normalizar depois.

**`caminho` e `conteudo` ambos nuláveis em `arquivos`.** A escolha entre disco
e banco fica aberta. Com 54,56 GB, BLOB é operável e elimina a necessidade de
sincronizar dois backups; filesystem é o caminho de menor mudança. Decidir
após conhecer o número real de arquivos no disco.

**`sha256` único.** Deduplica automaticamente. Em acervo de pagadoria o mesmo
documento anexado a vários processos é comum.

**`local_3` do legado descartado.** Tem valor constante ("Gerente") em 273 mil
registros. É constante, não informação.

### Perfis (correção do controle de acesso do legado)

O legado tem três grupos com lógica real — `ADM`, `PAPEM40`, `SASM` — mais um
comportamento residual para qualquer outro grupo cadastrado. Mapeamento
adotado:

| Legado | Novo (`perfil`) | Comportamento |
|---|---|---|
| ADM | `adm` | Acesso total: cadastro de usuário, inclusão de documento |
| PAPEM40 | `papem40` | Inclusão de documento; sem cadastro de usuário |
| SASM | `sasm` | Só pesquisa, restrita automaticamente à coleção PAPEM-41 |
| qualquer outro | `padrao` | Só pesquisa sem restrição, e a própria senha |

No legado essa regra é só de interface (achado de segurança acima). No
sistema novo, cada regra vira uma `Policy`:

```php
class DocumentoPolicy {
    public function create(User $user): bool {
        return in_array($user->perfil, ['adm', 'papem40']);
    }
}

class UserPolicy {
    public function create(User $user): bool {
        return $user->perfil === 'adm';
    }
}
```

A restrição do SASM à coleção PAPEM-41 vira `Scope` aplicado na query de
busca quando `$user->perfil === 'sasm'`, nunca dependente do que o front
envia.

**Bloqueio de conta.** O legado bloqueia após 5 tentativas
(`tentativas_login`), seta o equivalente a `bloqueado_em`, e o desbloqueio é
manual pelo ADM. Preservado no modelo acima. Melhoria adotada em relação ao
legado: desbloqueio força redefinição de senha (o legado não força).

---

## 3. Regras de conversão

### Datas do TSRECORD

`CHAR(15)` no formato `yyyyMMddHHmmss` + 1 caractere ignorado. Banco em UTC,
exibição em UTC−3.

```php
function converterDataTrim(?string $v): ?string {
    $v = substr(trim((string) $v), 0, 14);
    if (!preg_match('/^\d{14}$/', $v)) return null;

    $dt = DateTime::createFromFormat('YmdHis', $v, new DateTimeZone('UTC'));
    if (!$dt) return null;

    $ano = (int) $dt->format('Y');
    if ($ano < 1980 || $ano > (int) date('Y') + 1) return null;

    $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    return $dt->format('Y-m-d H:i:sP');
}
```

Usar `America/Sao_Paulo`, não offset fixo. O acervo atravessa anos com horário
de verão. A validação de ano existe porque há registros com data inválida
(ano 2148 observado).

### Datas dos campos customizados

Texto livre. Formatos encontrados: `dd/MM/yyyy`, `dd/MM/yy`, o literal `1`
(placeholder de migração anterior) e lixo (`X-X-X-X`).

```php
function converterDataLegado(?string $v): ?string {
    $v = trim((string) $v);

    if ($v === '' || $v === '1' || !preg_match('#^\d{2}/\d{2}/\d{2,4}$#', $v)) {
        return null;
    }

    [$d, $m, $a] = explode('/', $v);

    if (strlen($a) === 2) {
        $a = ((int) $a <= 30) ? '20'.$a : '19'.$a;   // suposição, validar
    }

    if (!checkdate((int) $m, (int) $d, (int) $a)) return null;

    return sprintf('%04d-%02d-%02d', $a, $m, $d);
}
```

A regra de corte do ano de dois dígitos é suposição. O documento mais antigo
observado é de 1989.

Todo retorno `null` grava em `etl_rejeitos` com o valor original.

### Caminho do arquivo

```
RESID   = 001+AAAAMM+NNNNNNNNNN.ext
caminho = <base>\001\AAAAMM\NNNNNNNNNN.ext
```

```php
function caminhoDoResid(string $resid, string $base): string {
    return $base . DIRECTORY_SEPARATOR
         . str_replace('+', DIRECTORY_SEPARATOR, trim($resid));
}
```

Inferência extraída de `Utilitaria.montaNomeArquivo` do legado. **Não
observada.** Validar contra o servidor antes do ETL de binários.

Ressalvas: o prefixo `001` está fixo no código; acervo com outro prefixo
seguiria regra diferente.

### Título do documento — provável dado corrompido

`OperacaoIncluirDocumento` nunca grava o texto digitado no campo "Título do
Documento" — falta o `case` correspondente em `Utilitaria.setDocumento`. O
legado grava o ID numérico do registro como título, silenciosamente, para
todo documento incluído por essa tela.

Verificar extensão antes do ETL:

```sql
SELECT COUNT(*) AS titulo_numerico
FROM TRIM.TSRECORD
WHERE REGEXP_LIKE(TRIM(TITLE), '^[0-9]+$');
```

Se a maioria for numérica, `documentos.titulo` não pode depender de
`TSRECORD.TITLE`. Regra de fallback no ETL:

```php
function tituloEfetivo(string $tituloOriginal, array $campos): string {
    if (preg_match('/^\d+$/', trim($tituloOriginal))) {
        return trim(implode(' - ', array_filter([
            $campos['tipo_documento'] ?? null,
            $campos['protocolo'] ?? null,
            $campos['beneficiario'] ?? null,
        ])) ?: 'Documento sem título');
    }
    return $tituloOriginal;
}
```

O título original entra preservado em coluna própria
(`titulo_legado_numerico BOOLEAN`) para rastreabilidade, sem inventar dado
novo além da composição de campos já existentes no próprio registro.

### Locais (TSRECLOC)

Quatro registros por documento, um de cada tipo:

| Tipo | Destino |
|---|---|
| 0 | `local_arquivo_id` |
| 1 | `setor_origem_id` |
| 2 | `setor_responsavel_id` |
| 3 | descartar (constante) |

Registros com tipo nulo (83.559) e tipo 4 (59) são exceção, não caso principal.

### Tipos Oracle → PostgreSQL

| Oracle | PostgreSQL | Cuidado |
|---|---|---|
| `NUMBER(*,0)` | `BIGINT` | |
| `NVARCHAR2(n)` | `TEXT` | |
| `NCHAR(n)` | `TEXT` | aplicar `TRIM` — Oracle preenche com espaços |
| `CHAR(1)` `T`/`F` | `BOOLEAN` | |
| `CHAR(15)` data | `TIMESTAMPTZ` | ver regra acima |

Há evidência de charset corrompido no acervo (`OF¿CIO`,
`COMUNICA¿¿O PADRONIZADA`). Definir política: preservar como está, ou
normalizar o caractere perdido no ETL.

---

## 4. Arquitetura da aplicação

### Estrutura

```
app/
  Models/
    Documento.php
    Arquivo.php
    TipoDocumento.php
    Setor.php
    LocalArquivo.php
    User.php
  Http/Controllers/
    DocumentoController.php
    ArquivoController.php
    Admin/
  Policies/
    DocumentoPolicy.php
    UserPolicy.php
  Livewire/
    BuscaDocumento.php
    UploadDocumento.php
  Services/
    BuscaService.php
    ArmazenamentoArquivo.php
  Console/Commands/Etl/          ← isolado, removido após a migração
    MigrarSetores.php
    MigrarTiposDocumento.php
    MigrarDocumentos.php
    MigrarArquivos.php
    Support/
      ConversorData.php
      ResolvedorCaminho.php
```

### Separação ETL / aplicação

| | ETL | Aplicação |
|---|---|---|
| Vida útil | roda algumas vezes e sai | anos |
| Conexões | Oracle + PostgreSQL | só PostgreSQL |
| Acesso a dados | SQL em lote | Eloquent |

Misturar significa carregar para sempre código que nunca mais roda e manter
Oracle como dependência eterna.

### Armazenamento de arquivo

Encapsular em `ArmazenamentoArquivo`, com interface única:

```php
interface ArmazenamentoArquivo {
    public function guardar(string $conteudo, string $sha256): array;
    public function obter(Arquivo $a): string;
}
```

Duas implementações: `ArmazenamentoDisco` e `ArmazenamentoBanco`. A escolha
vira configuração, não refatoração.

No disco, abandonar o esquema sequencial do legado (causa dos 92 arquivos
sobrescritos) e usar caminho derivado do hash:

```
storage/documentos/a3/f5/a3f5e8....pdf
```

Determinístico, sem colisão, sem contagem de diretório.

---

## 5. Ordem de construção

Fatias verticais completas, não camadas.

| Fatia | Conteúdo | Depende de |
|---|---|---|
| 1 | Login → busca → resultado → abrir documento | nada |
| 2 | Inclusão com upload | fatia 1 |
| 3 | ETL de metadados | modelo validado pelo uso |
| 4 | ETL de arquivos | acesso ao diretório |
| 5 | Administração: usuários, tipos, setores | lista canônica de tipos |

A fatia 1 cobre a maior parte do uso real.

### Semana 1

1. Laravel + Docker + PostgreSQL 16 + PHP 8.3
2. Repositório, `main` protegida, padrão de branch
3. Migrations das 7 tabelas
4. Seeders com 400 mil documentos falsos
5. Autenticação local (Breeze/Blade)
6. Tela de busca funcionando sobre os dados falsos

O volume dos seeders importa. Busca que funciona com 20 linhas e trava com
400 mil é armadilha que só aparece no ETL.

### Semana 2

7. Resultado da pesquisa com paginação
8. Visualizador de documento (PDF e TIFF)
9. Fechar a fatia 1

### Adiar

Relatórios, workflow, perfis granulares (começar com usuário e administrador),
API, cobertura ampla de testes.

### Testar desde o início

Apenas as funções puras do ETL: `converterDataTrim`, `converterDataLegado`,
`caminhoDoResid`, normalização de tipo. São de alto risco, fáceis de testar e
vão mudar várias vezes.

---

## 6. ETL

### Princípios

- Idempotente. Rodar duas vezes produz o mesmo resultado.
- Em lote. Nunca instanciar Eloquent por registro: 427 mil objetos derrubam a
  memória. Usar `DB::table()->insert()` em blocos de 1.000 a 5.000.
- Tudo que não converter vai para `etl_rejeitos`. Nunca inventar valor.
- Relatório ao final: lidos, gravados, rejeitados por motivo.

### Ordem

1. `setores` e `locais_arquivo` (de `TSLOCATION` + `TSRECLOC`)
2. `tipos_documento` (lista canônica; pode começar vazia)
3. `documentos` (de `TSRECORD` + `TSEXFIELDV` pivotado + `TSRECLOC`)
4. `arquivos` (de `TSRECELEC` + varredura do disco)
5. Vínculo `documentos.arquivo_id`

### Pivotar o EAV

3.465.921 linhas viram 427.065. Fazer no Oracle, não no PHP:

```sql
SELECT r.URI, r.RECORDID, r.TITLE, r.CREATIONDATETIME, r.REGDATETIME,
       MAX(CASE WHEN v.EVFIELDURI = 1  THEN v.EVFIELDVAL END) AS beneficiario,
       MAX(CASE WHEN v.EVFIELDURI = 2  THEN v.EVFIELDVAL END) AS consignado,
       MAX(CASE WHEN v.EVFIELDURI = 3  THEN v.EVFIELDVAL END) AS cpf,
       MAX(CASE WHEN v.EVFIELDURI = 4  THEN v.EVFIELDVAL END) AS data_protocolo,
       MAX(CASE WHEN v.EVFIELDURI = 5  THEN v.EVFIELDVAL END) AS entidade,
       MAX(CASE WHEN v.EVFIELDURI = 7  THEN v.EVFIELDVAL END) AS numero_documento,
       MAX(CASE WHEN v.EVFIELDURI = 8  THEN v.EVFIELDVAL END) AS observacoes,
       MAX(CASE WHEN v.EVFIELDURI = 9  THEN v.EVFIELDVAL END) AS origem,
       MAX(CASE WHEN v.EVFIELDURI = 10 THEN v.EVFIELDVAL END) AS protocolo,
       MAX(CASE WHEN v.EVFIELDURI = 11 THEN v.EVFIELDVAL END) AS tipo_documento,
       MAX(CASE WHEN v.EVFIELDURI = 12 THEN v.EVFIELDVAL END) AS nip_matricula,
       MAX(CASE WHEN v.EVFIELDURI = 13 THEN v.EVFIELDVAL END) AS data_criacao_leg,
       MAX(CASE WHEN v.EVFIELDURI = 14 THEN v.EVFIELDVAL END) AS data_protocolo_leg,
       MAX(CASE WHEN v.EVFIELDURI = 15 THEN v.EVFIELDVAL END) AS oficio_judicial,
       MAX(CASE WHEN v.EVFIELDURI = 17 THEN v.EVFIELDVAL END) AS urgente
FROM TRIM.TSRECORD r
LEFT JOIN TRIM.TSEXFIELDV v ON v.EVOBJECTURI = r.URI
GROUP BY r.URI, r.RECORDID, r.TITLE, r.CREATIONDATETIME, r.REGDATETIME;
```

Paginar por faixa de `URI` para não montar o resultado inteiro em memória.

Observação: `EVFIELDVAL` é `NVARCHAR2(255)`. Valores longos transbordam para
`TSNOTES` com `ntTableId = 100` e o campo fica com um marcador. Com
`TSNOTES` em apenas 1.299 linhas, o impacto é pequeno, mas verificar antes de
descartar.

### Validação pós-carga

| Verificação | Esperado |
|---|---|
| `COUNT(*)` documentos | 427.065 |
| `COUNT(*)` com arquivo | 426.383 |
| Documentos sem `uri_legado` | 0 |
| `uri_legado` duplicado | 0 |
| Soma por tipo de documento | bate com a origem |
| Amostra de 50 documentos | comparação campo a campo com o legado |
| Proporção de `titulo_legado_numerico = true` | reportar; esperado ser alto |

---

## 7. Decisões pendentes

| Item | Bloqueia | Depende de |
|---|---|---|
| Arquivo em disco ou em banco | fatia 4 | contagem real no servidor |
| Política para charset corrompido | ETL | decisão sua |
| Corte do ano de 2 dígitos | ETL | validação com o acervo |
| Lista canônica de tipos | fatia 5 | PAPEM-40 |
| Destino dos históricos de auditoria | escopo do ETL | PAPEM-40 |
| Substituto do fluxo de digitalização — confirmado sem versão funcional no legado (três tentativas abandonadas); decidir se entra no escopo do sistema novo | fatia 2 | PAPEM-40 |

Resolvidas nesta revisão: perfis reais (ADM/PAPEM40/SASM/padrão), regra de
bloqueio de conta (5 tentativas), convenção de senha de reset, e a
necessidade de tratamento do campo título corrompido.

Nenhuma pendência bloqueia as fatias 1 e 2.
