# SISIMAGEM — Próximos passos de programação

Ordem recomendada. Cada fase depende da anterior estar funcionando, não só
escrita. Para cada tarefa com o Claude Code: pedir o plano primeiro, revisar,
só então aprovar.

## Feito

- Ambiente de desenvolvimento, projeto Laravel, banco PostgreSQL
- Migrations 1 a 8 aplicadas
- Breeze instalado e testado

## Fase A. Banco

1. `php artisan migrate` para aplicar as migrations 9, 10, 12, 13, 14 e 15 (e a 11, criada
   pelo prompt de login). Conferir com `php artisan migrate:status`.
2. Copiar o dicionário, o DDL, o DER e o `CLAUDE.md` novos para o
   repositório.

## Fase B. Autenticação e perfis (Claude Code)

3. Perfis e setor: Model `User` (helpers, casts, `setor()`), Model `Setor`,
   testes, `CLAUDE.md`.
4. Login por NIP ou CPF: migration 11, `LoginIdentifier`, remoção do que
   dependia de e-mail (esqueci a senha, verificação, campo no perfil),
   seeder sem senha escrita no código.
5. Testar de ponta a ponta com um usuário de cada perfil.

## Fase C. Models e autorização

6. Models `Documento`, `Arquivo`, `TipoDocumento`, `LocalArquivo`, com `$table`
   explícito, `$timestamps = false` e relacionamentos (incluindo pai e
   filhos).
7. `DocumentoPolicy`, `UserPolicy` e o escopo global de visibilidade por
   setor. Toda ação passa por `authorize`.

## Fase D. Dados de teste

8. Seeder dos dois setores e dos 24 tipos oficiais.
9. Usuários de teste, um por perfil, com senha lida do `.env`.
10. Documentos falsos em volume próximo do real, inserção em lote. Incluir
    arquivos pequenos para testar o download.

## Fase E. Fatia 1: consulta

11. Busca com os filtros reais, resultado paginado, tela do documento com
    anexos e respostas.
12. Download pelo id do documento, com checagem de escopo.

## Fase F. Fatia 2: inclusão

13. Formulário com validação no servidor em todos os campos.
14. Upload com `sha256`, `mime` e tamanho; arquivo e documento na mesma
    transação.
15. Anexo e resposta: escolha do documento pai do mesmo setor.

## Fase G. Administração

16. Usuários: criar com perfil e setor, senha temporária, desbloquear,
    redefinir senha.
17. Tipos de documento por setor.

## Fase H. Migração

18. ETL de metadados (staging, setores, tipos, usuários, documentos, pai).
19. ETL de arquivos, depois do acesso ao servidor.
20. Validação e relatório de rejeitos.

## Em paralelo, sem depender de código

- Planilha de usuários e lista de tipos com o PAPEM-40
- Acesso de leitura ao servidor de arquivos
- Servidor Apache + PHP
- Repactuação do cronograma com a chefia
