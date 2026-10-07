<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compra no cartão paga pela fatura: a conta de onde saiu o dinheiro. Sem ela,
 * a compra paga debita a própria conta do cartão (comportamento de antes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despesas', function (Blueprint $t) {
            $t->foreignId('pago_com_banco_id')->nullable()->after('forma_pagamento')->constrained('bancos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('despesas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('pago_com_banco_id');
        });
    }
};
