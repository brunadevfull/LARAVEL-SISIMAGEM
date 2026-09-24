# SISIMAGEM — MER (Modelo de Entidade-Relacionamento)

O DER (`sisimagem-diagrama-er.md`) é o desenho das tabelas e as setas entre
elas. Este documento cobre o que o desenho não explica: o que cada tabela
representa, o que cada campo relevante significa, e por que as relações
foram desenhadas desse jeito.

## Usuário (`users`)

Representa quem acessa o sistema. É a tabela padrão do Laravel estendida
com os dados reais que existiam no legado.

O `nip` é o número de identificação do militar, possível identificador de
login além do e-mail. O `perfil` assume um entre `adm`, `papem40`, `sasm`
ou `padrao` — os três primeiros vêm direto dos grupos reais encontrados no
código do sistema antigo (ADM, PAPEM40, SASM); o quarto cobre qualquer
outro grupo que existisse lá, tratado como comportamento residual.

`tentativas_login` conta erros de senha e zera ao logar com sucesso;
`bloqueado_em` é preenchido depois de 5 tentativas erradas, o mesmo limite
do sistema antigo. `senha_temporaria` força troca de senha no próximo
login — usado na migração dos 235 usuários existentes, cada um com senha
gerada de forma aleatória (o sistema antigo usava a senha fixa "marinha"
para isso, o que não é reaproveitado aqui).

O banco só aceita os 4 valores de perfil listados acima — está travado por
uma restrição na própria tabela, não depende de nenhuma validação de
código pra isso funcionar.

## Setor (`setores`)

Uma unidade organizacional ligada a um documento — PAPEM-41, PAPEM-42, e
assim por diante. No legado isso vinha misturado com pessoa e com local
físico dentro de uma única tabela (`TSLOCATION`); aqui está separado de
propósito, porque descobrir se uma linha era gente ou lugar naquela tabela
exigia olhar outros campos.

## Local de arquivo (`locais_arquivo`)

Onde o documento está guardado fisicamente — estante, sala, ou situação
como "Lixeira" para documento marcado pra descarte. Vem do que era o tipo 0
dentro de `TSRECLOC` no legado.

## Tipo de documento (`tipos_documento`)

A classificação do documento: Ofício Judicial, Requerimento, Comunicação
Padronizada. Tem um campo `ativo`, que permite desativar um tipo sem apagar
o histórico de quem já usou ele.

No sistema antigo essa informação era texto livre, e virou 10.578
variações — erro de digitação, acentuação corrompida, maiúscula e
minúscula misturadas pro mesmo conceito. Esta tabela é a lista já
normalizada, que o ETL vai popular a partir de um mapeamento manual dos
valores mais comuns, feito com apoio do PAPEM-40.

## Arquivo (`arquivos`)

O arquivo digital — PDF, TIFF, imagem digitalizada — separado do registro
de documento de propósito.

| Campo | Significado |
|---|---|
| `sha256` | impressão digital do conteúdo do arquivo; dois arquivos idênticos (o mesmo documento anexado a processos diferentes) caem no mesmo hash e viram uma linha só |
| `caminho` / `conteudo` | um ou outro, nunca os dois — depende de o arquivo ficar em disco ou dentro do banco, decisão ainda aberta até saber o volume real do acervo |
| `sobrescrito` | marca os 92 casos confirmados no legado em que um arquivo foi sobrescrito por outro por colisão de nome, pra o sistema avisar que o conteúdo pode não bater com o registro |

Separar arquivo de documento também permite que o mesmo arquivo seja
referenciado por mais de um documento, e mantém o dado pesado longe do
dado leve — o que ajuda tanto o backup quanto a velocidade da busca.

## Documento (`documentos`)

O centro do sistema. Corresponde ao antigo `TSRECORD` somado aos 15 campos
que estavam espalhados em `TSEXFIELDV`, agora cada um na sua própria
coluna.

| Campo | Significado |
|---|---|
| `titulo` / `titulo_legado_numerico` | o sistema antigo tinha um bug que gravava o ID numérico no lugar do texto digitado como título; a segunda coluna marca quando isso aconteceu, pra não tratar um número como se fosse título de verdade |
| `tipo_documento_texto` / `tipo_documento_id` | o texto original fica preservado sem mexer; a versão normalizada é preenchida à medida que o mapeamento avança — os dois convivem até a limpeza estar completa |
| `cpf` | só 4,7% dos documentos do acervo original tinham esse campo preenchido — ausência aqui não é erro |
| `data_origem_invalida` | marca quando uma data do legado não pôde ser convertida — formato errado, ano fora de faixa, ou o literal "1" usado como preenchimento vazio numa migração anterior |
| `uri_legado` | referência ao `TSRECORD.URI` original, usada só pelo ETL pra rodar de novo sem duplicar nada |

O documento aponta para um arquivo, um tipo, um local de arquivo, e duas
vezes para setor — uma como origem, outra como responsável. São dois
papéis diferentes da mesma entidade, tratados como tal desde o legado.

## Rejeito de ETL (`etl_rejeitos`)

Não é entidade de negócio, é o registro da própria migração. Quando um
valor do legado não pode ser convertido com segurança — data inválida,
campo corrompido — o valor original fica guardado aqui em vez de ser
descartado ou substituído por um chute.

Não tem chave estrangeira porque precisa aceitar registro mesmo quando o
dado de origem é ruim a ponto de não corresponder a nenhuma linha válida
nas outras tabelas — é justamente esse caso que ela existe pra capturar.

## Relacionamentos

| De | Para | Cardinalidade |
|---|---|---|
| Documento | Arquivo | vários documentos podem apontar pro mesmo arquivo |
| Documento | Tipo de documento | vários documentos do mesmo tipo |
| Documento | Local de arquivo | vários documentos no mesmo local |
| Documento | Setor (origem) | vários documentos com a mesma origem |
| Documento | Setor (responsável) | vários documentos sob o mesmo responsável |
| Documento | Usuário (criado_por) | vários documentos incluídos pelo mesmo usuário |

Todas as relações são de muitos para um. Não existe nenhuma de muitos para
muitos aqui, o que evita tabela de junção extra e mantém a consulta mais
simples.

## O que o próprio banco garante, sem depender de código

- Perfil de usuário só pode ser um dos 4 valores válidos
- Nome de tipo de documento não duplica
- Hash de arquivo não duplica — a deduplicação é garantida pelo banco, não
  por lógica de aplicação que pode ter bug
- Todo documento migrado carrega a chave do registro original, o que
  deixa o ETL auditável e repetível
