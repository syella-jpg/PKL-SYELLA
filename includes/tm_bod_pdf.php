<?php
/** Convert the generated presentation so PDF uses exactly the same slides. */
function tmBodPresentationPdf(string $presentation): string {
    if(!function_exists('proc_open')||!is_executable('/usr/bin/libreoffice'))throw new RuntimeException('Konversi PDF belum tersedia di server. Pasang LibreOffice atau pilih Presentasi (ODP).');
    $dir=sys_get_temp_dir().'/tm-bod-pdf-'.bin2hex(random_bytes(12));
    if(!mkdir($dir,0700))throw new RuntimeException('Folder konversi PDF tidak dapat dibuat.');
    $process=null;$pipes=[];
    try{
        if(!copy($presentation,$dir.'/presentation.odp'))throw new RuntimeException('Presentasi tidak dapat disiapkan untuk PDF.');
        $process=proc_open(['/usr/bin/libreoffice','-env:UserInstallation=file://'.$dir.'/profile','--headless','--convert-to','pdf:impress_pdf_Export','--outdir',$dir,$dir.'/presentation.odp'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Konverter PDF tidak dapat dijalankan.');
        fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
        $start=microtime(true);$exit=-1;
        while(true){
            stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);$state=proc_get_status($process);
            if(!$state['running']){$exit=$state['exitcode'];break;}
            if(microtime(true)-$start>120){proc_terminate($process,9);throw new RuntimeException('Konversi PDF terlalu lama. Coba kembali atau pilih Presentasi (ODP).');}
            usleep(100000);
        }
        fclose($pipes[1]);fclose($pipes[2]);$pipes=[];proc_close($process);$process=null;
        $path=$dir.'/presentation.pdf';
        if($exit!==0||!is_file($path))throw new RuntimeException('Konversi PDF gagal. Coba kembali atau pilih Presentasi (ODP).');
        $bytes=file_get_contents($path);
        if($bytes===false||!str_starts_with($bytes,'%PDF-'))throw new RuntimeException('Hasil konversi PDF tidak valid.');
        return $bytes;
    }finally{
        if(is_resource($process)){proc_terminate($process,9);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($process);}
        $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file){if($file->isDir())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($dir);
    }
}
