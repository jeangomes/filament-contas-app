<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class MonthlySummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_month',
        'due_payment_month',
        'house_rental',
        'condominium',
        'eventual_apartment',
        'electricity_bill',
        'gas_bill',
        'internet_bill',
        'total_home_expenses',
        'total_home_expenses_per_person',
        'balance_difference',
        'balance_payer',
        'd_credit_card_total',
        'd_credit_card_common',
        'd_credit_card_individual',
        'd_living_cost',
        'j_credit_card_total',
        'j_credit_card_common',
        'j_credit_card_individual',
        'j_living_cost',
        'is_calculated',
        'calculated_at',
        'calculation_notes',
        'difference_paid'
    ];

    protected $casts = [
        'house_rental' => 'decimal:2',
        'condominium' => 'decimal:2',
        'eventual_apartment' => 'decimal:2',
        'electricity_bill' => 'decimal:2',
        'gas_bill' => 'decimal:2',
        'internet_bill' => 'decimal:2',
        'total_home_expenses' => 'decimal:2',
        'total_home_expenses_per_person' => 'decimal:2',
        'balance_difference' => 'decimal:2',
        'd_credit_card_total' => 'decimal:2',
        'd_credit_card_common' => 'decimal:2',
        'd_credit_card_individual' => 'decimal:2',
        'd_living_cost' => 'decimal:2',
        'j_credit_card_total' => 'decimal:2',
        'j_credit_card_common' => 'decimal:2',
        'j_credit_card_individual' => 'decimal:2',
        'j_living_cost' => 'decimal:2',
        'is_calculated' => 'boolean',
        'difference_paid' => 'boolean',
        'calculated_at' => 'datetime',
    ];

    /**
     * Accessor para formatar valores monetários
     */
    protected function formattedBalance(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format(abs($this->balance_difference), 2, ',', '.'),
        );
    }

    /**
     * Accessor para texto descritivo do saldo
     */
    protected function balanceDescription(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->balance_difference == 0) {
                    return 'Contas em dia';
                }

                $payer = $this->balance_payer;
                $amount = $this->formattedBalance;

                return "{$payer} deve pagar R$ {$amount}";
            },
        );
    }

    /**
     * Scope para buscar por mês de referência
     */
    public function scopeByReferenceMonth($query, string $month)
    {
        return $query->where('reference_month', $month);
    }

    /**
     * Scope para buscar apenas calculados automaticamente
     */
    public function scopeCalculated($query)
    {
        return $query->where('is_calculated', true);
    }

    /**
     * Scope para ordenar por mês mais recente
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('reference_month', 'desc');
    }
}
