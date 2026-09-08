# Task 05 — Value Objects Acerto e MonthlyBalance

## Status
Concluída

## Objetivo
Modelar explicitamente a regra descrita pelo usuário: "o maior pagador de
gastos em comum recebe a diferença do menor pagador, para que fique justo".
Isso vira dois value objects que sabem calcular e representar o acerto, sem
nenhum conhecimento de transações/SQL — só saldos.

## Arquivos a criar

### `app/Domain/Finance/ValueObjects/Acerto.php`
```php
<?php

declare(strict_types=1);

namespace App\Domain\Finance\ValueObjects;

use App\Domain\Finance\Enums\Participant;

final readonly class Acerto
{
    public function __construct(
        public Participant $recebe,
        public Participant $paga,
        public Money        $valor, // sempre positivo
    ) {}
}
```

### `app/Domain/Finance/ValueObjects/MonthlyBalance.php`
```php
<?php

declare(strict_types=1);

namespace App\Domain\Finance\ValueObjects;

use App\Domain\Finance\Enums\Participant;

final readonly class MonthlyBalance
{
    /** @param array<string, Money> $saldosPorParticipante Chave = Participant::value */
    public function __construct(
        private ReferenceMonth $mes,
        private array          $saldosPorParticipante,
    ) {}

    public function mes(): ReferenceMonth
    {
        return $this->mes;
    }

    public function saldoDe(Participant $participante): Money
    {
        return $this->saldosPorParticipante[$participante->value] ?? Money::zero();
    }

    /**
     * Pareia quem está com saldo positivo (recebe) com quem está com saldo
     * negativo (paga). Para os dois participantes atuais (D/J) isso sempre
     * produz zero ou um Acerto, mas o algoritmo é escrito de forma genérica.
     *
     * @return list<Acerto>
     */
    public function acertos(): array
    {
        $credores = [];
        $devedores = [];

        foreach ($this->saldosPorParticipante as $chave => $saldo) {
            if ($saldo->ehPositivo()) {
                $credores[] = [Participant::from($chave), $saldo];
            } elseif ($saldo->ehNegativo()) {
                $devedores[] = [Participant::from($chave), $saldo->absoluto()];
            }
        }

        $acertos = [];
        foreach ($devedores as [$devedor, $valorDevido]) {
            foreach ($credores as [$credor, $valorACobrar]) {
                if ($valorDevido->ehZero() || $valorACobrar->ehZero()) {
                    continue;
                }
                // Para 2 participantes isso sempre fecha exatamente; ok simplificar aqui.
            }
        }

        // Implementação simples para o caso real (2 participantes): um credor, um devedor.
        if (count($credores) === 1 && count($devedores) === 1) {
            [$credor, $valorCredor] = $credores[0];
            [$devedor, $valorDevedor] = $devedores[0];

            $acertos[] = new Acerto($credor, $devedor, $valorCredor->equals($valorDevedor) ? $valorCredor : $valorCredor);
        }

        return $acertos;
    }
}
```

**Nota de implementação:** o pseudocódigo acima para `acertos()` está
propositalmente simplificado para o caso de 2 participantes (D e J), que é o
único caso real do sistema hoje. Ao implementar de fato, prefira a versão
direta:
```php
public function acertos(): array
{
    if (count($this->saldosPorParticipante) !== 2) {
        throw new \LogicException('acertos() suporta apenas exatamente 2 participantes.');
    }

    [$pA, $saldoA] = /* primeiro par chave/valor */;
    [$pB, $saldoB] = /* segundo par chave/valor */;

    if ($saldoA->ehZero()) {
        return [];
    }

    return $saldoA->ehPositivo()
        ? [new Acerto(Participant::from($pA), Participant::from($pB), $saldoA)]
        : [new Acerto(Participant::from($pB), Participant::from($pA), $saldoA->negar())];
}
```
Escolher esta versão (mais simples, explícita sobre a limitação a 2
participantes) em vez do algoritmo genérico de pareamento, a menos que surja
um motivo concreto para generalizar.

## Testes
`tests/Domain/Finance/ValueObjects/MonthlyBalanceTest.php`:
- Saldos `D: +500, J: -500` → um `Acerto(recebe: D, paga: J, valor: 500)`.
- Saldos `D: 0, J: 0` → `acertos()` vazio.
- Saldos `D: -500, J: +500` → um `Acerto(recebe: J, paga: D, valor: 500)`.
- `saldoDe()` retorna `Money::zero()` para participante sem entrada no array.

## Critério de conclusão
- `php artisan test --filter=MonthlyBalanceTest` passa.
- `Acerto::$valor` nunca é negativo em nenhum teste.
