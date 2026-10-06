@extends('layouts.main')
@section('title', 'Planos')
@section('page-title', 'Planos')

@section('content')
<div class="section-header">
    <h2><i class="fa-solid fa-credit-card" style="color:var(--color-primary)"></i> Planos</h2>
    <button class="btn btn-primary" onclick="openModal('modal-novo')">
        <i class="fa-solid fa-plus"></i> Novo Plano
    </button>
</div>

<div class="card">
    @if($planos->isEmpty())
        <div class="empty-state">
            <i class="fa-solid fa-credit-card"></i>
            <p>Nenhum plano cadastrado ainda.</p>
        </div>
    @else
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th class="hide-mobile">Slug</th>
                    <th class="hide-mobile">Mensal</th>
                    <th class="hide-mobile">Anual</th>
                    <th>Usuários</th>
                    <th>Bancos</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach($planos as $plano)
                <tr>
                    <td class="fw-600">{{ $plano->nome }}</td>
                    <td class="text-muted hide-mobile">{{ $plano->slug }}</td>
                    <td class="hide-mobile">{{ brl($plano->preco_mensal) }}</td>
                    <td class="hide-mobile">{{ brl($plano->preco_anual) }}</td>
                    <td>{{ $plano->max_usuarios == -1 ? 'Ilimitado' : $plano->max_usuarios }}</td>
                    <td>{{ $plano->max_bancos == -1 ? 'Ilimitado' : $plano->max_bancos }}</td>
                    <td>
                        @if($plano->ativo)
                            <span class="badge badge-green">Ativo</span>
                        @else
                            <span class="badge badge-red">Inativo</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            <button class="btn btn-secondary btn-sm btn-icon"
                                onclick="editarPlano({{ $plano->id }}, '{{ addslashes($plano->nome) }}', '{{ $plano->slug }}', '{{ addslashes($plano->descricao ?? '') }}', '{{ $plano->preco_mensal }}', '{{ $plano->preco_anual }}', '{{ $plano->max_usuarios }}', '{{ $plano->max_bancos }}', {{ $plano->ativo ? 'true' : 'false' }})"
                                title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form method="POST" action="{{ route('admin.planos.destroy', $plano) }}" onsubmit="return confirm('Remover este plano?')">
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
    @endif
</div>

{{-- Modal Novo Plano --}}
<div class="modal-backdrop" id="modal-novo">
    <div class="modal" style="max-width:550px">
        <div class="modal-header">
            <i class="fa-solid fa-plus" style="color:var(--color-primary)"></i>
            <h3>Novo Plano</h3>
            <button class="modal-close" onclick="closeModal('modal-novo')">×</button>
        </div>
        <form method="POST" action="{{ route('admin.planos.store') }}">
            @csrf
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Slug *</label>
                        <input type="text" name="slug" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preço Mensal *</label>
                        <input type="number" name="preco_mensal" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preço Anual *</label>
                        <input type="number" name="preco_anual" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Usuários (-1 = ilimitado) *</label>
                        <input type="number" name="max_usuarios" class="form-control" value="-1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Bancos (-1 = ilimitado) *</label>
                        <input type="number" name="max_bancos" class="form-control" value="-1" required>
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-novo')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Criar</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Editar Plano --}}
<div class="modal-backdrop" id="modal-editar">
    <div class="modal" style="max-width:550px">
        <div class="modal-header">
            <i class="fa-solid fa-pen" style="color:var(--color-primary)"></i>
            <h3>Editar Plano</h3>
            <button class="modal-close" onclick="closeModal('modal-editar')">×</button>
        </div>
        <form method="POST" id="form-editar-plano" action="">
            @csrf @method('PUT')
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" id="edit-plano-nome" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Slug *</label>
                        <input type="text" name="slug" id="edit-plano-slug" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preço Mensal *</label>
                        <input type="number" name="preco_mensal" id="edit-plano-preco-mensal" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preço Anual *</label>
                        <input type="number" name="preco_anual" id="edit-plano-preco-anual" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Usuários *</label>
                        <input type="number" name="max_usuarios" id="edit-plano-max-usuarios" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Bancos *</label>
                        <input type="number" name="max_bancos" id="edit-plano-max-bancos" class="form-control" required>
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" id="edit-plano-descricao" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" name="ativo" id="edit-plano-ativo" value="1"> Ativo
                        </label>
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
function editarPlano(id, nome, slug, descricao, precoMensal, precoAnual, maxUsuarios, maxBancos, ativo) {
    document.getElementById('edit-plano-nome').value = nome;
    document.getElementById('edit-plano-slug').value = slug;
    document.getElementById('edit-plano-descricao').value = descricao;
    document.getElementById('edit-plano-preco-mensal').value = precoMensal;
    document.getElementById('edit-plano-preco-anual').value = precoAnual;
    document.getElementById('edit-plano-max-usuarios').value = maxUsuarios;
    document.getElementById('edit-plano-max-bancos').value = maxBancos;
    document.getElementById('edit-plano-ativo').checked = ativo;
    document.getElementById('form-editar-plano').action = '/admin/planos/' + id;
    openModal('modal-editar');
}
</script>
@endpush
