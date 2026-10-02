@extends('layouts.app')

@section('content')
<div class="card shadow-sm">
    <div class="card-header"><h5>{{ __('erp.payment_correction_title') }} — {{ $payment->no_transaksi }}</h5></div>
    <div class="card-body">
        <div class="alert alert-warning">{{ __('erp.payment_correction_notice') }}</div>
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form method="POST" enctype="multipart/form-data" action="{{ route('payment.correction.store', $payment->id_payment) }}">
            @csrf
            <label for="correction-coa" class="form-label">{{ __('erp.coa') }}</label>
            <select id="correction-coa" name="id_akun" class="form-select mb-3">
                <option value="">{{ __('erp.payment_correction_keep_coa') }}</option>
                @foreach($accounts as $account)
                    <option value="{{ $account->account_code }}" @selected(old('id_akun') == $account->account_code)>{{ $account->account_code }} — {{ $account->account_name }}</option>
                @endforeach
            </select>
            <div class="mb-3">{{ __('erp.coa') }}: {{ $payment->id_akun ?? '-' }}</div>
            <label class="form-check mb-3">
                <input type="checkbox" class="form-check-input" onchange="document.getElementById('correction-proof').disabled = !this.checked">
                <span class="form-check-label">{{ __('erp.payment_correction_add_proof') }}</span>
            </label>
            <fieldset id="correction-proof" disabled class="border rounded p-3 mb-3">
                <label for="correction-detail" class="form-label">{{ __('erp.payment_correction_detail') }}</label>
                <select id="correction-detail" name="proofs[0][id_detail]" class="form-select mb-2" required>
                    @foreach($payment->details as $detail)
                        @if(!\App\Services\PaymentPlanCorrectionService::available($detail->bukti_file))
                            <option value="{{ $detail->id_detail }}">#{{ $detail->id_detail }} — {{ $detail->keterangan }}</option>
                        @endif
                    @endforeach
                </select>
                <input type="file" name="proofs[0][file]" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required aria-label="{{ __('erp.payment_correction_add_proof') }}">
            </fieldset>
            <label for="correction-reason" class="form-label">{{ __('erp.payment_correction_reason') }}</label>
            <textarea id="correction-reason" name="reason" class="form-control mb-3" minlength="10" maxlength="1000" required>{{ old('reason') }}</textarea>
            <button class="btn btn-warning" type="submit">{{ __('erp.save') }}</button>
            <a class="btn btn-outline-secondary" href="{{ route('payment.edit', $payment->id_payment) }}">{{ __('erp.back_btn') }}</a>
        </form>
    </div>
</div>
@endsection