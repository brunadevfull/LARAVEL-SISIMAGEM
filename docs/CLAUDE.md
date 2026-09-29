# SISIMAGEM — contexto do projeto

Leia antes de gerar código. Se este arquivo, os documentos em `docs/` e o
código divergirem, aponte a divergência antes de alterar.

## O que é

Reescrita do zero, em Laravel, do SISIMAGEM, sistema de gestão documental da
PAPEM (Marinha do Brasil). O sistema atual é Java/JSP sobre Oracle, acessando
as tabelas do produto TRIM, hoje desativado.

## Fonte de verdade

| Assunto | Arquivo |
|---|---|
| Estrutura do banco | `docs/sisimagem-ddl.sql` |
| Significado das colunas | `docs/sisimagem-dicionario-dados.md` |
| Entidades e ligações | `docs/sisimagem-mer.md`, `docs/sisimagem-diagrama-er.md` |
| Stack, segurança, ETL | `docs/sisimagem-plano-tecnico.md` |

Os arquivos em `docs/docs/`, `docs/files/` e na pasta `migrations/` da raiz são
cópias antigas. Não use.

## Stack

PHP 8.3+, Laravel 13, PostgreSQL 16, Blade + Livewire + Alpine, Bootstrap 5.
Sem API separada, sem SPA, sem Docker no desenvolvimento.

## Convenções

- Banco em português; código em inglês.
- Todo Model declara `$table`: `Setor` = `setores`, `LocalArquivo` =
  `locais_arquivo`, `TipoDocumento` = `tipos_documento`.
- Só `users` tem `created_at`/`updated_at`. Os outros Models usam
  `$timestamps = false`.
- ETL em `app/Console/Commands/Etl/`, sem usar os Models da aplicação.
- SQL sempre com binding (Eloquent ou Query Builder). O sistema atual tem SQL
  injection confirmado.
- Autorização no servidor (Policy e escopo de consulta), nunca só escondendo
  botão.

## Migrations

- Migration já aplicada não se edita. Correção de estrutura vai em migration
  nova.
- Não rode `php artisan migrate` sem autorização.
- Estado alvo: migrations 1 a 15.

## Login e conta

- Sem e-mail. Entra com NIP (8 ou 9 dígitos) ou CPF (11 dígitos), só dígitos,
  e senha. `nip` é sempre texto: pode começar com zero.
- Sem cadastro público, sem "esqueci a senha", sem verificação de e-mail. Só o
  admin cria usuário e redefine senha.
- 5 erros de senha bloqueiam a conta (`tentativas_login`, `bloqueado_em`).
- `senha_temporaria` obriga a troca no próximo acesso. Nunca senha fixa ou
  escrita no código.

## Perfis e setor

| Perfil | Setor | Vê | Inclui | Edita e exclui |
|---|---|---|---|---|
| `admin` | nenhum | tudo | qualquer tipo | tudo |
| `gestor_setor` | PAPEM-41 ou 42 | o próprio setor | tipos do próprio setor | o próprio setor |
| `operador_setor` | PAPEM-41 ou 42 | o próprio setor | tipos do próprio setor | não |
| `padrao` | sempre PAPEM-41 | o PAPEM-41 | não | não |

- Setores com id fixo: 1 = PAPEM-41, 2 = PAPEM-42.
- Visibilidade: `documentos.setor_id = users.setor_id`, exceto admin.
- O `setor_id` do documento novo vem do usuário logado, nunca do formulário.
- `perfil`, `setor_id`, `senha_temporaria`, `bloqueado_em` e
  `tentativas_login` nunca entram por mass assignment.
- Helpers do User: `isAdmin()`, `isGestorSetor()`, `isOperadorSetor()`,
  `isPadrao()`. Os nomes antigos (`isSasm`, `isPapem40`, perfil `adm`) não
  existem mais.

## Documentos e arquivos

- Um único setor por documento (`setor_id`). Não existe setor responsável.
- `documento_pai_id` liga anexo, resposta e documento de processo ao pai.
  `natureza`: `documento`, `processo`, `resposta` ou `anexo`.
- Arquivo dentro do banco (`arquivos.conteudo`, bytea), nunca em disco. O
  download recebe só o id do documento e confere o setor.
- `nome_arquivo_original` só dá nome ao download; nunca é caminho.
- `criado_por_legado` guarda o nome do autor no sistema atual; `criado_por` só
  é preenchido quando esse autor virou usuário novo.
- `titulo` pode vir como número do registro (defeito do sistema atual); veja
  `titulo_legado_numerico`.
- `uri_legado` é só para o ETL.

## Ordem de construção

(1) login, busca, resultado, download; (2) inclusão com upload; (3) ETL de
metadados; (4) ETL de arquivos; (5) administração. Estamos na fatia 1. Não
avance para ETL ou administração sem pedido.

## Não fazer

- Tabela de auditoria sem pedido (escopo pendente).
- Reset de senha por e-mail.
- Senha "marinha" ou qualquer senha fixa.
- Arquivo em disco.
