@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Akuntansi' => '#', 'Laporan AR DP' => null]" />
@endsection

@section('content')
<div class="container-fluid px-4">
    <h4 class="fw-bold mb-4">Laporan Piutang & Uang Muka Penjualan</h4>
    <div class="row g-4">
        {{-- Piutang --}}
        <div class="col-md-6">
            <div class="card shadow-sm border-0 border-top border-primary border-4">
                <div class="card-header bg-white"><h6 class="fw-bold m-0 text-primary">Rekapitulasi Piutang (AR)</h6></div>
                <table class="table mb-0">
                    <thead class="table-light"><tr><th>Kode Akun</th><th>Nama Akun</th><th class="text-end">Saldo Akhir</th></tr></thead>
                    <tbody>
                        @foreach($piutang as $p)
                        <tr>
                            <td>{{ $p->account_code }}</td>
                            <td>
                                <a href="{{ route('reports.ar_subledger', ['account_code' => $p->account_code]) }}" class="fw-bold text-decoration-none text-primary">
                                    {{ $p->account_name }} <i class="fa-solid fa-arrow-up-right-from-square small ms-1" style="font-size: 0.7rem;"></i>
                                </a>
                            </td>
                            <td class="text-end fw-bold">Rp {{ number_format($p->balance,0,',','.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        {{-- Uang Muka Penjualan --}}
        <div class="col-md-6">
            <div class="card shadow-sm border-0 border-top border-warning border-4">
                <div class="card-header bg-white"><h6 class="fw-bold m-0 text-warning">Rekapitulasi Uang Muka Pelanggan</h6></div>
                <table class="table mb-0">
                    <thead class="table-light"><tr><th>Kode Akun</th><th>Nama Akun</th><th class="text-end">Saldo Akhir</th></tr></thead>
                    <tbody>
                        @foreach($uangMuka as $um)
                        <tr>
                            <td>{{ $um->account_code }}</td>
                            <td>
                                <a href="{{ route('reports.ar_subledger', ['account_code' => $um->account_code]) }}" class="fw-bold text-decoration-none text-warning">
                                    {{ $um->account_name }} <i class="fa-solid fa-arrow-up-right-from-square small ms-1" style="font-size: 0.7rem;"></i>
                                </a>
                            </td>
                            <td class="text-end fw-bold">Rp {{ number_format($um->balance,0,',','.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection