// Inspect generated Chrome PDF using browser's installed PDF viewer and sampled rendered pages.
const fs=require('fs'),path=require('path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.R1_PLAYWRIGHT_MODULE);
const out=process.argv[2],prefix=process.argv[3]||'u04',pdf=path.join(out,process.argv[3]?prefix+'.pdf':'u04-payment-print.pdf');
const data=fs.readFileSync(pdf),source=data.toString('latin1');
const report={status:'RUNNING',checks:[],boundary:'Chrome printToPDF document and PDF viewer render; PDF object count is structural, not text extraction.'};
const check=(name,a,b)=>{assert.deepEqual(a,b,name);report.checks.push({name,actual:a});};
(async()=>{const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 try{
  check('PDF signature',source.slice(0,5),'%PDF-');
  const pages=(source.match(/\/Type\s*\/Page\b/g)||[]).length;
   check('PDF pages present',pages>0,true);report.pages=pages;
   if(prefix==='u04')check('embedded image objects >=3',(source.match(/\/Subtype\s*\/Image\b/g)||[]).length>=3,true);
  // Isolated loopback PDF response: no operational filesystem exposure.
  const page=await browser.newPage({viewport:{width:1000,height:1200}});
  await page.route('http://127.0.0.1:8899/print.pdf',route=>route.fulfill({status:200,contentType:'application/pdf',body:data}));
  const response=await page.goto('http://127.0.0.1:8899/print.pdf');check('viewer response',response.status(),200);
  await page.waitForTimeout(1500);
  report.viewer={url:page.url(),title:await page.title(),body:(await page.locator('body').textContent()).slice(0,140)};
  await page.screenshot({path:path.join(out,prefix+'-pdf-viewer-first.png')});
  await page.goto('http://127.0.0.1:8899/print.pdf#page='+pages);
  await page.waitForTimeout(1800);
  await page.mouse.click(316,29);
  await page.keyboard.press('ControlOrMeta+A');
  await page.keyboard.type(String(pages));
  await page.keyboard.press('Enter');
  await page.waitForTimeout(1100);
  await page.screenshot({path:path.join(out,prefix+'-pdf-viewer-last.png')});
  report.viewer.lastPageNavigated=true;report.viewer.lastUrl=page.url();
  await page.mouse.click(316,29);await page.keyboard.press('ControlOrMeta+A');await page.keyboard.type('8');await page.keyboard.press('Enter');await page.waitForTimeout(900);
  await page.screenshot({path:path.join(out,prefix+'-pdf-viewer-middle.png')});
  report.renderedPages=[];
  for(let number=1;number<=pages;number++){
   await page.mouse.click(316,29);await page.keyboard.press('ControlOrMeta+A');await page.keyboard.type(String(number));await page.keyboard.press('Enter');await page.waitForTimeout(650);
    await page.screenshot({path:path.join(out,`${prefix}-pdf-page-${String(number).padStart(2,'0')}.png`)});
   report.renderedPages.push(number);
  }
  await page.keyboard.press('ControlOrMeta+f');await page.keyboard.type('Total rincian item');await page.waitForTimeout(1200);
  await page.screenshot({path:path.join(out,prefix+'-pdf-total-search.png')});
  report.status='PASS';
 }catch(e){report.status='FAIL';report.error=e.stack;process.exitCode=1;}
  finally{fs.writeFileSync(path.join(out,prefix+'-pdf-inspection.json'),JSON.stringify(report,null,2));await browser.close();}
 console.log(report.status+' '+report.checks.length+' checks; PDF pages '+report.pages);
})();