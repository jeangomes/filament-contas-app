# Task 02 — Corrigir e estender a entidade Transaction

## Status
Concluída

## Objetivo
Corrigir o bug de tipagem nula e remover o campo `isShared` redundante/morto
da entidade `app/Domain/Finance/Entities/Transaction.php`, além de preparar a
entidade para o cálculo de saldo (Task 06).

## Bug atual
```php
private string|null       $responsible, // Quem deveria pagar
private bool|null         $isShared,       // É despesa comum?
...
public function getResponsibleId(): string { return $this->responsible; }
public function isShared(): bool { return $this->isShared; }
```
Os getters declaram retorno não-nulo, mas as propriedades são nullable — isso
é um `TypeError` garantido assim que alguém chamar esses métodos. Hoje ninguém
chama (código morto), mas é uma armadilha para quem for religar
`isCrossDebt()`. Além disso, `isShared` nunca é preenchido em produção
(`LeitorDeFaturaCsv` sempre passa `isShared: null`) e duplica o conceito que
`scope`/`ExpenseScope` já representa.

## Mudanças

1. Remover completamente a propriedade `isShared` e o getter `isShared()`.
2. Trocar o tipo de `whoPaid` de `string` para `Participant` (Task 01).
3. Trocar o tipo de `responsible` de `string|null` para `?Participant`
   (Task 01). Renomear `getResponsibleId()` → `responsible(): ?Participant`.
4. Adicionar `type: TransactionType = TransactionType::FixedExpense` ao
   construtor (default porque a importação de CSV hoje não define esse
   campo — ver Task 09).
5. Remover o getter `getAmount()` duplicado (já existe `amount()`).
6. Reescrever `isCrossDebt()`:
   ```php
   public function isCrossDebt(): bool
   {
       return $this->ehDespesaIndividual()
           && $this->responsible !== null
           && $this->whoPaid !== $this->responsible;
   }
   ```
7. Adicionar `contaParaBalanco(): bool { return $this->type->contaParaBalanco(); }`.
8. Adicionar `referenceMonth(): ReferenceMonth { return ReferenceMonth::fromDate($this->date); }`
   (depende do value object criado na Task 03 — se a Task 03 ainda não
   existir, adicionar este método por último, depois de concluí-la).
9. `whoPaid(): Participant` e `responsible(): ?Participant` passam a
   retornar o enum, não mais `string`.

## Assinatura final do construtor
```php
public function __construct(
    private DateTimeImmutable $date,
    private string            $description,
    private Money             $amount,
    private Participant       $whoPaid,
    private ExpenseScope      $scope,
    private ?Participant      $responsible = null,
    private TransactionType   $type = TransactionType::FixedExpense,
) {}
```

## Testes
`tests/Domain/Finance/Entities/TransactionTest.php`, cobrindo:
- Individual + `responsible` diferente de `whoPaid` → `isCrossDebt()` true.
- Individual + `responsible` igual a `whoPaid` → `isCrossDebt()` false.
- Comum (mesmo com `whoPaid` != `responsible`) → `isCrossDebt()` false.
- `responsible` nulo → `isCrossDebt()` false, sem `TypeError`.
- `contaParaBalanco()` false só quando `type` é `TransactionType::BillPayment`.

## Impacto em outros arquivos
Esta mudança de assinatura do construtor quebra a compilação de
`app/Infra/Csv/LeitorDeFaturaCsv.php` (passa `whoPaid` como `string` e ainda
passa `isShared: null`). **Não corrigir isso nesta task** — é o objetivo da
Task 09. Até lá, `LeitorDeFaturaCsvTest` vai falhar; isso é esperado e
documentado — a suíte completa só volta a passar 100% ao final da Task 09.

## Critério de conclusão
- `php artisan test --filter=TransactionTest` passa.
- `app/Domain/Finance/Entities/Transaction.php` não tem mais `isShared` nem
  getters com tipo de retorno incompatível com a propriedade.
