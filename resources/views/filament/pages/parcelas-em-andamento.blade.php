<x-filament-panels::page>
    {{--
        Estilo escrito à mão pelo mesmo motivo do Consolidado Mensal: o tema
        Tailwind do painel é compilado pelo Vite e o build em public/build está
        desatualizado, então utilitário novo não chega ao CSS.
    --}}
    @push('styles')
        <style>
            .parcelas-filtros {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 1rem;
                margin-bottom: 1.5rem;
            }

            .parcelas-filtro-rotulo {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.875rem;
                color: rgb(75 85 99);
            }

            .parcelas-tabela { width: 100%; font-size: 0.875rem; border-collapse: collapse; }
            .parcelas-tabela th { padding: 0.5rem 0.75rem; text-align: left; font-weight: 600; }
            .parcelas-tabela td { padding: 0.5rem 0.75rem; border-top: 1px solid rgb(229 231 235); }
            .parcelas-tabela .num { text-align: right; font-variant-numeric: tabular-nums; }
            .parcelas-tabela tfoot td { font-weight: 600; border-top: 2px solid rgb(209 213 219); }

            .parcelas-quitada { opacity: 0.55; }

            .parcelas-badge {
                display: inline-block;
                padding: 0.05rem 0.4rem;
                border-radius: 0.375rem;
                font-size: 0.75rem;
                font-variant-numeric: tabular-nums;
                background: rgb(243 244 246);
                color: rgb(55 65 81);
            }

            .parcelas-barra {
                height: 0.3rem;
                margin-top: 0.3rem;
                border-radius: 999px;
                background: rgb(229 231 235);
                overflow: hidden;
            }

            .parcelas-barra span { display: block; height: 100%; background: rgb(59 130 246); }

            .parcelas-vazio { color: rgb(107 114 128); font-size: 0.875rem; }

            .parcelas-alerta { color: rgb(180 83 9); font-size: 0.8125rem; }

            .dark .parcelas-filtro-rotulo { color: rgb(156 163 175); }
            .dark .parcelas-tabela td { border-top-color: rgb(255 255 255 / 0.1); }
            .dark .parcelas-tabela tfoot td { border-top-color: rgb(255 255 255 / 0.2); }
            .dark .parcelas-badge { background: rgb(255 255 255 / 0.1); color: rgb(229 231 235); }
            .dark .parcelas-barra { background: rgb(255 255 255 / 0.1); }
            .dark .parcelas-alerta { color: rgb(251 191 36); }
        </style>
    @endpush

    <div class="parcelas-filtros">
        <label class="parcelas-filtro-rotulo">
            <input type="checkbox" wire:model.live="incluirQuitadas" />
            Mostrar séries já quitadas
        </label>

        <label class="parcelas-filtro-rotulo">
            Pagador:
            <select wire:model.live="pagador" class="fi-input-text-input border rounded px-2 py-1">
                @foreach ($this->opcoesDePagador() as $valor => $rotulo)
                    <option value="{{ $valor }}">{{ $rotulo }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                {{ $this->incluirQuitadas ? 'Compras parceladas' : 'Parcelas em andamento' }}
            </x-slot>

            @php($series = $this->parcelamentos)

            @if (count($series) === 0)
                <p class="parcelas-vazio">
                    Nenhuma compra parcelada encontrada. Itens de fatura importados antes desta
                    tela existir precisam de <code>php artisan transactions:extrair-parcelas</code>.
                </p>
            @else
                <div class="fi-ta-table overflow-hidden rounded-xl border border-gray-200">
                    <table class="parcelas-tabela">
                        <thead class="bg-gray-50">
                        <tr>
                            <th>Compra</th>
                            <th>Pagador</th>
                            <th>Parcela</th>
                            <th class="num">Valor da parcela</th>
                            <th class="num">Já pago</th>
                            <th class="num">Falta</th>
                            <th>Termina em</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach ($series as $serie)
                            <tr @class(['parcelas-quitada' => ! $serie->emAndamento()])>
                                <td>
                                    {{ $serie->descricao }}
                                    <div class="parcelas-barra">
                                        <span style="width: {{ $this->progresso($serie) }}%"></span>
                                    </div>
                                </td>
                                <td>{{ $serie->quemPagou->value }}</td>
                                <td><span class="parcelas-badge">{{ $serie->rotulo() }}</span></td>
                                <td class="num">{{ $this->formatMoney($serie->valorDaParcela) }}</td>
                                <td class="num">{{ $this->formatMoney($serie->totalPago) }}</td>
                                <td class="num">{{ $this->formatMoney($serie->valorRestante()) }}</td>
                                <td>
                                    @if (! $serie->emAndamento())
                                        <span class="parcelas-vazio">quitada</span>
                                    @elseif ($this->estaDefasada($serie))
                                        <span class="parcelas-alerta" title="A fatura seguinte não foi importada">
                                            sem lançamento desde {{ $this->formatCompetencia($serie->ultimaCompetencia) }}
                                        </span>
                                    @else
                                        {{ $this->formatCompetencia($serie->competenciaFinal()) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Compromisso já assumido nos próximos meses</x-slot>

            @php($compromissos = $this->compromissos)

            @if (count($compromissos) === 0)
                <p class="parcelas-vazio">Nenhuma parcela a vencer.</p>
            @else
                <div class="fi-ta-table overflow-hidden rounded-xl border border-gray-200">
                    <table class="parcelas-tabela">
                        <thead class="bg-gray-50">
                        <tr>
                            <th>Competência</th>
                            <th class="num">Parcelas a vencer</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach ($compromissos as $compromisso)
                            <tr>
                                <td>{{ $this->formatCompetencia($compromisso->mes) }}</td>
                                <td class="num">{{ $this->formatMoney($compromisso->valor) }}</td>
                            </tr>
                        @endforeach
                        </tbody>

                        <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="num">{{ $this->formatMoney($this->totalComprometido) }}</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
