@extends('layouts.app')
@section('content')
<div class="container py-3" id="bankWorkspace">
    <h1 class="h4">{{ __('bank_payment.title') }}</h1>
    <p class="text-muted">{{ __('bank_payment.scope') }}</p>
    <div role="alert" id="bankMessage" class="alert alert-info" aria-live="polite"></div>
    <div id="erpBankBalances" class="mb-3"></div>
    <pre id="apReconciliationDetails" class="small border p-2" style="white-space:pre-wrap"></pre>
    <form id="newBankRequest" class="card card-body mb-3">
        <h2 class="h5">{{ __('bank_payment.new') }}</h2>
        <div class="input-group my-2">
            <input id="apSearch" class="form-control" aria-label="{{ __('bank_payment.search_ap') }}" placeholder="{{ __('bank_payment.search_ap') }}">
            <button id="searchAp" type="button" class="btn btn-outline-primary">{{ __('bank_payment.search_ap') }}</button>
            <button id="previousAp" type="button" class="btn btn-outline-secondary">‹</button>
            <span id="apPage" class="input-group-text" aria-live="polite"></span>
            <button id="nextAp" type="button" class="btn btn-outline-secondary">›</button>
        </div>
        <label>{{ __('bank_payment.ap_source') }}<select name="purchaseBillId" class="form-select"><option value="">{{ __('bank_payment.direct_service') }}</option></select></label>
        <label>{{ __('bank_payment.payee') }}<input name="payee" class="form-control" required maxlength="150"></label>
        <label>{{ __('bank_payment.subject') }} (ID / EN / ZH)</label>
        <div class="row">@foreach(['id','en','zh'] as $locale)<div class="col-md-4"><input name="subject_{{ $locale }}" aria-label="{{ __('bank_payment.subject') }} {{ $locale }}" class="form-control" required></div>@endforeach</div>
        <label>{{ __('bank_payment.purpose') }} (ID / EN / ZH)</label>
        <div class="row">@foreach(['id','en','zh'] as $locale)<div class="col-md-4"><input name="purpose_{{ $locale }}" aria-label="{{ __('bank_payment.purpose') }} {{ $locale }}" class="form-control" required></div>@endforeach</div>
        <label>{{ __('bank_payment.amount') }} IDR<input name="amount" type="number" min="0.01" step="0.01" class="form-control" required></label>
        <label>{{ __('bank_payment.bank') }}<input name="recipientBank" class="form-control" required></label>
        <label>{{ __('bank_payment.account') }}<input name="recipientAccount" class="form-control" required></label>
        <label>{{ __('bank_payment.due') }}<input name="due" type="date" class="form-control" required></label>
        <button class="btn btn-primary mt-2" type="submit">{{ __('bank_payment.save') }}</button>
    </form>
    <button type="button" id="refreshBank" class="btn btn-outline-primary">{{ __('bank_payment.refresh') }}</button>
    <div id="bankRequests" class="mt-3"></div>
    <form id="bankAction" class="card card-body mt-3" hidden>
        <h2 id="selectedBankDoc" class="h5"></h2>
        <a id="printBank" class="btn btn-outline-secondary mb-2" target="_blank" rel="noopener">{{ __('bank_payment.print') }}</a>
        <details><summary>{{ __('bank_payment.approved_basis') }}</summary><pre id="approvedBasis" style="white-space:pre-wrap"></pre></details>
        <label>{{ __('bank_payment.action') }}<select name="action" class="form-select"></select></label>
        <label>{{ __('bank_payment.reference') }}<input name="reference" class="form-control" maxlength="500"></label>
        <label>{{ __('bank_payment.reason') }}<textarea name="reason" class="form-control"></textarea></label>
        <div id="settlementFields">
            @foreach(['base','vat','withholding','fees'] as $field)<label>{{ __('bank_payment.'.$field) }}<input name="{{ $field }}" type="number" min="0" step="0.01" class="form-control" value="0"></label>@endforeach
            <label>{{ __('bank_payment.coa') }}<input name="coa" class="form-control" maxlength="6"></label>
            <label>{{ __('bank_payment.source') }}<select name="sourceId" class="form-select"><option value="idr3">IDR3</option></select></label>
        </div>
        <label>{{ __('bank_payment.paid_date') }}<input name="paidDate" type="date" class="form-control"></label>
        <label><input name="checked" type="checkbox"> {{ __('bank_payment.confirm') }}</label>
        <label><input name="revisedCeiling" type="checkbox"> {{ __('bank_payment.ceiling') }}</label>
        <button type="submit" class="btn btn-primary mt-2">{{ __('bank_payment.execute') }}</button>
        <hr>
        <label>{{ __('bank_payment.attachment') }}<select name="kind" class="form-select"><option value="request">Request</option><option value="settlement">Settlement</option><option value="payment">Payment</option></select></label>
        <input type="file" name="file" class="form-control" accept="application/pdf,image/png,image/jpeg,image/webp">
        <button type="button" id="uploadBank" class="btn btn-outline-primary mt-2">{{ __('bank_payment.upload') }}</button>
        <div id="bankAttachments"></div>
    </form>
</div>
<script>
(() => {
 const endpoints = @json($endpoints);
 const token = @json(csrf_token()), roles = @json($actor['roles']);
 let selected = null, busy = false, createId = crypto.randomUUID(), pendingAction = null;
 const message = document.getElementById('bankMessage');
 async function api(url, body) {
   const response = await fetch(url, {method: body ? 'POST':'GET', credentials:'same-origin', headers:{Accept:'application/json', ...(body instanceof FormData ? {} : {'Content-Type':'application/json'}), 'X-CSRF-TOKEN':token}, body:body ? (body instanceof FormData ? body : JSON.stringify(body)):undefined});
   const data = await response.json(); if(!response.ok) throw new Error(data.error || (data.errors ? Object.values(data.errors).flat().join('\n') : data.message) || String(response.status)); return data;
 }
 async function run(fn) { if(busy) return; busy=true; try {await fn(); message.textContent='OK';} catch(e){message.textContent=e.message;} finally{busy=false;} }
 function select(row) {
   selected=row; const p=row.request, form=document.getElementById('bankAction'); form.hidden=false;
   document.getElementById('selectedBankDoc').textContent=p.doc+' — '+p.status+' — IDR '+p.amount;
   const rules={draft:[['submit','requester']],submitted:[['approve_request','director'],['reject','director']],request_approved:[['prepare_settlement','finance_staff'],['prepare_settlement','finance_manager']],settlement_review:[['approve_settlement','director']],settlement_approved:[['mark_maker','maker']],bank_prepared:[['release_gm','general_manager'],['hold_bank','general_manager']],bank_hold:[['restart_after_no_debit','finance_manager']],bank_released:[['confirm_paid','finance_staff'],['confirm_paid','finance_manager']],paid:[['post_external','accountant']]};
   const actions=[...new Set((rules[p.status]||[]).filter(x=>roles.includes(x[1])).map(x=>x[0]))];
   form.elements.action.replaceChildren(...actions.map(a=>new Option(a,a))); form.querySelector('button[type=submit]').disabled=!actions.length;
    form.elements.base.value=p.settlement?.base ?? p.amount;
    ['vat','withholding','fees','coa','sourceId'].forEach(k=>{if(p.settlement?.[k]!==undefined)form.elements[k].value=p.settlement[k];});
    document.getElementById('settlementFields').hidden=p.status!=='request_approved';
   document.getElementById('printBank').href=endpoints.print+'/'+encodeURIComponent(p.id)+'/print';
   document.getElementById('approvedBasis').textContent=JSON.stringify(p.accountingApproval||null,null,2);
   const links=document.getElementById('bankAttachments'); links.replaceChildren();
   (p.attachments||[]).forEach(a=>{const link=document.createElement('a'); link.href=endpoints.download+'/'+encodeURIComponent(a.id); link.textContent=a.kind+': '+a.name; link.className='d-block'; links.append(link);});
 }
 async function refresh() {const data=await api(endpoints.data); const list=document.getElementById('bankRequests'); list.replaceChildren();
   const balances=document.getElementById('erpBankBalances');balances.replaceChildren();
   Object.entries(data.bankBalances||{}).forEach(([key,b])=>{const line=document.createElement('p');line.textContent=key+' · GL '+b.ledger+' · pending debit '+b.unposted_actual_debits+' · reserved '+b.reserved+' · available '+b.available;balances.append(line);});
   data.requests.forEach(row=>{const b=document.createElement('button');b.className='btn btn-outline-secondary d-block mb-2';b.textContent=row.request.doc+' — '+row.request.payee+' — '+row.request.status;b.onclick=()=>select(row);list.append(b);if(selected?.request.id===row.request.id)select(row);});}
 document.getElementById('refreshBank').onclick=()=>run(refresh);
  let apPage=1, apLastPage=1, apGeneration=0;
  async function loadAp(page=1){
    const generation=++apGeneration;
    const response=await fetch(endpoints.purchaseBills+'?'+new URLSearchParams({q:document.getElementById('apSearch').value,page}),{headers:{Accept:'application/json'}});
    if(response.status===404||response.status===403){document.getElementById('searchAp').disabled=true;return;}
    const data=await response.json();if(!response.ok)throw new Error(data.message||'AP lookup failed');if(generation!==apGeneration)return;
    apPage=data.page;apLastPage=data.lastPage;
    const source=document.getElementById('newBankRequest').elements.purchaseBillId, old=source.selectedOptions[0];
    const saved=old?.value?new Option(old.text,old.value):null;if(saved)saved.disabled=old.disabled;
    while(source.options.length>1)source.remove(1);
    data.items.forEach(p=>{const option=new Option([p.number,p.vendor,p.date,p.status,p.residual,p.blocked].filter(Boolean).join(' / '),p.id);option.disabled=!!p.blocked;source.add(option);});
    if(saved&&!data.items.some(p=>String(p.id)===saved.value))source.add(saved);
    if(saved)source.value=saved.value;
    document.getElementById('apPage').textContent=apPage+' / '+apLastPage;
    document.getElementById('previousAp').disabled=apPage<=1;document.getElementById('nextAp').disabled=apPage>=apLastPage;
    document.getElementById('apReconciliationDetails').textContent=JSON.stringify(data.items,null,2);
  }
  document.getElementById('searchAp').onclick=()=>run(()=>loadAp(1));
  document.getElementById('apSearch').onkeydown=e=>{if(e.key==='Enter'){e.preventDefault();run(()=>loadAp(1));}};
  document.getElementById('previousAp').onclick=()=>run(()=>loadAp(Math.max(1,apPage-1)));
  document.getElementById('nextAp').onclick=()=>run(()=>loadAp(Math.min(apLastPage,apPage+1)));
 document.getElementById('newBankRequest').onsubmit=e=>{e.preventDefault();run(async()=>{const f=new FormData(e.target), body={id:createId};['payee','amount','recipientBank','recipientAccount','due'].forEach(k=>body[k]=f.get(k));if(f.get('purchaseBillId'))body.purchaseBillId=Number(f.get('purchaseBillId'));['subject','purpose'].forEach(k=>body[k]=['id','en','zh'].map(l=>f.get(k+'_'+l)));const row=await api(endpoints.request,body);createId=crypto.randomUUID();select(row);await refresh();});};
 document.getElementById('bankAction').onsubmit=e=>{e.preventDefault();run(async()=>{const f=new FormData(e.target), action=f.get('action'), input={checked:f.has('checked'),reference:f.get('reference'),reason:f.get('reason')}; if(action==='prepare_settlement')['base','vat','withholding','fees','coa','sourceId'].forEach(k=>input[k]=f.get(k));if(action==='approve_settlement')input.revisedCeiling=f.has('revisedCeiling');if(action==='confirm_paid')input.paidDate=f.get('paidDate');const body={id:selected.request.id,version:selected.version,action,input}, signature=JSON.stringify(body);if(pendingAction?.signature!==signature)pendingAction={signature,key:crypto.randomUUID()};selected=await api(endpoints.action,{...body,mutationId:pendingAction.key});pendingAction=null;await refresh();});};
 document.getElementById('uploadBank').onclick=()=>run(async()=>{const form=document.getElementById('bankAction'),f=new FormData();f.set('id',selected.request.id);f.set('version',selected.version);f.set('kind',form.elements.kind.value);if(!form.elements.file.files[0])throw new Error('File required');f.set('file',form.elements.file.files[0]);selected=await api(endpoints.attachment,f);await refresh();});
  run(async()=>{await refresh();await loadAp();});
})();
</script>
@endsection