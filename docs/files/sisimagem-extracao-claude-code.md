# SISIMAGEM — Roteiro de extração via Claude Code

Rodar dentro do repositório do legado, com o Claude Code apontando para a
raiz do projeto. Cada bloco é um prompt separado. Rodar em sequência, um de
cada vez, revisando o resultado antes do próximo.

O objetivo é preencher por leitura de código tudo que não depende de
observar o sistema rodando nem de perguntar a alguém.

---

## Bloco 1 — Mapa de telas e rotas

```
Leia src/main/webapp/WEB-INF/web.xml e src/main/java/controller/ServletControlador.java.
Liste todos os comandos (parâmetro "cmd") que o servlet aceita, a classe
Operacao* que trata cada um, e para qual JSP o fluxo normalmente redireciona
ao final. Monte uma tabela: comando | classe | JSP de destino em caso de
sucesso | JSP de destino em caso de erro.
```

## Bloco 2 — Campos de cada tela

Rodar uma vez por JSP de formulário (`incluirDocumento.jsp`,
`pesquisarDocumento.jsp`, `login.jsp`, cadastro de usuário, etc.):

```
Leia [nome do arquivo].jsp. Liste todos os campos de formulário: name, id,
tipo de input, valor padrão se houver, e o texto do rótulo (label) associado
a cada um. Inclua também qualquer texto de ajuda ou placeholder visível.
```

## Bloco 3 — Validações client-side

```
Leia src/main/webapp/js/validar.js e qualquer outro arquivo .js referenciado
pelas telas de formulário. Para cada campo validado, informe: nome do campo,
regra de validação (obrigatório, formato, tamanho mínimo/máximo, regex), e a
mensagem de erro exibida ao usuário, com o texto exato.
```

## Bloco 4 — Validações server-side

```
Leia todas as classes Operacao*.java em src/main/java/controller/. Para cada
uma, liste: quais campos ela valida antes de gravar, o que acontece se a
validação falhar (mensagem, redirecionamento), e se há alguma regra de
negócio que não é óbvia pelo nome do campo (por exemplo: um campo que só é
obrigatório se outro tiver determinado valor).
```

## Bloco 5 — Perfis e permissões

```
Leia src/main/java/utilitaria/LoginFilter.java e qualquer classe relacionada
a Usuario ou Grupo em src/main/java/bean/ e src/main/java/model/DAOTrim.java.
Existe mais de um tipo de usuário ou nível de permissão no sistema? Se sim,
o que cada nível pode ou não pode fazer, e onde essa checagem é feita no
código (arquivo e método)?
```

## Bloco 6 — Fluxo de inclusão de documento, detalhado

```
Leia OperacaoIncluirDocumento.java e Documento.java (bean). Descreva o passo
a passo, na ordem em que o código executa: o que é lido do formulário, o que
é validado, o que é gravado no banco, o que é gravado no disco, e em que
ordem essas gravações acontecem. Se alguma etapa puder falhar deixando dado
gravado pela metade (por exemplo: gravou o registro mas falhou o arquivo),
identifique onde.
```

## Bloco 7 — O botão de scanner

```
Leia ChamaScaner.java e qualquer JSP ou JS que o referencie. Explique o que
esse fluxo faz, quais são as dependências externas (executável, biblioteca),
e se há alguma indicação no código de quando isso foi implementado ou se
ainda é chamado por alguma tela ativa.
```

## Bloco 8 — Mensagens de erro, texto completo

```
Busque em todo o projeto (Java e JSP) por strings literais que pareçam
mensagens de erro ou de sucesso mostradas ao usuário (System.out.println não
conta - procure texto que vai para response, request.setAttribute com
palavras como "erro", "sucesso", "falha", ou texto direto em JSP). Liste
todas, com o arquivo de origem.
```

## Bloco 9 — Diferenças entre Documento e o banco

```
Leia bean/Documento.java e compare com as colunas de TSRECORD e TSEXFIELDV
que a aplicação usa (já sabemos que são: Beneficiário, Consignado, CPF, Data
de Protocolo, Entidade Consignatária, OBSERVAÇÕES, Número do Documento,
Origem, Protocolo, Tipo de Documento, NIP/Matrícula, Ofício Judicial Anexo,
Urgente, mais duas datas "Legado"). Existe algum campo na classe Documento
que não corresponde a nenhum desses, ou vice-versa? Isso pode indicar campo
que existe na tela mas não é salvo, ou campo salvo que a tela não mostra.
```

---

## O que fazer com o resultado

Depois de rodar os nove blocos, você terá:

- Tabela completa de comandos e telas (bloco 1)
- Todos os campos de todas as telas, com rótulo exato (bloco 2)
- Todas as regras de validação e suas mensagens, client e server (blocos 3, 4, 8)
- Resposta definitiva sobre quantos perfis existem (bloco 5)
- O fluxo de inclusão passo a passo, incluindo pontos de falha (bloco 6)
- Se o scanner é código morto ou ativo (bloco 7)
- Divergência entre o que a tela mostra e o que o banco grava (bloco 9)

Isso preenche as seções 1, 3 e 6 do roteiro de requisitos quase por completo,
e boa parte da especificação de telas que o Militar 2 vai construir.

## O que sobra para o Militar 1 aplicar com o PAPEM-40

Só o que nenhum código revela:

- Por que CPF está preenchido em só 4,7% dos documentos
- O que significa "Consignado" na prática, com exemplo real
- Quais tipos de documento são realmente usados, para virar a lista canônica
- Se o scanner (confirmado ativo ou morto pelo bloco 7) ainda é usado por
  alguém, mesmo que funcione
- O que dói no uso diário, que não deixa rastro em código
- Se existe fluxo paralelo fora do sistema
- Confirmação sobre os 682 documentos sem arquivo e os 92 sobrescritos

O roteiro de entrevista fica bem mais curto depois desta extração — grande
parte das perguntas técnicas já estará respondida antes da conversa começar.
