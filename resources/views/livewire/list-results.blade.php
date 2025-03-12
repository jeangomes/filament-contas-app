<div>
    {{-- A good traveler has no fixed plans and is not intent upon arriving. --}}
    <br>
    <div style="display: none;">
        {{ $this->table }}
    </div>
    <br>

    <div class="container mx-auto mt-8 px-4">
        <h2 class="text-2xl font-semibold text-center mb-6">Resumo de Pagamentos</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full table-auto border-collapse">
                <thead class="bg-gray-800">
                <tr>
                    <th class="py-3 px-4 text-left">Mês Ref</th>
                    <th class="py-3 px-4 text-left">Mês Vencimento</th>
                    <th class="py-3 px-4 text-left">Aluguel</th>
                    <th class="py-3 px-4 text-left">Condomínio</th>
                    <th class="py-3 px-4 text-left">Eventualidades</th>
                    <th class="py-3 px-4 text-left">LIGHT</th>
                    <th class="py-3 px-4 text-left">Naturgy</th>
                    <th class="py-3 px-4 text-left">Claro</th>
                </tr>
                </thead>
                <tbody>
                @foreach($resultados as $resultado)
                    <tr class="border-b hover:bg-gray-100">
                        <td class="py-3 px-4">{{ $resultado->mes_ref }}</td>
                        <td class="py-3 px-4">{{ $resultado->mes_vcto }}</td>
                        <td class="py-3 px-4">{{ $this->formatNumber($resultado->aluguel) }}</td>
                        <td class="py-3 px-4">{{ $this->formatNumber($resultado->condominio) }}</td>
                        <td class="py-3 px-4">{{ $this->formatNumber($resultado->eventualidades) }}</td>
                        <td class="py-3 px-4">{{ $this->formatNumber($resultado->light) }}</td>
                        <td class="py-3 px-4">{{ $this->formatNumber($resultado->naturgy) }}</td>
                        <td class="py-3 px-4">{{ $this->formatNumber($resultado->claro) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <br>
        <h2 class="text-2xl font-semibold text-center mb-6">Apuração dos cartões de crédito</h2>
        <p class="font-medium text-1xl text-center mb-6">Divisão igualitária dos gastos em comum:</p>
        <br>
        <table>
            @foreach ($finalBalances as $balance)
            <tr>
                <td>
                    {!! "Mês: {$balance['mes_ano']} - {$balance['participant']} pagou {$balance['total_paid']} e deveria pagar {$balance['share']}, saldo: {$balance['balance']}" !!}
                </td>
            </tr>
            @endforeach
        </table>
    </div>
</div>
