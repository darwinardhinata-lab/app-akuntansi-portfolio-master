<x-filament-panels::page>
<style>
 #bankWorkspace{--bank-border:#cbd5e1}#bankWorkspace [hidden]{display:none!important}#bankWorkspace label{display:block;margin:.65rem 0}#bankWorkspace input:not([type=checkbox]),#bankWorkspace select,#bankWorkspace textarea{display:block;width:100%;border:1px solid var(--bank-border);border-radius:.5rem;padding:.6rem;background:white;color:#111827}#bankWorkspace .card{border:1px solid var(--bank-border);border-radius:.75rem;padding:1rem;margin:1rem 0}#bankWorkspace .btn{display:inline-block;border:1px solid var(--bank-border);border-radius:.5rem;padding:.6rem .9rem;margin:.25rem;cursor:pointer}#bankWorkspace .btn-primary{background:#1d4ed8;color:white}#bankWorkspace button:disabled{opacity:.5;cursor:wait}#bankWorkspace pre{overflow-wrap:anywhere;white-space:pre-wrap;max-height:24rem;overflow:auto}#bankWorkspace .row{display:grid;gap:.75rem;grid-template-columns:repeat(3,minmax(0,1fr))}#bankWorkspace .input-group{display:flex;flex-wrap:wrap;gap:.3rem;align-items:center}#bankWorkspace .input-group input{flex:1;min-width:10rem}#bankWorkspace .alert{padding:.75rem;border-left:4px solid #2563eb;margin:.75rem 0}#bankWorkspace h2{font-size:1.15rem;font-weight:600}#bankWorkspace a{text-decoration:underline}#bankWorkspace .d-block{display:block}#bankWorkspace .bank-error{border-color:#dc2626}#bankWorkspace .bank-detail{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem} @media(max-width:640px){#bankWorkspace .row,#bankWorkspace .bank-detail{grid-template-columns:1fr}#bankWorkspace .btn{max-width:100%;overflow-wrap:anywhere}}
</style>
<div id="bankWorkspace" wire:ignore>
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
        <details><summary>{{ __('bank_payment.items') }}</summary><div id="bankLines"></div><button type="button" id="addBankLine" class="btn">{{ __('bank_payment.add_item') }}</button></details>
        <button class="btn btn-primary mt-3">{{ __('bank_payment.create') }}</button>
    </form>
    <button type="button" id="refreshBank" class="btn btn-outline-primary">{{ __('bank_payment.refresh') }}</button>
    <label>{{ __('bank_payment.inbox') }}<select id="bankInbox"><option value="mine">{{ __('bank_payment.awaiting_me') }}</option><option value="all">{{ __('bank_payment.all_visible') }}</option></select></label>
    <div id="bankRequests" class="mt-3"></div>
    <form id="bankAction" class="card card-body mt-3" hidden>
        <h2 id="selectedBankDoc" class="h5"></h2>
        <div id="bankDetail" class="bank-detail"></div>
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
 const ui = @json(__('bank_payment'));
 const rules={draft:[['submit','requester']],submitted:[['approve_request','director'],['reject','director']],request_approved:[['prepare_settlement','finance_staff'],['prepare_settlement','finance_manager']],settlement_review:[['approve_settlement','director']],settlement_approved:[['mark_maker','maker']],bank_prepared:[['release_gm','general_manager'],['hold_bank','general_manager']],bank_hold:[['restart_after_no_debit','finance_manager']],bank_released:[['confirm_paid','finance_staff'],['confirm_paid','finance_manager']],paid:[['post_external','accountant']]};
 const actionsFor=p=>[...new Set((rules[p.status]||[]).filter(x=>roles.includes(x[1]) && (p.status!=='draft'||p.createdBy===@json($actor['id']))).map(x=>x[0]))];
 const label=(group,key)=>ui[group]?.[key]||key;
 document.getElementById('addBankLine').onclick=()=>{
   if(document.querySelectorAll('#bankLines fieldset').length>=100)return;
   const row=document.createElement('fieldset');row.className='card';
   ['id','en','zh'].forEach(lang=>{const l=document.createElement('label');l.textContent=ui.subject+' ('+lang+')';const input=document.createElement('input');input.dataset.lineDescription=lang;input.required=true;input.maxLength=1000;l.append(input);row.append(l);});
   ['qty','unitPrice'].forEach(key=>{const l=document.createElement('label');l.textContent=ui[key];const input=document.createElement('input');input.type='number';input.dataset.lineValue=key;input.min=key==='qty'?'0.0001':'0.01';input.step=key==='qty'?'0.0001':'0.01';input.required=true;input.value=key==='qty'?'1':'';l.append(input);row.append(l);});
   const remove=document.createElement('button');remove.type='button';remove.textContent=ui.remove_item;remove.className='btn';remove.onclick=()=>row.remove();row.append(remove);document.getElementById('bankLines').append(row);
 };
 let selected = null, busy = false, createId = crypto.randomUUID(), pendingAction = null;
 const message = document.getElementById('bankMessage');
 async function api(url, body) {
   const response = await fetch(url, {method: body ? 'POST':'GET', credentials:'same-origin', headers:{Accept:'application/json', ...(body instanceof FormData ? {} : {'Content-Type':'application/json'}), 'X-CSRF-TOKEN':token}, body:body ? (body instanceof FormData ? body : JSON.stringify(body)):undefined});
   const data = await response.json(); if(!response.ok) throw new Error(data.error || (data.errors ? Object.values(data.errors).flat().join('\n') : data.message) || String(response.status)); return data;
 }
 async function run(fn) { if(busy) return; busy=true; message.classList.remove('bank-error');message.textContent=ui.loading;document.getElementById('bankWorkspace').setAttribute('aria-busy','true'); try {await fn(); message.textContent=ui.success;} catch(e){message.classList.add('bank-error');message.textContent=ui.error+' '+e.message;} finally{busy=false;document.getElementById('bankWorkspace').setAttribute('aria-busy','false');} }
 function select(row) {
   selected=row; const p=row.request, form=document.getElementById('bankAction'); form.hidden=false;
   document.getElementById('selectedBankDoc').textContent=p.doc+' — '+label('statuses',p.status)+' — IDR '+p.amount;
   const detail=document.getElementById('bankDetail');detail.replaceChildren();
   ['payee','recipientBank','recipientAccount','due','invoice','createdName'].forEach(k=>{const item=document.createElement('p');item.textContent=(ui[k]||k)+': '+(p[k]||'—');detail.append(item);});
   ['subject','purpose'].forEach(k=>{const item=document.createElement('p');item.textContent=ui[k]+': '+(p[k]||[]).join(' / ');detail.append(item);});
   const actions=actionsFor(p);
   form.elements.action.replaceChildren(...actions.map(a=>new Option(label('actions',a),a))); form.querySelector('button[type=submit]').disabled=!actions.length;
    form.elements.base.value=p.settlement?.base ?? p.amount;
    ['vat','withholding','fees','coa','sourceId'].forEach(k=>{if(p.settlement?.[k]!==undefined)form.elements[k].value=p.settlement[k];});
    document.getElementById('settlementFields').hidden=p.status!=='request_approved';
   document.getElementById('printBank').href=endpoints.print+'/'+encodeURIComponent(p.id)+'/print';
   document.getElementById('approvedBasis').textContent=JSON.stringify(p.accountingApproval||null,null,2);
   const links=document.getElementById('bankAttachments'); links.replaceChildren();
   (p.attachments||[]).forEach(a=>{const link=document.createElement('a'); link.href=endpoints.download+'/'+encodeURIComponent(a.id); link.textContent=a.kind+': '+a.name; link.className='d-block'; links.append(link);const preview=document.createElement('a');preview.href=link.href+'?preview=1';preview.target='_blank';preview.rel='noopener';preview.textContent=ui.preview;links.append(preview);});
 }
 async function refresh() {const data=await api(endpoints.data); const list=document.getElementById('bankRequests'); list.replaceChildren();
   const balances=document.getElementById('erpBankBalances');balances.replaceChildren();
   Object.entries(data.bankBalances||{}).forEach(([key,b])=>{const line=document.createElement('p');line.textContent=key+' · GL '+b.ledger+' · pending debit '+b.unposted_actual_debits+' · reserved '+b.reserved+' · available '+b.available;balances.append(line);});
   const visible=data.requests.filter(row=>document.getElementById('bankInbox').value==='all'||actionsFor(row.request).length);
   if(!visible.length){const empty=document.createElement('p');empty.textContent=ui.empty;list.append(empty);}
   visible.forEach(row=>{const b=document.createElement('button');b.type='button';b.className='btn btn-outline-secondary d-block mb-2';b.textContent=row.request.doc+' — '+row.request.payee+' — '+label('statuses',row.request.status);b.onclick=()=>select(row);list.append(b);});
   const current=data.requests.find(row=>selected?.request.id===row.request.id);if(current)select(current);}
 document.getElementById('refreshBank').onclick=()=>run(refresh);
 document.getElementById('bankInbox').onchange=()=>run(refresh);
 document.getElementById('newBankRequest').hidden=!roles.some(r=>['requester','finance_staff','finance_manager'].includes(r));
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
 document.getElementById('newBankRequest').onsubmit=e=>{e.preventDefault();run(async()=>{const f=new FormData(e.target), body={id:createId};['payee','amount','recipientBank','recipientAccount','due'].forEach(k=>body[k]=f.get(k));if(f.get('purchaseBillId'))body.purchaseBillId=Number(f.get('purchaseBillId'));['subject','purpose'].forEach(k=>body[k]=['id','en','zh'].map(l=>f.get(k+'_'+l)));
 const lines=[...document.querySelectorAll('#bankLines fieldset')].map(row=>({description:['id','en','zh'].map(l=>row.querySelector('[data-line-description="'+l+'"]').value),qty:row.querySelector('[data-line-value="qty"]').value,unitPrice:row.querySelector('[data-line-value="unitPrice"]').value}));if(lines.length)body.lines=lines;
 const row=await api(endpoints.request,body);createId=crypto.randomUUID();document.getElementById('bankLines').replaceChildren();select(row);await refresh();});};
 document.getElementById('bankAction').onsubmit=e=>{e.preventDefault();run(async()=>{const f=new FormData(e.target), action=f.get('action'), input={checked:f.has('checked'),reference:f.get('reference'),reason:f.get('reason')}; if(action==='prepare_settlement')['base','vat','withholding','fees','coa','sourceId'].forEach(k=>input[k]=f.get(k));if(action==='approve_settlement')input.revisedCeiling=f.has('revisedCeiling');if(action==='confirm_paid')input.paidDate=f.get('paidDate');const body={id:selected.request.id,version:selected.version,action,input}, signature=JSON.stringify(body);if(pendingAction?.signature!==signature)pendingAction={signature,key:crypto.randomUUID()};selected=await api(endpoints.action,{...body,mutationId:pendingAction.key});pendingAction=null;await refresh();});};
 document.getElementById('uploadBank').onclick=()=>run(async()=>{const form=document.getElementById('bankAction'),f=new FormData();f.set('id',selected.request.id);f.set('version',selected.version);f.set('kind',form.elements.kind.value);if(!form.elements.file.files[0])throw new Error('File required');f.set('file',form.elements.file.files[0]);selected=await api(endpoints.attachment,f);await refresh();});
  run(async()=>{await refresh();await loadAp();});
})();
</script>
</x-filament-panels::page>