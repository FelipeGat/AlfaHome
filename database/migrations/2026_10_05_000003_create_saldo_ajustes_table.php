<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo da conta passa a ser calculado: último ajuste + movimentos realizados
 * depois dele. O ajuste inicial de cada conta é o saldo que ela tem hoje, então
 * nenhum número muda na virada — só passa a andar sozinho daqui em diante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldo_ajustes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('banco_id')->constrained('bancos')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->date('data');
            $t->decimal('saldo', 15, 2);
            $t->decimal('diferenca', 15, 2)->default(0);
            $t->string('observacao')->nullable();
            $t->timestamps();

            $t->index(['banco_id', 'data']);
        });

        $hoje = now()->toDateString();
        $agora = now();
        DB::table('bancos')->orderBy('id')->each(function ($b) use ($hoje, $agora) {
            DB::table('saldo_ajustes')->insert([
                'tenant_id'  => $b->tenant_id,
                'banco_id'   => $b->id,
                'data'       => $hoje,
                'saldo'      => $b->saldo ?? 0,
                'diferenca'  => 0,
                'observacao' => 'Saldo inicial (cadastro da conta)',
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saldo_ajustes');
    }
};
