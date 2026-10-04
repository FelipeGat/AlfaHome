<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Trust Docker internal network (nginx container) and loopback only.
// Cloudflare real IP is resolved by nginx (set_real_ip_from) before reaching Laravel.
Request::setTrustedProxies(
    ['172.0.0.0/8', '10.0.0.0/8', '127.0.0.1'],
    Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO
);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permissao'        => \App\Http\Middleware\CheckPermission::class,
            'tenant.ativo'     => \App\Http\Middleware\EnsureTenantActive::class,
            'tenant.ativo.api' => \App\Http\Middleware\EnsureTenantActiveApi::class,
            'role'             => \App\Http\Middleware\CheckRole::class,
            'manutencao'       => \App\Http\Middleware\CheckManutencao::class,
            'planilha.dia'     => \App\Http\Middleware\VerificarPlanilhaDoDia::class,
        ]);

        // O service worker reenvia a fila offline sem página aberta, logo sem
        // token CSRF. O cookie de sessão é SameSite=Lax (não acompanha POST
        // vindo de outro site) e as rotas só aceitam JSON.
        $middleware->validateCsrfTokens(except: ['api/sync/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API requests must always receive JSON, never redirects/HTML.
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->is('logout')) {
                if (Auth::guard('web')->check()) {
                    Auth::guard('web')->logout();
                }

                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }

                return redirect('/login')->with('status', 'Sua sessão foi encerrada.');
            }

            return redirect()->back()->withInput($request->except(['password', 'password_confirmation']))->with(
                'error',
                'Sua sessão expirou. Recarregue a página e tente novamente.'
            );
        });
    })->create();
