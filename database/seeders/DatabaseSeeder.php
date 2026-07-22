<?php

namespace Database\Seeders;

use App\Models\Banco;
use App\Models\Categoria;
use App\Models\Despesa;
use App\Models\Familiar;
use App\Models\Fornecedor;
use App\Models\Investimento;
use App\Models\Plano;
use App\Models\Receita;
use App\Models\Revenda;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\CategoriasDefaultSeeder;
use Database\Seeders\FornecedoresDefaultSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Planos ──────────────────────────────────────────────────────────
        $planoIndividual = Plano::create([
            'nome'         => 'Individual',
            'slug'         => 'individual',
            'descricao'    => 'Para uso individual',
            'preco_mensal' => 19.90,
            'preco_anual'  => 199.00,
            'max_usuarios' => 1,
            'max_bancos'   => 1,
            'ativo'        => true,
        ]);

        $planoCasal = Plano::create([
            'nome'         => 'Casal',
            'slug'         => 'casal',
            'descricao'    => 'Para casais',
            'preco_mensal' => 29.90,
            'preco_anual'  => 299.00,
            'max_usuarios' => 2,
            'max_bancos'   => 4,
            'ativo'        => true,
        ]);

        $planoFamilia = Plano::create([
            'nome'         => 'Família',
            'slug'         => 'familia',
            'descricao'    => 'Para famílias — recursos ilimitados',
            'preco_mensal' => 49.90,
            'preco_anual'  => 499.00,
            'max_usuarios' => -1,
            'max_bancos'   => -1,
            'ativo'        => true,
        ]);

        // ─── Super Admin ─────────────────────────────────────────────────────
        User::create([
            'name'      => 'Super Admin',
            'email'     => 'admin@alfahome.com',
            'password'  => Hash::make('password'),
            'role'      => 'super_admin',
            'ativo'     => true,
        ]);

        // ─── Revenda de exemplo ──────────────────────────────────────────────
        $revenda = Revenda::create([
            'nome'     => 'Revenda Demo',
            'cnpj'     => '12.345.678/0001-00',
            'email'    => 'contato@revendademo.com',
            'telefone' => '(11) 99999-0000',
            'status'   => 'ativo',
        ]);

        User::create([
            'name'       => 'Admin Revenda',
            'email'      => 'revenda@alfahome.com',
            'password'   => Hash::make('password'),
            'role'       => 'admin_revenda',
            'revenda_id' => $revenda->id,
            'ativo'      => true,
        ]);

        // ─── Tenant (cliente da revenda) ─────────────────────────────────────
        $tenant = Tenant::create([
            'nome'       => 'Família Felipe',
            'ativo'      => true,
            'revenda_id' => $revenda->id,
            'plano_id'   => $planoFamilia->id,
            'status'     => 'ativo',
        ]);

        $user = User::create([
            'name'      => 'Felipe',
            'email'     => 'felipe@controleflex.com',
            'password'  => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'role'      => 'master',
            'ativo'     => true,
        ]);

        // Conta padrão de STAGING — a equipe inteira valida os stagings com ela.
        // NUNCA remover: um rebuild que a apague só aparece um dia depois, quando
        // as sessões antigas caem, e parece "sistema fora do ar" (AlfaJornada,
        // 2026-07-21). Guardada contra produção: credencial conhecida. Role master
        // + tenant do seed = cai direto no /dashboard, como o restante da equipe usa.
        if (! app()->isProduction()) {
            User::firstOrCreate(
                ['email' => 'admin@teste.com'],
                [
                    'name'      => 'Administrador Teste',
                    'password'  => Hash::make('Staging@2026'),
                    'tenant_id' => $tenant->id,
                    'role'      => 'master',
                    'ativo'     => true,
                ]
            );
        }

        // Login para que o trait BelongsToTenant auto-preencha tenant_id
        Auth::login($user);

        // ─── Familiares ───────────────────────────────────────────────────────

        $felipe = Familiar::create(['user_id' => $user->id, 'nome' => 'Felipe', 'salario' => 5000, 'limite_cartao' => 3000, 'limite_cheque' => 1000]);
        $maria  = Familiar::create(['user_id' => $user->id, 'nome' => 'Maria',  'salario' => 4000, 'limite_cartao' => 2000, 'limite_cheque' => 500]);

        // Vincular o master ao seu familiar
        $user->update(['familiar_id' => $felipe->id]);

        // ─── Categorias ───────────────────────────────────────────────────────

        CategoriasDefaultSeeder::seedParaTenant($tenant->id, $user->id);

        // Mapa para uso no seed de despesas/receitas
        $cm = Categoria::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy(fn($c) => $c->nome . '_' . $c->tipo)
            ->map(fn($c) => $c->id)
            ->toArray();

        // ─── Fornecedores ─────────────────────────────────────────────────────

        FornecedoresDefaultSeeder::seedParaTenant($tenant->id, $user->id);

        // Aliases para uso nos seeds de despesas
        $supermercado = Fornecedor::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('nome', 'Extra')->first()
            ?? Fornecedor::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('grupo', 'Supermercados')->first();
        $farmacia     = Fornecedor::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('nome', 'Droga Raia')->first();
        $restaurante  = Fornecedor::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('nome', "McDonald's")->first();
        $posto        = Fornecedor::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('nome', 'Posto Shell')->first();
        $academia     = Fornecedor::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('nome', 'Smart Fit')->first();

        // ─── Bancos ───────────────────────────────────────────────────────────

        $nubank   = Banco::create(['user_id' => $user->id, 'nome' => 'Nubank',   'tipo_conta' => 'Conta Corrente', 'saldo' => 3500.00, 'limite_cartao' => 5000, 'saldo_cartao' => 1200]);
        $itau     = Banco::create(['user_id' => $user->id, 'nome' => 'Itaú',     'tipo_conta' => 'Conta Corrente', 'saldo' => 8200.00, 'cheque_especial' => 2000]);
        $carteira = Banco::create(['user_id' => $user->id, 'nome' => 'Carteira', 'tipo_conta' => 'Dinheiro',       'saldo' => 350.00]);
        $poupanca = Banco::create(['user_id' => $user->id, 'nome' => 'Poupança', 'tipo_conta' => 'Poupança',       'saldo' => 12000.00]);

        // ─── Despesas (6 meses) ───────────────────────────────────────────────

        $modelosDespesas = [
            ['for' => $supermercado->id, 'cat' => $cm['Supermercado / Feira_DESPESA'],    'valor' => 450,  'fam' => $felipe->id, 'banco' => $nubank->id,   'dia' => 5],
            ['for' => $restaurante->id,  'cat' => $cm['Restaurante / Delivery_DESPESA'],  'valor' => 180,  'fam' => $maria->id,  'banco' => $nubank->id,   'dia' => 12],
            ['for' => $farmacia->id,     'cat' => $cm['Farmácia / Remédios_DESPESA'],     'valor' => 95,   'fam' => $felipe->id, 'banco' => $itau->id,    'dia' => 8],
            ['for' => $posto->id,        'cat' => $cm['Combustível_DESPESA'],             'valor' => 220,  'fam' => $felipe->id, 'banco' => $nubank->id,   'dia' => 15],
            ['for' => null,              'cat' => $cm['Aluguel / Financiamento_DESPESA'], 'valor' => 1500, 'fam' => $felipe->id, 'banco' => $itau->id,    'dia' => 1],
            ['for' => $academia->id,     'cat' => $cm['Academia / Esportes_DESPESA'],     'valor' => 110,  'fam' => $maria->id,  'banco' => $nubank->id,   'dia' => 3],
            ['for' => null,              'cat' => $cm['Escola / Faculdade_DESPESA'],       'valor' => 350,  'fam' => $felipe->id, 'banco' => $itau->id,    'dia' => 10],
        ];

        for ($m = 0; $m < 6; $m++) {
            foreach ($modelosDespesas as $d) {
                $dataCompra = Carbon::now()->subMonths($m)->startOfMonth()->addDays($d['dia'] - 1)->format('Y-m-d');
                $pago       = $m > 0; // mês atual fica como "a pagar", anteriores como pagos

                Despesa::create([
                    'user_id'              => $user->id,
                    'quem_comprou'         => $d['fam'],
                    'onde_comprou'         => $d['for'],
                    'categoria_id'         => $d['cat'],
                    'forma_pagamento'      => $d['banco'],
                    'valor'                => $d['valor'],
                    'data_compra'          => $dataCompra,
                    'data_pagamento'       => $pago ? $dataCompra : null,
                    'recorrente'           => true,
                    'parcelas'             => 6,
                    'frequencia'           => 'mensal',
                    'grupo_recorrencia_id' => 'seed-desp-' . $d['cat'],
                ]);
            }
        }

        // Despesa vencida (para testar badge vermelho)
        Despesa::create([
            'user_id'         => $user->id,
            'quem_comprou'    => $felipe->id,
            'onde_comprou'    => $farmacia->id,
            'categoria_id'    => $cm['Farmácia / Remédios_DESPESA'],
            'forma_pagamento' => $nubank->id,
            'valor'           => 75.00,
            'data_compra'     => Carbon::now()->subDays(10)->format('Y-m-d'),
            'data_pagamento'  => null,
            'recorrente'      => false,
            'parcelas'        => 1,
            'frequencia'      => 'mensal',
        ]);

        // ─── Receitas (6 meses) ───────────────────────────────────────────────

        for ($m = 0; $m < 6; $m++) {
            $dataPrevista = Carbon::now()->subMonths($m)->startOfMonth()->addDays(4)->format('Y-m-d');
            $recebido     = $m > 0;

            // Salário Felipe
            Receita::create([
                'user_id'                   => $user->id,
                'quem_recebeu'              => $felipe->id,
                'categoria_id'              => $cm['Salário_RECEITA'],
                'forma_recebimento'         => $itau->id,
                'valor'                     => 5000,
                'data_prevista_recebimento' => $dataPrevista,
                'data_recebimento'          => $recebido ? $dataPrevista : null,
                'recorrente'                => true,
                'parcelas'                  => 6,
                'frequencia'                => 'mensal',
                'grupo_recorrencia_id'      => 'seed-salario-felipe',
            ]);

            // Salário Maria
            Receita::create([
                'user_id'                   => $user->id,
                'quem_recebeu'              => $maria->id,
                'categoria_id'              => $cm['Salário_RECEITA'],
                'forma_recebimento'         => $nubank->id,
                'valor'                     => 4000,
                'data_prevista_recebimento' => $dataPrevista,
                'data_recebimento'          => $recebido ? $dataPrevista : null,
                'recorrente'                => true,
                'parcelas'                  => 6,
                'frequencia'                => 'mensal',
                'grupo_recorrencia_id'      => 'seed-salario-maria',
            ]);

            // Freelance esporádico (somente meses pares)
            if ($m % 2 === 0) {
                $dataFreelance = Carbon::now()->subMonths($m)->startOfMonth()->addDays(14)->format('Y-m-d');
                Receita::create([
                    'user_id'                   => $user->id,
                    'quem_recebeu'              => $felipe->id,
                    'categoria_id'              => $cm['Freelance / Bico_RECEITA'],
                    'forma_recebimento'          => $nubank->id,
                    'valor'                     => rand(800, 2000),
                    'data_prevista_recebimento' => $dataFreelance,
                    'data_recebimento'          => $recebido ? $dataFreelance : null,
                    'recorrente'                => false,
                    'parcelas'                  => 1,
                    'frequencia'                => 'mensal',
                ]);
            }
        }

        // ─── Investimentos ────────────────────────────────────────────────────

        $aportes = [
            ['nome' => 'Tesouro Selic 2029',      'tipo' => 'Renda Fixa',          'valor' => 2000, 'cotas' => 0,       'banco' => $itau->id,     'meses_atras' => 5],
            ['nome' => 'CDB Nubank 120% CDI',      'tipo' => 'Renda Fixa',          'valor' => 1500, 'cotas' => 0,       'banco' => $nubank->id,   'meses_atras' => 4],
            ['nome' => 'IVVB11',                   'tipo' => 'ETF',                 'valor' => 800,  'cotas' => 5.2,     'banco' => $itau->id,     'meses_atras' => 3],
            ['nome' => 'MXRF11',                   'tipo' => 'Fundo Imobiliário',   'valor' => 600,  'cotas' => 55.0,    'banco' => $nubank->id,   'meses_atras' => 2],
            ['nome' => 'Tesouro Selic 2029',       'tipo' => 'Renda Fixa',          'valor' => 2000, 'cotas' => 0,       'banco' => $itau->id,     'meses_atras' => 1],
            ['nome' => 'PETR4',                    'tipo' => 'Ação',                'valor' => 500,  'cotas' => 18.5,    'banco' => $nubank->id,   'meses_atras' => 0],
        ];

        foreach ($aportes as $inv) {
            Investimento::create([
                'user_id'           => $user->id,
                'banco_id'          => $inv['banco'],
                'nome_ativo'        => $inv['nome'],
                'tipo_investimento' => $inv['tipo'],
                'data_aporte'       => Carbon::now()->subMonths($inv['meses_atras'])->startOfMonth()->addDays(19)->format('Y-m-d'),
                'valor_aportado'    => $inv['valor'],
                'quantidade_cotas'  => $inv['cotas'],
                'observacoes'       => null,
            ]);
        }
    }
}
