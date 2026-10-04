<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planilha_fontes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->text('url');                                 // cifrado (cast encrypted)
            $t->timestamp('verificada_em')->nullable();
            $t->string('status', 20)->nullable();            // sucesso | sem_alteracoes | rejeitada | falha
            $t->text('erro')->nullable();
            $t->char('arquivo_hash', 64)->nullable();        // último arquivo importado com sucesso
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planilha_fontes');
    }
};
