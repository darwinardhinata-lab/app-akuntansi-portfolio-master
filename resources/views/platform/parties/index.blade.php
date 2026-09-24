@extends('layouts.app')
@section('title', 'Master Party')
@section('content')
<div class="card"><div class="card-body">
    <h1 class="h4">Master Party — {{ $company->code }}</h1>
    @include('platform.messages')
    <div class="d-flex gap-2 mb-3">
        <a href="{{ route('platform.company.edit') }}" class="btn btn-outline-secondary">Ganti perusahaan</a>
        @can('create', \App\Modules\Platform\Models\Party::class)
            <a href="{{ route('platform.parties.create') }}" class="btn btn-primary">Tambah Party</a>
        @endcan
    </div>
    <form method="GET" class="d-flex gap-2 mb-3">
        <label for="q" class="visually-hidden">Cari kode atau nama</label>
        <input id="q" name="q" value="{{ $search }}" maxlength="100" class="form-control" placeholder="Cari kode atau nama">
        <button class="btn btn-outline-primary">Cari</button>
    </form>
    <div class="table-responsive"><table class="table table-striped">
        <thead><tr><th>Kode</th><th>Nama legal</th><th>Peran aktif</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        @forelse($parties as $party)
            <tr><td>{{ $party->code }}</td><td>{{ $party->legal_name }}</td>
                <td>{{ $party->roles->where('active', true)->pluck('role')->join(', ') }}</td>
                <td>{{ $party->active ? 'Aktif' : 'Nonaktif' }}</td>
                <td>@can('update', $party)<a href="{{ route('platform.parties.edit', $party->id) }}">Edit</a>@endcan</td></tr>
        @empty
            <tr><td colspan="5">Belum ada Party yang sesuai pencarian.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $parties->links() }}
</div></div>
@endsection
