"""Integration check using generated /tmp dummy workbooks only; no user files or DB."""
import json,os,subprocess,time
import uno
from com.sun.star.beans import PropertyValue

def prop(name,value):
 p=PropertyValue();p.Name=name;p.Value=value;return p
pipe='near_ed_dummy_'+str(os.getpid())
profile='/tmp/lo-near-ed-uno-'+str(os.getpid())
process=subprocess.Popen(['libreoffice','-env:UserInstallation=file://'+profile,'--headless','--norestore','--nodefault','--accept=pipe,name='+pipe+';urp;StarOffice.ServiceManager'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
doc=None
try:
 local=uno.getComponentContext();resolver=local.ServiceManager.createInstanceWithContext('com.sun.star.bridge.UnoUrlResolver',local)
 for attempt in range(100):
  try:context=resolver.resolve('uno:pipe,name='+pipe+';urp;StarOffice.ComponentContext');break
  except Exception:
   if attempt==99:raise
   time.sleep(.1)
 desktop=context.ServiceManager.createInstanceWithContext('com.sun.star.frame.Desktop',context)
 doc=desktop.loadComponentFromURL(uno.systemPathToFileUrl('/tmp/dummy-near-ed-regression.xlsx'),'_blank',0,(prop('Hidden',True),prop('UpdateDocMode',3)))
 doc.calculateAll();top=doc.Sheets.getByName('TOP 10 NEAR ED')
 with open('/tmp/dummy-near-ed-expected.json') as f:expected=json.load(f)
 groups=['GB '+str(i) for i in range(1,7)]+['ETHICAL']
 for group_index,group in enumerate(groups):
  first=5+group_index*15
  for bucket in range(4):
   actual=[top.getCellByPosition(bucket+1,first+i).String for i in range(10)]
   want=expected[group][bucket]+['']*(10-len(expected[group][bucket]))
   assert actual==want,(group,bucket,actual,want)
 source=doc.Sheets.getByName('GB 1');summary=doc.Sheets.getByName('NEAR ED');old=summary.getCellRangeByName('E5').Value
 source.unprotect('testing');source.Rows.insertByIndex(19,1)
 for col,value in [('B', 'Inserted largest dummy'),('K',123456789),('I',123456789),('F',1),('J',1)]:
  cell=source.getCellRangeByName(col+'20')
  if isinstance(value,str):cell.String=value
  else:cell.Value=value
 doc.calculateAll()
 assert top.getCellRangeByName('B6').String=='Inserted largest dummy','Inserted row omitted from TOP 10'
 assert summary.getCellRangeByName('E5').Value==old+123456789,'Summary omitted inserted row'
 source.Rows.removeByIndex(19,1);source.Rows.removeByIndex(5,1);doc.calculateAll()
 assert top.getCellRangeByName('B6').String=='GB 1 Dummy 2','First-row deletion breaks ranking/tie ordering'
 source.Rows.removeByIndex(5,13);doc.calculateAll()
 assert top.getCellRangeByName('B6').String=='','Deleting all product rows should clear TOP 10'
 assert summary.getCellRangeByName('E5').Value==0,'Deleting all products should yield zero summary'
 assert top.getCellRangeByName('B96').String==expected['ETHICAL'][0][0],'Other groups changed'
 print('PASS Calc integration: all seven groups/four independent rankings, stable ties, fewer than ten products, blank names, insertion at TOTAL, first/all-product deletion and summary recalculation. Dummy workbook only.')
finally:
 if doc:doc.close(True)
 process.terminate()
 try:process.wait(timeout=10)
 except subprocess.TimeoutExpired:process.kill()
