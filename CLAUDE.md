# SISIMAGEM — contexto do projeto

Leia isto antes de gerar qualquer código. Se algo aqui conflitar com o que
foi pedido na conversa, pergunte antes de assumir.

## O que é

Reescrita do SISIMAGEM, sistema de gestão documental (GED) da PAPEM (Marinha
do Brasil). O legado é Java/Servlet/JSP acessando direto o schema interno de
um produto de terceiros (TRIM/OpenText), hoje desativado. Este projeto é uma
reescrita completa do zero — não é adoção de outro produto pronto.

Especificação completa em `docs/sisimagem-plano-tecnico.md`. Este arquivo é
só o resumo que toda sessão deve ter em mente.

## Stack (decidida, não reabrir sem motivo)

- PHP 8.3+, Laravel
- PostgreSQL 16+
- Blade + Livewire + Alpine — sem API separada, sem SPA
- Bootstrap 5
- Autenticação local (sem LDAP/AD)
- Sem Docker no ambiente de desenvolvimento

## Convenções

- **Tabelas e colunas do banco em português.** Código (classes, métodos,
  variáveis) em inglês. Ex.: `class Documento` com
  `protected $table = 'documentos'`.
- Scripts de ETL ficam isolados em `app/Console/Commands/Etl/`, nunca usam
  os Models da aplicação, e serão removidos do projeto quando a migração de
  dado terminar.
- Nunca concatenar valor de usuário em SQL — sempre Eloquent ou Query
  Builder com binding. (O legado tinha SQL Injection confirmado; não
  repetir.)
- Nunca aceitar caminho de arquivo vindo do cliente. Toda operação de
  arquivo resolve o caminho no servidor a partir do `id`, nunca de um
  parâmetro recebido. (O legado tinha path traversal confirmado.)
- Autorização sempre no servidor (Policy/Gate do Laravel), nunca só
  escondendo elemento na tela.

## Perfis de usuário

`adm`, `papem40`, `sasm`, `padrao` — mapeados dos grupos reais do legado.
Regras em `docs/sisimagem-plano-tecnico.md`, seção "Perfis".

## Schema

7 tabelas: `users` (estendida), `setores`, `locais_arquivo`,
`tipos_documento`, `arquivos`, `documentos`, `etl_rejeitos`. DDL de
referência em `docs/sisimagem-ddl.sql`, diagrama em
`docs/sisimagem-diagrama-er.md`.

## Ordem de construção

Fatias verticais completas, não por camada:

1. Login → busca → resultado → abrir documento
2. Inclusão de documento com upload
3. ETL de metadados (Oracle → PostgreSQL)
4. ETL de arquivos
5. Administração (usuários, tipos, setores)

Estamos na fatia 1. Não avance para ETL ou administração sem pedido
explícito.

## Coisas para nunca inventar

- Não crie tabela de auditoria sem pedido — escopo ainda pendente com a
  chefia.
- Não implemente reset de senha por e-mail — decidido reset manual pelo
  Administrador.
- Não use a string `"marinha"` como senha padrão em nenhum lugar — era bug
  de segurança do legado.
- Campo `titulo` de documento pode vir corrompido do legado (numérico) — ver
  `titulo_legado_numerico` no schema antes de assumir que o dado é limpo.
