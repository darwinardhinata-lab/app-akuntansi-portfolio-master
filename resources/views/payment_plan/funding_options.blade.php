@php($fundingAccounts = \App\Support\PaymentFundingAccount::options())
@foreach($fundingAccounts as $fundingAccount)
    <option value="{{ $fundingAccount->account_code }}" @selected((string) ($selectedFunding ?? '') === (string) $fundingAccount->account_code)>{{ $fundingAccount->account_code }} — {{ $fundingAccount->account_name }}</option>
@endforeach