const fs=require('fs'),vm=require('vm'),assert=require('assert'),X=require('/tmp/target-sheetjs.js'),detect=require('../upload/file-detection.js');
const native=fs.readFileSync('/tmp/service-level-2027-test.ods').toString('base64');let request;
const c={window:{XLSX:X},location:{protocol:'file:',hostname:''},document:{},URL,URLSearchParams,TextEncoder,TextDecoder,Uint8Array,fetch:async url=>{request=url;return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({success:true,base64:native})};}};
vm.runInNewContext(fs.readFileSync('dashboard/report-templates.js','utf8'),c);
(async()=>{
 const book={SheetNames:['LAPORAN','SERVICE LEVEL'],Sheets:{LAPORAN:X.utils.aoa_to_sheet([['Keep report']]),'SERVICE LEVEL':X.utils.aoa_to_sheet([])}};
 await c.window.replaceServiceLevelSheet(book,'2027-03');assert(request.includes('service_level_input_api.php?period=2027-03'));
 assert.equal(book.Sheets['SERVICE LEVEL'].A1.v,'TH. 2027');assert.equal(book.Sheets['SERVICE LEVEL'].A30.v,'TH. 2026');
 const result=detect(book,'Template_SERVICE LEVEL_2027-03 (2).ods');assert.equal(result.choices[0].section,'SERVICE LEVEL');assert.equal(result.year,2027);assert.equal(result.month,3);
 const output=c.window.writeMtmOds(book),zip=X.CFB.read(new Uint8Array(output),{type:'array'}),original=X.CFB.read(native,{type:'base64'});
 const text=(archive,name)=>new TextDecoder().decode(new Uint8Array(X.CFB.find(archive,name).content));
 const table=xml=>xml.match(/<table:table\b[^>]*table:name="SERVICE LEVEL"[^>]*>[\s\S]*?<\/table:table>/)[0];
 assert.equal(table(text(zip,'content.xml')).replace(/SERVICELEVEL_/g,''),table(text(original,'content.xml')));
 const read=X.read(output,{type:'array',cellFormula:true});assert.equal(read.Sheets.LAPORAN.A1.v,'Keep report');assert.equal(read.Sheets['SERVICE LEVEL'].A30.v,'TH. 2026');
 assert.equal(book.SheetNames.filter(n=>n==='SERVICE LEVEL').length,1);
 console.log('PASS: 2027 API loading, automatic section/period recognition, exact native SERVICE LEVEL table preserved in report, other sheets intact.');
})().catch(e=>{console.error(e);process.exit(1)});
