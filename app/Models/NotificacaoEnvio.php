<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Fila de envios: uma linha por destinatário e ocorrência (a chave impede duplicata). */
class NotificacaoEnvio extends Model
{
    use BelongsToTenant;

    public const PENDENTE = 'pendente';
    public const ENVIANDO = 'enviando';
    public const ENVIADO  = 'enviado';
    public const FALHOU   = 'falhou';
    public const EXPIRADO = 'expirado';

    public const MAX_TENTATIVAS = 5;

    protected $fillable = ['tenant_id', 'destinatario_id', 'tipo', 'chave', 'texto', 'status', 'tentativas', 'erro', 'enviado_em'];

    protected $casts = ['enviado_em' => 'datetime'];

    public function destinatario()
    {
        return $this->belongsTo(TelegramDestinatario::class, 'destinatario_id');
    }
}
