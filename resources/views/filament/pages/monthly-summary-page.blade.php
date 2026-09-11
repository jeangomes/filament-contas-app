<x-filament-panels::page>
    <div class="space-y-6">
        @php($ultimo = $this->ultimoFechamento)

        @if($ultimo)
            {{-- Resumo do fechamento mais recente --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <x-filament::card>
                    <p class="text-sm text-gray-600">Última competência</p>
                    <p class="text-lg font-semibold">{{ $ultimo->competencia->format('m/Y') }}</p>
                    <p class="text-xs text-gray-500">gastos de {{ $ultimo->reference_month->format('m/Y') }}</p>
                </x-filament::card>

                <x-filament::card>
                    <p class="text-sm text-gray-600">Despesas da casa</p>
                    <p class="text-lg font-semibold">{{ $this->formatMoney($ultimo->total_home_expenses) }}</p>
                </x-filament::card>

                <x-filament::card>
                    <p class="text-sm text-gray-600">Total comum</p>
                    <p class="text-lg font-semibold">{{ $this->formatMoney($ultimo->total_common_expenses) }}</p>
                </x-filament::card>

                <x-filament::card>
                    <p class="text-sm text-gray-600">Acerto</p>
                    <p class="text-sm font-semibold">{{ $ultimo->balance_description }}</p>
                    @if($ultimo->difference_paid)
                        <p class="text-xs text-green-600">já acertado</p>
                    @endif
                </x-filament::card>
            </div>

            {{-- Custo de vida de cada um na última competência --}}
            <x-filament::card>
                <h3 class="text-lg font-medium mb-4">Custo de vida — {{ $ultimo->competencia->format('m/Y') }}</h3>
                <div class="grid grid-cols-2 gap-4">
                    @foreach($ultimo->participantSummaries->sortBy('participant') as $participante)
                        <div class="text-center">
                            <p class="text-xs text-gray-500 mb-1">{{ $participante->participant->value }}</p>
                            <p class="text-lg font-semibold">{{ $this->formatMoney($participante->living_cost) }}</p>
                        </div>
                    @endforeach
                </div>
            </x-filament::card>
        @endif

        {{ $this->table }}

        <x-filament::card>
            <h3 class="text-lg font-medium mb-4">Como funciona</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                <div>
                    <h4 class="font-medium mb-2 text-gray-900">Calcular mês</h4>
                    <ul class="space-y-1">
                        <li>• A competência é o mês do <strong>vencimento</strong> das faturas.</li>
                        <li>• Os números são apurados das transações, não digitados.</li>
                        <li>• Recalcular quantas vezes quiser é seguro: substitui, não duplica.</li>
                        <li>• O que você marca como <strong>Pago</strong> sobrevive ao recálculo.</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium mb-2 text-gray-900">Custo de vida × saldo</h4>
                    <ul class="space-y-1">
                        <li>• <strong>Custo de vida</strong>: quanto a vida de cada um custou no mês.</li>
                        <li>• <strong>Saldo</strong>: quem adiantou dinheiro e quem ficou devendo.</li>
                        <li>• São perguntas diferentes — não se somam.</li>
                        <li>• Em "Detalhe" você vê as despesas da casa e os números completos.</li>
                    </ul>
                </div>
            </div>
        </x-filament::card>
    </div>
</x-filament-panels::page>
