<x-filament-panels::page>
    <div>

        <div class="container mx-auto mt-8">
            <h2 class="text-2xl font-semibold text-center mb-6">Resumo de Pagamentos das Contas da Casa</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto border-collapse divide-y divide-gray-200 table-striped">
                    <thead>
                    <tr>
                        <th class="border py-1 px-2 text-left text-sm">Mês Ref</th>
                        <th class="border py-1 px-2 text-left text-sm">Mês <br> Vencimento</th>
                        <th class="border py-1 px-2 text-left text-sm">Total <br> Casa</th>
                        <th class="border py-1 px-2 text-left text-sm">Casa <br> por 2</th>

                        <th class="border py-1 px-2 text-left text-sm">D <br> NB Total</th>
                        <th class="border py-1 px-2 text-left text-sm">D <br> NB Comum</th>
                        <th class="border py-1 px-2 text-left text-sm">D <br> NB Individual</th>
                        <th class="border py-1 px-2 text-left text-sm my-bg-primary">D <br> Custo <br> de Vida</th>

                        <th class="border py-1 px-2 text-left text-sm">J <br> NB Total</th>
                        <th class="border py-1 px-2 text-left text-sm">J <br> NB Comum</th>
                        <th class="border py-1 px-2 text-left text-sm">J <br> NB Individual</th>
                        <th class="border py-1 px-2 text-left text-sm my-bg-primary">J <br> Custo <br> de Vida</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($resultados as $resultado)
                        <tr class="border-b {!! $loop->even ? 'my-striped-color' : ''!!}">
                            <td class="border py-1 px-2 text-sm">{{ $resultado->mes_ref }}</td>
                            <td class="border py-1 px-2 text-sm">{{ $resultado->mes_vcto }}</td>
                            <td class="border py-1 px-2 text-sm">{{ $this->formatNumber($resultado->amount_home_expenses) }}</td>
                            <td class="border py-1 px-2 text-sm">{{ $this->formatNumber($resultado->amount_home_expenses / 2) }}</td>

                            <td class="border py-1 px-2 text-sm">{{ $this->formatNumber($resultado->bill_d?->amount) }}</td>
                            <td class="border py-1 px-2 text-sm">{{ $this->formatNumber($resultado->bill_d?->common_amount) }}</td>
                            <td class="border py-1 px-2 text-sm">{{ $this->formatNumber($resultado->bill_d?->individual_amount) }}</td>
                            <td class="border py-1 px-2 text-sm">
                                {{ $this->formatNumber($resultado->living_cost['D']) }}
                            </td>

                            <td class="border py-1 px-2 text-sm">{{ $this->formatNumber($resultado->bill_j?->amount) }}</td>
                            <td class="border py-1 px-2 text-sm">{{ $this->formatNumber($resultado->bill_j?->common_amount) }}</td>
                            <td class="border py-1 px-2 text-sm">{{ $this->formatNumber($resultado->bill_j?->individual_amount) }}</td>
                            <td class="border py-1 px-2 text-sm">
                                {{ $this->formatNumber($resultado->living_cost['J']) }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</x-filament-panels::page>
