<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Um chat do Telegram que recebe os avisos da família. */
class TelegramDestinatario extends Model
{
    use BelongsToTenant;

    protected $table = 'telegram_destinatarios';

    protected $fillable = ['tenant_id', 'chat_id', 'nome', 'ativo', 'motivo_inativo'];

    protected $casts = ['ativo' => 'boolean', 'chat_id' => 'integer'];
}
