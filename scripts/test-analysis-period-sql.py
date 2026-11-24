"""Test actual CRUD SQL against a synthetic SQLite database in memory."""
import re, sqlite3, subprocess, json
code=open('dashboard/analysis_api.php').read()
queries=re.findall(r"prepare\('([^']+)'\)",code)
insert=next(q for q in queries if q.startswith('INSERT INTO report_analysis '))
update=next(q for q in queries if q.startswith('UPDATE report_analysis '))
delete=next(q for q in queries if q.startswith('DELETE FROM report_analysis '))
db=sqlite3.connect(':memory:')
db.execute('CREATE TABLE report_analysis(id TEXT PRIMARY KEY,report_name TEXT,tab_name TEXT,analysis_text TEXT,analysis_html TEXT,created_by TEXT,created_role TEXT,original_created_label TEXT,report_period TEXT,updated_by TEXT,updated_role TEXT,updated_at TEXT,created_at TEXT)')
for ident,period in [('sep','2026-09'),('oct','2026-10'),('next-year','2027-10'),('unassigned',None)]:
 db.execute(insert,(ident,'Dummy Report','Dummy Tab',ident+' dummy','<b>dummy</b>','dummy','dummy',None,period))
rows=db.execute('SELECT id FROM report_analysis WHERE report_period IS NOT NULL ORDER BY id').fetchall()
assert len(rows)==3
assert db.execute('SELECT analysis_text FROM report_analysis WHERE report_name=? AND tab_name=? AND report_period=?',('Dummy Report','Dummy Tab','2026-09')).fetchone()[0]=='sep dummy'
db.execute(update,('attempt wrong month','',None,None,'oct','Dummy Report','Dummy Tab','2026-09'))
assert db.execute('SELECT analysis_text FROM report_analysis WHERE id=?',('oct',)).fetchone()[0]=='oct dummy'
db.execute(delete,('oct','Dummy Report','Dummy Tab','2026-09'))
assert db.execute('SELECT COUNT(*) FROM report_analysis WHERE id=?',('oct',)).fetchone()[0]==1
db.execute(delete,('sep','Dummy Report','Dummy Tab','2026-09'))
assert db.execute('SELECT COUNT(*) FROM report_analysis').fetchone()[0]==3
assert db.execute('SELECT COUNT(*) FROM report_analysis WHERE report_period IS NULL').fetchone()[0]==1
php="require 'includes/analysis_locks.php'; echo json_encode([analysisLockScope('Dummy','Tab','2026-09'),analysisLockScope('Dummy','Tab','2026-10'),analysisLockScope('Dummy','Tab','2027-10')]);"
scopes=json.loads(subprocess.check_output(['php','-r',php]).decode())
assert len(set(scopes))==3
transfer=open('dashboard/tm_bod_analysis_api.php').read()
assert 'FROM report_analysis WHERE id=? AND report_period=?' in transfer
print('PASS dummy analysis SQL: separate September/October/year, scoped edits/deletes, legacy notes retained but unassigned, independent lock scopes and TM BOD period guard. No live DB accessed.')
