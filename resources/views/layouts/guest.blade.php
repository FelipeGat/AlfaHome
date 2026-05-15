<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'AlfaHome') }}</title>
        <link rel="icon" type="image/png" href="/favicon.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            @keyframes shimmer {
                0% { background-position: -200% center; }
                100% { background-position: 200% center; }
            }

            @keyframes pulse-glow {
                0%, 100% { box-shadow: 0 0 20px rgba(16, 185, 129, 0.3), 0 0 40px rgba(16, 185, 129, 0.1); }
                50% { box-shadow: 0 0 30px rgba(16, 185, 129, 0.5), 0 0 60px rgba(16, 185, 129, 0.2); }
            }

            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-5px); }
            }

            .referral-banner {
                background: linear-gradient(135deg,
                    rgba(16, 185, 129, 0.15) 0%,
                    rgba(20, 184, 166, 0.15) 50%,
                    rgba(59, 130, 246, 0.15) 100%);
                border: 1px solid rgba(16, 185, 129, 0.3);
                animation: pulse-glow 3s ease-in-out infinite;
                position: relative;
                overflow: hidden;
            }

            .referral-banner::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(90deg,
                    transparent 0%,
                    rgba(255, 255, 255, 0.1) 50%,
                    transparent 100%);
                background-size: 200% 100%;
                animation: shimmer 3s linear infinite;
            }

            .gift-icon {
                animation: float 2s ease-in-out infinite;
            }

            .referral-btn:hover {
                transform: scale(1.05);
                box-shadow: 0 0 25px rgba(16, 185, 129, 0.4);
            }

            .referral-btn {
                transition: all 0.3s ease;
            }
        </style>
    </head>
    <body class="font-sans text-gray-100 antialiased">
        <div class="min-h-screen flex flex-col justify-center items-center px-4 py-6 sm:py-0 bg-gradient-to-br from-slate-900 via-slate-800 to-black">
            <div class="mb-6">
                <a href="/" class="flex items-center justify-center"
                   style="padding: 14px 32px; border-radius: 14px;
                          background: radial-gradient(ellipse at center, rgba(20,184,166,.18) 0%, rgba(15,23,42,.0) 70%);
                          box-shadow: 0 0 40px rgba(20,184,166,.12), inset 0 1px 0 rgba(255,255,255,.05);">
                    <img src="/alfa-home-logo/alfa-home-logo.png" alt="AlfaHome" style="height: 38px; width: auto; filter: drop-shadow(0 2px 12px rgba(20,184,166,.35));">
                </a>
            </div>

            <div class="w-full sm:max-w-md px-6 py-8 bg-slate-800/50 backdrop-blur-sm shadow-xl overflow-hidden sm:rounded-xl border border-slate-700/50">
                {{ $slot }}
            </div>

            <!-- Banner de Indicação Compacto -->
            <div class="w-full sm:max-w-md mt-6 px-2">
                <div class="referral-banner rounded-xl p-6 sm:p-8 backdrop-blur-sm">
                    <div class="relative z-10 flex items-center gap-6">
                        <!-- Texto + Botão -->
                        <div class="flex-1 min-w-0">
                            <h3 class="text-lg font-bold text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-teal-300 mb-1">
                                Indique e Ganhe 1 Mês Grátis!
                            </h3>
                            <p class="text-sm text-gray-300 mb-3">
                                Compartilhe e <span class="text-emerald-400 font-semibold">economize 100%</span>
                            </p>

                            <a href="#" onclick="alert('Funcionalidade de indicação em breve! Você receberá seu link exclusivo por email.'); return false;"
                               class="referral-btn inline-block px-5 py-2.5 rounded-full font-semibold text-sm
                                      bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600
                                      text-white shadow-lg">
                                Quero Indicar
                                <svg class="inline-block w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </a>
                        </div>

                        <!-- Ícone animado à direita -->
                        <div class="gift-icon flex-shrink-0">
                            <svg class="w-16 h-16 sm:w-20 sm:h-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <defs>
                                    <linearGradient id="giftGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style="stop-color:rgb(16,185,129);stop-opacity:1" />
                                        <stop offset="100%" style="stop-color:rgb(59,130,246);stop-opacity:1" />
                                    </linearGradient>
                                </defs>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" stroke="url(#giftGradient)"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Badge de prova social -->
                    <div class="relative z-10 mt-5 pt-5 border-t border-gray-600/30 flex items-center justify-center gap-2 text-xs text-gray-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                        </svg>
                        <span>Mais de <strong class="text-emerald-400">127 indicações</strong> este mês</span>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
