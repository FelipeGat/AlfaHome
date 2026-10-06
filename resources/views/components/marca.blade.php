{{-- Marca alfahome: símbolo (A de duas linhas que convergem + travessa) e o nome.
     As cores vêm de --marca-1/2/3 e --marca-texto, que o tema escuro troca. --}}
@props(['tamanho' => 32, 'nome' => true, 'assinatura' => false])
<span {{ $attributes->merge(['class' => 'marca']) }} style="display:inline-flex;align-items:center;gap:{{ round($tamanho * .3) }}px;">
    <svg viewBox="0 0 64 64" width="{{ $tamanho }}" height="{{ $tamanho }}" aria-hidden="true" focusable="false" style="flex-shrink:0;">
        <path d="M11 54 L29.6 12.4 Q32 7.2 34.4 12.4" fill="none" stroke="var(--marca-1, #0B1D38)" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M34.4 12.4 L53 54" fill="none" stroke="var(--marca-2, #2563EB)" stroke-width="9" stroke-linecap="round"/>
        <path d="M21 39 H43" fill="none" stroke="var(--marca-3, #14B8A6)" stroke-width="7" stroke-linecap="round"/>
    </svg>
    @if($nome)
        <span style="display:flex;flex-direction:column;line-height:1;">
            <span class="marca-nome" style="font-family:'Plus Jakarta Sans',system-ui,sans-serif;font-weight:700;font-size:{{ round($tamanho * .72) }}px;letter-spacing:-.02em;color:var(--marca-texto, #0B1D38);">alfahome</span>
            @if($assinatura)
                <span style="font-size:{{ max(11, round($tamanho * .27)) }}px;color:var(--color-text-muted, #64748B);margin-top:4px;letter-spacing:.01em;">suas finanças, mais simples.</span>
            @endif
        </span>
    @endif
    <span class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);">alfahome</span>
</span>
