# SISIMAGEM — Levantamento de requisitos e fluxos

Este roteiro complementa o inventário de telas. O inventário de telas mostra
como o sistema atual se parece; este documento busca entender por que ele
funciona assim e em que ordem as coisas acontecem.

Aplicar com o PAPEM-40, em conversa, não em formulário fechado. Deixar a
pessoa explicar com as próprias palavras e anotar exemplos reais que ela
lembrar.

---

## 1. Fluxo principal: da chegada do documento ao arquivamento

Perguntar, em ordem cronológica:

- Como um documento novo chega até vocês? (malote físico, e-mail, scanner,
  outro sistema)
- O que é feito primeiro quando o documento chega?
- Quem decide o tipo do documento?
- Em que momento ele é digitalizado?
- Quem tem permissão para incluir um documento no sistema?
- Depois de incluído, o documento passa por mais alguma etapa, ou já está
  "pronto"?
- Existe aprovação ou conferência antes de considerar o documento
  definitivamente incluído?
- O que acontece se o documento for incluído com erro? Dá para corrigir?
  Quem corrige?
- O que acontece se um documento precisar ser excluído? Isso é permitido?

Desenhar o fluxo resultante como uma sequência simples:

```
chegada → [passo] → [passo] → [passo] → arquivado
```

## 2. Fluxo de busca

- Quando alguém precisa encontrar um documento, qual é o dado que a pessoa
  tem em mãos com mais frequência? (protocolo, nome, CPF, NIP)
- Existe situação em que a busca por esses campos falha e a pessoa precisa
  adivinhar ou tentar de outro jeito?
- Depois de encontrar o documento, o que a pessoa normalmente faz com ele?
  (só consulta, imprime, anexa a outro processo, encaminha)
- Existe caso de buscar mais de um documento ao mesmo tempo, ou é sempre um
  por vez?

## 3. Perfis e permissões

- Existem tipos diferentes de usuário no sistema? Quais?
- O que cada tipo pode fazer que os outros não podem?
- Existe algo que só um "chefe" ou "administrador" pode fazer?
- Um usuário pode ver documentos incluídos por outra pessoa, ou só os
  próprios?
- Existe documento que só determinadas pessoas podem abrir? (sigiloso,
  restrito)

## 4. Campos e seu significado real

Usar a lista de campos do inventário de telas e perguntar, campo a campo,
apenas os que geraram dúvida na análise técnica:

- **CPF**: por que esse campo está preenchido em poucos documentos? É
  obrigatório preencher hoje? Sempre foi assim?
- **Consignado**: o que exatamente se anota aqui? Nome, número, outra coisa?
- **Tipo de Documento**: quais são os tipos que vocês realmente usam no dia a
  dia? (a lista de valores no banco tem mais de dez mil variações por erro de
  digitação — a resposta aqui vira a lista oficial)
- **Urgente**: quando um documento é marcado como urgente, o que muda no
  tratamento dele?
- **Ofício Judicial Anexo**: em que situação esse campo é usado?

## 5. O que dói hoje

Perguntas abertas, deixar a pessoa falar livremente:

- Qual é a reclamação mais comum sobre o sistema atual?
- Existe alguma tarefa que todo mundo faz "por fora" do sistema porque ele
  não dá conta? (planilha paralela, caderno, e-mail)
- Se pudesse mudar uma coisa no sistema amanhã, o que seria?
- Existe algum documento ou situação que o sistema atual não sabe lidar bem?

## 6. Itens técnicos pendentes de resposta

Perguntas específicas, resultado direto do levantamento técnico:

- O botão de digitalizar via scanner ainda funciona? Alguém ainda usa?
  (o sistema tenta abrir um programa da Microsoft que foi descontinuado há
  anos — se ninguém usa, não precisamos recriar essa função)
- Existe exigência de manter histórico de quem alterou cada documento e
  quando, por período determinado? Alguma norma exige isso?
- 682 documentos no sistema não têm nenhum arquivo anexado. Isso é normal,
  ou pode ser falha?
- Encontramos 92 casos em que dois documentos diferentes acabaram apontando
  para o mesmo arquivo digitalizado, por uma falha do sistema atual. Vamos
  levar a lista para vocês identificarem se algum desses documentos precisa
  ser reencontrado ou redigitalizado.

---

## Como registrar o resultado

Não precisa de formulário rígido. Registrar em texto corrido, por seção,
igual à estrutura acima. O que importa:

- Exemplos concretos que a pessoa deu, não só a regra geral
- Quando a resposta for "depende", anotar de que depende
- Divergência entre o que duas pessoas diferentes responderem sobre o mesmo
  ponto — não resolver sozinho, trazer para discutir

## Para que serve

- Seções 1 e 2 definem a ordem das telas e o que cada botão deve fazer —
  vira a especificação que orienta a construção
- Seção 3 define quantos perfis de usuário o sistema novo precisa ter
- Seção 4 resolve dúvidas que a análise do banco não consegue resolver sozinha
- Seção 5 aponta oportunidades de melhoria para a versão seguinte, sem mudar
  o escopo da migração
- Seção 6 fecha decisões que estavam em aberto no levantamento técnico
