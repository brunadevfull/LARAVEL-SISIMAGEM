# SISIMAGEM — Plano de desenvolvimento

## Contexto em uma página

O SISIMAGEM é uma aplicação Java (Servlet/JSP) que acessa diretamente o banco
Oracle do TRIM, produto comercial de gestão documental hoje desativado. Com o
produto fora, os dados permanecem em formatos internos dele. As regras de
leitura foram extraídas do código Java e estão documentadas abaixo.

### Números do acervo

| | |
|---|---|
| Documentos | 427.065 |
| Com arquivo digitalizado | 426.383 |
| Sem arquivo | 682 |
| Volume de arquivos | 54,56 GB |
| Arquivos no repositório (segundo o banco) | 617.664 |
| Usuários | 235 |
| Repositório | `D:\HPTRIM - Repositorios\Eterno\` |

### Stack definida

| Camada | Escolha |
|---|---|
| Banco | PostgreSQL 16+ |
| Backend | PHP 8.3+, Laravel |
| Interface | Blade + Livewire + Alpine |
| CSS | Bootstrap 5 |
| Servidor web | Apache + PHP-FPM (`mod_proxy_fcgi`) |
| Arquivos | `Storage` do Laravel |
| Fila | driver `database` |
| Autenticação | local (senha no banco), redefinição forçada no cutover |

### Convenções

- Tabelas e colunas em português. Código em inglês.
- Um repositório, monolito Laravel.
- `main` protegida. Branch por tarefa, pull request, merge feito por Bruna.
- Docker para o ambiente local.
- ETL isolado em `app/Console/Commands/Etl/`, sem usar os Models da aplicação.
  Sai do projeto num commit único quando a migração terminar.

---

## Riscos e achados a reportar

Problemas pré-existentes, revelados pelo levantamento. Não são causados pela
migração.

1. **92 arquivos sobrescritos.** O legado gera nome sequencial contando
   arquivos na pasta. Arquivo apagado faz o próximo upload reutilizar o número.
   Perda já consumada.
2. **682 documentos sem arquivo associado.**
3. **191.281 arquivos a mais no disco** do que registros apontando para arquivo.
   Pendente de verificação no servidor.
4. **Datas do acervo antigo perdidas.** Os campos "Data de Criação (Legado)" e
   "Data de Protocolo (Legado)" contêm majoritariamente o literal `1`,
   preenchimento sem significado herdado de migração anterior.
5. **10.578 variações de "Tipo de Documento"** por digitação livre, incluindo
   acentuação corrompida (`OF¿CIO`, `COMUNICA¿¿O PADRONIZADA`).
6. **`REMODIFIEDDATETIME` corrompido.** O legado grava com máscara
   `YYYYMMDDHHMMSS`, onde `MM` em Oracle é mês, não minuto. Não usar como
   referência temporal.
7. **Credenciais em texto claro** no código-fonte versionado.

---

## Regras de conversão extraídas do legado

### Datas do TSRECORD

`CHAR(15)` no formato `yyyyMMddHHmmss` + 1 caractere ignorado. Banco em UTC,
exibição em UTC−3.

```php
$dt = DateTime::createFromFormat('YmdHis', substr($v, 0, 14), new DateTimeZone('UTC'));
$dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
```

Usar `America/Sao_Paulo`, não offset fixo. O acervo atravessa anos com horário
de verão.

### Datas dos campos customizados

Texto livre. Formatos encontrados: `dd/MM/yyyy`, `dd/MM/yy`, o literal `1`
(placeholder) e lixo (`X-X-X-X`).

```php
function converterDataLegado(?string $v): ?string {
    $v = trim((string) $v);

    if ($v === '' || $v === '1' || !preg_match('#^\d{2}/\d{2}/\d{2,4}$#', $v)) {
        return null;
    }

    [$d, $m, $a] = explode('/', $v);

    if (strlen($a) === 2) {
        $a = ((int) $a <= 30) ? '20'.$a : '19'.$a;   // suposição a validar
    }

    if (!checkdate((int) $m, (int) $d, (int) $a)) {
        return null;
    }

    return "$a-$m-$d";
}
```

Todo retorno `null` vai para `etl_rejeitos` com o valor original. Nunca
inventar data.

### Caminho do arquivo

```
RESID   = 001+AAAAMM+NNNNNNNNNN.ext
caminho = <base>/001/AAAAMM/NNNNNNNNNN.ext
```

Trocar `+` por separador de diretório. `<base>` vem do `context-param path` do
`web.xml`.

**Isto é inferência do código, não observação.** Precisa ser confirmado contra
o servidor de arquivos antes de escrever o ETL de binários.

### Locais (TSRECLOC)

Quatro registros por documento, um de cada tipo:

| Tipo | Significado |
|---|---|
| 0 | local de arquivamento |
| 1 | setor de origem |
| 2 | setor responsável |
| 3 | constante "Gerente" — descartar |

`TSLOCATION` mistura pessoas e lugares. No modelo novo, separar em `setores` e
`locais_arquivo`.

### Conversão de tipos Oracle → PostgreSQL

| Oracle | PostgreSQL |
|---|---|
| `NUMBER(*,0)` | `BIGINT` |
| `NVARCHAR2(n)` | `TEXT` |
| `NCHAR(n)` | `CHAR(n)` — aplicar `TRIM` no ETL |
| `CHAR(1)` com `T`/`F` | `BOOLEAN` |
| `CHAR(15)` de data | `TIMESTAMPTZ` |

Garantir encoding `UTF8` no PostgreSQL. Há evidência de confusão de charset no
histórico do sistema.

---

## Modelo de dados

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
    id          SMALLSERIAL PRIMARY KEY,
    nome        TEXT NOT NULL UNIQUE,
    ativo       BOOLEAN DEFAULT TRUE
);

CREATE TABLE arquivos (
    id            BIGSERIAL PRIMARY KEY,
    sha256        CHAR(64) UNIQUE,
    resid_legado  TEXT,
    extensao      TEXT,
    mime          TEXT,
    bytes         BIGINT,
    caminho       TEXT,      -- se ficar em disco
    conteudo      BYTEA,     -- se ficar no banco
    sobrescrito   BOOLEAN DEFAULT FALSE
);

CREATE TABLE documentos (
    id                     BIGSERIAL PRIMARY KEY,
    uri_legado             BIGINT UNIQUE NOT NULL,
    record_id              TEXT NOT NULL,
    titulo                 TEXT NOT NULL,
    arquivo_id             BIGINT REFERENCES arquivos(id),

    tipo_documento_texto   TEXT,      -- original preservado
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
    urgente                BOOLEAN DEFAULT FALSE,
    observacoes            TEXT,

    data_protocolo         DATE,
    data_criacao_legado    DATE,
    data_protocolo_legado  DATE,

    criado_em              TIMESTAMPTZ,
    registrado_em          TIMESTAMPTZ,
    data_origem_invalida   BOOLEAN DEFAULT FALSE
);

CREATE TABLE etl_rejeitos (
    id           BIGSERIAL PRIMARY KEY,
    uri_legado   BIGINT,
    campo        TEXT,
    valor_bruto  TEXT,
    motivo       TEXT,
    criado_em    TIMESTAMPTZ DEFAULT now()
);

CREATE INDEX idx_doc_nip       ON documentos(nip_matricula);
CREATE INDEX idx_doc_cpf       ON documentos(cpf);
CREATE INDEX idx_doc_protocolo ON documentos(protocolo);
CREATE INDEX idx_doc_numero    ON documentos(numero_documento);
CREATE INDEX idx_doc_busca     ON documentos
  USING GIN (to_tsvector('portuguese',
             coalesce(titulo,'') || ' ' || coalesce(beneficiario,'')));
```

Decisões de projeto:

- `uri_legado` em tudo, com índice único. Permite rodar o ETL várias vezes sem
  duplicar e reconciliar depois.
- Os 15 campos customizados viram colunas. O EAV de 3,4 milhões de linhas
  colapsa em 427 mil linhas.
- `caminho` e `conteudo` ambos nuláveis. A escolha entre disco e banco fica
  aberta até conhecer o número real de arquivos.
- `tipo_documento` em dois campos: texto original preservado e chave
  normalizada, preenchida quando houver mapeamento.

---

## Ordem de construção

Construir por fatia vertical completa, não por camada. Uma fatia inteira
funcionando vale mais que todos os models prontos.

| Fatia | Conteúdo | Depende de |
|---|---|---|
| 1 | Login → busca → resultado → abrir documento | nada |
| 2 | Inclusão de documento com upload | fatia 1 |
| 3 | ETL de metadados | modelo validado pelo uso |
| 4 | ETL de arquivos | acesso ao diretório |
| 5 | Administração: usuários, tipos, setores | lista canônica de tipos |

A fatia 1 cobre cerca de 90% do uso real do sistema.

Adiar sem culpa: relatórios, workflow, perfis granulares de permissão
(começar com dois: usuário e administrador), API, cobertura ampla de testes.

Testar desde o início apenas as funções puras do ETL: conversão de data,
derivação de caminho, normalização de tipo. São de alto risco e vão mudar
várias vezes.

---

## Passos para Bruna

### Semana 1

1. Subir projeto Laravel com Docker (PostgreSQL 16 + PHP 8.3)
2. Criar repositório, proteger `main`, definir padrão de branch e commit
3. Escrever as migrations das 7 tabelas
4. Criar seeders com **400 mil documentos falsos** — volume próximo do real
5. Implementar autenticação local (Laravel Breeze com Blade)
6. Montar a tela de busca funcionando sobre os dados falsos

Sobre o volume dos seeders: busca que funciona com 20 linhas e trava com 400
mil é armadilha que só aparece no ETL.

### Semana 2

7. Tela de resultado da pesquisa com paginação
8. Visualizador de documento
9. Converter as telas entregues pelo Militar 2
10. Fechar a fatia 1 e apresentar

### Em paralelo, sem esperar

- Pedir acesso de leitura ao diretório de arquivos
- Pedir provisionamento do servidor Apache + PHP
- Levar os achados de risco à chefia

O servidor é o item com maior risco de virar gargalo. Provisionamento
institucional demora. Pedir mesmo sem ter o que publicar.

---

## Passos para o Militar 1 — documentação e dados

Trabalho sem código, no caminho crítico, que ninguém mais pode fazer.

### Tarefa 1 — Inventário das telas atuais

Para cada tela do SISIMAGEM em produção, produzir uma página com:

- Captura da tela
- Lista de todos os campos, com nome exato como aparece
- Quais campos são obrigatórios
- Quais validações existem (formato de CPF, tamanho máximo, etc.)
- Mensagens de erro que aparecem
- O que cada botão faz e para onde leva

Telas a documentar: login, menu, pesquisar documento, resultado da pesquisa,
visualizar documento, incluir documento, lista de usuários, cadastro de
usuário.

Este documento vira o critério de aceitação do sistema novo. É o único
registro do comportamento atual.

### Tarefa 2 — Lista canônica de tipos de documento

Planilha com duas colunas: valor bruto e tipo canônico.

Começar pelos 60 valores mais frequentes, que já cobrem a maior parte do
acervo. Exemplo do que agrupar:

| Valor bruto | Tipo canônico |
|---|---|
| `Ofício Judicial`, `OFÍCIO JUDICIAL`, `Oficio Judicial`, `OFICIO JUDICIAL` | Ofício Judicial |
| `OFI`, `OFICIO`, `OF`, `OF¿CIO`, `OFÍCIO` | Ofício |
| `Comunicação Padronizada`, `COMUNICACAO PADRONIZADA`, `CP`, `COM. PADRONIZADA` | Comunicação Padronizada |

Fazer com o PAPEM-40. São eles que sabem quais tipos existem de fato.

Nota: `Documento Antigo` e `Outros` não são tipo, são ausência de
classificação. Marcar como "Não classificado".

### Tarefa 3 — Entrevistas com o PAPEM-40

Perguntas objetivas:

- Por quais campos vocês pesquisam de fato no dia a dia?
- Quais telas ninguém usa?
- O que mais incomoda no sistema atual?
- Por que o campo CPF está preenchido em apenas 4,7% dos documentos?
- O fluxo de digitalização por scanner ainda é usado? (o legado chama o
  `MSPSCAN.EXE`, software da Microsoft descontinuado)
- Os históricos de auditoria precisam ser preservados por exigência normativa?

### Tarefa 4 — Depois, homologação

Rodar o mesmo caso de uso no sistema legado e no novo, comparar resultado,
registrar divergências.

---

## Passos para o Militar 2 — telas

Não é necessário saber programar. O trabalho é HTML e CSS com Bootstrap.

### Preparação

1. Instalar VS Code e Git
2. Estudar o básico de Bootstrap 5 (grid, tabela, formulário, botão, alerta) —
   a documentação oficial tem exemplos prontos para copiar
3. Aprender quatro comandos de Git: `clone`, `branch`, `commit`, `push`

### Como funciona a entrega

Bruna entrega a primeira tela pronta, já funcionando, como modelo. As demais
seguem o mesmo padrão visual.

Para cada tela, entregar um arquivo `.html` com:

- Layout completo em Bootstrap
- Dados inventados escritos à mão (nomes fictícios, protocolos inventados)
- Nenhuma lógica, nenhum PHP, nenhum JavaScript

Exemplo do que entregar:

```html
<table class="table">
  <thead>
    <tr><th>Protocolo</th><th>Beneficiário</th><th>Tipo</th></tr>
  </thead>
  <tbody>
    <tr><td>12345</td><td>João da Silva</td><td>Ofício Judicial</td></tr>
    <tr><td>12346</td><td>Maria Souza</td><td>Requerimento</td></tr>
  </tbody>
</table>
```

Bruna converte para o formato do sistema trocando as linhas de exemplo por uma
repetição automática. O HTML, as classes e o visual permanecem intactos.

### Ordem das telas

Uma por vez. Revisar com os usuários antes de passar para a próxima.

| Ordem | Tela | Observação |
|---|---|---|
| 1 | Login | padrão definido por Bruna |
| 2 | Menu principal | |
| 3 | Pesquisar documento | formulário com os campos de busca |
| 4 | Resultado da pesquisa | tabela com paginação visual |
| 5 | Lista de usuários | |
| 6 | Cadastro de usuário | |
| 7 | Tipos de documento | tela administrativa |
| 8 | Incluir documento | só o layout; a lógica é de Bruna |

A tela de visualização de documento fica com Bruna — depende do visualizador
de PDF e TIFF.

### Limite do que é delegável

O Militar 2 entrega a aparência de cada tela. O comportamento — paginação real,
filtro, upload, validação — é de Bruna.

Depois que a tela entra no sistema, pequenos ajustes visuais continuam
possíveis para ele, com uma explicação curta do que não pode ser alterado.

---

## Decisão pendente antes de começar

**As telas novas replicam o legado ou é oportunidade de redesenhar?**

Recomendação: réplica funcional na versão 1, com melhorias óbvias de
usabilidade. Redesenho amplo numa versão 2.

Migração e redesenho simultâneos é onde se perde prazo, e elimina a
possibilidade de comparar comportamento na homologação.

---

## Pendências externas

| Item | De quem depende | Bloqueia |
|---|---|---|
| Acesso de leitura ao diretório de arquivos | infraestrutura | fatia 4 |
| Servidor Apache + PHP | infraestrutura | publicação |
| Lista canônica de tipos | PAPEM-40 | fatia 5 |
| Destino dos históricos de auditoria | PAPEM-40 | escopo do ETL |
| Substituto do fluxo de digitalização | PAPEM-40 | fatia 2 |

Nenhuma delas bloqueia as fatias 1 e 2.
