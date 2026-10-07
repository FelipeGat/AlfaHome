#!/bin/sh
# Ao subir o container: guarda configuração, rotas, eventos e telas em cache.
# Se qualquer passo falhar, limpa os caches e sobe assim mesmo (o site nunca
# fica fora do ar por causa disso).
cd /var/www
if ! (php artisan config:cache && php artisan route:cache && php artisan event:cache && php artisan view:cache) >/tmp/otimizar.log 2>&1; then
    echo "Aviso: cache do Laravel falhou; seguindo sem cache." >&2
    cat /tmp/otimizar.log >&2
    php artisan optimize:clear >/dev/null 2>&1 || true
fi
# Caches gerados como root: devolve ao usuário do PHP-FPM.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
exec "$@"
