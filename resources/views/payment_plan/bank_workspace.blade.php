<x-filament-panels::page>
<style>
 #bankWorkspace{--bank-border:#cbd5e1}#bankWorkspace [hidden]{display:none!important}#bankWorkspace label{display:block;margin:.65rem 0}#bankWorkspace input:not([type=checkbox]),#bankWorkspace select,#bankWorkspace textarea{display:block;width:100%;border:1px solid var(--bank-border);border-radius:.5rem;padding:.6rem;background:white;color:#111827}#bankWorkspace .card{border:1px solid var(--bank-border);border-radius:.75rem;padding:1rem;margin:1rem 0}#bankWorkspace .btn{display:inline-block;border:1px solid var(--bank-border);border-radius:.5rem;padding:.6rem .9rem;margin:.25rem;cursor:pointer}#bankWorkspace .btn-primary{background:#1d4ed8;color:white}#bankWorkspace button:disabled{opacity:.5;cursor:wait}#bankWorkspace pre{overflow-wrap:anywhere;white-space:pre-wrap;max-height:24rem;overflow:auto}#bankWorkspace .row{display:grid;gap:.75rem;grid-template-columns:repeat(3,minmax(0,1fr))}#bankWorkspace .input-group{display:flex;flex-wrap:wrap;gap:.3rem;align-items:center}#bankWorkspace .input-group input{flex:1;min-width:10rem}#bankWorkspace .alert{padding:.75rem;border-left:4px solid #2563eb;margin:.75rem 0}#bankWorkspace h2{font-size:1.15rem;font-weight:600}#bankWorkspace a{text-decoration:underline}#bankWorkspace .d-block{display:block}#bankWorkspace .bank-error{border-color:#dc2626}#bankWorkspace .bank-detail{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem} @media(max-width:640px){#bankWorkspace .row,#bankWorkspace .bank-detail{grid-template-columns:1fr}#bankWorkspace .btn{max-width:100%;overflow-wrap:anywhere}}
 #bankWorkspace .bank-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem}#bankWorkspace .bank-inbox-list{display:grid;gap:.5rem}#bankWorkspace .bank-inbox-row{display:grid;grid-template-columns:1fr 1.5fr 1fr auto;gap:.75rem;text-align:start;border:1px solid var(--bank-border);border-radius:.5rem;padding:.85rem;width:100%;overflow-wrap:anywhere}#bankWorkspace .bank-inbox-row:hover{background:#dbeafe;color:#172554}#bankWorkspace :focus-visible{outline:2px solid #2563eb;outline-offset:3px}#bankWorkspace dt{font-size:.85rem;font-weight:600}#bankWorkspace dd{overflow-wrap:anywhere}#bankWorkspace details{margin:.75rem 0}#bankWorkspace summary{cursor:pointer;font-weight:600}#bankWorkspace fieldset{min-width:0}#bankWorkspace legend{font-weight:600}#bankWorkspace #bankAttachments p{margin:.5rem 0}
 #bankWorkspace #selectedBankDoc{min-width:0;overflow-wrap:anywhere}#bankWorkspace .alert:empty{display:none}
 @media(max-width:640px){#bankWorkspace .row,#bankWorkspace .bank-detail,#bankWorkspace .bank-inbox-row{grid-template-columns:minmax(0,1fr)}#bankWorkspace .bank-inbox-row{gap:.2rem}}
 #bankWorkspace .bank-inbox-filters{display:grid;gap:.75rem;grid-template-columns:2fr 1fr 1fr;margin:.75rem 0}
 #bankWorkspace .bank-inbox-filters label{min-width:0;margin:0}
 #bankWorkspace #bankInboxCount{margin:.5rem 0;font-size:.875rem}
 @media(max-width:640px){#bankWorkspace .bank-inbox-filters{grid-template-columns:minmax(0,1fr)}}
</style>
<div id="bankWorkspace" wire:ignore>
    <header class="bank-toolbar">
        <p>{{ __('bank_payment.scope') }}</p>
        <a href="{{ route('payment.index') }}">{{ __('bank_payment.legacy') }}</a>
    </header>
    <div role="alert" id="bankMessage" class="alert alert-info" aria-live="polite"></div>
    <nav class="bank-toolbar" aria-label="{{ __('bank_payment.title') }}">
        <button type="button" class="btn" id="openBankFunds" hidden>{{ __('bank_payment.funds_position') }}</button>
    </nav>
    <section id="bankFunds" class="card" aria-labelledby="bankFundsHeading" hidden>
        <div class="bank-toolbar"><h2 id="bankFundsHeading" tabindex="-1">{{ __('bank_payment.funds_position') }}</h2><button type="button" id="backBankFunds" class="btn">{{ __('bank_payment.back_inbox') }}</button></div>
        <p>{{ __('bank_payment.funds_read_only') }}</p>
        <div id="erpBankBalances"></div>
        <a class="btn" href="{{ route('payment-plan-v33.idr3-movements') }}" id="idr3MovementLink" hidden>{{ __('idr3.title') }}</a>
    </section>
    <form id="newBankRequest" class="card card-body mb-3" hidden aria-labelledby="bankCreateHeading">
        <div class="bank-toolbar"><h2 id="bankCreateHeading" tabindex="-1">{{ __('bank_payment.new') }}</h2><button type="button" id="cancelBankCreate" class="btn">{{ __('bank_payment.back_inbox') }}</button></div>
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
    <section id="bankInboxPanel" class="card" aria-labelledby="bankInboxHeading">
        <div class="bank-toolbar"><h2 id="bankInboxHeading" tabindex="-1">{{ __('bank_payment.inbox') }}</h2><div><button type="button" id="openBankCreate" class="btn btn-primary" hidden>{{ __('bank_payment.new') }}</button><button type="button" id="refreshBank" class="btn">{{ __('bank_payment.refresh') }}</button></div></div>
        <div class="bank-inbox-filters">
            <label>{{ __('bank_payment.inbox_search') }}<input type="search" id="bankInboxSearch" maxlength="200" aria-controls="bankRequests"></label>
            <label>{{ __('bank_payment.inbox_scope') }}<select id="bankInbox" aria-controls="bankRequests"><option value="all">{{ __('bank_payment.all_visible') }}</option><option value="mine">{{ __('bank_payment.awaiting_me') }}</option></select></label>
            <label>{{ __('bank_payment.status') }}<select id="bankInboxStatus" aria-controls="bankRequests">
                <option value="all">{{ __('bank_payment.inbox_all_statuses') }}</option>
                @foreach (__('bank_payment.statuses') as $status => $statusLabel)
                    <option value="{{ $status }}">{{ $statusLabel }}</option>
                @endforeach
            </select></label>
        </div>
        <h3 id="bankQueueHeading" hidden>{{ __('bank_payment.queue') }}</h3>
        <p id="bankInboxCount" role="status"></p>
        <div id="bankRequests" class="bank-inbox-list"></div>
    </section>
    <form id="bankAction" class="card card-body mt-3" hidden>
        <div class="bank-toolbar"><h2 id="selectedBankDoc" tabindex="-1"></h2><button type="button" id="backBankInbox" class="btn">{{ __('bank_payment.back_inbox') }}</button></div>
        <div id="bankDetail" class="bank-detail"></div>
        <a id="printBank" class="btn btn-outline-secondary mb-2" target="_blank" rel="noopener">{{ __('bank_payment.print') }}</a>
        <details open><summary id="bankBasisHeading">{{ __('bank_payment.approved_basis') }}</summary><dl id="approvedBasis" class="bank-detail"></dl></details>
        <details><summary>{{ __('bank_payment.history') }}</summary><ol id="bankHistory"></ol></details>
        <fieldset id="bankActionFields"><legend>{{ __('bank_payment.action') }}</legend>
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
        </fieldset>
        <hr>
        <h2>{{ __('bank_payment.private_evidence') }}</h2>
        <div id="bankUploadFields" hidden>
        <p id="bankEvidenceHint"></p>
        <label>{{ __('bank_payment.attachment') }}<select name="kind" class="form-select"></select></label>
        <input type="file" name="file" aria-label="{{ __('bank_payment.private_evidence') }}" class="form-control" accept="application/pdf,image/png,image/jpeg,image/webp">
        <button type="button" id="uploadBank" class="btn btn-outline-primary mt-2">{{ __('bank_payment.upload') }}</button>
        </div>
        <div id="bankAttachments"></div>
    </form>
</div>
<script>
(() => {
 const endpoints = @json($endpoints);
 const token = @json(csrf_token()), roles = @json($actor['roles']);
 const ui = @json(__('bank_payment'));
 const actorId=@json($actor['id']);
 const label=(group,key)=>ui[group]?.[key]||key;
 const byId=id=>document.getElementById(id);
 const screens={inbox:['bankInboxPanel','bankInboxHeading'],create:['newBankRequest','bankCreateHeading'],detail:['bankAction','selectedBankDoc'],funds:['bankFunds','bankFundsHeading']};
 function show(screen, focus=true){Object.entries(screens).forEach(([name,[id]])=>byId(id).hidden=name!==screen);if(focus)byId(screens[screen][1]).focus();}
 byId('openBankCreate').onclick=()=>show('create');
 byId('cancelBankCreate').onclick=()=>{show('inbox',false);byId('openBankCreate').focus();};
 byId('backBankInbox').onclick=()=>{selected=null;show('inbox');};
 byId('openBankFunds').onclick=()=>{selected=null;show('funds');run(refresh);};
 byId('backBankFunds').onclick=()=>{show('inbox',false);byId('openBankFunds').focus();};
 const langIndex={id:0,en:1,zh_CN:2}[document.documentElement.lang.replace('-','_')]??0;
 const translated=values=>values?.[langIndex] || values?.find(Boolean) || '—';
 const amount=value=>new Intl.NumberFormat(document.documentElement.lang.replace('_','-'),{style:'currency',currency:'IDR',minimumFractionDigits:2}).format(value);
 function term(root,name,value){const item=document.createElement('div'),key=document.createElement('dt'),body=document.createElement('dd');key.textContent=name;body.textContent=value??'—';item.append(key,body);root.append(item);}
 const actionForm=byId('bankAction');
 function actionFields(){const action=actionForm.elements.action.value;byId('settlementFields').hidden=action!=='prepare_settlement';for(const [field,actions] of Object.entries({reference:['mark_maker','release_gm','hold_bank','restart_after_no_debit','confirm_paid','post_external'],reason:['reject','prepare_settlement','hold_bank','restart_after_no_debit'],paidDate:['confirm_paid'],checked:['approve_request','prepare_settlement','approve_settlement','release_gm','hold_bank','restart_after_no_debit','confirm_paid','post_external'],revisedCeiling:['approve_settlement']})){const input=actionForm.elements[field];input.disabled=!actions.includes(action);input.closest('label').hidden=input.disabled;}}
 actionForm.elements.action.onchange=actionFields;
 document.getElementById('addBankLine').onclick=()=>{
   if(document.querySelectorAll('#bankLines fieldset').length>=50)return;
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
 async function run(fn) { if(busy) return; busy=true; message.classList.remove('bank-error');message.textContent=ui.loading;byId('bankWorkspace').setAttribute('aria-busy','true'); try {await fn(); message.textContent=ui.success;} catch(e){message.classList.add('bank-error');message.textContent=ui.error+' '+e.message;} finally{busy=false;byId('bankWorkspace').setAttribute('aria-busy','false');} }
 function select(row, focus=false) {
   const p=row.request, form=actionForm;
   if(selected?.request.id!==p.id || selected?.request.status!==p.status)form.reset();
   selected=row;
   const heading=document.getElementById('selectedBankDoc');heading.textContent=p.doc+' — '+label('statuses',p.status)+' — '+amount(p.amount);
   heading.dataset.bankRequestId=p.id;heading.dataset.bankDoc=p.doc;
   const detail=document.getElementById('bankDetail');detail.replaceChildren();
   ['payee','recipientBank','recipientAccount','due','invoice','createdName'].forEach(k=>{const item=document.createElement('p');item.textContent=(ui[k]||k)+': '+(p[k]||'—');detail.append(item);});
   ['subject','purpose'].forEach(k=>{const item=document.createElement('p');item.textContent=ui[k]+': '+translated(p[k]);detail.append(item);});
   const actions=row.actions??[];
   form.elements.action.replaceChildren(...actions.map(a=>new Option(label('actions',a),a))); form.querySelector('button[type=submit]').disabled=!actions.length;
   byId('bankActionFields').hidden=!actions.length;
    form.elements.base.value=p.settlement?.base ?? p.amount;
   for(const k of ['vat','withholding','fees','coa','sourceId'])form.elements[k].value=p.settlement?.[k]??({coa:'',sourceId:'idr3'}[k]??'0');
   actionFields();
   document.getElementById('printBank').href=endpoints.print+'/'+encodeURIComponent(p.id)+'/print';
   const approval=byId('approvedBasis'), history=byId('bankHistory');approval.replaceChildren();history.replaceChildren();
   const record=p.accountingApproval;
   if(record){term(approval,ui.basis_approved_by,p.settlementApproval?.actor || record.actor_id);term(approval,ui.basis_approved_at,record.approved_at);term(approval,ui.basis_settings_version,record.basis?.settings_version);}
   else if(!p.settlement) term(approval,ui.approved_basis,ui.no_approval);
   byId('bankBasisHeading').textContent=!record&&p.settlement?ui.pending_settlement:ui.approved_basis;
   const settlement=record?.basis?.settlement??p.settlement;
   const source=record?.basis?.source_account?.id??p.settlement?.sourceId;
   if(source)term(approval,ui.source,source.toUpperCase());
   for(const k of ['base','vat','withholding','fees','gross','debit'])if(settlement?.[k]!=null)term(approval,ui[k],amount(settlement[k]));
   for(const line of record?.basis?.lines||[])term(approval,ui.coa+' '+line.account_code,label('positions',line.position)+' · '+amount(line.amount));
   if(p.journal?.journalId)term(approval,ui.journal,p.journal.journalId);
   for(const decision of p.decisions||[]){const item=document.createElement('li');item.textContent=[label('actions',decision.action),decision.actor,decision.at,decision.reference,decision.reason].filter(Boolean).join(' · ');history.append(item);}
   if(!history.children.length){const item=document.createElement('li');item.textContent=ui.no_approval;history.append(item);}
   const links=document.getElementById('bankAttachments'); links.replaceChildren();
   (p.attachments||[]).forEach(a=>{const entry=document.createElement('p'),link=document.createElement('a');link.href=endpoints.download+'/'+encodeURIComponent(a.id);link.textContent=label('attachment_kinds',a.kind)+': '+a.name;const preview=document.createElement('a');preview.href=link.href+'?preview=1';preview.target='_blank';preview.rel='noopener';preview.textContent=ui.preview;entry.append(link,' · ',preview);links.append(entry);});
   const kinds=[];if(p.status==='draft'&&p.createdBy===actorId)kinds.push('request');
   if(roles.some(r=>['finance_staff','finance_manager'].includes(r))){if(p.status==='request_approved')kinds.push('settlement');if(['bank_released','bank_hold'].includes(p.status))kinds.push('payment');}
   form.elements.kind.replaceChildren(...kinds.map(k=>new Option(label('attachment_kinds',k),k)));byId('bankUploadFields').hidden=!kinds.length;
   byId('bankEvidenceHint').textContent=p.status==='request_approved'?ui.settlement_evidence_hint:p.status==='bank_released'?ui.debit_evidence_hint:'';
   show('detail',focus);
 }
 let inboxRows=[];
 const inboxSearchText=value=>String(value??'').normalize('NFKC').trim().toLowerCase();
 function renderInbox() {
   const list=byId('bankRequests');
   const query=inboxSearchText(byId('bankInboxSearch').value);
   const status=byId('bankInboxStatus').value;
   const awaitingMe=byId('bankInbox').value==='mine';
   byId('bankQueueHeading').hidden=!awaitingMe;
   const visible=inboxRows.filter(({request:p,actions})=>
     (status==='all'||p.status===status)
     &&(!awaitingMe||actions.length>0)
     &&(!query||[p.doc,p.payee,p.subject,p.purpose].flat().some(value=>inboxSearchText(value).includes(query)))
   );
   list.replaceChildren();
   byId('bankInboxCount').textContent=ui.inbox_count.replace(':count',new Intl.NumberFormat().format(visible.length));
   if(!visible.length){const empty=document.createElement('p');empty.textContent=ui.empty;list.append(empty);}
   visible.forEach(row=>{const p=row.request,b=document.createElement('button');b.type='button';b.className='bank-inbox-row';for(const [tag,value] of [['strong',p.payee],['span',p.doc],['span',label('statuses',p.status)],['span',amount(p.amount)]]){const field=document.createElement(tag);field.textContent=value;b.append(field);}if(awaitingMe){const next=document.createElement('span');next.style.gridColumn='1 / -1';next.textContent=ui.next_action+': '+row.actions.map(a=>label('actions',a)).join(' · ');b.append(next);}b.dataset.bankRequestId=p.id;b.dataset.bankDoc=p.doc;b.onclick=()=>select(row,true);list.append(b);});
 }
 async function refresh() {
   const data=await api(endpoints.data);
   const balances=document.getElementById('erpBankBalances');balances.replaceChildren();
   const idr3=data.bankBalances?.idr3;
   byId('openBankFunds').hidden=!idr3;
   byId('idr3MovementLink').hidden=!idr3;
   if(idr3){const heading=document.createElement('h3'),values=document.createElement('dl');heading.textContent=ui.bank_balance+' · IDR3 · '+idr3.account_code;values.className='bank-detail';for(const field of ['available','ledger','unposted_actual_debits','reserved','opening','movement'])term(values,label('balance_fields',field),amount(idr3[field]));term(values,ui.ledger_cutoff,idr3.cutoff);balances.append(heading,values);}
   else if(!byId('bankFunds').hidden)show('inbox');
   inboxRows=data.requests;
   renderInbox();
   const current=inboxRows.find(row=>selected?.request.id===row.request.id);if(current)select(current);
 }
 byId('refreshBank').onclick=()=>run(refresh);
 byId('bankInboxSearch').oninput=renderInbox;
 byId('bankInbox').onchange=renderInbox;
 byId('bankInboxStatus').onchange=renderInbox;
 byId('openBankCreate').hidden=!roles.some(r=>['requester','finance_staff','finance_manager'].includes(r));
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
  }
  document.getElementById('searchAp').onclick=()=>run(()=>loadAp(1));
  document.getElementById('apSearch').onkeydown=e=>{if(e.key==='Enter'){e.preventDefault();run(()=>loadAp(1));}};
  document.getElementById('previousAp').onclick=()=>run(()=>loadAp(Math.max(1,apPage-1)));
  document.getElementById('nextAp').onclick=()=>run(()=>loadAp(Math.min(apLastPage,apPage+1)));
 document.getElementById('newBankRequest').onsubmit=e=>{e.preventDefault();run(async()=>{const f=new FormData(e.target), body={id:createId};['payee','amount','recipientBank','recipientAccount','due'].forEach(k=>body[k]=f.get(k));if(f.get('purchaseBillId'))body.purchaseBillId=Number(f.get('purchaseBillId'));['subject','purpose'].forEach(k=>body[k]=['id','en','zh'].map(l=>f.get(k+'_'+l)));
 const lines=[...document.querySelectorAll('#bankLines fieldset')].map(row=>({description:['id','en','zh'].map(l=>row.querySelector('[data-line-description="'+l+'"]').value),qty:row.querySelector('[data-line-value="qty"]').value,unitPrice:row.querySelector('[data-line-value="unitPrice"]').value}));if(lines.length)body.lines=lines;
  const row=await api(endpoints.request,body);createId=crypto.randomUUID();e.target.reset();document.getElementById('bankLines').replaceChildren();select(row,true);await refresh();});};
 document.getElementById('bankAction').onsubmit=e=>{e.preventDefault();run(async()=>{const f=new FormData(e.target), action=f.get('action'), input={checked:f.has('checked'),reference:f.get('reference'),reason:f.get('reason')}; if(action==='prepare_settlement')['base','vat','withholding','fees','coa','sourceId'].forEach(k=>input[k]=f.get(k));if(action==='approve_settlement')input.revisedCeiling=f.has('revisedCeiling');if(action==='confirm_paid')input.paidDate=f.get('paidDate');const body={id:selected.request.id,version:selected.version,action,input}, signature=JSON.stringify(body);if(pendingAction?.signature!==signature)pendingAction={signature,key:crypto.randomUUID()};selected=await api(endpoints.action,{...body,mutationId:pendingAction.key});pendingAction=null;await refresh();});};
 document.getElementById('uploadBank').onclick=()=>run(async()=>{const form=document.getElementById('bankAction'),f=new FormData();f.set('id',selected.request.id);f.set('version',selected.version);f.set('kind',form.elements.kind.value);if(!form.elements.file.files[0])throw new Error('File required');f.set('file',form.elements.file.files[0]);selected=await api(endpoints.attachment,f);await refresh();});
  run(async()=>{await refresh();if(location.hash==='#bankFunds'&&!byId('openBankFunds').hidden)show('funds');await loadAp();});
})();
</script>
</x-filament-panels::page>