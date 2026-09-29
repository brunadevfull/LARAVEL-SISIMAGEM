# SISIMAGEM — Plano técnico

Cobre stack, segurança, regras de conversão, arquitetura, ordem de
construção e ETL. O schema não é repetido aqui: a fonte única é
`sisimagem-ddl.sql`, explicada em `sisimagem-dicionario-dados.md`,
`sisimagem-mer.md` e `sisimagem-diagrama-er.md`.

Estado: banco modelado (migrations 1 a 15; 1 a 8 aplicadas), Breeze
instalado, decisões de perfil, setor, login e arquivo fechadas.

---

## 1. Stack e convenções

| Camada | Escolha |
|---|---|
| Banco | PostgreSQL 16+, encoding UTF8 |
| Backend | PHP 8.3+, Laravel |
| Interface | Blade + Livewire + Alpine, sem API separada |
| CSS | Bootstrap 5 |
| Servidor web | Apache + PHP-FPM (`mod_proxy_fcgi`) |
| Arquivos | dentro do banco (`arquivos.conteudo`, bytea) |
| Fila | driver `database` |
| Autenticação | local, por NIP ou CPF e senha |
| Ambiente local | sem Docker |

- Tabelas e colunas em português; código em inglês.
- Todo Model declara `$table` (os nomes fogem do plural do Laravel) e usa
  `$timestamps = false`, exceto `User`.
- Um repositório, monolito. `main` protegida, uma branch por tarefa, merge
  por pull request.
- ETL isolado em `app/Console/Commands/Etl/`, sem usar os Models da
  aplicação.

## 2. Segurança: achados do legado e requisitos

O levantamento do código encontrou falhas concretas. Nada disso é
comportamento a replicar.

| Achado no legado | Requisito no sistema novo |
|---|---|
| SQL Injection: `DAOTrim.listaDocumento` monta o `WHERE` concatenando texto | Todo acesso a dados usa Eloquent ou Query Builder com binding |
| Path traversal: `OperacaoAbrirDocumento` abre o caminho recebido do cliente | O download recebe só o id do documento; o arquivo está no banco, não há caminho |
| Controle de acesso só visual: nenhuma classe `Operacao*` checa o perfil | Autorização no servidor: Policies e escopo de consulta |
| Senha de reset fixa ("marinha") | Senha temporária aleatória por usuário, nunca fixa |
| Quase nenhuma validação no servidor | Form Request em todo campo; o ETL trata todo valor do legado como não confiável |
| Contador de tentativas na sessão | Contador no banco (`tentativas_login`) |

## 3. Decisões de modelo

- Os 15 campos do TRIM que estavam em linhas viram colunas de `documentos`.
- `uri_legado` em quase toda tabela: torna o ETL repetível e conciliável.
- Um único setor por documento (`setor_id`); o responsável do legado não é
  trazido.
- Documentos formam árvore por `documento_pai_id`, com `natureza`
  (documento, processo, resposta, anexo).
- Arquivo dentro do banco e em tabela própria: 682 documentos não têm arquivo,
  a consulta comum não lê o binário, o Eloquent lê todas as colunas por padrão.
- Tipos de documento pertencem a um setor; "Ofício" e "CP" existem nos dois
  como cadastros distintos.
- `tipo_documento_texto` preserva o texto original; `tipo_documento_id` só
  recebe valor quando casa com a lista oficial.

## 4. Perfis, setor e visibilidade

| Perfil | Vê | Inclui | Edita/exclui |
|---|---|---|---|
| `admin` | tudo | qualquer tipo | tudo |
| `gestor_setor` | só o próprio setor | tipos do próprio setor | só do próprio setor |
| `operador_setor` | só o próprio setor | tipos do próprio setor | não |
| `padrao` | só PAPEM-41 | não | não |

- Visibilidade: `documentos.setor_id = users.setor_id`, exceto admin.
- Implementação: escopo global no Model `Documento` (toda consulta já sai
  filtrada) e Policies para incluir, editar e excluir. A rota de download
  confere o escopo antes de entregar o arquivo. Documento fora do escopo
  responde 404, para não revelar que existe.
- Documento novo: `setor_id` vem do usuário logado, `criado_por` também.
  O tipo escolhido precisa pertencer ao setor do usuário.
- Filho de um documento herda o setor do pai.
- Os grupos do legado (ADM, PAPEM40, SASM) só servem de entrada para a planilha
  de usuários. O SASM, que só consultava o PAPEM-41, corresponde ao `padrao`.
  Quem é gestor e quem é operador em cada setor o legado não diz; o
  PAPEM-40 informa.

## 5. Login e conta

- `LoginIdentifier` remove a pontuação e decide pelo tamanho: 8 ou 9 dígitos
  é NIP, 11 é CPF.
- Rate limiting do Breeze com chave baseada no identificador + IP; mensagem de
  erro genérica.
- 5 erros de senha bloqueiam a conta. Desbloquear é ação do admin e exige
  nova senha.
- `senha_temporaria` obriga a troca no primeiro acesso.
- Sem cadastro público, sem recuperação por e-mail, sem verificação de
  e-mail. O reset é manual, feito pelo admin.
- Sem dígito verificador de CPF/NIP por enquanto.

## 6. Regras de conversão (legado para banco novo)

**Datas de `TSRECORD` (`CHAR(15)`).** Formato `yyyyMMddHHmmss` mais um
caractere ignorado. Gravado em UTC; a tela do legado mostrava UTC−3. Usar
`America/Sao_Paulo`, não deslocamento fixo. `criado_em` vem de
`CREATIONDATETIME` e `registrado_em` de `REGDATETIME`; no legado as duas
vinham do relógio do navegador.

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

Há registros com ano 2148 e outros com data vazia; ambos viram vazio e vão
para `etl_rejeitos`.

**Datas dos campos customizados.** Texto livre: `dd/MM/yyyy`, `dd/MM/yy`, o
literal `1` (preenchimento) e lixo (`X-X-X-X`).

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
    if (!checkdate((int) $m, (int) $d, (int) $a)) return null;
    return sprintf('%04d-%02d-%02d', $a, $m, $d);
}
```

**Título.** O legado gravava o número do registro no lugar do título digitado.
Se `TITLE` for só dígitos, marcar `titulo_legado_numerico` e compor o título
com tipo, protocolo e beneficiário; sem nada disso, "Documento sem título".

**Tipo de documento.** Casar o texto bruto com a lista oficial do setor. O que
não casar fica só com o texto. Valores frequentes fora das duas listas, como
"Documento Antigo" (cerca de 89 mil), "Outros" e "Papeleta de Manufal",
dependem de decisão do PAPEM-40. Atenção ao acento corrompido (`OF¿CIO`,
`COMUNICA¿¿O`).

**Ofício judicial anexo.** "SIM" vira `true`; vazio vira `false`.

**CPF.** Só dígitos; se não tiver 11, vazio e `etl_rejeitos`.

**Nome original do arquivo.** `TSRECELEC.REFILENAME` vai para
`documentos.nome_arquivo_original`. O legado gravava o que o navegador enviou, e
navegador antigo manda o caminho completo do computador do usuário. Guardar só o
que vem depois da última barra (`\` ou `/`). Não conferi como os dados estão
no acervo; a regra cobre os dois casos. No download, o nome é só rótulo: passa
por limpeza (sem barras nem caracteres de controle) antes de ir no cabeçalho.

**NIP.** Sempre texto, nunca inteiro: existe nome de usuário de 8 dígitos com
zero à esquerda (07349327). Vale para o CSV do staging também.

**Setor e natureza.** Saem do tipo de registro (`TSRECTYPE`); a tabela está em
`sisimagem-dicionario-dados.md`. `TSRECLOC` tipo 1 só serve de conferência:
concorda em 100% dos 283 mil documentos que o têm.

**Pai.** `RCCONTAINERURI` casa com `uri_legado`. Os valores 0 e 1 são
preenchimento do legado; pais que são pastas viram vazio.

**Autor.** `TSRECELEC.RENAMEURI` aponta para `TSLOCATION`. O nome antigo vai
sempre para `criado_por_legado`. `criado_por` só é preenchido quando esse nome
foi casado, pela planilha do PAPEM-40, com um usuário novo (via
`users.uri_legado`). Nome vazio quer dizer sem autor; é o caso de cerca de 78%
dos documentos. Contas com código de seção (4145, 4223...) não viram usuário.

**Tipos Oracle para PostgreSQL.** `NUMBER(*,0)` para `BIGINT`;
`NVARCHAR2` para `TEXT`; `NCHAR` para `TEXT` com `TRIM` (o Oracle completa
com espaços); `CHAR(1)` `T`/`F` para `BOOLEAN`; `CHAR(15)` de data para
`TIMESTAMPTZ`. Garantir UTF8; o legado tem acento corrompido.

## 7. Arquitetura da aplicação

```
app/
  Models/        User, Setor, LocalArquivo, TipoDocumento, Arquivo, Documento
  Policies/      DocumentoPolicy, UserPolicy
  Support/       LoginIdentifier
  Http/Controllers/, Livewire/
  Console/Commands/Etl/     isolado; sai depois da migração
```

- Os Models da tabela `arquivos` nunca são carregados por padrão em telas:
  o download é a única rota que lê `conteudo`.
- Upload: calcula `sha256`, `mime` e tamanho; grava arquivo e documento na
  mesma transação. Se o hash já existe, reaproveita a linha de `arquivos`.
- O tipo do arquivo (`mime`) é descoberto pelo servidor, não pelo nome.

## 8. Ordem de construção

1. Autenticação: Breeze, perfis por setor, login por NIP ou CPF.
2. Models restantes, Policies e escopo de visibilidade.
3. Dados de teste em volume real (seeders).
4. Fatia 1: busca, resultado, tela do documento, download.
5. Fatia 2: inclusão com upload, anexos e respostas.
6. Administração: usuários (perfil, setor, desbloqueio, reset), tipos.
7. ETL de metadados, depois de arquivos.

Adiar: relatórios, workflow, API, auditoria (escopo pendente).

Testes desde o início: as funções puras (conversão de data, título,
`LoginIdentifier`).

## 9. ETL

Não copiar linha a linha. Duas paradas:

1. **Área de preparação (staging).** Exportar as tabelas do Oracle em CSV
   (UTF-8), na rede da PAPEM, e carregar no PostgreSQL com `COPY`, sem
   transformar. Os CSV têm CPF e nome de beneficiário e não saem da rede.
2. **Transformação** do staging para as tabelas finais, em SQL ou em comandos
   Artisan que chamam as funções de conversão acima.

Ordem: setores (duas linhas, à mão) e locais, tipos (seed dos 24 oficiais), usuários (a partir da
planilha do PAPEM-40), documentos com `documento_pai_id` vazio, segunda
passada do pai, arquivos, validação.

- Idempotente: `INSERT ... ON CONFLICT (uri_legado)`. A carga final ("delta")
  é exportar de novo e rodar tudo outra vez.
- Tudo que não converter vai para `etl_rejeitos`, com o valor bruto.
- Arquivos: ler do repositório copiado do servidor Windows, montar o caminho a
  partir do `RESID` (troca `+` por `/`; regra ainda não confirmada no
  servidor), calcular `sha256`, gravar em `arquivos.conteudo`. Marcar os 92
  duplicados como `sobrescrito`. Os 191 mil arquivos sem registro só são
  contados.
- Lotes: nunca um Model por linha.

Números de referência: Oracle com 427.065 documentos (o dump de teste tem
420.792), 426.383 com arquivo, 682 sem, 3.465.921 linhas de campos
customizados, 54,56 GB registrados em 617.664 arquivos.

Validação: contagem de documentos e de arquivos, documentos sem `setor_id`,
`uri_legado` duplicado, amostra de 50 documentos comparada com o legado,
proporção de `titulo_legado_numerico`, relatório de rejeitos.

## 10. Pendências

| Item | De quem depende |
|---|---|
| Planilha de usuários: nome antigo, NIP ou CPF, perfil, setor | PAPEM-40 |
| Lista oficial dos tipos e destino dos valores frequentes fora dela | PAPEM-40 |
| Significado de `consignado`; o que são os "Modelos PAPEM41" | PAPEM-40 |
| Contas de seção do legado (4145, 4223, 4212, 42s1, 42s2...): quem as usa | PAPEM-40 |
| Destino da auditoria | PAPEM-40 |
| Acesso de leitura ao servidor de arquivos; regra do `RESID` | Infraestrutura |
| Maior arquivo do acervo (limite de 1 GB por valor) e tamanho médio | consulta em `TSRECELEC.REBYTES` |
| Servidor Apache + PHP para publicação | Infraestrutura |
| Repactuação do cronograma formal | Chefia |
| Corte do ano de 2 dígitos nas datas | validação com o acervo |
| Charset corrompido: preservar ou normalizar | decisão sua |
