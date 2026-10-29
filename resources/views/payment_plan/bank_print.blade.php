<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $payload['doc'] ?? $row->id }}</title>
    <style>
        body{font:14px Arial,sans-serif;color:#111;margin:24px}table{width:100%;border-collapse:collapse;margin:16px 0}th,td{border:1px solid #bbb;padding:8px;text-align:left;overflow-wrap:anywhere}pre{white-space:pre-wrap;overflow-wrap:anywhere}h1{font-size:22px}@media print{.no-print{display:none}body{margin:0}thead{display:table-header-group}tr{break-inside:avoid}}@page{size:A4;margin:16mm}
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">{{ __('bank_payment.print') }}</button>
    <h1>{{ __('bank_payment.title') }} — {{ $payload['doc'] ?? $row->id }}</h1>
    <p>{{ __('bank_payment.status') }}: {{ $row->status }} · {{ __('bank_payment.version') }}: {{ $row->version }}</p>
    <table>
        @foreach(['payee','amount','currency','due'] as $field)
            <tr><th>{{ __('bank_payment.'.$field) }}</th><td>{{ $payload[$field] ?? '—' }}</td></tr>
        @endforeach
        <tr><th>{{ __('bank_payment.bank') }}</th><td>{{ $payload['recipientBank'] ?? '—' }}</td></tr>
        <tr><th>{{ __('bank_payment.account') }}</th><td>{{ $payload['recipientAccount'] ?? '—' }}</td></tr>
        <tr><th>{{ __('bank_payment.ap_source') }}</th><td>{{ $payload['purchaseBillId'] ?? '—' }}</td></tr>
    </table>
    @foreach(['subject','purpose'] as $field)
        <h2>{{ __('bank_payment.'.$field) }}</h2>
        @foreach((array) ($payload[$field] ?? []) as $text)<p>{{ $text }}</p>@endforeach
    @endforeach
    <h2>{{ __('bank_payment.approved_basis') }}</h2>
    @php($basis = $payload['accountingApproval']['basis'] ?? [])
    <table>
        @foreach(['base','vat','withholding','fees'] as $field)
            <tr><th>{{ __('bank_payment.'.$field) }}</th><td>{{ $basis['settlement'][$field] ?? '—' }}</td></tr>
        @endforeach
        <tr><th>{{ __('bank_payment.source') }}</th><td>{{ $basis['source_account']['name'] ?? '—' }} · {{ $basis['source_account']['number'] ?? '—' }}</td></tr>
        <tr><th>{{ __('bank_payment.ap_source') }}</th><td>{{ $basis['source']['number'] ?? '—' }} · {{ $basis['source']['journal_id'] ?? '—' }}</td></tr>
        @foreach($basis['lines'] ?? [] as $line)
            <tr><th>{{ $line['account_code'] }} · {{ $line['position'] }}</th><td>{{ $line['amount'] }} {{ $basis['currency'] ?? '' }}</td></tr>
        @endforeach
    </table>
    <details class="no-print"><summary>{{ __('bank_payment.approved_basis') }}</summary><pre>{{ json_encode($payload['accountingApproval'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details>
    <h2>{{ __('bank_payment.journal') }}</h2>
    <pre>{{ json_encode($payload['journal'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    <h2>{{ __('bank_payment.attachment') }}</h2>
    <table>
        @foreach($payload['attachments'] ?? [] as $attachment)
            <tr><th>{{ $attachment['kind'] ?? '—' }}</th><td>{{ $attachment['name'] ?? $attachment['id'] ?? '—' }}</td></tr>
        @endforeach
    </table>
    <h2>{{ __('bank_payment.history') }}</h2>
    @foreach($events as $event)<pre>{{ json_encode($event, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>@endforeach
</body>
</html>