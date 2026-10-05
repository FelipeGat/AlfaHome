<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacao_configs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $t->text('telegram_token')->nullable();
            $t->string('telegram_bot')->nullable();
            $t->string('codigo_vinculo', 32)->nullable();
            $t->timestamp('codigo_vinculo_ate')->nullable();
            $t->json('tipos_desligados')->nullable();
            $t->timestamp('ligado_em')->nullable();
            $t->timestamp('processado_em')->nullable();
            $t->timestamps();
        });

        Schema::create('telegram_destinatarios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->bigInteger('chat_id');
            $t->string('nome')->nullable();
            $t->boolean('ativo')->default(true);
            $t->string('motivo_inativo')->nullable();
            $t->timestamps();

            $t->unique(['tenant_id', 'chat_id']);
        });

        Schema::create('notificacao_envios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('destinatario_id')->constrained('telegram_destinatarios')->cascadeOnDelete();
            $t->string('tipo', 20);
            $t->string('chave', 191);
            $t->text('texto');
            $t->string('status', 12)->default('pendente');
            $t->unsignedTinyInteger('tentativas')->default(0);
            $t->string('erro')->nullable();
            $t->timestamp('enviado_em')->nullable();
            $t->timestamps();

            $t->unique(['destinatario_id', 'chave']);
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('notificacao_estados', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('chave', 191);
            $t->boolean('em_alerta')->default(false);
            $t->unsignedInteger('vezes')->default(0);
            $t->timestamps();

            $t->unique(['tenant_id', 'chave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacao_estados');
        Schema::dropIfExists('notificacao_envios');
        Schema::dropIfExists('telegram_destinatarios');
        Schema::dropIfExists('notificacao_configs');
    }
};
