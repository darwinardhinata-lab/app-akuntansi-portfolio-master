<x-filament-panels::page>
    <p>{{ __('ap_reconciliation.instructions') }}</p>
    <label class="block">{{ __('ap_reconciliation.search_invoice') }}<input type="search" class="block border rounded p-2 text-gray-950 w-full" wire:model.live.debounce.400ms="invoiceSearch" maxlength="100"></label>
    <label class="block">{{ __('ap_reconciliation.purchase_bill_id') }}
        <select class="block border rounded p-2 text-gray-950 w-full" wire:model.live="proposal.purchase_bill_id">
            <option value="">{{ __('ap_reconciliation.choose') }}</option>
            @foreach($queue as $invoice)
                <option value="{{ $invoice['id'] }}">{{ $invoice['number'] }} / {{ $invoice['payee'] }} / {{ $invoice['date'] }} / {{ $invoice['status'] }} / {{ $invoice['residual'] ?? '—' }} / {{ $invoice['blocked'] ?? '' }}</option>
            @endforeach
        </select>
    </label>
    {{ $invoices->links() }}
    @foreach($queue as $invoice)
        <details class="border p-3"><summary>#{{ $invoice['id'] }} {{ $invoice['number'] }} — {{ $invoice['blocked'] ?? $invoice['residual'] }}</summary>
            <pre class="whitespace-pre-wrap">{{ json_encode($invoice, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    @endforeach
    <form wire:submit="propose" class="space-y-3">
        <label class="block">{{ __('ap_reconciliation.search_return') }}<input type="search" class="block border rounded p-2 text-gray-950 w-full" wire:model.live.debounce.400ms="returnSearch" maxlength="100"></label>
        <label class="block">{{ __('ap_reconciliation.purchase_return_detail_id') }}
            <select class="block border rounded p-2 text-gray-950 w-full" wire:model.live="proposal.purchase_return_detail_id">
                <option value="">{{ __('ap_reconciliation.no_return') }}</option>
                @foreach($returns as $detail)
                    <option value="{{ $detail->id }}" @disabled($detail->status !== 'COMPLETED' || bccomp($detail->remaining, '0', 6) <= 0)>{{ $detail->return_number }} / {{ $detail->item_code }} / {{ $detail->return_date }} / {{ $detail->status }} / {{ __('ap_reconciliation.remaining') }} {{ $detail->remaining }} / IDR {{ bcmul($detail->remaining, (string) $detail->price, 2) }}</option>
                @endforeach
            </select>
        </label>
        {{ $returns->links() }}
        <label class="block">{{ __('ap_reconciliation.search_journal') }}<input type="search" class="block border rounded p-2 text-gray-950 w-full" wire:model.live.debounce.400ms="journalSearch" maxlength="100"></label>
        <label class="block">{{ __('ap_reconciliation.journal_id') }}
            <select class="block border rounded p-2 text-gray-950 w-full" wire:model="proposal.journal_id" required>
                <option value="">{{ __('ap_reconciliation.choose') }}</option>
                @foreach($journals as $journal)
                    <option value="{{ $journal->journal_id }}" @disabled(isset($journal->preview['blocked']) || bccomp($journal->preview['remaining'] ?? '0', '0', 2) <= 0)>{{ $journal->source_doc_no }} / {{ $journal->journal_id }} / {{ $journal->transaction_date }} / {{ $journal->preview['blocked'] ?? __('ap_reconciliation.remaining').' IDR '.$journal->preview['remaining'] }}</option>
                @endforeach
            </select>
        </label>
        {{ $journals->links() }}
        @foreach(['purchase_bill_id','journal_id','purchase_return_detail_id'] as $field)
            @error($field)<p role="alert">{{ $message }}</p>@enderror
        @endforeach
        @foreach(['amount','reason','evidence','quantity'] as $field)
            <label class="block">{{ __('ap_reconciliation.'.$field) }}<input class="block border rounded p-2 text-gray-950" wire:model="proposal.{{ $field }}" @required(!in_array($field, ['purchase_return_detail_id','quantity']))></label>
            @error('proposal.'.$field)<p>{{ $message }}</p>@enderror
            @error($field)<p>{{ $message }}</p>@enderror
        @endforeach
        <x-filament::button type="submit" wire:loading.attr="disabled">{{ __('ap_reconciliation.propose') }}</x-filament::button>
    </form>
    @error('reconciliation')<p role="alert">{{ $message }}</p>@enderror
    <label>{{ __('ap_reconciliation.decision_reason') }}<input class="block border rounded p-2 text-gray-950" wire:model="decisionReason"></label>
    @foreach($resolutions as $resolution)
        <div class="border p-3">#{{ $resolution->id }} Invoice {{ $resolution->purchase_bill_id }} / {{ $resolution->journal_id }} / {{ $resolution->amount }} / {{ $resolution->status }} / v{{ $resolution->version }}
            <p>{{ $resolution->reason }} — {{ $resolution->evidence }}</p>
            <p>{{ __('ap_reconciliation.purchase_return_detail_id') }}: {{ $resolution->purchase_return_detail_id ?? '—' }} / {{ __('ap_reconciliation.quantity') }}: {{ $resolution->quantity ?? '—' }}</p>
            @if($resolution->status === 'proposed')<x-filament::button wire:click="decide({{ $resolution->id }}, {{ $resolution->version }}, 'approve')">{{ __('ap_reconciliation.approve') }}</x-filament::button>@endif
            @if($resolution->status === 'approved')<x-filament::button wire:click="decide({{ $resolution->id }}, {{ $resolution->version }}, 'cancel')">{{ __('ap_reconciliation.cancel') }}</x-filament::button>@endif
        </div>
    @endforeach
    {{ $resolutions->links() }}
</x-filament-panels::page>