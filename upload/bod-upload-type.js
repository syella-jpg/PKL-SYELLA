(function(root){
  'use strict';
  root.initBodUploadType=function({section,period,isBusy}){
    const wrap=document.getElementById('bodUploadTypeWrap');
    const type=document.getElementById('bodUploadType'),help=document.getElementById('bodUploadTypeHelp');
    function update(){
      const visible=section.value.trim()==='BOD KX Sell In';
      wrap.hidden=!visible;type.disabled=!visible||isBusy();type.required=visible;
      help.textContent=type.value==='rod'
        ? 'Unggah raw ROD melalui File Dokumen. Tahun mengikuti Periode Laporan; target Januari–Desember otomatis masuk ke sheet ROD.'
        : 'Unggah satu laporan penjualan melalui File Dokumen. Bulan dan tahun harus sesuai Periode Laporan; Sub Total otomatis masuk ke sheet PENJUALAN.';
    }
    root.updateBodUploadType=update;
    section.addEventListener('input',update);section.addEventListener('change',update);
    period.addEventListener('change',update);type.addEventListener('change',update);update();
  };
})(window);
