{{-- v33 referenceRequestSheet / referenceSettlementSheet: manual spaces, never signature images. --}}
@php
    $settlement = $payload['settlement'] ?? [];
    $created = collect($payload['history'] ?? [])->firstWhere('event', 'create_request');
    $groups = [
        'request_signatures' => [
            ['applicant', ['actor' => $payload['createdName'] ?? null, 'actorId' => $payload['createdBy'] ?? null, 'at' => $payload['createdAt'] ?? $created['at'] ?? null], 'recorded'],
            ['department_head', $payload['managerAcknowledgement'] ?? null, 'recorded'],
            ['request_director', $payload['requestApproval'] ?? null, 'approved'],
        ],
        'settlement_signatures' => [
            ['reimbursement_department', $payload['managerAcknowledgement'] ?? null, 'recorded'],
            ['finance_preparer', !empty($settlement['preparedAt']) ? ['actor' => $settlement['preparedName'] ?? null, 'actorId' => $settlement['preparedBy'] ?? null, 'at' => $settlement['preparedAt']] : null, 'recorded'],
            ['general_manager', $payload['bankRelease'] ?? null, 'recorded'],
        ],
    ];
@endphp
@foreach($groups as $group => $slots)
    <section class="signature-group" data-signature-group="{{ $group }}" aria-label="{{ __('bank_payment.'.$group) }}">
        <h2>{{ __('bank_payment.'.$group) }}</h2>
        <p class="signature-note">{{ __('bank_payment.manual_signature_note') }}</p>
        <table class="signature-grid"><tbody><tr>
        @foreach($slots as [$label, $decision, $recordStatus])
            <td data-signature-slot="{{ $label }}" data-workflow-recorded="{{ $decision ? 'true' : 'false' }}">
                <strong>{{ __('bank_payment.signature_roles.'.$label) }}</strong>
                <div class="manual-space" aria-label="{{ __('bank_payment.manual_signature') }}"></div>
                <small>{{ __('bank_payment.manual_signature') }}</small>
                <small>{{ __('bank_payment.manual_date') }}: __________</small>
                <strong>{{ $decision['actor'] ?? '—' }}</strong>
                <small>{{ __('bank_payment.workflow_actor_id') }}: {{ $decision['actorId'] ?? '—' }}</small>
                <small>{{ __('bank_payment.workflow_record') }}: {{ __('bank_payment.signature_states.'.($decision ? $recordStatus : 'missing')) }}</small>
                <small>{{ __('bank_payment.workflow_date') }}: {{ $decision['at'] ?? '—' }}</small>
            </td>
        @endforeach
        </tr></tbody></table>
    </section>
@endforeach
<section class="signature-group" data-workflow-approval="settlement">
    <h2>{{ __('bank_payment.settlement_director_record') }}</h2>
    <p>{{ __('bank_payment.workflow_record') }}: {{ __('bank_payment.signature_states.'.(!empty($payload['settlementApproval']) ? 'approved' : 'missing')) }}</p>
    <p>{{ $payload['settlementApproval']['actor'] ?? '—' }} · {{ __('bank_payment.workflow_actor_id') }}: {{ $payload['settlementApproval']['actorId'] ?? '—' }}</p>
    <p>{{ __('bank_payment.workflow_date') }}: {{ $payload['settlementApproval']['at'] ?? '—' }}</p>
</section>