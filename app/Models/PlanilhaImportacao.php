<?php

namespace App\Models;

use App\Services\Planejamento\PlanilhaParser;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PlanilhaImportacao extends Model
{
    use BelongsToTenant;

    public const SUCESSO = 'sucesso';
    public const SEM_ALTERACOES = 'sem_alteracoes';
    public const REJEITADA = 'rejeitada';

    protected $table = 'planilha_importacoes';

    protected $fillable = [
        'tenant_id', 'user_id', 'arquivo_nome', 'arquivo_hash', 'status', 'resumo', 'avisos', 'erros',
    ];

    protected $casts = [
        'resumo' => 'array',
        'avisos' => 'array',
        'erros'  => 'array',
    ];

    /**
     * Resumo por aba na ordem das abas da planilha — o MySQL reordena as
     * chaves de uma coluna JSON ao gravar.
     */
    public function getResumoAttribute($valor): ?array
    {
        $resumo = $this->castAttribute('resumo', $valor);
        if (! is_array($resumo)) {
            return null;
        }

        $ordem = array_flip(array_keys(PlanilhaParser::abas()));
        uksort($resumo, fn ($a, $b) => ($ordem[$a] ?? 99) <=> ($ordem[$b] ?? 99));

        return $resumo;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
