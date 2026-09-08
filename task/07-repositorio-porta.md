# Task 07 — Porta RepositorioDeLancamentos

## Status
Concluída

## Objetivo
Definir a interface (porta, no sentido hexagonal) que o domínio usa para
obter lançamentos, sem saber nada sobre Eloquent/SQL. A implementação real
fica para a Task 08.

## Arquivo a criar

`app/Domain/Finance/Repositories/RepositorioDeLancamentos.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domain\Finance\Repositories;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\ValueObjects\ReferenceMonth;

interface RepositorioDeLancamentos
{
    /**
     * @return list<Transaction>
     */
    public function paraCompetencia(ReferenceMonth $mes): array;

    /**
     * Meses distintos em que existe pelo menos um lançamento (usado para
     * reconstruir o relatório histórico que hoje `calculateFinalBalances()`
     * gera varrendo todos os meses da tabela).
     *
     * @return list<ReferenceMonth>
     */
    public function competenciasDisponiveis(): array;
}
```

Isto é análogo ao já existente `app/Domain/FonteDeLancamentos.php` (porta
usada pela importação de CSV) — mas para consulta de lançamentos já
persistidos, filtrando por competência.

## Sem implementação nesta task
Não criar nenhuma classe que implemente esta interface aqui — isso é a
Task 08. Nesta task o único critério é a interface compilar e não ter nenhum
`use` de Eloquent/Illuminate.

## Critério de conclusão
- Arquivo criado, com `declare(strict_types=1)`, sem dependência de
  Eloquent/Illuminate.
- `composer dump-autoload` ou `php -l` no arquivo sem erros.
