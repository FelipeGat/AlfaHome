{{-- Cartão de número: $rotulo, $valor (já formatado), $cor (opcional), $nota (opcional) --}}
<div class="card" style="padding:16px 18px;">
    <div style="font-size:11px;font-weight:600;color:var(--color-text-subtle);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">{{ $rotulo }}</div>
    <div style="font-size:22px;font-weight:700;color:{{ $cor ?? 'var(--color-text)' }};">{{ $valor }}</div>
    @isset($nota)
        <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">{{ $nota }}</div>
    @endisset
</div>
