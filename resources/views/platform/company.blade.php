@extends('layouts.app')
@section('title', 'Perusahaan Aktif')
@section('content')
<div class="card"><div class="card-body">
    <h1 class="h4">Perusahaan Aktif</h1>
    <p>Pilih perusahaan untuk master Party dan pilihan Party pada formulir PO/SO.</p>
    @include('platform.messages')
    @if($companies->isEmpty())
        <div class="alert alert-warning">Belum ada perusahaan aktif yang dapat diakses. Hubungi administrator.</div>
    @else
        <form method="POST" action="{{ route('platform.company.update') }}">
            @csrf
            <label for="company_id" class="form-label">Perusahaan</label>
            <select id="company_id" name="company_id" class="form-select mb-3" required>
                <option value="">Pilih perusahaan</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" @selected((string) old('company_id', $selected?->id) === (string) $company->id)>{{ $company->code }} — {{ $company->name }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary">Gunakan perusahaan</button>
        </form>
    @endif
    @if($selected)
        <p class="mt-3">Aktif: <strong>{{ $selected->code }} — {{ $selected->name }}</strong></p>
        @can('viewAny', \App\Modules\Platform\Models\Party::class)
            <a href="{{ route('platform.parties.index') }}" class="btn btn-outline-primary">Daftar Party</a>
        @endcan
        @can('create', \App\Modules\Platform\Models\Party::class)
            <a href="{{ route('platform.parties.create') }}" class="btn btn-outline-primary">Tambah Party</a>
        @endcan
    @endif
</div></div>
@endsection
