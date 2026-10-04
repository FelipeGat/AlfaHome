<?php

namespace App\Providers;

use App\Models\Despesa;
use App\Models\PlanilhaFonte;
use App\Models\Receita;
use App\Models\Transferencia;
use App\Observers\DespesaObserver;
use App\Observers\ReceitaObserver;
use App\Observers\TransferenciaObserver;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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

        // Situação da análise automática da planilha, para o cabeçalho das telas
        // de planejamento.
        View::composer('planejamento._abas', fn ($view) => $view->with('fontePlanilha', PlanilhaFonte::first()));

        // O sistema é só em português: mensagens (lang/pt_BR) e Carbon (nomes
        // de meses), independente do APP_LOCALE que estiver no .env do servidor.
        App::setLocale('pt_BR');
        Carbon::setLocale('pt_BR');
    }
}
