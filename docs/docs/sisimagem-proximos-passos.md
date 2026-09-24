# SISIMAGEM — Próximos passos de programação

Ordem recomendada. Não pule fase — cada uma depende da anterior estar
funcionando, não só "escrita".

## Fase A — Fechar a autenticação (Breeze)

1. Rodar o Breeze, se ainda não terminou: `composer require laravel/breeze --dev`
   → `php artisan breeze:install blade` → `npm install && npm run build` →
   `php artisan migrate`.
2. Testar login/registro genérico com `php artisan serve`, antes de qualquer
   customização — confirma que a base funciona.
3. Decidir sobre autocadastro. No legado, só o ADM cria usuário — não existe
   tela pública de registro. Recomendo desativar a rota `/register` do
   Breeze (comentar em `routes/auth.php`) assim que confirmar que o login
   funciona, para não abrir brecha que o sistema original nunca teve.
4. Customizar o `User` Model — adicionar `$fillable`/`$casts` para `nip`,
   `perfil`, `senha_temporaria`, `bloqueado_em`; criar métodos auxiliares
   tipo `isAdmin()`, `isSasm()`.
5. Ajustar a tela de login do Breeze
   (`resources/views/auth/login.blade.php`) se quiser trocar "email" por
   "NIP" como identificador — depende de como o PAPEM-40 confirmar que o
   login deve funcionar (email vs. matrícula).

## Fase B — Models e relacionamentos

6. Criar os 6 Models restantes: `Setor`, `LocalArquivo`, `TipoDocumento`,
   `Arquivo`, `Documento`, `EtlRejeito` — com `php artisan make:model
   NomeDoModel`.
7. Declarar relacionamentos Eloquent em cada um, batendo com as FKs:
   `Documento::arquivo()` (`belongsTo`), `Documento::tipoDocumento()`,
   `Documento::setorOrigem()`, `Documento::setorResponsavel()`,
   `Documento::localArquivo()`, `Documento::criadoPor()` (aponta pra
   `User`). Do lado inverso: `Arquivo::documentos()` (`hasMany`, por causa
   da deduplicação por hash).
8. Casts nos campos booleanos/data — `titulo_legado_numerico`, `urgente`,
   `data_origem_invalida` como `boolean`; as três colunas de data como
   `date`.

## Fase C — Autorização (corrige o bug do legado)

9. Criar `DocumentoPolicy` e `UserPolicy` (`php artisan make:policy`),
   implementando as regras reais: só `adm`/`papem40` incluem documento; só
   `adm` mexe em usuário.
10. Registrar as Policies (Laravel 11+ faz auto-discovery se o nome bater
    com o Model; senão, registrar manualmente).
11. Aplicar `$this->authorize(...)` em cada Controller/Livewire antes de
    qualquer ação — isso é o que o legado nunca fez, e é o achado de
    segurança mais grave que encontramos.
12. Criar um `Scope` de query para o perfil SASM — restringe a busca à
    coleção/setor PAPEM-41 automaticamente, nunca dependente do que o
    formulário envia.

## Fase D — Dados de teste em volume real

13. Criar Factories para `Documento`, `Setor`, `TipoDocumento`, etc.
    (`php artisan make:factory`).
14. Criar um Seeder que gere um volume próximo do real — a ideia é não
    descobrir problema de performance só na hora do ETL. Rodar em lote
    (`insert()` em blocos de 1000, não `Documento::factory()->create()` um
    por um, que derruba a memória em volume grande).

## Fase E — Fatia 1: login → busca → resultado → abrir documento

15. Rota e Livewire component de busca (`php artisan make:livewire
    BuscaDocumento`) — formulário com os campos reais (NIP, CPF, protocolo,
    tipo, datas), aplicando o Scope do SASM quando for o caso.
16. Tela de resultado com paginação — usar paginação do próprio
    Livewire/Eloquent, não recriar na mão.
17. Rota de abrir/baixar documento — recebe só `documento_id`, nunca
    caminho de arquivo do cliente (é a correção direta do path traversal
    que achamos no legado). Resolve o `arquivo_id` no servidor, serve via
    `Storage`.
18. Teste manual com usuário de cada perfil — criar 4 usuários de teste (um
    por perfil) via seeder e confirmar na prática que cada um vê só o que
    deveria.

## Fase F — Fatia 2: inclusão de documento

19. Livewire component de upload, com validação server-side em todos os
    campos (o legado não validava quase nada — não repetir isso).
20. Serviço `ArmazenamentoArquivo` — comece pela implementação em disco
    (mais simples), calculando `sha256` no upload para dedupe automática.
21. Envolver gravação do registro + do arquivo em transação — corrige o bug
    do legado que deixava arquivo órfão em disco quando o banco dava
    rollback.
22. Caminho de arquivo novo por hash, não mais sequencial — elimina de vez
    a possibilidade dos "92 arquivos sobrescritos" se repetir.

## Depois disso (não é "próximo passo" ainda)

ETL de metadados (fase 3), ETL de arquivos (fase 4) e administração
(fase 5) só entram depois que as fatias 1 e 2 estiverem estáveis — é o que
o `CLAUDE.md` já registra como ordem obrigatória, para não se perder
programando fora de sequência.
