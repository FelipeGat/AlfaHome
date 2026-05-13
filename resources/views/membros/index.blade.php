@extends('layouts.main')
@section('title', 'Membros')
@section('page-title', 'Membros da Família')

@section('content')
<div class="section-header">
    <h2><i class="fa-solid fa-users" style="color:var(--color-primary)"></i> Membros da Família</h2>
    <button class="btn btn-primary" onclick="openModal('modal-novo')">
        <i class="fa-solid fa-user-plus"></i> Novo Membro
    </button>
</div>

@if($membros->isEmpty())
    <div class="card">
        <div class="empty-state">
            <i class="fa-solid fa-users"></i>
            <p>Nenhum membro cadastrado ainda.<br>Adicione membros da sua família para compartilhar o acesso.</p>
        </div>
    </div>
@else
<div class="card">
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th class="hide-mobile">E-mail</th>
                    <th>Status</th>
                    <th class="hide-mobile">Permissões</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach($membros as $membro)
                <tr>
                    <td class="fw-600">{{ $membro->name }}</td>
                    <td class="text-muted hide-mobile">{{ $membro->email }}</td>
                    <td>
                        @if($membro->ativo)
                            <span class="badge badge-green"><i class="fa-solid fa-circle" style="font-size:7px"></i> Ativo</span>
                        @else
                            <span class="badge badge-red"><i class="fa-solid fa-circle" style="font-size:7px"></i> Inativo</span>
                        @endif
                    </td>
                    <td class="hide-mobile">
                        @php $perms = $membro->permissoes ?? []; @endphp
                        <span class="text-subtle" style="font-size:12px">
                            {{ collect($perms)->filter(fn($p) => collect($p)->contains(true))->keys()->map(fn($k) => ucfirst($k))->implode(', ') ?: 'Sem permissões' }}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            <button class="btn btn-secondary btn-sm btn-icon"
                                onclick="editarMembro({{ $membro->id }}, '{{ addslashes($membro->name) }}', '{{ $membro->email }}', {{ $membro->ativo ? 'true' : 'false' }}, {{ json_encode($membro->permissoes ?? new stdClass) }})"
                                title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form method="POST" action="{{ route('membros.destroy', $membro) }}"
                                onsubmit="return confirm('Remover este membro?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Remover">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ─── Modal Novo Membro ──────────────────────────────────────────────────── --}}
<div class="modal-backdrop" id="modal-novo">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <i class="fa-solid fa-user-plus" style="color:var(--color-primary)"></i>
            <h3>Novo Membro</h3>
            <button class="modal-close" onclick="closeModal('modal-novo')">×</button>
        </div>
        <form method="POST" action="{{ route('membros.store') }}">
            @csrf
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">E-mail *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Senha *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="form-label mb-2" style="font-size:12px;color:var(--color-text-muted)">PERMISSÕES</div>
                    @include('membros._permissoes_grid', ['prefix' => '', 'permissoes' => []])
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-novo')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Criar Membro</button>
            </div>
        </form>
    </div>
</div>

{{-- ─── Modal Editar Membro ─────────────────────────────────────────────────── --}}
<div class="modal-backdrop" id="modal-editar">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <i class="fa-solid fa-pen" style="color:var(--color-primary)"></i>
            <h3>Editar Membro</h3>
            <button class="modal-close" onclick="closeModal('modal-editar')">×</button>
        </div>
        <form method="POST" id="form-editar" action="">
            @csrf @method('PUT')
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="name" id="edit-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">E-mail *</label>
                        <input type="email" name="email" id="edit-email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nova senha <span class="text-subtle">(deixe em branco para manter)</span></label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <div class="form-group" style="align-self:flex-end">
                        <label class="form-check">
                            <input type="checkbox" name="ativo" id="edit-ativo" value="1"> Conta ativa
                        </label>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="form-label mb-2" style="font-size:12px;color:var(--color-text-muted)">PERMISSÕES</div>
                    <div id="edit-permissoes-grid">
                        @include('membros._permissoes_grid', ['prefix' => 'edit_', 'permissoes' => []])
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-editar')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function editarMembro(id, name, email, ativo, permissoes) {
    document.getElementById('edit-name').value  = name;
    document.getElementById('edit-email').value = email;
    document.getElementById('edit-ativo').checked = ativo;
    document.getElementById('form-editar').action = '/membros/' + id;

    const modulos = ['despesas','receitas','transferencias','investimentos','bancos','categorias','fornecedores','familiares'];
    const acoes   = ['ver','criar','editar','excluir'];
    modulos.forEach(m => {
        acoes.forEach(a => {
            const cb = document.querySelector(`[name="perm_${m}_${a}"]`);
            if (cb) cb.checked = permissoes && permissoes[m] && permissoes[m][a] ? true : false;
        });
    });

    openModal('modal-editar');
}
</script>
@endpush
