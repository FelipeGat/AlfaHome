<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Avisos da família: bot do Telegram (token cifrado), código de vínculo e tipos desligados. */
class NotificacaoConfig extends Model
{
    use BelongsToTenant;

    public const TIPOS = [
        'resumo'  => 'Resumo do dia às 7h',
        'pagar'   => 'Contas a pagar (3 dias antes e no dia)',
        'receber' => 'Contas a receber (no dia e em atraso)',
        'saldo'   => 'Saldo negativo',
        'compra'  => 'Compra nova no cartão',
        'limite'  => 'Limite do cartão acima de 80%',
    ];

    protected $fillable = ['tenant_id', 'telegram_token', 'telegram_bot', 'codigo_vinculo', 'codigo_vinculo_ate', 'tipos_desligados', 'ligado_em', 'processado_em'];

    protected $hidden = ['telegram_token'];

    protected $casts = [
        'telegram_token'     => 'encrypted',
        'codigo_vinculo_ate' => 'datetime',
        'tipos_desligados'   => 'array',
        'ligado_em'          => 'datetime',
        'processado_em'      => 'datetime',
    ];

    /** Só as famílias liberadas em `services.notificacoes.tenants` usam os avisos. */
    public static function liberado(?int $tenantId): bool
    {
        return $tenantId !== null && in_array($tenantId, config('services.notificacoes.tenants', []), true);
    }

    public function ligado(string $tipo): bool
    {
        return ! in_array($tipo, $this->tipos_desligados ?? [], true);
    }

    /** Segredo que o Telegram devolve no webhook desta família; derivado da APP_KEY, nunca gravado. */
    public static function segredoWebhook(int $tenantId): string
    {
        return hash_hmac('sha256', "telegram:{$tenantId}", (string) config('app.key'));
    }

    /** Chave do relógio desta família; derivada da APP_KEY, nunca gravada. */
    public static function chaveRelogio(int $tenantId): string
    {
        return substr(hash_hmac('sha256', "relogio:{$tenantId}", (string) config('app.key')), 0, 40);
    }
}
