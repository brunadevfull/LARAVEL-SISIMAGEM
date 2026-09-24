# SISIMAGEM v2 — Triagem do documento de requisitos

Este documento organiza as 75 perguntas (SE-01 a SE-75) do
`SE_SISIMAGEM_v2.docx` em três categorias, para decidir o que fazer antes de
continuar programando.

---

## Achado estrutural — reconciliar antes de tudo

O documento de requisitos descreve um sistema com conceitos que **não
existem no SISIMAGEM legado** analisado até agora: armário, índice,
reindexação, tratamento de imagem, hierarquia de quatro perfis
(Administrador, Operador Master, Operador, Visualizador), campo OM
(Organização Militar).

Isso pode significar duas coisas, e a diferença importa:

**A.** O documento descreve o sistema **novo desejado**, que vai além de
replicar o legado — é expansão de escopo deliberada.

**B.** Parte disso já existe no legado e nossa análise de código não
encontrou, porque olhamos só `DAOTrim`, `Utilitaria` e
`OperacaoAbrirDocumento`.

**Ação:** confirmar com quem escreveu o RF01–RF42 se este é o escopo da
primeira entrega ou de uma versão futura. Isso muda o cronograma discutido
anteriormente (fatias 1–5), que assumia réplica funcional na v1.

### Mapeamento provável com o que já modelamos

| Termo do RF | Corresponde a |
|---|---|
| Armário | `colecoes` no nosso modelo (PAPEM-41, PAPEM-42) — mas agora com controle de acesso próprio, não só agrupamento |
| Índice | `documentos` |
| Metadados (14) | Os 15 campos customizados que já levantamos, menos um, ou mais o OM |
| Perfis | Não modelado. Legado não tinha hierarquia — só sessão simples (`LoginFilter`) |
| OM | Não modelado. Pode ser o mesmo que `setores`, ou entidade nova |

Se confirmado que são conceitos novos, o schema do plano técnico precisa de
revisão antes do ETL de metadados (fatia 3), mas **não bloqueia** a fatia 1
de autenticação e busca básica.

---

## Bloqueantes — resolver antes de codar a área correspondente

Sem resposta, você estaria adivinhando estrutura de dados ou regra de
permissão que depois é cara de mudar.

| ID | Tema | Por que bloqueia |
|---|---|---|
| SE-26 | Nomenclatura de perfis (RF10 vs RF14 contraditórios) | Define a tabela de perfis e toda a lógica de permissão. Sem isso, não dá para escrever o model de usuário. |
| SE-14 | OM: lista, API ou texto livre | Define se é FK para tabela nova ou campo texto |
| SE-16 | Usuário vinculado a mais de uma OM | Define relação 1:N ou N:N — schema diferente |
| SE-27 | Usuário com mais de um perfil | Define se `perfil_id` é coluna única ou tabela pivô |
| SE-28 | Visualizador externo acessa quais armários | Define o modelo de controle de acesso |
| SE-29 | Armário é agrupador ou unidade de controle de acesso | Decisão de arquitetura central — afeta toda a modelagem de permissão |
| SE-30 | Restrição por tipo dentro do armário | Define granularidade da ACL |
| SE-41 | Ofício e CP existem nos dois armários — mesmo cadastro ou duplicado | Afeta a unicidade de `tipos_documento.nome` já definida no schema |
| SE-42 | Tipos filtrados por armário | Precisa de tabela de vínculo `armario_tipo_documento` |
| SE-43 | NIP na indexação: do operador ou do militar referenciado | Ambiguidade semântica que já víamos no campo `nip_matricula` — agora fica explícita e precisa resposta |
| SE-46 | Ofício judicial anexo: booleano ou arquivo | Muda o tipo da coluna no schema (`TEXT` vira `BOOLEAN` ou FK) |
| SE-49 / SE-57 | Um índice pode ter múltiplos arquivos | **Crítico.** O schema atual assume 1 arquivo por documento (`arquivo_id` como FK direta). Se a resposta for sim, precisa de tabela `documento_arquivo` N:N antes de qualquer migration de arquivo. |
| SE-52 | "Página" = arquivo ou página de PDF | Afeta o design da reindexação |
| SE-53 | Reindexação move ou copia | Afeta se existe histórico de reindexação e como |
| SE-67 | Visualização interna (no navegador) ou externa (abre outro programa) | Define se você constrói visualizador de PDF/TIFF na aplicação — item da fatia 1 |
| SE-75 | Escopo real da auditoria (RF40 diz "todas as ações", RF42 lista só duas) | Define a tabela de log e onde instrumentar o código |

**Recomendação:** leve esses 16 itens numa reunião única com quem escreveu o
documento, antes de tocar nas fatias 3 (ETL), 5 (administração) e no
visualizador da fatia 1.

---

## Default assumível — codar agora, documentar a suposição

Não bloqueiam. Adotar um valor razoável e seguir. Se a resposta real vier
diferente, o ajuste é pontual.

| ID | Suposição adotada |
|---|---|
| SE-01 | Reset de senha feito pelo Administrador, sem fluxo de e-mail na v1 |
| SE-02 | Expiração de sessão por inatividade: 30 minutos |
| SE-03 | **Já decidido**: autenticação local, sem LDAP |
| SE-04 | Senha mínima 8 caracteres, sem expiração periódica forçada |
| SE-05 | Mensagem de erro genérica ("credenciais inválidas"), por segurança |
| SE-06 | Bloqueio automático temporário após 3 tentativas (rate limiting padrão do Laravel), sem exigir ação manual do Administrador |
| SE-08 | Desbloqueio exige redefinição obrigatória de senha |
| SE-10 | Permitir múltiplos Administradores |
| SE-11 | Acesso de recuperação via linha de comando (`artisan`) direto no servidor, documentado em runbook interno |
| SE-12 | Campo nome aceita nome completo |
| SE-13 | Obrigatórios no cadastro: nome, NIP, senha, perfil. OM opcional |
| SE-15 / SE-17 | Senha temporária definida no cadastro, troca obrigatória no primeiro acesso — já é o padrão desenhado para a migração dos 235 usuários |
| SE-18 | Administrador pode editar e excluir usuários (CRUD completo) |
| SE-19 | Usuário inativo oculto de listagens ativas, mantido em histórico |
| SE-20 | Documentos de usuário inativado mantêm a referência |
| SE-21 | Reativação permitida, dados preservados |
| SE-23 | NIP não editável após criado (chave natural) |
| SE-24 | Mudança de perfil surte efeito no próximo login, não na sessão ativa |
| SE-31 | Operador Master pode supervisionar ações do Operador; granularidade fina fica para v2 |
| SE-32 | Sessão expirada perde trabalho não salvo |
| SE-33 | Acesso simultâneo em mais de um dispositivo permitido |
| SE-34 | Exclusão de armário é lógica (mesmo padrão de `ativo` já usado em `tipos_documento`) |
| SE-35 | Código do armário gerado automaticamente |
| SE-36 | Renome de armário não afeta vínculos existentes |
| SE-37 | Mesma regra do RF19: impedir exclusão com usuário vinculado |
| SE-38 | Tipo de documento inativado preserva histórico, impede novo uso |
| SE-39 | Renome de tipo permitido |
| SE-40 | Lista de tipos extensível pelo Administrador, não fixa |
| SE-44 | Sem rascunho na v1 — índice só existe completo |
| SE-45 | Obrigatoriedade de metadado fixa por ora, variação por tipo fica para v2 |
| SE-48 | Formatos aceitos: PDF, JPEG, PNG, TIFF |
| SE-50 | Verificação abre visualizador simples do arquivo |
| SE-51 | Metadados editáveis durante a verificação |
| SE-54 | Reindexação permite trocar de armário |
| SE-55 | Todos os campos do destino editáveis antes de confirmar |
| SE-56 | Cancelar reindexação desfaz qualquer alteração no destino |
| SE-58 | Documento indexado pode ser substituído; original preservado |
| SE-60 | Campos de busca = os mesmos metadados do RF27 |
| SE-61 | Todos os campos de busca opcionais |
| SE-62 | Múltiplos filtros combinados com E lógico |
| SE-63 | Nenhum campo obrigatório para buscar |
| SE-64 | Busca por data suporta intervalo |
| SE-66 | Ordenação padrão: mais recente primeiro |
| SE-68 | Download permitido para Operador e acima; Visualizador só vê na tela |
| SE-69 | Documentos de usuário inativo aparecem normalmente na busca |
| SE-73 | Apenas Administrador consulta logs de auditoria |

---

## Adiável para v2 — não bloqueia, não precisa de suposição agora

| ID | Item |
|---|---|
| SE-07 | Notificação ao Administrador quando usuário é bloqueado |
| SE-25 | Auditoria de edição de dados cadastrais (depende de SE-75 primeiro) |
| SE-59 | Versionamento completo de documentos substituídos |
| SE-65 | Exportação de resultados de busca |
| SE-70, SE-71, SE-72 | **Tratamento de imagem inteiro.** É funcionalidade nova e substancial (edição de imagem, versionamento de original vs. tratada). Recomendo tratar como módulo de v2, fora do escopo da migração. |
| SE-74 | Exportação de logs de auditoria |
| SE-09 | Importação de cadastro de usuário da base antiga — na prática já resolvido: os 235 usuários entram pelo ETL, cadastro manual é só para os que vierem depois |

---

## Como isso se encaixa no que já estava definido

- **Fatia 1** (login, busca, abrir documento) segue como planejado, com os
  defaults acima. SE-67 (visualização interna) precisa de resposta antes de
  fechar o visualizador de documento.
- **Fatia 3** (ETL de metadados) espera a reconciliação de armário/OM antes
  de rodar, para não migrar para um schema que muda em seguida.
- **Fatia 5** (administração) depende dos 16 bloqueantes quase inteiramente
  — é a fatia mais afetada por este documento.
- Tratamento de imagem sai do roadmap da migração e vira decisão separada de
  produto.

**Próximo passo sugerido:** uma reunião de 30 a 40 minutos cobrindo só os 16
itens bloqueantes, com quem redigiu o RF01–RF42. O restante você decide e
documenta sozinha, sem precisar de reunião.
