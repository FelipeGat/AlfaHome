<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Despesa "todo mês" (sem fim) gera 60 meses de lançamentos de uma vez. No
 * cartão, só a do mês consome limite — diferente da compra parcelada, que
 * prende o valor todo. A marca separa uma da outra.
 *
 * Existentes: no cartão, a série sem fim é a que não tem "Parcela i/N" na
 * observação (a parcelada sempre tem, desde a criação) e foi criada com 60.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despesas', function (Blueprint $t) {
            $t->boolean('recorrencia_sem_fim')->default(false)->after('recorrente');
        });

        $grupos = DB::table('despesas')
            ->where('tipo_pagamento', 'credito')->whereNotNull('grupo_recorrencia_id')->where('parcelas', 60)
            ->groupBy('grupo_recorrencia_id')
            ->havingRaw("SUM(CASE WHEN observacoes LIKE 'Parcela %' THEN 1 ELSE 0 END) = 0")
            ->pluck('grupo_recorrencia_id');

        DB::table('despesas')->whereIn('grupo_recorrencia_id', $grupos)->update(['recorrencia_sem_fim' => true]);
    }

    public function down(): void
    {
        Schema::table('despesas', function (Blueprint $t) {
            $t->dropColumn('recorrencia_sem_fim');
        });
    }
};
