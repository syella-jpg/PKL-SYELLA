const assert=require('assert'),{create}=require('../upload/precheck-client.js');
const tick=()=>new Promise(r=>setTimeout(r,8));
(async()=>{
 const pending=[],states=[],warnings=[];
 const gate=create({delay:0,timeout:5000,request:(file,section,period,signal)=>new Promise(resolve=>pending.push({file,section,period,signal,resolve})),onState:(s,m)=>states.push([s,m]),onRejected:m=>warnings.push(m)});
 const file={};gate.schedule(file,'IB','2026-09');assert(gate.blocked());await tick();
 gate.schedule(file,'IB','2026-10');await tick();assert(pending[0].signal.aborted);
 pending[0].resolve({success:false,rejected:true,message:'stale'});await tick();assert.equal(warnings.length,0);assert(gate.blocked());
 pending[1].resolve({success:false,rejected:true,message:'UPLOAD DITOLAK! locked data'});await tick();assert.equal(warnings.length,1);assert.equal(gate.getState(),'rejected');assert(gate.blocked());
 gate.schedule(file,'IB','2026-10',true);await tick();pending[2].resolve({success:true});await tick();assert.equal(gate.getState(),'passed');assert(!gate.blocked());
 gate.schedule(file,'IB','2026-10');await tick();assert.equal(pending.length,3,'No duplicate checks for unchanged selection');
 gate.schedule({},'IB','2026-10');await tick();gate.reset();pending[3].resolve({success:false,rejected:true,message:'removed file'});await tick();assert.equal(warnings.length,1);assert.equal(gate.getState(),'idle');
 gate.schedule(file,'IB','2026-11');await tick();pending[4].resolve({success:false,rejected:false,message:'connection unavailable'});await tick();assert.equal(gate.getState(),'error');assert(gate.blocked());gate.reset();
 console.log('PASS precheck UI: waiting blocks Upload, rejection popup, retry, stale-response/abort handling, selection changes, same-selection dedupe and file removal. Mock requests only.');
})().catch(e=>{console.error(e);process.exitCode=1;});
