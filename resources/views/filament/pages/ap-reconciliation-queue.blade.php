<x-filament-panels::page>
    <p>{{ __('ap_reconciliation.instructions') }}</p>
    @foreach($queue as $invoice)
        <details class="border p-3"><summary>#{{ $invoice['id'] }} {{ $invoice['number'] }} — {{ $invoice['blocked'] ?? $invoice['residual'] }}</summary>
            <pre class="whitespace-pre-wrap">{{ json_encode($invoice, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    @endforeach
    <form wire:submit="propose" class="space-y-3">
        @foreach(['purchase_bill_id','journal_id','amount','reason','evidence','purchase_return_detail_id','quantity'] as $field)
            <label class="block">{{ __('ap_reconciliation.'.$field) }}<input class="block border rounded p-2 text-gray-950" wire:model="proposal.{{ $field }}" @required(!in_array($field, ['purchase_return_detail_id','quantity']))></label>
            @error('proposal.'.$field)<p>{{ $message }}</p>@enderror
            @error($field)<p>{{ $message }}</p>@enderror
        @endforeach
        <x-filament::button type="submit">{{ __('ap_reconciliation.propose') }}</x-filament::button>
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
</x-filament-panels::page>