# SISIMAGEM: Modelo de entidade-relacionamento (MER)

As entidades do banco novo e como se ligam. Colunas e tipos estão no
dicionário de dados; o desenho, no DER.

## Entidades

**Usuário (`users`).** Quem acessa o sistema. Entra com NIP ou CPF e senha;
não há e-mail. Tem um perfil (`admin`, `gestor_setor`, `operador_setor` ou
`padrao`) e, exceto o admin, pertence a um setor. O `padrao` pertence sempre
ao PAPEM-41. Conta tentativas de senha errada e é bloqueado na quinta.

**Setor (`setores`).** PAPEM-41 (id 1) e PAPEM-42 (id 2). É dono dos
documentos e dos tipos de documento, e define o que cada usuário vê. Os dois
são criados pela migration, com id fixo.

**Local de arquivo (`locais_arquivo`).** Onde o documento está guardado, ou a
situação dele, como "Lixeira".

**Tipo de documento (`tipos_documento`).** Ofício, Sentença, Requerimento e
outros. Pertence a um setor. "Ofício" e "Comunicação Padronizada" existem nos
dois setores como cadastros separados. São 24 tipos oficiais.

**Arquivo (`arquivos`).** O PDF ou TIFF, guardado dentro do banco. Arquivos
idênticos são guardados uma vez só, pelo `sha256`. Fica separado do documento
porque há documentos sem arquivo e porque a consulta comum não deve carregar o
arquivo.

**Documento (`documentos`).** O registro central, com os metadados de busca.
Tem um setor dono, pode ter um documento pai e tem uma natureza: documento,
processo, resposta ou anexo. Guarda também o autor e o nome original do arquivo
vindos do sistema atual.

**Rejeito de migração (`etl_rejeitos`).** Guarda o valor original do que a
migração não conseguiu converter. Não tem ligação com as outras tabelas.

## Ligações

| De | Para | Cardinalidade |
|---|---|---|
| Usuário | Setor | vários usuários por setor; o admin não tem setor |
| Tipo de documento | Setor | vários tipos por setor |
| Documento | Setor | vários documentos por setor |
| Documento | Documento (pai) | um pai tem vários filhos; a maioria não tem pai |
| Documento | Arquivo | vários documentos podem usar o mesmo arquivo |
| Documento | Tipo de documento | vários documentos do mesmo tipo |
| Documento | Local de arquivo | vários documentos no mesmo local |
| Documento | Usuário | vários documentos incluídos pelo mesmo usuário |

Não há ligação de muitos para muitos.

## Regras que o banco garante

- Perfil só aceita os quatro valores válidos.
- Quem não é admin tem setor; o `padrao` só pode ser do PAPEM-41.
- Natureza do documento só aceita os quatro valores válidos.
- Nome do tipo não se repete dentro do setor; nome do setor não se repete.
- O mesmo arquivo não é guardado duas vezes.
- Não se apaga setor em uso nem documento que tenha filhos.
- A migração de dados não duplica documentos (`uri_legado` único).

## Regras que ficam na aplicação

- Visibilidade: `documentos.setor_id = users.setor_id`; o admin vê tudo.
- O documento filho tem o mesmo setor do pai.
- Usuário precisa ter NIP, CPF ou os dois.
- O tipo escolhido na inclusão precisa ser do setor do usuário.
