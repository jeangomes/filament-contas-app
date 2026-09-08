# Task 03 — Value Object ReferenceMonth

## Status
Concluída

## Objetivo
Criar um value object para representar um mês de competência ("YYYY-MM"),
substituindo a validação por regex + `Carbon` solta hoje em
`app/Services/MonthlySummaryService.php:28-35`:
```php
if (!preg_match('/^\d{4}-\d{2}$/', $referenceMonth)) {
    throw new InvalidArgumentException('Formato de mês inválido. Use YYYY-MM');
}
$duePaymentMonth = Carbon::createFromFormat('Y-m', $referenceMonth)->addMonth()->format('Y-m');
```

## Arquivo a criar

`app/Domain/Finance/ValueObjects/ReferenceMonth.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domain\Finance\ValueObjects;

use App\Domain\Finance\Exceptions\ValorMonetarioInvalido; // ou nova exceção de domínio (ver nota)
use DateTimeImmutable;

final readonly class ReferenceMonth
{
    private function __construct(
        private int $ano,
        private int $mes,
    ) {}

    public static function fromDate(DateTimeImmutable $data): self
    {
        return new self((int) $data->format('Y'), (int) $data->format('n'));
    }

    public static function fromString(string $chave): self
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $chave, $m)) {
            throw new \DomainException(sprintf('Mês de competência inválido: "%s". Use YYYY-MM.', $chave));
        }

        return new self((int) $m[1], (int) $m[2]);
    }

    public function proximoMes(): self
    {
        return $this->mes === 12
            ? new self($this->ano + 1, 1)
            : new self($this->ano, $this->mes + 1);
    }

    public function ano(): int
    {
        return $this->ano;
    }

    public function numeroDoMes(): int
    {
        return $this->mes;
    }

    public function equals(self $outro): bool
    {
        return $this->ano === $outro->ano && $this->mes === $outro->mes;
    }

    public function chave(): string
    {
        return sprintf('%04d-%02d', $this->ano, $this->mes);
    }
}
```

Nota: não reaproveitar `ValorMonetarioInvalido` (é especificamente sobre
dinheiro). Se quiser uma exceção de domínio dedicada, criar
`app/Domain/Finance/Exceptions/CompetenciaInvalida.php` seguindo o mesmo
padrão de `ValorMonetarioInvalido` (factory estático `paraEntrada(string
$entrada): self`, extends `DomainException`). Caso contrário, `DomainException`
genérica é aceitável.

## Testes
`tests/Domain/Finance/ValueObjects/ReferenceMonthTest.php`:
- `fromString('2026-01')` → `ano() === 2026`, `numeroDoMes() === 1`.
- `fromString('2026-13')`, `fromString('abc')`, `fromString('2026-1')` →
  lançam exceção.
- `fromString('2026-12')->proximoMes()` → equivale a `fromString('2027-01')`
  (usar `equals()`).
- `fromDate(new DateTimeImmutable('2026-03-15'))->chave() === '2026-03'`.
- `equals()` true/false conforme esperado.

## Critério de conclusão
- `php artisan test --filter=ReferenceMonthTest` passa.
- Nenhum outro arquivo usa `ReferenceMonth` ainda (isso começa na Task 02,
  passo 8, e continua nas Tasks 06-08).
