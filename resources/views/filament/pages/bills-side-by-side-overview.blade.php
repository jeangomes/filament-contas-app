<x-filament-panels::page>
    {{-- Navegação por mês --}}
    <div class="flex items-center gap-4 mb-6">
        <x-filament::button wire:click="previousMonth" color="gray">
            ← Mês anterior
        </x-filament::button>

        <input
            type="month"
            wire:model="month"
            class="fi-input-text-input border rounded px-2 py-1"
        />

        <x-filament::button wire:click="nextMonth" color="gray">
            Próximo mês →
        </x-filament::button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        {{-- Fatura D --}}
        <x-filament::section>
            <x-slot name="heading">Fatura de D</x-slot>

            @if($billD)
                <div class="space-y-2">
                    <p><strong>Valor Total:</strong> R$ {{ number_format($billD->amount, 2, ',', '.') }}</p>
                    <p><strong>Despesas Comuns:</strong> R$ {{ number_format($billD->common_amount, 2, ',', '.') }}</p>
                    <p><strong>Despesas Individuais:</strong> R$ {{ number_format($billD->individual_amount, 2, ',', '.') }}</p>
                </div>
                <br>
                <div class="fi-ta-table overflow-hidden rounded-xl border border-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Descrição</th>
                            <th class="px-3 py-2 text-left font-semibold">Data</th>
                            <th class="px-3 py-2 text-left font-semibold">Individual</th>
                            <th class="px-3 py-2 text-right font-semibold">Valor</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach ($billD->transactions as $t)
                            <tr class="border-t">
                                <td class="px-3 py-2">{{ $t->description }}</td>
                                <td class="px-3 py-2">{{ $t->transaction_date->format('d/m/Y') }}</td>
                                <td class="px-3 py-2">{{ $t->individual_expense ? 'Sim' : 'Não' }}</td>
                                <td class="px-3 py-2 text-right">
                                    R$ {{ number_format($t->amount, 2, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500">Nenhuma fatura encontrada para este mês.</p>
            @endif

        </x-filament::section>

        {{-- Fatura J --}}
        <x-filament::section>
            <x-slot name="heading">Fatura de J</x-slot>

            @if($billJ)
                <div class="space-y-2">
                    <p><strong>Valor Total:</strong> R$ {{ number_format($billJ->amount, 2, ',', '.') }}</p>
                    <p><strong>Despesas Comuns:</strong> R$ {{ number_format($billJ->common_amount, 2, ',', '.') }}</p>
                    <p><strong>Despesas Individuais:</strong> R$ {{ number_format($billJ->individual_amount, 2, ',', '.') }}</p>
                </div>
                <br>
                <div class="fi-ta-table overflow-hidden rounded-xl border border-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Descrição</th>
                            <th class="px-3 py-2 text-left font-semibold">Data</th>
                            <th class="px-3 py-2 text-left font-semibold">Individual</th>
                            <th class="px-3 py-2 text-right font-semibold">Valor</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach ($billJ->transactions as $t)
                            <tr class="border-t">
                                <td class="px-3 py-2">{{ $t->description }}</td>
                                <td class="px-3 py-2">{{ $t->transaction_date->format('d/m/Y') }}</td>
                                <td class="px-3 py-2">{{ $t->individual_expense ? 'Sim' : 'Não' }}</td>
                                <td class="px-3 py-2 text-right">
                                    R$ {{ number_format($t->amount, 2, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500">Nenhuma fatura encontrada para este mês.</p>
            @endif

        </x-filament::section>

    </div>
</x-filament-panels::page>
