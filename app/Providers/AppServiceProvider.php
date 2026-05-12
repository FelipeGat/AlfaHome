<?php

namespace App\Providers;

use App\Models\Despesa;
use App\Models\Receita;
use App\Models\Transferencia;
use App\Observers\DespesaObserver;
use App\Observers\ReceitaObserver;
use App\Observers\TransferenciaObserver;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Despesa::observe(DespesaObserver::class);
        Receita::observe(ReceitaObserver::class);
        Transferencia::observe(TransferenciaObserver::class);

        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
        URL::forceRootUrl(config('app.url'));

        // Locale pt_BR para Carbon (nomes de meses em português)
        Carbon::setLocale('pt_BR');
    }
}
