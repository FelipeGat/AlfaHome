<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferencias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->decimal('valor', 12, 2);
            $t->date('data');
            $t->foreignId('origem_id')->constrained('bancos');
            $t->foreignId('destino_id')->constrained('bancos');
            $t->text('observacao')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index(['tenant_id', 'data']);
            $t->index('origem_id');
            $t->index('destino_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencias');
    }
};
