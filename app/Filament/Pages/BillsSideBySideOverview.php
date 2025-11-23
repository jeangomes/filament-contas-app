<?php

namespace App\Filament\Pages;

use App\Models\CreditCardBill;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class BillsSideBySideOverview extends Page
{
    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-credit-card';
    protected string $view = 'filament.pages.bills-side-by-side-overview';
    protected static ?string $title = 'Faturas Mensais';
    protected static string | UnitEnum | null $navigationGroup = 'Resultados/Relatórios';
    protected static ?int $navigationSort = 1;

    public ?string $month = null;

    public CreditCardBill|null $billD;
    public CreditCardBill|null $billJ;

    public function mount(): void
    {
        $this->month = now()->format('Y-m');

        $this->loadBills();
    }

    public function updatedMonth(): void
    {
        $this->loadBills();
    }

    public function previousMonth(): void
    {
        $this->month = now()->create($this->month . '-01')->subMonth()->format('Y-m');
        $this->loadBills();
    }

    public function nextMonth(): void
    {
        $this->month = now()->create($this->month . '-01')->addMonth()->format('Y-m');
        $this->loadBills();
    }

    private function loadBills(): void
    {
        $date = now()->create($this->month . '-01');

        $this->billD = CreditCardBill::query()->where('owner_bill', 'D')
            ->whereYear('due_date', $date->year)
            ->whereMonth('due_date', $date->month)
            ->with(['transactions' => function ($query) {
                $query->orderBy('transaction_date', 'asc')->orderBy('id', 'asc');
            }])
            ->first();

        $this->billJ = CreditCardBill::query()->where('owner_bill', 'J')
            ->whereYear('due_date', $date->year)
            ->whereMonth('due_date', $date->month)
            ->with(['transactions' => function ($query) {
                $query->select(['id', 'credit_card_bill_id', 'transaction_date', 'description', 'individual_expense', 'amount']);
                $query->orderBy('transaction_date', 'asc')->orderBy('id', 'asc');
            }])
            ->first();
    }
}
