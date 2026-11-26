<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('idr3.title') }}</title>
    <style>
        body{font:1rem/1.5 system-ui,sans-serif;color:#111827;background:#f8fafc;margin:0}
        main{max-width:74rem;margin:auto;padding:1rem}h1,h2{line-height:1.2}h2{font-size:1.2rem}
        a{color:#1d4ed8}a:focus-visible,input:focus-visible,button:focus-visible,[tabindex]:focus-visible{outline:2px solid #2563eb;outline-offset:3px}
        section{background:white;border:1px solid #cbd5e1;border-radius:.75rem;padding:1rem;margin:1rem 0}
        dl,form,nav{display:flex;gap:.75rem 1.5rem;flex-wrap:wrap;align-items:end}dd{margin:0;font-weight:600}dt{font-size:.875rem}
        label{display:block}input,button{font:inherit;color:inherit;background:white;border:1px solid #64748b;border-radius:.4rem;padding:.4rem .65rem;max-width:100%;box-sizing:border-box}button{cursor:pointer}
        .table-scroll{overflow:auto}table{border-collapse:collapse;width:100%;min-width:40rem}th,td{padding:.6rem;text-align:start;border-bottom:1px solid #cbd5e1;vertical-align:top}thead{background:#f1f5f9}
        .amount{text-align:end;white-space:nowrap;font-variant-numeric:tabular-nums}.notes{max-width:25rem;overflow-wrap:anywhere}.journal{white-space:nowrap}
        .mobile-label{display:none}
        @media(max-width:640px){
            main,section{padding:.75rem}h1{font-size:1.45rem}
            .table-scroll{overflow:visible}table{min-width:0}table,tbody{display:block}
            thead{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}
            tbody tr{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:.55rem .75rem;border:1px solid #cbd5e1;border-radius:.5rem;padding:.7rem;margin-bottom:.6rem}
            td{padding:0;border:0;min-width:0}.journal,.notes{grid-column:1/-1;white-space:normal;overflow-wrap:anywhere}
            td.amount{text-align:start;white-space:normal;overflow-wrap:anywhere}td.amount:last-child{grid-column:1/-1;font-weight:600}
            .mobile-label{display:block;font-size:.8rem;font-weight:600}
        }
    </style>
</head>
<body>
<main>
    <a href="{{ \App\Filament\Pages\BankPayments::getUrl(panel: 'erp') }}#bankFunds">{{ __('bank_payment.funds_position') }}</a>
    <h1>{{ __('idr3.title') }}</h1>
    <section aria-labelledby="current-funds">
        <h2 id="current-funds">{{ __('idr3.current', ['date' => $today]) }}</h2>
        <p>{{ __('idr3.explanation') }}</p>
        <dl>
            @foreach (['ledger', 'unposted_actual_debits', 'reserved', 'available'] as $field)
                <div><dt>{{ __('idr3.'.$field) }}</dt><dd id="current-{{ $field }}">{{ $projection[$field] }}</dd></div>
            @endforeach
        </dl>
    </section>
    <section aria-labelledby="period-funds">
        <h2 id="period-funds">{{ __('idr3.period') }}</h2>
        <form action="{{ route('payment-plan-v33.idr3-movements') }}" method="get">
            <div><label for="start">{{ __('idr3.start') }}</label><input id="start" name="start" type="date" value="{{ $start }}" min="{{ $projection['cutoff'] }}" max="{{ $today }}" required></div>
            <div><label for="end">{{ __('idr3.end') }}</label><input id="end" name="end" type="date" value="{{ $end }}" min="{{ $projection['cutoff'] }}" max="{{ $today }}" required></div>
            <button type="submit">{{ __('idr3.apply') }}</button>
        </form>
        <p>{{ __('idr3.cutoff', ['date' => $projection['cutoff']]) }}</p>
        <dl>
            <div><dt>{{ __('idr3.opening') }}</dt><dd id="period-opening">{{ $opening }}</dd></div>
            <div><dt>{{ __('idr3.net') }}</dt><dd id="period-net">{{ bcsub($running, $opening, 2) }}</dd></div>
            <div><dt>{{ __('idr3.closing') }}</dt><dd id="period-closing">{{ $running }}</dd></div>
        </dl>
        @if ($rows)
            <dl>
                <div><dt>{{ __('idr3.page_opening') }}</dt><dd id="page-opening">{{ $pageOpening }}</dd></div>
                <div><dt>{{ __('idr3.page_closing') }}</dt><dd id="page-closing">{{ $pageClosing }}</dd></div>
            </dl>
            <div class="table-scroll" role="region" aria-label="{{ __('idr3.title') }}" tabindex="0">
                <table role="table">
                    <thead role="rowgroup"><tr role="row">
                        @foreach (['date', 'journal', 'description', 'debit', 'credit', 'balance'] as $column)
                            <th role="columnheader" scope="col" @class(['amount' => in_array($column, ['debit', 'credit', 'balance'], true)])>{{ __('idr3.'.$column) }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody role="rowgroup">
                        @foreach ($rows as $row)
                            <tr role="row" data-journal="{{ $row['journal'] }}">
                                <td role="cell"><time datetime="{{ $row['date'] }}">{{ $row['date'] }}</time></td>
                                <td role="cell" class="journal">
                                    @if ($row['journal_url'])<a href="{{ $row['journal_url'] }}">{{ $row['journal'] }}</a>
                                    @else{{ $row['journal'] }}@endif
                                    @if ($row['request_url'])<br><a href="{{ $row['request_url'] }}">{{ __('idr3.request') }}</a>@endif
                                </td>
                                <td role="cell" class="notes">{{ $row['description'] ?: '—' }}</td>
                                @foreach (['debit', 'credit', 'balance'] as $column)
                                    <td role="cell" class="amount"><span class="mobile-label" aria-hidden="true">{{ __('idr3.'.$column) }}</span><span>{{ $row[$column] }}</span></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p id="movement-empty">{{ __('idr3.empty') }}</p>
        @endif
        @if ($last > 1)
            <nav aria-label="{{ __('idr3.title') }}">
                @if ($page > 1)<a rel="prev" href="{{ route('payment-plan-v33.idr3-movements', ['start' => $start, 'end' => $end, 'page' => $page - 1]) }}">{{ __('idr3.previous') }}</a>@endif
                <span>{{ __('idr3.page', ['page' => $page, 'last' => $last]) }}</span>
                @if ($page < $last)<a rel="next" href="{{ route('payment-plan-v33.idr3-movements', ['start' => $start, 'end' => $end, 'page' => $page + 1]) }}">{{ __('idr3.next') }}</a>@endif
            </nav>
        @endif
    </section>
</main>
</body>
</html>