<?php

namespace App\Models;

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
