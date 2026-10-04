<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despesas', function (Blueprint $t) {
            $t->decimal('valor_previsto', 10, 2)->nullable()->after('valor');
        });
        Schema::table('receitas', function (Blueprint $t) {
            $t->decimal('valor_previsto', 10, 2)->nullable()->after('valor');
        });
    }

    public function down(): void
    {
        Schema::table('despesas', function (Blueprint $t) {
            $t->dropColumn('valor_previsto');
        });
        Schema::table('receitas', function (Blueprint $t) {
            $t->dropColumn('valor_previsto');
        });
    }
};
