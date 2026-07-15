<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class AppUpdateController extends Controller
{
    private const MANIFEST_PATH = 'app_update.json';

    /**
     * GET /api/app/version
     *
     * Manifesto de versão consultado pelo app mobile no boot para
     * auto-atualização (sem depender de loja). Publicado via
     * `php artisan app:publish-update`. Sem manifesto = 204 (app
     * trata como "está na última versão").
     */
    public function version(): JsonResponse|Response
    {
        if (!Storage::disk('local')->exists(self::MANIFEST_PATH)) {
            return response()->noContent();
        }

        $manifest = json_decode(Storage::disk('local')->get(self::MANIFEST_PATH), true);

        return response()->json(['data' => $manifest]);
    }
}
