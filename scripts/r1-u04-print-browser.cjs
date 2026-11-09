// Authorized print of an already normal-created approved synthetic request; no SQL status edits.
const fs=require('fs'),path=require('path'),crypto=require('crypto'),assert=require('node:assert/strict');
const {chromium}=require(process.env.R1_PLAYWRIGHT_MODULE);
const [root,base]=process.argv.slice(2),out=path.dirname(root);
assert.equal(new URL(base).hostname,'127.0.0.1');assert.equal(fs.readFileSync(path.join(root,'marker'),'utf8'),'R1 disposable web v1');
const approved=JSON.parse(fs.readFileSync(path.join(out,'u04-approved-response.json'),'utf8'));
const stored=JSON.parse(fs.readFileSync(path.join(out,'u04-photos.json'),'utf8'));
const report={status:'RUNNING',checks:[]};
const check=(name,actual,expected)=>{assert.deepEqual(actual,expected,name);report.checks.push({name,actual});};
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 try{
  const user=JSON.parse(fs.readFileSync(path.join(root,'financial-users.json'))).fs;
  const page=await browser.newPage({viewport:{width:1440,height:900}});
  await page.goto(base+'/login');await page.locator('[name=email]').fill(user.email);await page.locator('[name=password]').fill(user.password);await page.locator('button[type=submit]').click();await page.waitForURL('**/dashboard');
  await page.goto(base+'/erp-panel/bank-payments');
  const print=await page.goto(base+'/payment-plan/bank/'+approved.request.id+'/print');check('authorized print',print.status(),200);
  await page.waitForFunction(()=>[...document.querySelectorAll('.photo img')].length>=3&&[...document.querySelectorAll('.photo img')].every(img=>img.complete&&img.naturalWidth>0),null,{timeout:15000}).catch(async e=>{throw Error(e.message+'; images '+JSON.stringify(await page.locator('.photo img').evaluateAll(xs=>xs.map(x=>({url:x.src,loaded:x.complete,width:x.naturalWidth})))));});
  const doc=await page.evaluate(()=>({title:document.title,text:document.body.innerText,images:[...document.querySelectorAll('.photo img')].map(i=>({src:i.getAttribute('src'),width:i.naturalWidth,height:i.naturalHeight,caption:i.closest('figure').querySelector('figcaption').innerText})),items:document.querySelectorAll('.print-item').length,
   itemCells:[...document.querySelectorAll('.print-item')].map(row=>[...row.cells].map(cell=>cell.innerText)),
   total:[...document.querySelectorAll('.item-total')].map(el=>el.innerText),basis:[...document.querySelectorAll('h2.accounting + table tr')].map(row=>[...row.cells].map(cell=>cell.innerText))}));
  check('print doc number',doc.title,approved.request.doc);
  check('print item count',doc.items,approved.request.lines.length);
  check('print images include three',doc.images.length>=3,true);
  check('all contract photos printed',doc.images.length,approved.request.attachments.filter(a=>a.type?.startsWith('image/')).length);
  check('all fixture photos present',stored.every(photo=>doc.images.some(image=>image.src.includes(photo.id))),true);
  for(const [index,image] of doc.images.filter(image=>stored.some(item=>image.src.includes(item.id))).entries()){
   const photo=stored.find(item=>image.src.includes(item.id));check('image expected attachment '+index,!!photo,true);
   check('image private route '+index,image.src.includes('/payment-plan/api/attachment/')&&image.src.includes('preview=1'),true);
   check('image hash in caption '+index,image.caption.includes(photo.sha256),true);
   check('image dimensions '+index,[image.width,image.height],photo.size);
   const response=await page.request.get(new URL(image.src,base).href);check('authorized private image '+index,response.status(),200);
   check('private image bytes '+index,crypto.createHash('sha256').update(await response.body()).digest('hex'),photo.sha256);
  }
  check('print payee',doc.text.includes(approved.request.payee),true);
  check('print amount',doc.text.includes(approved.request.amount),true);
  check('print source bank',doc.text.includes(approved.request.accountingApproval.basis.source_account.number),true);
  check('print approved account',doc.text.includes(approved.request.accountingApproval.basis.lines[0].account_code),true);
  check('print long last item',doc.text.includes(approved.request.lines.at(-1).description[2]),true);
  check('item total once',doc.total.length,1);
  check('item total matches request',doc.total[0].includes(approved.request.amount),true);
  check('approval actor printed',doc.text.includes(String(approved.request.accountingApproval.actor_id)),true);
  check('approval timestamp printed',doc.text.includes(approved.request.accountingApproval.approved_at),true);
  for(const [index,item] of approved.request.lines.entries()){
   check('item '+(index+1)+' descriptions',doc.itemCells[index][1].replaceAll('\n',''),item.description.join(''));
   check('item '+(index+1)+' quantities and amounts',doc.itemCells[index].slice(2),[item.qty,item.unitPrice,item.total].map(String));
  }
  const basis=approved.request.accountingApproval.basis;
  const cells=doc.basis.map(row=>row[1]);
  check('basis approval identity',cells.slice(0,2),[String(approved.request.accountingApproval.actor_id),approved.request.accountingApproval.approved_at]);
  check('basis settlement fields',cells.slice(2,8),['base','vat','withholding','fees','gross','debit'].map(key=>String(basis.settlement[key])));
  check('all journal lines',doc.basis.slice(-basis.lines.length),basis.lines.map(line=>[line.account_code+' · '+line.position,line.amount+' '+basis.currency]));
  check('all attachment names',approved.request.attachments.every(a=>doc.text.includes(a.name)),true);
  const client=await page.context().newCDPSession(page);await client.send('Page.enable');
  const pdf=await client.send('Page.printToPDF',{printBackground:true,preferCSSPageSize:true,displayHeaderFooter:false});
  fs.writeFileSync(path.join(out,'u04-payment-print.pdf'),Buffer.from(pdf.data,'base64'));
  await page.screenshot({path:path.join(out,'u04-print-screen.png'),fullPage:true});
  report.request_id=approved.request.id;report.print=doc;report.photos=stored;report.pdf_size=Buffer.from(pdf.data,'base64').length;
  report.status='PASS';
 }catch(e){report.status='FAIL';report.error=e.stack;process.exitCode=1;}
 finally{fs.writeFileSync(path.join(out,'u04-print-browser.json'),JSON.stringify(report,null,2));await browser.close();}
 console.log(report.status+' '+report.checks.length+' checks');
})();