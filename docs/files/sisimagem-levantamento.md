# SISIMAGEM — Queries de levantamento

Base Oracle, schema `TRIM`. Rodar no SQL Developer.
Todas são somente leitura. Nenhuma altera dado.

---

## 1. Volume e quantidade de arquivos registrados no banco

```sql
SELECT URI, ESNAME, ESITEMS, ESBYTES,
       ROUND(ESBYTES/1024/1024/1024, 2) AS GB,
       ESDOSPATHLONG, ESNODE
FROM TRIM.TSELECSTOR;
```

`ESITEMS` = quantidade de arquivos.
`ESBYTES` = volume total.
`ESDOSPATHLONG` = caminho raiz do repositório no servidor.

Ressalva: esses contadores eram mantidos pelo TRIM. Como o produto está fora
de uso, tudo que o SISIMAGEM gravou depois provavelmente não entrou na conta.
Espere divergência para menos.

---

## 2. Contagens por tabela

```sql
SELECT 'TSRECORD'  AS tabela, COUNT(*) AS linhas FROM TRIM.TSRECORD
UNION ALL SELECT 'TSRECELEC',  COUNT(*) FROM TRIM.TSRECELEC
UNION ALL SELECT 'TSEXFIELDV', COUNT(*) FROM TRIM.TSEXFIELDV
UNION ALL SELECT 'TSLOCATION', COUNT(*) FROM TRIM.TSLOCATION
UNION ALL SELECT 'TSNOTES',    COUNT(*) FROM TRIM.TSNOTES;
```

`TSRECORD` = documentos.
`TSRECELEC` = documentos com arquivo digitalizado (1:1, PK compartilhada).
`TSNOTES` = texto longo fatiado em pedaços de 231 caracteres.
Se `TSNOTES` tiver volume, é acervo que o SISIMAGEM nunca leu — decidir se migra.

---

## 3. Documentos sem arquivo

```sql
SELECT COUNT(*) AS sem_arquivo
FROM TRIM.TSRECORD r
WHERE NOT EXISTS (SELECT 1 FROM TRIM.TSRECELEC e WHERE e.URI = r.URI);
```

---

## 4. Nome de arquivo repetido

```sql
SELECT COUNT(*) AS resid_duplicado FROM (
  SELECT RESID FROM TRIM.TSRECELEC
  GROUP BY RESID HAVING COUNT(*) > 1
);
```

O legado gera o nome sequencial contando arquivos na pasta
(`listFiles().length + 1`). Se algum arquivo foi apagado, o próximo upload
reutiliza o número e sobrescreve. Resultado maior que zero indica perda
silenciosa de documento.

Para ver quais:

```sql
SELECT RESID, COUNT(*) AS ocorrencias
FROM TRIM.TSRECELEC
GROUP BY RESID HAVING COUNT(*) > 1
ORDER BY 2 DESC;
```

---

## 5. Campos customizados reais e uso

```sql
SELECT f.URI, f.EXFIELDNAME, f.EXSEARCHABLE, COUNT(v.URI) AS preenchidos
FROM TRIM.TSEXFIELD f
LEFT JOIN TRIM.TSEXFIELDV v ON v.EVFIELDURI = f.URI
GROUP BY f.URI, f.EXFIELDNAME, f.EXSEARCHABLE
ORDER BY 4 DESC;
```

`EXSEARCHABLE` indica se o campo era pesquisável.
Campos com `preenchidos` igual ou próximo de zero não entram no modelo novo.

A quais tipos de documento cada campo se aplica:

```sql
SELECT f.EXFIELDNAME, rt.RECTYPENAME, u.EUFIELDBOBTYPE
FROM TRIM.TSEXFIELD f
JOIN TRIM.TSEXFIELDU u ON u.EUFIELDURI = f.URI
LEFT JOIN TRIM.TSRECTYPE rt ON rt.URI = u.EUFIELDRECORDTYPE
ORDER BY f.EXFIELDNAME;
```

---

## 6. Amostra para validar a regra de caminho

```sql
SELECT RESID FROM TRIM.TSRECELEC WHERE ROWNUM <= 20;
```

Regra derivada do código legado (`Utilitaria.montaNomeArquivo`):

```
RESID   = 001+AAAAMM+NNNNNNNNNN.ext
caminho = <base>/001/AAAAMM/NNNNNNNNNN.ext
```

Trocar `+` por separador de diretório. `<base>` vem do `context-param path`
do `web.xml`. Isso é inferência do código, não observação — precisa ser
confirmado contra o servidor de arquivos.

---

## 7. Faixa temporal do acervo

```sql
SELECT MIN(CREATIONDATETIME) AS mais_antigo,
       MAX(CREATIONDATETIME) AS mais_recente
FROM TRIM.TSRECORD;
```

Datas são `CHAR(15)` no formato `yyyyMMddHHmmss` + 1 caractere ignorado.
O banco guarda UTC; a tela exibe UTC−3.

Conversão em PHP para o ETL:

```php
$dt = DateTime::createFromFormat('YmdHis', substr($valor, 0, 14), new DateTimeZone('UTC'));
$dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
```

Usar `America/Sao_Paulo`, não offset fixo — o acervo atravessa anos com
horário de verão.

Não usar `TSRECELEC.REMODIFIEDDATETIME` como referência. O legado grava com
máscara `YYYYMMDDHHMMSS`, onde `MM` em Oracle é mês, não minuto. A coluna tem
hora incorreta em todo registro inserido pelo SISIMAGEM.

---

## 8. Tipos de documento

```sql
SELECT rt.URI, rt.RECTYPENAME, COUNT(r.URI) AS documentos
FROM TRIM.TSRECTYPE rt
LEFT JOIN TRIM.TSRECORD r ON r.RCRECTYPEURI = rt.URI
GROUP BY rt.URI, rt.RECTYPENAME
ORDER BY 3 DESC;
```

---

## 9. Triagem de tabelas vivas x mortas

```sql
SELECT table_name, num_rows, last_analyzed
FROM all_tables
WHERE owner = 'TRIM'
ORDER BY num_rows DESC NULLS LAST;
```

`num_rows` é estatística do otimizador e pode estar desatualizada. Serve para
triagem rápida. Para as finalistas, rodar `COUNT(*)` de verdade.

Tabelas que o `DAOTrim` efetivamente usa:

| Tabela | Uso |
|---|---|
| `TSRECELEC` | arquivo eletrônico |
| `TSLOCATION` | usuários e responsáveis |
| `TSRECORD` | documento |
| `TSRECLOC` | vínculo documento ↔ responsável |
| `TSJURGROUP` | agrupamento |
| `TSEXFIELDV` | valores de campos customizados |
| `TSEXFIELD` | definição de campos customizados |
| `TSRECTYPE` | tipo de documento |

As demais são funcionalidades do produto TRIM que a unidade não utilizou.
Tabela fora dessa lista com volume relevante é acervo da era TRIM — decisão
de negócio se migra ou descarta.

---

## Checklist no servidor de arquivos

Depende de acesso de leitura ao diretório. Substituir `BASE` pelo caminho do
`context-param path` do `web.xml`.

```bash
BASE=/caminho/do/repositorio

du -sh "$BASE"                                    # volume total
find "$BASE" -type f | wc -l                      # quantidade de arquivos
find "$BASE" -type f -printf '%h\n' | sort | uniq -c | sort -k2   # por ano/mês
find "$BASE" -type f | sed 's/.*\.//' | tr 'A-Z' 'a-z' | sort | uniq -c | sort -rn
find "$BASE" -type f -size 0 | wc -l              # arquivos vazios
```

Verificar também se existe mais de um repositório. O banco aponta um caminho
(`ESDOSPATHLONG`) e o `web.xml` configura outro. Podem ser diretórios
distintos: um da era TRIM e outro do SISIMAGEM.

---

## Comparações que interessam

| Comparar | O que revela |
|---|---|
| `ESITEMS` vs contagem no disco | arquivos gravados após o TRIM sair, ou perda |
| `TSRECELEC` vs contagem no disco | órfãos nas duas direções |
| `ESBYTES` vs `du -sh` | dimensionamento do disco do servidor novo |
| `resid_duplicado` > 0 | documento sobrescrito, perda silenciosa |
| amostra de `RESID` vs caminho real | confirma ou derruba a regra de derivação |
