<x-filament-panels::page>
    {{--
        Estilo escrito à mão de propósito: o tema Tailwind do painel é compilado
        pelo Vite e o build em public/build está desatualizado, então utilitário
        novo (gap-x-6, grid-cols-4, ...) não chega ao CSS. Estas classes valem
        sem depender de rebuild.
    --}}
    @push('styles')
        <style>
            .fechamento-card .fi-section-content { padding: 1rem; }

            .fechamento-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
                gap: 0.75rem 1.5rem;
            }

            .fechamento-item { min-width: 0; }

            .fechamento-rotulo {
                font-size: 0.6875rem;
                line-height: 1rem;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: rgb(107 114 128);
            }

            .fechamento-valor {
                font-size: 1rem;
                line-height: 1.375rem;
                font-weight: 600;
                font-variant-numeric: tabular-nums;
            }

            .fechamento-nota { font-size: 0.6875rem; line-height: 1rem; color: rgb(107 114 128); }

            .fechamento-ok { font-size: 0.6875rem; line-height: 1rem; color: rgb(5 150 105); }

            .fechamento-custo {
                display: flex;
                flex-wrap: wrap;
                align-items: baseline;
                gap: 0.25rem 1.25rem;
                margin-top: 0.75rem;
                padding-top: 0.75rem;
                border-top: 1px solid rgb(229 231 235);
            }

            .fechamento-custo-valor { font-size: 0.875rem; font-weight: 600; font-variant-numeric: tabular-nums; }

            .dark .fechamento-rotulo,
            .dark .fechamento-nota { color: rgb(156 163 175); }
            .dark .fechamento-ok { color: rgb(52 211 153); }
            .dark .fechamento-custo { border-top-color: rgb(255 255 255 / 0.1); }
        </style>
    @endpush

    <div class="space-y-6">
        @php($ultimo = $this->ultimoFechamento)

        @if($ultimo)
            {{-- Resumo do fechamento mais recente --}}
            <x-filament::card class="fechamento-card">
                <div class="fechamento-grid">
                    <div class="fechamento-item">
                        <p class="fechamento-rotulo">Última competência</p>
                        <p class="fechamento-valor">{{ $ultimo->competencia->format('m/Y') }}</p>
                        <p class="fechamento-nota">gastos de {{ $ultimo->reference_month->format('m/Y') }}</p>
                    </div>

                    <div class="fechamento-item">
                        <p class="fechamento-rotulo">Despesas da casa</p>
                        <p class="fechamento-valor">{{ $this->formatMoney($ultimo->total_home_expenses) }}</p>
                    </div>

                    <div class="fechamento-item">
                        <p class="fechamento-rotulo">Total comum</p>
                        <p class="fechamento-valor">{{ $this->formatMoney($ultimo->total_common_expenses) }}</p>
                    </div>

                    <div class="fechamento-item">
                        <p class="fechamento-rotulo">Acerto</p>
                        <p class="fechamento-valor">{{ $ultimo->balance_description }}</p>
                        @if($ultimo->difference_paid)
                            <p class="fechamento-ok">já acertado</p>
                        @endif
                    </div>
                </div>

                {{-- Custo de vida de cada um na última competência --}}
                <div class="fechamento-custo">
                    <p class="fechamento-rotulo">Custo de vida — {{ $ultimo->competencia->format('m/Y') }}</p>
                    @foreach($ultimo->participantSummaries->sortBy('participant') as $participante)
                        <p>
                            <span class="fechamento-rotulo">{{ $participante->participant->value }}</span>
                            <span class="fechamento-custo-valor">{{ $this->formatMoney($participante->living_cost) }}</span>
                        </p>
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
