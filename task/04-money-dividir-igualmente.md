# Task 04 — Money::dividirIgualmente()

## Status
Concluída

## Objetivo
Adicionar um método a `app/Domain/Finance/ValueObjects/Money.php` que divide
um valor em N partes iguais sem perder centavos — corrigindo um bug real de
arredondamento presente no código legado.

## Bug atual
`app/Services/MonthlyBalanceCalculator.php:99`:
```php
$sharePerParticipant = $totalCommon / $numberOfCommonParticipants;
```
Isso é divisão float. Para um total com centavo ímpar (ex.: R$ 100,01 / 2),
o resultado é `50.005`, que ao ser somado/subtraído em cascata pode gerar
diferenças de centavo entre o que foi "distribuído" e o total original.

## Método a adicionar em `Money`
```php
/**
 * Divide o valor em partes iguais, distribuindo o resto (em centavos) uma
 * unidade por vez entre as primeiras partes, de forma que a soma das partes
 * retornadas seja sempre exatamente igual ao valor original.
 *
 * @return list<self>
 */
public function dividirIgualmente(int $partes): array
{
    if ($partes < 1) {
        throw new \InvalidArgumentException('Número de partes deve ser >= 1.');
    }

    $base = intdiv($this->centavos, $partes);
    $resto = $this->centavos % $partes;

    $resultado = [];
    for ($i = 0; $i < $partes; $i++) {
        $resultado[] = new self($base + ($i < abs($resto) ? ($resto <=> 0) : 0));
    }

    return $resultado;
}
```
(Ajustar a lógica de sinal do resto conforme necessário para valores
negativos — cobrir isso explicitamente nos testes abaixo. O importante é a
invariante: `array_reduce($resultado, fn($acc,$m) => $acc->somar($m), Money::zero())->equals($this)` deve ser sempre verdadeiro.)

## Testes
Adicionar em `tests/Domain/Finance/ValueObjects/ValorTest.php` (ou criar
`tests/Domain/Finance/ValueObjects/MoneyDivisaoTest.php` seguindo o mesmo
padrão — `final class`, `#[Test]`, nomes em português, sem mocks):
- `Money::emCentavos(10000)->dividirIgualmente(2)` → duas partes de 5000,
  soma bate.
- `Money::emCentavos(10001)->dividirIgualmente(2)` → 5001 + 5000 (ou
  5000 + 5001 — decidir e documentar qual das partes recebe o centavo extra),
  soma bate com o original.
- `Money::emCentavos(100)->dividirIgualmente(3)` → soma das 3 partes bate
  com 100 (ex.: 34+33+33).
- `Money::emCentavos(-10001)->dividirIgualmente(2)` → soma bate com -10001.
- `dividirIgualmente(0)` → lança `InvalidArgumentException`.

## Critério de conclusão
- `php artisan test --filter=Valor` (ou `MoneyDivisao`) passa, incluindo os
  novos casos.
- A invariante "soma das partes == valor original" está coberta por pelo
  menos um teste com resto não-zero.
