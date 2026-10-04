<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** De onde o sistema busca a planilha da família sozinho: um link do OneDrive por tenant. */
class PlanilhaFonte extends Model
{
    use BelongsToTenant;

    public const FALHA = 'falha';

    protected $table = 'planilha_fontes';

    protected $fillable = ['tenant_id', 'user_id', 'url', 'verificada_em', 'status', 'erro', 'arquivo_hash'];

    protected $hidden = ['url'];

    protected $casts = [
        'url'           => 'encrypted',
        'verificada_em' => 'datetime',
    ];

    public function teveProblema(): bool
    {
        return in_array($this->status, [self::FALHA, PlanilhaImportacao::REJEITADA], true);
    }
}
