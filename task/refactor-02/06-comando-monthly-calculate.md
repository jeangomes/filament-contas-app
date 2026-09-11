# Task 06 — Comando `monthly:calculate`

## Status
Concluída — 117 testes passando (9 novos). Rodado contra o banco real.

### Decisões
- **`--all` continua diante de falha**, coleta os meses que quebraram, lista
  todos no fim e sai com `FAILURE` (para cron/CI perceberem). Cada mês é uma
  transação independente no serviço, então um mês problemático não desfaz nem
  impede os outros — e o fechamento é cache recalculável, logo progresso
  parcial não deixa nada inconsistente.
- **Competência + `--all` juntos = erro**, em vez de um vencer silenciosamente.
- A mudança de semântica está no texto do argumento e aparece no `--help`:
  "o mês do VENCIMENTO, não o de referência". O formato é idêntico ao antigo,
  então o erro passaria despercebido sem o aviso.
- O `--from`/`--to` comentado foi removido junto com os dois blocos de código
  morto que nunca saíram do comentário.
- A linha do acerto reusa o acessor `balance_description` do model — a mesma
  frase que a interface mostra, escrita num lugar só.

### Desempenho
`--all` faz uma consulta de lançamentos por competência. No banco real são
7 transações em 1 competência; mesmo com anos de histórico seriam algumas
dezenas de consultas pequenas e indexadas. O serviço já devolve o resumo com
as relações carregadas, então a exibição não faz consulta por linha.

### Saída contra o banco real
```
Competência: 07/2026   (gastos de 06/2026)
Total casa: R$ 1.200,00   |   Total comum: R$ 1.583,00
| Participante | Cartão    | Comum     | Individual | Custo de vida | Saldo      |
| D            | R$ 521,00 | R$ 383,00 | R$ 138,00  | R$ 929,50     | R$ -408,50 |
| J            | R$ 421,00 | R$ 0,00   | R$ 421,00  | R$ 1.212,50   | R$ 408,50  |
Acerto: D deve pagar R$ 408,50
```
Números idênticos aos conferidos com SQL independente na Task 05.

## Objetivo
Adaptar o comando à nova chave (competência) e finalmente entregar o `--all`,
que hoje existe só como bloco comentado em
`app/Console/Commands/CalculateMonthlySummary.php`.

## Arquivos

- `app/Console/Commands/CalculateMonthlySummary.php` (reescrever)
- `tests/Feature/CalculateMonthlySummaryCommandTest.php` (novo)

## Assinatura

```
monthly:calculate {competencia? : Competência YYYY-MM (default: mês atual)}
                  {--all : Recalcula todas as competências com lançamento}
```

Some o par `{--from=}/{--to=}` (nunca saiu do comentário). O `--all` usa
`RepositorioDeLancamentos::competenciasDisponiveis()`, que já existe do
refactor 1 e devolve exatamente a lista de meses com lançamento.

## Mudança de semântica (avisar no `--help`/descrição)
O argumento agora é a **competência** (o mês que se está fechando, o do
vencimento da fatura), não mais o "mês de referência". Quem rodava
`monthly:calculate 2026-06` para fechar julho passa a rodar
`monthly:calculate 2026-07`. Vale deixar isso explícito na descrição do
comando, porque é uma pegadinha silenciosa: o comando antigo aceitaria o mesmo
formato e faria outra coisa.

Validação de formato: `ReferenceMonth::fromString()` já valida e lança
`CompetenciaInvalida` — capturar e transformar em erro amigável em vez de
repetir regex no comando.

## Saída
A tabela do console (`displaySummary()`) referencia colunas que deixaram de
existir (`d_credit_card_total`, `d_living_cost`, `reference_month`...).
Reescrever lendo do novo modelo, uma linha por participante:

```
Competência: 2026-07  (ref. 06/2026)
Total casa: R$ 1.200,00   |   Total comum: R$ 1.534,00
+-------------+------------+-----------+-------------+---------------+
| Participante| Cartão     | Comum     | Individual  | Custo de vida |
+-------------+------------+-----------+-------------+---------------+
| D           | R$ 125,00  | R$ 70,00  | R$ 55,00    | R$ 822,00     |
| J           | R$ 483,00  | R$ 264,00 | R$ 219,00   | R$ 986,00     |
+-------------+------------+-----------+-------------+---------------+
Acerto: D paga R$ 697,00 para J
```

## Testes (feature)
1. `monthly:calculate 2026-07` cria o fechamento da competência certa.
2. Competência inválida (`2026-13`, `abc`) → sai com falha e mensagem clara,
   sem stack trace.
3. `--all` cria um fechamento para cada competência com lançamento.
4. Rodar `--all` duas vezes não duplica registros.

Use `$this->artisan(...)->assertSuccessful()` / `assertFailed()`.

## Critério de conclusão
- Testes verdes e `docker compose exec app php artisan monthly:calculate --all`
  rodando contra o banco real sem erro.
