"""Render administrator-uploaded PowerPoint designs using isolated LibreOffice profile."""
import sys, subprocess, tempfile, pathlib, re, zipfile
source=pathlib.Path(sys.argv[1]); output=pathlib.Path(sys.argv[2]);output.mkdir(parents=True,exist_ok=True)
if source.suffix.lower() not in ['.ppt','.pptx']:raise ValueError('Gunakan file PPT atau PPTX.')
if source.suffix.lower()=='.ppt' and source.read_bytes()[:8]!=bytes.fromhex('D0CF11E0A1B11AE1'):raise ValueError('File PPT tidak valid.')
if source.suffix.lower()=='.pptx':
 with zipfile.ZipFile(str(source)) as book:
  if len(book.infolist())>10000 or sum(i.file_size for i in book.infolist())>200*1024*1024:raise ValueError('Isi file PowerPoint terlalu besar.')
  if 'ppt/presentation.xml' not in book.namelist():raise ValueError('PPTX tidak valid.')
  for name in book.namelist():
   if 'vbaproject' in name.lower() or '/embeddings/' in name.lower():raise ValueError('Template tidak boleh mengandung macro atau objek tertanam.')
with tempfile.TemporaryDirectory(prefix='tm-bod-profile-') as profile:
 result=subprocess.run(['/usr/bin/libreoffice','-env:UserInstallation='+pathlib.Path(profile).as_uri(),'--headless','--convert-to','pdf','--outdir',str(output),str(source)],stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=90)
 pdf=output/(source.stem+'.pdf')
 if result.returncode or not pdf.is_file():raise ValueError('PowerPoint gagal dikonversi oleh LibreOffice.')
 info=subprocess.run(['/usr/bin/pdfinfo',str(pdf)],stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=10)
 pages=re.search(r'^Pages:\s+(\d+)',info.stdout.decode('utf-8','replace'),re.M)
 if not pages or not 1<=int(pages.group(1))<=12:raise ValueError('Template desain maksimal 12 slide. Unggah hanya slide desain yang diperlukan.')
 subprocess.run(['/usr/bin/pdftoppm','-png','-r','72','-scale-to-x','960','-scale-to-y','540',str(pdf),str(output/'page')],stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=60,check=True)
 print('OK')
