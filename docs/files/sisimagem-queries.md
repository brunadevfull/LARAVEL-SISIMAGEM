# SISIMAGEM — Levantamento da base

Base Oracle, schema `TRIM`.

Todas as consultas abaixo são **somente leitura**. Nenhuma altera, insere ou
apaga dado. Podem ser executadas em produção.

Objetivo: dimensionar a migração do sistema. Preciso saber quantos documentos
existem, quantos têm arquivo digitalizado e qual o volume total.

Favor executar e devolver o resultado de cada uma.

---

## 1. Repositório de arquivos: quantidade e volume

```sql
SELECT URI, ESNAME, ESITEMS, ESBYTES,
       ROUND(ESBYTES/1024/1024/1024, 2) AS GB,
       ESDOSPATHLONG, ESNODE
FROM TRIM.TSELECSTOR;
```

Retorna o caminho do repositório no servidor, a quantidade de arquivos e o
volume em GB.

---

## 2. Contagem de registros

```sql
SELECT 'TSRECORD'  AS tabela, COUNT(*) AS linhas FROM TRIM.TSRECORD
UNION ALL SELECT 'TSRECELEC',  COUNT(*) FROM TRIM.TSRECELEC
UNION ALL SELECT 'TSEXFIELDV', COUNT(*) FROM TRIM.TSEXFIELDV
UNION ALL SELECT 'TSLOCATION', COUNT(*) FROM TRIM.TSLOCATION
UNION ALL SELECT 'TSNOTES',    COUNT(*) FROM TRIM.TSNOTES;
```

Total de documentos, de arquivos, de campos preenchidos e de usuários.

---

## 3. Documentos sem arquivo associado

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

Resultado maior que zero indica que arquivos podem ter sido sobrescritos.

---

## 5. Campos customizados

```sql
SELECT f.URI, f.EXFIELDNAME, f.EXSEARCHABLE, COUNT(v.URI) AS preenchidos
FROM TRIM.TSEXFIELD f
LEFT JOIN TRIM.TSEXFIELDV v ON v.EVFIELDURI = f.URI
GROUP BY f.URI, f.EXFIELDNAME, f.EXSEARCHABLE
ORDER BY 4 DESC;
```

Retorna apenas o nome dos campos e quantos têm valor. Não retorna conteúdo.

---

## 6. Amostra de nomes de arquivo

```sql
SELECT RESID FROM TRIM.TSRECELEC WHERE ROWNUM <= 20;
```

Só o nome do arquivo, sem dado de pessoa. Serve para confirmar em que pasta
do servidor os arquivos estão gravados.

---

## 7. Período coberto pelo acervo

```sql
SELECT MIN(CREATIONDATETIME) AS mais_antigo,
       MAX(CREATIONDATETIME) AS mais_recente
FROM TRIM.TSRECORD;
```

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

## 9. Tabelas com maior volume

```sql
SELECT table_name, num_rows, last_analyzed
FROM all_tables
WHERE owner = 'TRIM'
ORDER BY num_rows DESC NULLS LAST;
```

Serve para identificar o que ainda tem dado e o que está vazio.

---

## Observação

Nenhuma das consultas retorna nome, CPF ou conteúdo de documento. São
contagens, nomes de campo e nomes de arquivo.

Se preferir, o resultado pode ser exportado em CSV pelo próprio SQL Developer.
