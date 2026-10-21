"""Build the one-analysis slide template from the supplied corporate ODP."""
import sys
from pathlib import Path
from zipfile import ZipFile
from lxml import etree as E
source = Path(sys.argv[1])
target = Path(__file__).resolve().parents[1] / 'dashboard/assets/tm-bod/analysis-template.odp'
ns = {k: 'urn:oasis:names:tc:opendocument:xmlns:' + v + ':1.0' for k,v in {'office':'office','draw':'drawing','text':'text','style':'style','svg':'svg-compatible','fo':'xsl-fo-compatible'}.items()}
def q(prefix, name): return '{' + ns[prefix] + '}' + name
with ZipFile(source) as original:
    root = E.fromstring(original.read('content.xml'))
    presentation = root.find('.//office:presentation', ns)
    page = presentation.findall('draw:page', ns)[5]
    for child in list(page): page.remove(child)
    for child in list(presentation): presentation.remove(child)
    presentation.append(page)
    page.set(q('draw','name'), 'analysis-1')
    styles = root.find('office:automatic-styles', ns)
    for name, size, bold in [('AnalysisTitle','24pt',True),('AnalysisSubtitle','18pt',True),('AnalysisBody','18pt',False)]:
        style=E.SubElement(styles,q('style','style'),{q('style','name'):name,q('style','family'):'paragraph'})
        E.SubElement(style,q('style','paragraph-properties'),{q('fo','margin-top'):'0cm',q('fo','margin-bottom'):'0cm',q('fo','line-height'):'120%'})
        E.SubElement(style,q('style','text-properties'),{q('fo','font-family'):'Arial',q('fo','font-size'):size,q('fo','color'):'#111111',q('fo','font-weight'):'bold' if bold else 'normal'})
    style=E.SubElement(styles,q('style','style'),{q('style','name'):'AnalysisFrame',q('style','family'):'graphic'})
    E.SubElement(style,q('style','graphic-properties'),{q('draw','fill'):'none',q('draw','stroke'):'none',q('fo','padding'):'0cm',q('draw','auto-grow-height'):'false'})
    for name, x,y,w,h in [('Title',190,18,710,70),('Subtitle',34,96,820,54),('Body',34,158,820,310)]:
        frame=E.SubElement(page,q('draw','frame'),{q('draw','name'):name,q('draw','style-name'):'AnalysisFrame',**{q('svg',key):str(value*25.4/960)+'cm' for key,value in [('x',x),('y',y),('width',w),('height',h)]}})
        box=E.SubElement(frame,q('draw','text-box'))
        E.SubElement(box,q('text','p'),{q('text','style-name'):'Analysis'+name})
    # ODF keeps the original masters, logos, background images, and manifest.
    with ZipFile(target,'w') as out:
        for info in original.infolist():
            out.writestr(info, E.tostring(root,xml_declaration=True,encoding='UTF-8') if info.filename=='content.xml' else original.read(info.filename))
print(target)
