<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Colunas comuns a toda tabela que espelha uma aba da planilha. */
    private function comuns(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
        $t->foreignId('importacao_id')->nullable()->constrained('planilha_importacoes')->nullOnDelete();
        $t->char('chave', 40);
        $t->char('conteudo_hash', 40);
        $t->unsignedSmallInteger('linha');
        $t->timestamps();

        $t->unique(['tenant_id', 'chave']);
    }

    public function up(): void
    {
        Schema::create('plan_lancamentos', function (Blueprint $t) {
            $this->comuns($t);
            $t->date('data');
            $t->string('tipo', 10);
            $t->string('descricao');
            $t->string('categoria')->nullable();
            $t->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $t->string('forma', 20)->nullable();
            $t->string('conta')->nullable();
            $t->decimal('valor_previsto', 12, 2)->nullable();
            $t->decimal('valor_realizado', 12, 2)->nullable();
            $t->string('status', 20);
            $t->string('status_planilha')->nullable();
            $t->text('observacao')->nullable();
            $t->string('id_planilha')->nullable();

            $t->index(['tenant_id', 'data']);
            $t->index(['tenant_id', 'tipo', 'data']);
        });

        Schema::create('plan_contas_fixas', function (Blueprint $t) {
            $this->comuns($t);
            $t->string('conta');
            $t->string('categoria')->nullable();
            $t->unsignedTinyInteger('dia_vencimento')->nullable();
            $t->decimal('valor_previsto', 12, 2)->nullable();
            $t->decimal('valor_realizado', 12, 2)->nullable();
            $t->string('forma', 20)->nullable();
            $t->boolean('recorrente')->nullable();
            $t->string('status', 20)->nullable();
            $t->string('status_planilha')->nullable();
            $t->date('mes_inicial')->nullable();
            $t->text('observacao')->nullable();
        });

        Schema::create('plan_cartoes', function (Blueprint $t) {
            $this->comuns($t);
            $t->string('nome');
            $t->string('banco')->nullable();
            $t->decimal('limite_total', 12, 2)->nullable();
            $t->decimal('limite_utilizado', 12, 2)->nullable();
            $t->unsignedTinyInteger('dia_fechamento')->nullable();
            $t->unsignedTinyInteger('dia_vencimento')->nullable();
            $t->decimal('fatura_atual', 12, 2)->nullable();
            $t->string('status_fatura')->nullable();
            $t->text('observacao')->nullable();
        });

        Schema::create('plan_compras_parceladas', function (Blueprint $t) {
            $this->comuns($t);
            $t->string('compra');
            $t->string('cartao')->nullable();
            $t->foreignId('plan_cartao_id')->nullable()->constrained('plan_cartoes')->nullOnDelete();
            $t->date('data')->nullable();
            $t->decimal('valor_total', 12, 2)->nullable();
            $t->unsignedSmallInteger('parcelas')->nullable();
            $t->unsignedSmallInteger('parcela_atual')->nullable();
            $t->unsignedSmallInteger('parcelas_pagas')->nullable();
            $t->date('proximo_vencimento')->nullable();
            $t->text('observacao')->nullable();
        });

        Schema::create('plan_dividas', function (Blueprint $t) {
            $this->comuns($t);
            $t->string('nome');
            $t->string('credor')->nullable();
            $t->decimal('saldo_inicial', 12, 2)->nullable();
            $t->decimal('saldo_atual', 12, 2)->nullable();
            $t->decimal('taxa_mensal', 8, 6)->nullable();
            $t->decimal('parcela_mensal', 12, 2)->nullable();
            $t->unsignedTinyInteger('dia_vencimento')->nullable();
            $t->string('status')->nullable();
            $t->string('prioridade', 10)->nullable();
            $t->date('previsao_quitacao')->nullable();
            $t->text('observacao')->nullable();
        });

        Schema::create('plan_metas', function (Blueprint $t) {
            $this->comuns($t);
            $t->string('nome');
            $t->string('objetivo')->nullable();
            $t->decimal('valor_alvo', 12, 2)->nullable();
            $t->decimal('valor_atual', 12, 2)->nullable();
            $t->date('prazo')->nullable();
            $t->string('prioridade', 10)->nullable();
            $t->decimal('aporte_mensal', 12, 2)->nullable();
            $t->text('observacao')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_metas');
        Schema::dropIfExists('plan_dividas');
        Schema::dropIfExists('plan_compras_parceladas');
        Schema::dropIfExists('plan_cartoes');
        Schema::dropIfExists('plan_contas_fixas');
        Schema::dropIfExists('plan_lancamentos');
    }
};
