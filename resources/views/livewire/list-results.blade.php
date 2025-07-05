<div>
    {{-- A good traveler has no fixed plans and is not intent upon arriving. --}}

    <div class="container mx-auto mt-6 px-4">
        <h2 class="text-2xl font-semibold text-center mb-6">Resumo de Pagamentos das Contas da Casa</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full table-auto border-collapse divide-y divide-gray-200 table-striped">
                <thead>
                <tr>
                    <th class="border py-1 px-2 text-left text-sm">Mês Ref</th>
                    <th class="border py-1 px-2 text-left text-sm">Mês <br> Vencimento</th>
                    <th class="border py-1 px-2 text-left text-sm">Aluguel</th>
                    <th class="border py-1 px-2 text-left text-sm">Condomínio</th>
                    <th class="border py-1 px-2 text-left text-sm">Eventuais</th>
                    <th class="border py-1 px-2 text-left text-sm">LIGHT</th>
                    <th class="border py-1 px-2 text-left text-sm">Naturgy</th>
                    <th class="border py-1 px-2 text-left text-sm">Claro</th>
                    <th class="border py-1 px-2 text-left text-sm">Total <br> Casa</th>
                    <th class="border py-1 px-2 text-left text-sm">Dividido <br> por 2</th>
                    <th class="border py-1 px-2 text-left text-sm">Diferença <br> a pagar</th>
                    <th class="border py-1 px-2 text-left text-sm">Quem <br> paga</th>
                </tr>
                </thead>
                <tbody>
                @foreach($resultados as $resultado)
                    <tr class="border-b {!! $loop->even ? 'my-striped-color' : ''!!}">
                        <td class="border py-1 px-2 text-sm">{{ $resultado->mes_ref }}</td>
                        <td class="border py-1 px-2 text-sm">{{ $resultado->mes_vcto }}</td>
                        <td class="border py-1 px-2 text-sm">{{ $this->formatNumber($resultado->aluguel) }}</td>
                        <td class="border py-1 px-2 text-sm">{{ $this->formatNumber($resultado->condominio) }}</td>
                        <td class="border py-1 px-2 text-sm">{{ $this->formatNumber($resultado->eventualidades) }}</td>
                        <td class="border py-1 px-2 text-sm">{{ $this->formatNumber($resultado->light) }}</td>
                        <td class="border py-1 px-2 text-sm">{{ $this->formatNumber($resultado->naturgy) }}</td>
                        <td class="border py-1 px-2 text-sm">{{ $this->formatNumber($resultado->claro) }}</td>
                        <td class="border py-1 px-2 text-sm font-bold">{{ $this->formatNumber($resultado->amount_home_expenses) }}</td>
                        <td class="border py-1 px-2 text-sm font-bold text-danger-600">{{ $this->formatNumber($resultado->amount_home_expenses / 2) }}</td>
                        <td class="border py-1 px-2 text-sm">{{ $this->formatNumber($resultado->balance) }}</td>
                        <td class="border py-1 px-2 text-sm">{{ $resultado->balance_payer }}</td>
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
                    {!! "Mês: {$balance['mes_ano']} -
                    {$balance['participant']} pagou {$this->formatNumber($balance['total_paid'])}
                    e deveria pagar {$this->formatNumber($balance['share'])},
                     saldo: {$this->formatNumber($balance['balance'])}" !!}
                </td>
            </tr>
            @endforeach
        </table>
    </div>
</div>
