<x-filament-panels::page>
    <p>AP reconciliation — IDR journal attribution. PO-only returns remain blocked until quantity/value allocation is supported. Evidence must reference a verifiable internal document, not a credential or public download URL.</p>
    @foreach($queue as $invoice)
        <details class="border p-3"><summary>#{{ $invoice['id'] }} {{ $invoice['number'] }} — {{ $invoice['blocked'] ?? $invoice['residual'] }}</summary>
            <pre class="whitespace-pre-wrap">{{ json_encode($invoice, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    @endforeach
    <form wire:submit="propose" class="space-y-3">
        @foreach(['purchase_bill_id','journal_id','amount','reason','evidence'] as $field)
            <label class="block">{{ $field }}<input class="block border rounded p-2 text-gray-950" wire:model="proposal.{{ $field }}" required></label>
            @error('proposal.'.$field)<p>{{ $message }}</p>@enderror
            @error($field)<p>{{ $message }}</p>@enderror
        @endforeach
        <x-filament::button type="submit">Propose allocation</x-filament::button>
    </form>
    @error('reconciliation')<p role="alert">{{ $message }}</p>@enderror
    <label>Decision reason<input class="block border rounded p-2 text-gray-950" wire:model="decisionReason"></label>
    @foreach($resolutions as $resolution)
        <div class="border p-3">#{{ $resolution->id }} Invoice {{ $resolution->purchase_bill_id }} / {{ $resolution->journal_id }} / {{ $resolution->amount }} / {{ $resolution->status }} / v{{ $resolution->version }}
            <p>{{ $resolution->reason }} — {{ $resolution->evidence }}</p>
            @if($resolution->status === 'proposed')<x-filament::button wire:click="decide({{ $resolution->id }}, {{ $resolution->version }}, 'approve')">Approve</x-filament::button>@endif
            @if($resolution->status === 'approved')<x-filament::button wire:click="decide({{ $resolution->id }}, {{ $resolution->version }}, 'cancel')">Cancel linked allocation</x-filament::button>@endif
        </div>
    @endforeach
</x-filament-panels::page>