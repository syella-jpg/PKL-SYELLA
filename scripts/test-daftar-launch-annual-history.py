"""Exercise the actual PHP selection queries using only an in-memory synthetic DB."""
import re
import sqlite3
from pathlib import Path

db = sqlite3.connect(':memory:')
for table, filename in [('daftar', 'daftar_input_store.php'), ('launch_input', 'launch_input_store.php')]:
    source = Path('includes', filename).read_text()
    sql = re.search(r"prepare\('?(SELECT report_period[^']+?)'\)", source)
    if not sql:
        raise AssertionError('Selection query not found: ' + table)
    query = sql.group(1)
    db.execute('CREATE TABLE `' + table + '` (report_period TEXT PRIMARY KEY, filename TEXT, base64 TEXT)')
    def select(period):
        row = db.execute(query, (period[:4] + '-01', period)).fetchone()
        return row[0] if row else None
    db.execute('INSERT INTO `' + table + '` VALUES (?,?,?)', ('2025-12', 'synthetic', 'fake'))
    assert select('2026-01') is None
    db.execute('INSERT INTO `' + table + '` VALUES (?,?,?)', ('2026-02', 'synthetic', 'first'))
    assert select('2026-01') is None
    for month in range(2, 13):
        assert select('2026-%02d' % month) == '2026-02'
    db.execute('INSERT INTO `' + table + '` VALUES (?,?,?)', ('2026-07', 'synthetic', 'updated'))
    assert select('2026-06') == '2026-02'
    for month in range(7, 13):
        assert select('2026-%02d' % month) == '2026-07'
    assert select('2027-01') is None
    db.execute('UPDATE `' + table + '` SET base64=? WHERE report_period=?', ('replacement', '2026-07'))
    assert db.execute(query, ('2026-01', '2026-12')).fetchone()[-1] == 'replacement'
    print('PASS synthetic ' + table + ': carry forward through December, updates take effect from upload month, earlier history/year isolation retained.')
