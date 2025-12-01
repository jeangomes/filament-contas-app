<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Cards de Resumo Rápido --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @php
                $latestSummary = \App\Models\MonthlySummary::latest()->first();
            @endphp

            @if($latestSummary)
                <x-filament::card>
                    <div class="flex items-center space-x-2">
                        <div class="p-2 bg-primary-100 rounded-full">
                            <x-heroicon-o-home class="w-5 h-5 text-primary-600" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Último Mês Calculado</p>
                            <p class="text-lg font-semibold">{{ $latestSummary->reference_month }}</p>
                        </div>
                    </div>
                </x-filament::card>

                <x-filament::card>
                    <div class="flex items-center space-x-2">
                        <div class="p-2 bg-green-100 rounded-full">
                            <x-heroicon-o-currency-dollar class="w-5 h-5 text-green-600" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Despesas Casa</p>
                            <p class="text-lg font-semibold">{{ $this->formatMoney($latestSummary->total_home_expenses) }}</p>
                        </div>
                    </div>
                </x-filament::card>

                <x-filament::card>
                    <div class="flex items-center space-x-2">
                        <div class="p-2 bg-blue-100 rounded-full">
                            <x-heroicon-o-credit-card class="w-5 h-5 text-blue-600" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Cartões</p>
                            <p class="text-lg font-semibold">{{ $this->formatMoney($latestSummary->d_credit_card_total + $latestSummary->j_credit_card_total) }}</p>
                        </div>
                    </div>
                </x-filament::card>

                <x-filament::card>
                    <div class="flex items-center space-x-2">
                        <div class="p-2 {{ $latestSummary->balance_difference == 0 ? 'bg-green-100' : 'bg-yellow-100' }} rounded-full">
                            <x-heroicon-o-scale class="w-5 h-5 {{ $latestSummary->balance_difference == 0 ? 'text-green-600' : 'text-yellow-600' }}" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Saldo</p>
                            <p class="text-sm font-semibold">{{ $latestSummary->balance_description }}</p>
                        </div>
                    </div>
                </x-filament::card>
            @else
                <div class="col-span-4">
                    <x-filament::card>
                        <div class="text-center py-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhum resumo encontrado</h3>
                            <p class="text-gray-600 mb-4">Clique em "Calcular Mês" para gerar o primeiro resumo</p>
                        </div>
                    </x-filament::card>
                </div>
            @endif
        </div>

        {{-- Gráfico Simples dos Últimos Meses (opcional) --}}
        @php
            $recentSummaries = \App\Models\MonthlySummary::latest()->limit(6)->get();
        @endphp

        @if($recentSummaries->count() > 1)
            <x-filament::card>
                <h3 class="text-lg font-medium mb-4">Custo de Vida - Últimos Meses</h3>
                <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                    @foreach($recentSummaries->reverse() as $summary)
                        <div class="text-center">
                            <p class="text-xs text-gray-500 mb-1">{{ \Carbon\Carbon::createFromFormat('Y-m-d', $summary->reference_month)->format('M/y') }}</p>
                            <div class="space-y-1">
                                <div class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                    D: {{ $this->formatMoney($summary->d_living_cost) }}
                                </div>
                                <div class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">
                                    J: {{ $this->formatMoney($summary->j_living_cost) }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::card>
        @endif

        {{-- Instruções de Uso --}}
        <x-filament::card>
            <h3 class="text-lg font-medium mb-4">💡 Como Usar</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <h4 class="font-medium mb-2">Calcular Mês</h4>
                    <ul class="space-y-1 text-gray-600">
                        <li>• Selecione o mês de referência desejado</li>
                        <li>• O sistema calculará automaticamente todas as despesas</li>
                        <li>• Dados de cartões e contas serão processados</li>
                        <li>• O resultado será salvo na tabela abaixo</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium mb-2">Visualizar Detalhes</h4>
                    <ul class="space-y-1 text-gray-600">
                        <li>• Clique no ícone "👁" para ver detalhes completos</li>
                        <li>• Visualize despesas por categoria</li>
                        <li>• Veja gastos individuais e comuns dos cartões</li>
                        <li>• Confira o cálculo do custo de vida</li>
                    </ul>
                </div>
            </div>
        </x-filament::card>

        {{-- Tabela --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>
