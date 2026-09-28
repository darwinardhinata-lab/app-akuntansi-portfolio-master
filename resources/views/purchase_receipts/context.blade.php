@if(config('platform.order_company_scope_enabled'))
    <input type="hidden" name="context_company_id" value="{{ app(\App\Modules\Platform\Support\OperationalCompany::class)->id() }}">
    <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
@endif
