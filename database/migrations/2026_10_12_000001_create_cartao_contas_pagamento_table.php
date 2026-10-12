<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conta que paga a fatura de um cartão da planilha. A planilha registra a
 * compra no cartão com a conta do próprio cartão; o dinheiro, porém, sai da
 * conta que pagou a fatura (ex.: Mercado Pago pago pelo Sicoob).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartao_contas_pagamento', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('cartao', 120); // nome normalizado do cartão (PlanejamentoService::chaveConta)
            $t->foreignId('banco_id')->constrained('bancos')->cascadeOnDelete();
            $t->timestamps();

            $t->unique(['tenant_id', 'cartao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cartao_contas_pagamento');
    }
};
