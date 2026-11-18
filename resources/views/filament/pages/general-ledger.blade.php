<x-filament-panels::page>
    <p>{{ __('gl.scope') }} {{ $company->code }} / {{ $company->base_currency }}. {{ __('gl.precision') }}</p>
    <form wire:submit="apply" class="flex flex-wrap gap-3">
        <label>{{ __('gl.start') }} <input type="date" wire:model="start" class="border rounded text-gray-950" required></label>
        <label>{{ __('gl.end') }} <input type="date" wire:model="end" class="border rounded text-gray-950" required></label>
        <x-filament::button type="submit">{{ __('gl.apply') }}</x-filament::button>
    </form>
    @error('start')<p role="alert">{{ $message }}</p>@enderror
    @error('end')<p role="alert">{{ $message }}</p>@enderror
    <p data-gl-period>{{ $this->start }} — {{ $this->end }}</p>
    <div class="overflow-x-auto">
        <table class="w-full text-sm"><thead><tr>
            <th>{{ __('gl.account') }}</th><th>{{ __('gl.opening') }}</th><th>{{ __('gl.debit') }}</th><th>{{ __('gl.credit') }}</th><th>{{ __('gl.closing') }}</th>
        </tr></thead><tbody>
        @foreach($summary as $code => $row)
            <tr data-gl-account="{{ $code }}">
                <td><a class="underline" href="{{ $this->link(['account' => (string) $code, 'journal' => '', 'page' => 1]) }}">{{ $code }} — {{ $row['name'] }}</a></td>
                <td data-gl-opening>{{ $row['opening'] }}</td><td data-gl-debit>{{ $row['debit'] }}</td><td data-gl-credit>{{ $row['credit'] }}</td><td data-gl-closing>{{ $row['closing'] }}</td>
            </tr>
        @endforeach
        </tbody></table>
    </div>
    @if($transactions)
        <h2>{{ __('gl.transactions') }}: {{ $this->account }}</h2>
        <p data-gl-page-opening>{{ __('gl.page_opening') }}: {{ $pageOpening }}</p>
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr>
            <th>{{ __('gl.date') }}</th><th>{{ __('gl.journal') }}</th><th>{{ __('gl.reference') }}</th><th>{{ __('gl.debit') }}</th><th>{{ __('gl.credit') }}</th><th>{{ __('gl.running') }}</th>
        </tr></thead><tbody>
        @foreach($transactions as $line)
            <tr data-gl-line="{{ $line->id }}">
                <td>{{ $line->transaction_date }}</td>
                <td><a class="underline" href="{{ $this->link(['journal' => $line->journal_id]) }}">{{ $line->evidence_number }} / {{ $line->journal_id }}</a></td>
                <td>{{ $line->source_doc_no ?: '—' }}</td>
                <td>{{ $line->position === 'DEBET' ? \App\Modules\Currency\Support\Money::round((string) $line->amount) : '0.00' }}</td>
                <td>{{ $line->position === 'KREDIT' ? \App\Modules\Currency\Support\Money::round((string) $line->amount) : '0.00' }}</td>
                <td data-gl-running>{{ $line->running_balance }}</td>
            </tr>
        @endforeach
        </tbody></table></div>
        <nav class="flex gap-4" aria-label="{{ __('gl.transactions') }}">
            @if($transactions->currentPage() > 1)<a data-gl-prev class="underline" href="{{ $this->link(['page' => $this->page - 1, 'journal' => '']) }}">{{ __('gl.previous') }}</a>@endif
            <span>{{ $transactions->currentPage() }} / {{ $transactions->lastPage() }} ({{ $transactions->total() }})</span>
            @if($transactions->hasMorePages())<a data-gl-next class="underline" href="{{ $this->link(['page' => $this->page + 1, 'journal' => '']) }}">{{ __('gl.next') }}</a>@endif
        </nav>
    @endif
    @if($detail)
        <section data-gl-journal="{{ $detail->journal_id }}">
            <h2>{{ __('gl.journal') }}: {{ $detail->journal_id }}</h2>
            <p>{{ $detail->transaction_date }} / {{ $detail->evidence_number }} / {{ $detail->journal_type }}</p>
            <p>{{ __('gl.reference') }}: {{ $detail->source_doc_no ?: '—' }}</p>
            <p>{{ $detail->notes }}</p>
            <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr>
                <th>{{ __('gl.account') }}</th><th>{{ __('gl.description') }}</th><th>{{ __('gl.debit') }}</th><th>{{ __('gl.credit') }}</th><th>{{ __('gl.fx') }}</th>
            </tr></thead><tbody>
            @php $debit = '0.00'; $credit = '0.00'; @endphp
            @foreach($detail->details as $line)
                @php
                    if ($line->position === 'DEBET') $debit = \App\Modules\Currency\Support\Money::add($debit, (string) $line->amount);
                    else $credit = \App\Modules\Currency\Support\Money::add($credit, (string) $line->amount);
                @endphp
                <tr><td>{{ $line->account_code }} — {{ $line->account?->translatedName() ?? __('gl.missing_account') }}</td>
                    <td>{{ $line->description }} / {{ $line->helper_code }}</td>
                    <td>{{ $line->position === 'DEBET' ? $line->amount : '0.00' }}</td><td>{{ $line->position === 'KREDIT' ? $line->amount : '0.00' }}</td>
                    <td>{{ $line->currency_code ?? '—' }} / {{ $line->amount_txn ?? '—' }} / {{ $line->exchange_rate ?? '—' }}</td></tr>
            @endforeach
            </tbody><tfoot><tr><th>{{ __('gl.total') }}</th><td></td><td data-gl-journal-debit>{{ $debit }}</td><td data-gl-journal-credit>{{ $credit }}</td><td></td></tr></tfoot></table></div>
            @if($source)
                <a data-gl-source class="underline" href="{{ $source['url'] }}">{{ __('gl.source') }}: {{ $source['label'] }}</a>
            @else
                <p data-gl-unlinked>{{ __('gl.unlinked') }}</p>
            @endif
        </section>
    @endif
</x-filament-panels::page>