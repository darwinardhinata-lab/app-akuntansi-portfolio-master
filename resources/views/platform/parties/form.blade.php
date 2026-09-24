@extends('layouts.app')
@section('title', $party->exists ? 'Edit Party' : 'Tambah Party')
@section('content')
<div class="card"><div class="card-body">
    <h1 class="h4">{{ $party->exists ? 'Edit Party' : 'Tambah Party' }} — {{ $company->code }}</h1>
    @include('platform.messages')
    <form method="POST" action="{{ $party->exists ? route('platform.parties.update', $party->id) : route('platform.parties.store') }}">
        @csrf
        @if($party->exists) @method('PUT') @endif
        <input type="hidden" name="context_company_id" value="{{ $company->id }}">
        @foreach(['code' => ['Kode', 64], 'legal_name' => ['Nama legal', 255], 'tax_no' => ['NPWP / nomor pajak', 50], 'phone' => ['Telepon', 50], 'email' => ['Email', 150]] as $field => $meta)
            <div class="mb-3">
                <label for="{{ $field }}" class="form-label">{{ $meta[0] }}</label>
                <input id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" class="form-control" maxlength="{{ $meta[1] }}" value="{{ old($field, $party->$field) }}" @required(in_array($field, ['code', 'legal_name']))>
            </div>
        @endforeach
        <div class="mb-3"><label for="address" class="form-label">Alamat</label><textarea id="address" name="address" class="form-control" maxlength="5000">{{ old('address', $party->address) }}</textarea></div>
        <div class="mb-3"><label for="active" class="form-label">Status</label><select id="active" name="active" class="form-select">
            <option value="1" @selected((string) old('active', (int) $party->active) === '1')>Aktif</option>
            <option value="0" @selected((string) old('active', (int) $party->active) === '0')>Nonaktif</option>
        </select></div>
        <fieldset class="mb-3"><legend class="fs-6">Peran aktif</legend>
            @foreach($roles as $role)
                <label class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role }}" @checked(in_array($role, is_array(old('roles', $selectedRoles)) ? old('roles', $selectedRoles) : []))><span class="form-check-label">{{ $role }}</span></label>
            @endforeach
        </fieldset>
        <button class="btn btn-primary">Simpan</button>
        <a href="{{ route('platform.company.edit') }}" class="btn btn-outline-secondary">Kembali</a>
    </form>
</div></div>
@endsection
