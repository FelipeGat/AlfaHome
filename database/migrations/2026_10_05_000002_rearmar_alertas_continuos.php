<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Antes da correção, o relógio marcava saldo negativo e limite acima de 80%
 * como já avisados mesmo sem ninguém vinculado ao Telegram. Zerar os estados
 * faz esses alertas saírem uma vez na próxima passagem.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('notificacao_estados')->delete();
    }

    public function down(): void
    {
        // Nada a desfazer: os estados se recompõem na próxima passagem.
    }
};
