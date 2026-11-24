"""Evaluate report-source metadata selection using synthetic SQLite rows only."""
import json, sqlite3, subprocess
php="require 'includes/upload_file_locks.php'; $out=[];foreach(['IB','MTM','MNJ','UNIT & Revenue','BIAYA','DAFTAR','LAUNCH','SERVICE LEVEL','BOD MNJ','BOD BI.Opr & BOD BI.SDM','BOD JML SDM','BOD KX Sell In','UPB BJ kx','UPB non BJ','UPB DISTR'] as $s)foreach(['2026-05','2026-06','2026-09','2026-11','2027-02'] as $p)$out[]=[$s,$p,uploadEffectivePeriodSql($s,$p)];echo json_encode($out);"
rows=json.loads(subprocess.check_output(['php','-r',php]).decode())
db=sqlite3.connect(':memory:')
db.execute('CREATE TABLE dummy(report_period TEXT)')
db.executemany('INSERT INTO dummy VALUES (?)',[('2025-12',),('2026-06',),('2026-10',)])
for section,period,rule in rows:
    where,order,types,params=rule
    values=db.execute('SELECT report_period FROM dummy WHERE '+where+' ORDER BY '+order+' LIMIT 1',params).fetchone()
    actual=values[0] if values else None
    if period.startswith('2027'): expected=None
    elif section in ['BOD JML SDM','BOD KX Sell In']: expected=period if period=='2026-06' else None
    elif section in ['IB','MTM','MNJ','UNIT & Revenue','BIAYA','SERVICE LEVEL','BOD MNJ','BOD BI.Opr & BOD BI.SDM','UPB non BJ','UPB DISTR']:
        expected='2026-06' if period in ['2026-05','2026-06'] else '2026-10'
    elif section=='UPB BJ kx': expected='2026-10'
    elif period=='2026-05': expected='2026-06' if section=='SERVICE LEVEL' else None
    elif period in ['2026-06','2026-09']: expected='2026-06'
    else: expected='2026-10'
    assert actual==expected,(section,period,actual,expected)
print('PASS 75 dummy checklist cases: October cumulative upload covers September, earlier snapshot does not hide later coverage, monthly isolation, annual sources, no cross-year leakage. No live DB or workbook opened.')
