<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AppUpdateController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\PushController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BancoApiController;
use App\Http\Controllers\Api\V1\CatalogosController;
use App\Http\Controllers\Api\V1\CategoriaApiController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DespesaController as ApiDespesaController;
use App\Http\Controllers\Api\V1\FamiliarApiController;
use App\Http\Controllers\Api\V1\FornecedorApiController;
use App\Http\Controllers\Api\V1\InvestimentoApiController;
use App\Http\Controllers\Api\V1\ReceitaController as ApiReceitaController;
use App\Http\Controllers\Api\V1\TransferenciaController;

/*
|--------------------------------------------------------------------------
| API Routes — AlfaHome PWA (legacy / session-based)
|--------------------------------------------------------------------------
| These routes are stateless from the CSRF point of view but rely on the
| web session cookie. They power the existing PWA Service Worker and are
| kept untouched for backwards compatibility.
*/

/*
|--------------------------------------------------------------------------
| Auto-atualização do app mobile (público, sem sessão/token)
|--------------------------------------------------------------------------
| Consultado no boot do app pra saber se existe uma versão mais nova
| publicada — ver AppUpdateController e `php artisan app:publish-update`.
*/
Route::get('/app/version', [AppUpdateController::class, 'version'])
    ->name('api.app.version');

Route::middleware(['auth', 'tenant.ativo'])->group(function () {

    Route::get('/dashboard/snapshot', [DashboardApiController::class, 'snapshot'])
        ->name('api.dashboard.snapshot');

    Route::post('/push/subscribe',   [PushController::class, 'subscribe'])
        ->name('api.push.subscribe');
    Route::post('/push/unsubscribe', [PushController::class, 'unsubscribe'])
        ->name('api.push.unsubscribe');

    Route::post('/sync/despesa',  [App\Http\Controllers\Api\SyncController::class, 'despesa'])
        ->name('api.sync.despesa');
    Route::post('/sync/receita',  [App\Http\Controllers\Api\SyncController::class, 'receita'])
        ->name('api.sync.receita');

});

/*
|--------------------------------------------------------------------------
| API Routes V1 — AlfaHome Mobile App (Sanctum, token-based)
|--------------------------------------------------------------------------
| Stateless REST API consumed by the Flutter mobile app.
| Authentication: Bearer token (Laravel Sanctum personal access tokens).
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ── Public ───────────────────────────────────────────────────────────
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    // ── Authenticated (Sanctum) ──────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant.ativo.api'])->group(function () {

        // Auth
        Route::get(   'auth/me',           [AuthController::class, 'me'])->name('auth.me');
        Route::put(   'auth/me',           [AuthController::class, 'updateMe'])->name('auth.me.update');
        Route::delete('auth/me',           [AuthController::class, 'deleteMe'])->name('auth.me.delete');
        Route::post(  'auth/me/password',  [AuthController::class, 'updatePassword'])->name('auth.me.password');
        Route::post(  'auth/me/foto',      [AuthController::class, 'uploadFoto'])->name('auth.me.foto.upload');
        Route::delete('auth/me/foto',      [AuthController::class, 'deleteFoto'])->name('auth.me.foto.delete');
        Route::post(  'auth/logout',       [AuthController::class, 'logout'])->name('auth.logout');
        Route::post(  'auth/logout-all',   [AuthController::class, 'logoutAll'])->name('auth.logout-all');

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ── Catálogos (listagens read-only para dropdowns) ──────────────
        Route::get('categorias',   [CatalogosController::class, 'categorias'])->name('categorias.index');
        Route::get('familiares',   [CatalogosController::class, 'familiares'])->name('familiares.index');
        Route::get('fornecedores', [CatalogosController::class, 'fornecedores'])->name('fornecedores.index');
        Route::get('bancos',       [CatalogosController::class, 'bancos'])->name('bancos.index');

        // ── Categorias (CRUD) ───────────────────────────────────────────
        Route::post(  'categorias',              [CategoriaApiController::class, 'store'])->name('categorias.store');
        Route::get(   'categorias/{categoria}',  [CategoriaApiController::class, 'show'])->name('categorias.show');
        Route::put(   'categorias/{categoria}',  [CategoriaApiController::class, 'update'])->name('categorias.update');
        Route::delete('categorias/{categoria}',  [CategoriaApiController::class, 'destroy'])->name('categorias.destroy');

        // ── Bancos (CRUD) ───────────────────────────────────────────────
        Route::post(  'bancos',          [BancoApiController::class, 'store'])->name('bancos.store');
        Route::get(   'bancos/{banco}',  [BancoApiController::class, 'show'])->name('bancos.show');
        Route::put(   'bancos/{banco}',  [BancoApiController::class, 'update'])->name('bancos.update');
        Route::delete('bancos/{banco}',  [BancoApiController::class, 'destroy'])->name('bancos.destroy');

        // ── Fornecedores (CRUD) ─────────────────────────────────────────
        Route::post(  'fornecedores',               [FornecedorApiController::class, 'store'])->name('fornecedores.store');
        Route::get(   'fornecedores/{fornecedor}',  [FornecedorApiController::class, 'show'])->name('fornecedores.show');
        Route::put(   'fornecedores/{fornecedor}',  [FornecedorApiController::class, 'update'])->name('fornecedores.update');
        Route::delete('fornecedores/{fornecedor}',  [FornecedorApiController::class, 'destroy'])->name('fornecedores.destroy');

        // ── Familiares (CRUD) ───────────────────────────────────────────
        Route::post(  'familiares',             [FamiliarApiController::class, 'store'])->name('familiares.store');
        Route::get(   'familiares/{familiar}',  [FamiliarApiController::class, 'show'])->name('familiares.show');
        Route::put(   'familiares/{familiar}',  [FamiliarApiController::class, 'update'])->name('familiares.update');
        Route::delete('familiares/{familiar}',  [FamiliarApiController::class, 'destroy'])->name('familiares.destroy');

        // ── Investimentos (CRUD + rendimentos) ──────────────────────────
        Route::get(   'investimentos',                [InvestimentoApiController::class, 'index'])->name('investimentos.index');
        Route::post(  'investimentos',                [InvestimentoApiController::class, 'store'])->name('investimentos.store');
        Route::get(   'investimentos/{investimento}', [InvestimentoApiController::class, 'show'])->name('investimentos.show');
        Route::put(   'investimentos/{investimento}', [InvestimentoApiController::class, 'update'])->name('investimentos.update');
        Route::delete('investimentos/{investimento}', [InvestimentoApiController::class, 'destroy'])->name('investimentos.destroy');
        Route::post(  'investimentos/{investimento}/rendimentos',              [InvestimentoApiController::class, 'storeRendimento'])->name('investimentos.rendimentos.store');
        Route::put(   'investimentos/{investimento}/rendimentos/{rendimento}', [InvestimentoApiController::class, 'updateRendimento'])->name('investimentos.rendimentos.update');
        Route::delete('investimentos/{investimento}/rendimentos/{rendimento}', [InvestimentoApiController::class, 'destroyRendimento'])->name('investimentos.rendimentos.destroy');

        // ── Transferências (entidade própria — não infla KPIs) ──────────
        Route::get(   'transferencias',                   [TransferenciaController::class, 'index'])->name('transferencias.index');
        Route::post(  'transferencias',                   [TransferenciaController::class, 'store'])->name('transferencias.store');
        Route::get(   'transferencias/{transferencia}',   [TransferenciaController::class, 'show'])->name('transferencias.show');
        Route::put(   'transferencias/{transferencia}',   [TransferenciaController::class, 'update'])->name('transferencias.update');
        Route::delete('transferencias/{transferencia}',   [TransferenciaController::class, 'destroy'])->name('transferencias.destroy');

        // ── Despesas (CRUD) ──────────────────────────────────────────────
        Route::get(   'despesas',                   [ApiDespesaController::class, 'index'])->name('despesas.index');
        Route::post(  'despesas',                   [ApiDespesaController::class, 'store'])->name('despesas.store');
        Route::get(   'despesas/grupo/{grupoId}',   [ApiDespesaController::class, 'grupo'])->name('despesas.grupo');
        Route::get(   'despesas/{despesa}',         [ApiDespesaController::class, 'show'])->name('despesas.show');
        Route::put(   'despesas/{despesa}', [ApiDespesaController::class, 'update'])->name('despesas.update');
        Route::delete('despesas/{despesa}', [ApiDespesaController::class, 'destroy'])->name('despesas.destroy');

        // ── Receitas (CRUD) ──────────────────────────────────────────────
        Route::get(   'receitas',           [ApiReceitaController::class, 'index'])->name('receitas.index');
        Route::post(  'receitas',           [ApiReceitaController::class, 'store'])->name('receitas.store');
        Route::get(   'receitas/{receita}', [ApiReceitaController::class, 'show'])->name('receitas.show');
        Route::put(   'receitas/{receita}', [ApiReceitaController::class, 'update'])->name('receitas.update');
        Route::delete('receitas/{receita}', [ApiReceitaController::class, 'destroy'])->name('receitas.destroy');

    });

});
