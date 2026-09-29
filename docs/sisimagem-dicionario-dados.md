# SISIMAGEM: Dicionário de dados

Banco de dados do novo SISIMAGEM. Versão de 28/09/2026.

Cada tabela do banco novo, coluna por coluna. Quando o dado vem do sistema atual (Oracle/TRIM), a origem está indicada.

## 1. Legenda

| Marca | Significado |
|---|---|
| PK | Chave primária. Identifica cada linha da tabela e nunca se repete nem fica vazia. |
| FK | Chave estrangeira. Liga a linha a outra tabela e só aceita um valor que exista na tabela indicada. |
| UK | Valor único. Não pode se repetir entre as linhas da tabela. |
| NOT NULL | Sim = sempre tem valor. Não = pode ficar vazio. |

bigint, smallint, integer: número inteiro. varchar, text: texto. boolean: verdadeiro/falso. date: data. timestamp: data e hora. bytea: arquivo (binário).

## 2. Usuários (tabela `users`)

Quem acessa o sistema. Entra com NIP ou CPF e senha; não há e-mail.

**Chave primária:** `id`. **Chaves estrangeiras:** `setor_id` → `setores`.

| Coluna | Tipo | Chave | NOT NULL | Descrição |
|---|---|---|---|---|
| `id` | bigint | PK | Sim | Identificador interno do usuário. |
| `name` | varchar |  | Sim | Nome do usuário, usado para exibição. |
| `password` | varchar |  | Sim | Senha guardada de forma criptografada; não reaproveita a do sistema atual. |
| `remember_token` | varchar |  | Não | Recurso da ferramenta de desenvolvimento para manter o usuário conectado. |
| `created_at` | timestamp |  | Não | Data e hora em que o cadastro foi criado. |
| `updated_at` | timestamp |  | Não | Data e hora da última alteração do cadastro. |
| `nip` | varchar | UK | Não | Número de identificação do militar (NIP), com 8 ou 9 dígitos. Serve para entrar no sistema. É guardado como texto porque pode começar com zero. |
| `cpf` | char(11) | UK | Não | CPF, somente com dígitos. Também serve para entrar no sistema. Todo usuário deve ter NIP, CPF ou os dois, e a aplicação confere isso. |
| `uri_legado` | integer | UK | Não | Número do usuário no Oracle do sistema atual (TSLOCATION.URI). Usado apenas na migração de dados. |
| `perfil` | varchar |  | Sim | Papel do usuário: admin, gestor_setor, operador_setor ou padrao. O padrao é sempre do PAPEM-41. |
| `setor_id` | smallint | FK | Não | Setor em que o usuário atua. Obrigatório, exceto para admin. No perfil padrao é sempre o PAPEM-41. Aponta para `setores`. Ao apagar a linha apontada, o banco recusa a exclusão. |
| `tentativas_login` | smallint |  | Sim | Número de erros de senha seguidos. Volta a zero quando o usuário acerta. |
| `bloqueado_em` | timestamp |  | Não | Data e hora em que a conta foi bloqueada, depois de 5 erros de senha seguidos. Fica vazia enquanto a conta está liberada. |
| `senha_temporaria` | boolean |  | Sim | Quando verdadeiro, obriga o usuário a trocar a senha no próximo acesso. |

### O que cada perfil pode fazer

| Perfil | Vê | Inclui | Edita e exclui |
|---|---|---|---|
| `admin` | todos os setores | qualquer tipo | tudo |
| `gestor_setor` | só o próprio setor | tipos do próprio setor | só o próprio setor |
| `operador_setor` | só o próprio setor | tipos do próprio setor | não |
| `padrao` | só o PAPEM-41, seu setor | não | não |

## 3. Setores (tabela `setores`)

PAPEM-41 (id 1) e PAPEM-42 (id 2), criados pela própria migration com id fixo. Não é possível apagar um setor com usuários, tipos ou documentos ligados.

**Chave primária:** `id`. **Chaves estrangeiras:** nenhuma.

| Coluna | Tipo | Chave | NOT NULL | Descrição |
|---|---|---|---|---|
| `id` | smallint | PK | Sim | Identificador interno do setor. |
| `uri_legado` | integer | UK | Não | Não é usada. Os dois setores são cadastrados manualmente e não têm correspondência no Oracle. |
| `nome` | varchar | UK | Sim | Nome do setor: PAPEM-41 ou PAPEM-42. |

## 4. Locais de arquivo (tabela `locais_arquivo`)

Onde o documento está guardado, ou a situação dele, como “Lixeira”.

**Chave primária:** `id`. **Chaves estrangeiras:** nenhuma.

| Coluna | Tipo | Chave | NOT NULL | Descrição |
|---|---|---|---|---|
| `id` | smallint | PK | Sim | Identificador interno do local. |
| `uri_legado` | integer | UK | Não | Número do local no Oracle do sistema atual (TSLOCATION.URI). |
| `nome` | varchar |  | Sim | Nome do local. |

## 5. Tipos de documento (tabela `tipos_documento`)

Classificação do documento. 24 tipos oficiais: 21 no PAPEM-41 e 3 no PAPEM-42.

**Chave primária:** `id`. **Chaves estrangeiras:** `setor_id` → `setores`.

| Coluna | Tipo | Chave | NOT NULL | Descrição |
|---|---|---|---|---|
| `id` | smallint | PK | Sim | Identificador interno do tipo. |
| `setor_id` | smallint | FK | Sim | Setor dono do tipo. Ofício e Comunicação Padronizada existem nos dois setores, como cadastros separados. Aponta para `setores`. Ao apagar a linha apontada, o banco recusa a exclusão. |
| `nome` | varchar |  | Sim | Nome do tipo. Não se repete dentro do mesmo setor. |
| `ativo` | boolean |  | Sim | Quando falso, o tipo deixa de ser usado sem que o histórico seja apagado. |

## 6. Arquivos (tabela `arquivos`)

O arquivo digital, guardado dentro do banco. Sem caminho em disco; o download usa o id do documento.

**Chave primária:** `id`. **Chaves estrangeiras:** nenhuma.

| Coluna | Tipo | Chave | NOT NULL | Descrição |
|---|---|---|---|---|
| `id` | bigint | PK | Sim | Identificador interno do arquivo. |
| `sha256` | char(64) | UK | Sim | Impressão digital do conteúdo (hash SHA-256). Arquivos idênticos geram o mesmo valor e são guardados uma só vez. |
| `conteudo` | bytea |  | Sim | O arquivo digitalizado (PDF ou TIFF), guardado dentro do banco. |
| `resid_legado` | text |  | Não | Nome do arquivo no sistema atual, no formato 001+AAAAMM+número.extensão. Origem: TSRECELEC.RESID. |
| `extensao` | varchar |  | Não | Extensão do arquivo, como pdf ou tif. |
| `mime` | varchar |  | Não | Tipo do arquivo, identificado pelo servidor no momento do envio. |
| `bytes` | bigint |  | Não | Tamanho do arquivo em bytes. |
| `sobrescrito` | boolean |  | Sim | Verdadeiro nos 92 casos em que o sistema atual gravou um arquivo por cima de outro. |
| `criado_em` | timestamp |  | Sim | Data e hora em que o registro foi criado. |

## 7. Documentos (tabela `documentos`)

Registro central. Reúne o antigo TSRECORD e os 15 campos que ficavam em TSEXFIELDV.

**Chave primária:** `id`. **Chaves estrangeiras:** `setor_id` → `setores`; `documento_pai_id` → `documentos`; `arquivo_id` → `arquivos`; `tipo_documento_id` → `tipos_documento`; `local_arquivo_id` → `locais_arquivo`; `criado_por` → `users`.

### Identificação

| Coluna | Tipo | Chave | NOT NULL | Descrição |
|---|---|---|---|---|
| `id` | bigint | PK | Sim | Identificador interno do documento. |
| `uri_legado` | bigint | UK | Não | Número do documento no Oracle do sistema atual. Permite repetir a migração sem duplicar registros. Origem: TSRECORD.URI. |
| `record_id` | varchar |  | Sim | Número do registro. Na tela do sistema atual aparece como Processo. Origem: TSRECORD.RECORDID. |
| `titulo` | text |  | Sim | Título do documento. Origem: TSRECORD.TITLE. |
| `titulo_legado_numerico` | boolean |  | Sim | Verdadeiro quando o título do sistema atual era apenas o número do registro, por um defeito que descartava o título digitado. |
| `natureza` | varchar(20) |  | Sim | Papel do registro: documento, processo, resposta ou anexo. Origem: tipo de registro (TSRECTYPE). |
| `criado_por_legado` | varchar |  | Não | Nome de usuário do sistema atual que incluiu o documento, mesmo quando essa conta não virou usuário novo. Origem: TSRECELEC.RENAMEURI, nome em TSLOCATION.LCNAME. |
| `nome_arquivo_original` | text |  | Não | Nome do arquivo como o usuário o enviou. Fica no documento porque o mesmo arquivo pode servir a mais de um. Nunca é usado como caminho. Origem: TSRECELEC.REFILENAME. |

### Vínculos com outras tabelas

| Coluna | Tipo | Chave | NOT NULL | Descrição |
|---|---|---|---|---|
| `setor_id` | smallint | FK | Não | Setor dono do documento. Define quem pode vê-lo. Origem: tipo de registro (TSRECTYPE), cujo nome indica o setor. Aponta para `setores`. Ao apagar a linha apontada, o banco recusa a exclusão. |
| `documento_pai_id` | bigint | FK | Não | Documento que contém este: anexo, resposta ou documento dentro de um processo. Fica vazio em cerca de 92% dos documentos. Origem: TSRECORD.RCCONTAINERURI. Aponta para `documentos`. Ao apagar a linha apontada, o banco recusa a exclusão. |
| `arquivo_id` | bigint | FK | Não | Arquivo digital do documento. Fica vazio nos 682 documentos sem arquivo. Origem: TSRECELEC. Aponta para `arquivos`. Ao apagar a linha apontada, o campo fica vazio. |
| `tipo_documento_id` | smallint | FK | Não | Tipo normalizado do documento. Preenchido quando o texto digitado corresponde à lista oficial do setor. Aponta para `tipos_documento`. Ao apagar a linha apontada, o campo fica vazio. |
| `local_arquivo_id` | smallint | FK | Não | Local em que o documento está guardado. Origem: TSRECLOC, tipo 0. Aponta para `locais_arquivo`. Ao apagar a linha apontada, o campo fica vazio. |
| `criado_por` | bigint | FK | Não | Usuário novo que incluiu o documento, quando o autor do sistema atual virou usuário novo. Origem: TSRECELEC.RENAMEURI, ligado a users.uri_legado. Aponta para `users`. Ao apagar a linha apontada, o campo fica vazio. |

### Campos de conteúdo

| Coluna | Tipo | Chave | NOT NULL | Descrição |
|---|---|---|---|---|
| `tipo_documento_texto` | text |  | Não | Tipo exatamente como foi digitado no sistema atual, sem correção. Origem: Tipo de Documento. |
| `beneficiario` | text |  | Não | Pessoa beneficiária. Origem: Beneficiário. |
| `consignado` | text |  | Não | Significado a confirmar com o PAPEM-40. Origem: Consignado. |
| `cpf` | char(11) |  | Não | CPF, somente com dígitos. Preenchido em cerca de 5% dos documentos. Origem: CPF. |
| `nip_matricula` | text |  | Não | NIP ou matrícula. Origem: NIP/Matrícula. |
| `origem` | text |  | Não | De onde veio o documento. Origem: Origem. |
| `numero_documento` | text |  | Não | Número do documento. Origem: Número do Documento. |
| `protocolo` | text |  | Não | Número de protocolo. Origem: Protocolo. |
| `entidade_consignataria` | text |  | Não | Entidade consignatária. Origem: Entidade Consignatária. |
| `oficio_judicial_anexo` | boolean |  | Sim | Indica se veio ofício judicial anexado. O padrão é falso. Origem: Ofício Judicial Anexo. |
| `urgente` | boolean |  | Sim | Indica documento urgente. O padrão é falso. Origem: Urgente. |
| `observacoes` | text |  | Não | Observações em texto livre. Origem: OBSERVAÇÕES. |

### Datas e controle

| Coluna | Tipo | Chave | NOT NULL | Descrição |
|---|---|---|---|---|
| `data_protocolo` | date |  | Não | Data do protocolo. Origem: Data de Protocolo. |
| `data_criacao_legado` | date |  | Não | Data de criação em sistema anterior ao TRIM. Quase sempre vinha só “1”, sem data real. Origem: Data de Criação (Legado). |
| `data_protocolo_legado` | date |  | Não | Mesmo caso, para a data de protocolo. Origem: Data de Protocolo (Legado). |
| `criado_em` | timestamp |  | Não | Data de inclusão do documento. Podia ter erro de hora no sistema atual. Origem: TSRECORD.CREATIONDATETIME. |
| `registrado_em` | timestamp |  | Não | Data do documento, a “Data do Documento” da tela antiga. Origem: TSRECORD.REGDATETIME. |
| `atualizado_em` | timestamp |  | Não | Data e hora da última alteração. |
| `data_origem_invalida` | boolean |  | Sim | Verdadeiro quando alguma data do sistema atual não pôde ser convertida. O valor original fica em etl_rejeitos. |

## 8. Rejeitos da migração (tabela `etl_rejeitos`)

Guarda o valor original do que não pôde ser convertido. Sem chave estrangeira: precisa aceitar dado inválido demais para virar documento.

**Chave primária:** `id`. **Chaves estrangeiras:** nenhuma.

| Coluna | Tipo | Chave | NOT NULL | Descrição |
|---|---|---|---|---|
| `id` | bigint | PK | Sim | Identificador do registro. |
| `uri_legado` | bigint |  | Não | Número do registro de origem no Oracle. |
| `tabela` | text |  | Não | Tabela do Oracle de onde veio o valor. |
| `campo` | text |  | Não | Campo com problema. |
| `valor_bruto` | text |  | Não | O valor exatamente como estava no sistema atual. |
| `motivo` | text |  | Não | Por que o valor foi rejeitado. |
| `criado_em` | timestamp |  | Sim | Data e hora em que o registro foi criado. |

## 9. Restrições e índices

| Onde | Regra | O que faz |
|---|---|---|
| `users.perfil` | valores permitidos | Só aceita admin, gestor_setor, operador_setor e padrao. |
| `users` (`perfil` e `setor_id`) | regra combinada | Quem não é admin precisa ter setor. |
| `users` (`perfil` e `setor_id`) | regra combinada | O perfil padrao só aceita o setor PAPEM-41 (id 1). |
| `setores.nome` | valor único | O nome do setor não se repete. |
| `users.nip`, `users.cpf`, `users.uri_legado` | valor único | Não se repetem. |
| `tipos_documento` (`setor_id` e `nome`) | valor único | O mesmo nome não se repete dentro do setor. |
| `arquivos.sha256` | valor único | O mesmo conteúdo é guardado uma só vez. |
| `documentos.uri_legado` | valor único | A migração não duplica documentos. |
| `documentos.natureza` | valores permitidos | Só aceita documento, processo, resposta e anexo. |
| `documentos` (`nip_matricula`, `cpf`, `protocolo`, `numero_documento`, `tipo_documento_id`, `criado_em`) | índice | Acelera buscas exatas e a ordenação. |
| `documentos` (`setor_id` e `criado_em`) | índice | Acelera a listagem filtrada por setor e ordenada por data. |
| `documentos.documento_pai_id` | índice | Acelera a busca dos filhos de um documento. |
| `documentos` (`titulo`, `beneficiario`, `observacoes`) | índice de texto em português | Permite buscar por palavra ignorando flexões: “documento” encontra “documentos”. |

## 10. Como a migração define setor, natureza, pai e autor

Setor e natureza vêm do tipo de registro do sistema atual (TSRECTYPE). Só 8 documentos ficam sem setor, e apenas o administrador os vê.

| Tipo de registro no sistema atual | Documentos | Setor | Natureza |
|---|---|---|---|
| Documentos PAPEM41 - LEGADO | 273 mil | PAPEM-41 | documento |
| Documentos PAPEM41 | 91 mil | PAPEM-41 | documento |
| Anexos PAPEM41 | 81 | PAPEM-41 | anexo |
| Documento de Resposta PAPEM-41 | 18 | PAPEM-41 | resposta |
| Modelos PAPEM41 | 65 | PAPEM-41 | documento, até o PAPEM-40 dizer o que são |
| Processos PAPEM42 | 28 mil | PAPEM-42 | processo |
| Documentos PAPEM42 | 11 mil | PAPEM-42 | documento |
| Documento de Resposta PAPEM-42 | 24 mil | PAPEM-42 | resposta |
| Document, Pastas PAPEM40, Folder e vazio | 8 | sem setor | revisão manual |

Os dois setores são criados pela migration do banco, com id fixo. A migração de dados não os importa do Oracle.

O documento pai vem do campo RCCONTAINERURI, ligado a `uri_legado`.

O autor vem de TSRECELEC.RENAMEURI. Cerca de 78% dos documentos não têm autor no sistema atual.

## 11. O que não é trazido do sistema atual

- Setor responsável do documento (TSRECLOC, tipo 2).
- Setor de origem (TSRECLOC, tipo 1) como fonte do setor. Serve só de conferência.
- Número de páginas do arquivo (TSRECELEC.RENBRPAGES), por decisão.
- Registro de local de tipo 3, que é a constante “Gerente” em 273 mil registros.
- Arquivos do repositório sem documento correspondente (191 mil), que são apenas contados.

## 12. Pendências

- `consignado`: o significado de negócio depende do PAPEM-40.
- Modelos PAPEM41 (65 registros): confirmar o que são e qual natureza têm.
- `registrado_em`: o nome sugere registro, mas a coluna guarda a data do documento. Renomear para `data_documento` é opcional e ainda não foi decidido.
- Usuários: 235 (238 no conjunto analisado) é o total da tabela TSLOCATION, que mistura pessoas, setores e locais. As estatísticas do Oracle indicam cerca de 94 contas de login (TSLOCLOGIN), o que ainda precisa ser confirmado.
- Contas de seção: 4145, 4223, 4212, 42s1, 42s2 e outras aparecem como autoras de milhares de documentos. O PAPEM-40 precisa esclarecer quem as usa.

## 13. Glossário

| Termo | Significado |
|---|---|
| Oracle e TRIM | Banco de dados e produto de gestão documental sobre os quais o sistema atual funciona. |
| ETL | Processo que lê os dados do Oracle, converte e grava no banco novo. O nome aparece na tabela `etl_rejeitos`. |
| URI | Número que o sistema atual dava a cada linha de suas tabelas. Aqui aparece como `uri_legado`. |
| NIP | Número de identificação do militar na Marinha. |
| Hash (SHA-256) | Sequência de 64 caracteres calculada a partir do conteúdo de um arquivo. Conteúdo igual gera hash igual. |
| Binário | O conteúdo de um arquivo, em bytes, guardado em coluna do tipo bytea. |
| Índice | Estrutura que acelera a busca por uma coluna, como o índice remissivo de um livro. |
