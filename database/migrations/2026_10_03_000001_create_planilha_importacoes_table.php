<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planilha_importacoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('arquivo_nome');
            $t->char('arquivo_hash', 64);
            $t->string('status', 20);
            $t->json('resumo')->nullable();
            $t->json('avisos')->nullable();
            $t->json('erros')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planilha_importacoes');
    }
};
