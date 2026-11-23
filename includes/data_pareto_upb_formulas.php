<?php
require_once __DIR__.'/upb_bj_template_history.php';

/** Resize local vertical SUM totals to the current header/detail/total boundaries. */
function refreshDataParetoUpbSums(DOMElement $table): void {
 $rows=upbBjCells($table);$headers=[];$previousTotals=[];
 $colIndex=static function(string $letters): int {
  $value=0;foreach(str_split(strtoupper($letters)) as $letter)$value=$value*26+ord($letter)-64;return $value;
 };
 foreach($rows as $r=>$cells){
  foreach($cells as $c=>$cell){
   $label=strtoupper(trim(preg_replace('/\s+/u',' ',$cell->textContent)));
   if(preg_match('/^(?:NAMA\s+(?:PRODUK|BARANG)|KODE\s+(?:PRODUK|BARANG)|NO\.?|NOMOR)$/',$label))$headers[$c]=$r;
  }
  foreach($cells as $c=>$cell){
   $formula=$cell->getAttributeNS(UPB_T,'formula');if($formula==='')continue;
   $labelColumn=null;
   foreach($cells as $lc=>$labelCell){
    if($lc>=$c)break;
    $label=strtoupper(trim(preg_replace('/\s+/u',' ',$labelCell->textContent)));
    if(preg_match('/^(?:(?:SUB\s*|GRAND\s*)?TOTAL|JUMLAH)(?:\s|$)/',$label))$labelColumn=$lc;
   }
   if($labelColumn===null)continue;
   $header=0;foreach($headers as $hc=>$hr)if($hc<=$c&&$hr<$r)$header=max($header,$hr);
   $changed=false;
   $updated=preg_replace_callback('/SUM\(\[\.?(\$?)([A-Z]+)(\$?)(\d+):\.?(\$?)([A-Z]+)(\$?)(\d+)\]\)/i',
    static function($m)use($r,$c,$header,$previousTotals,$colIndex,&$changed){
     if(strtoupper($m[2])!==strtoupper($m[6])||$colIndex($m[2])!==$c)return $m[0];
     $start=(int)$m[4];
     if($start>=$r)return $m[0]; // A forward-looking summary is not a detail subtotal.
     $start=max($header>0?$header+1:$start,($previousTotals[$c]??0)+1);
     $end=$r-1;if($end<$start)return $m[0];
     $changed=true;
     return 'SUM([.'.$m[1].$m[2].$m[3].$start.':.'.$m[5].$m[6].$m[7].$end.'])';
    },$formula);
   if($changed){
    $cell->setAttributeNS(UPB_T,'table:formula',$updated);
    // Refresh cached display from saved numeric cells without evaluating user formulas.
    if(preg_match('/^of:=SUM\(\[\.\$?([A-Z]+)\$?(\d+):\.\$?\1\$?(\d+)\]\)$/i',$updated,$m)){
     $sum=0;foreach($rows as $sr=>$source)if($sr>=(int)$m[2]&&$sr<=(int)$m[3]){
      $value=($source[$c]??null)?->getAttributeNS(UPB_O,'value');if($value!==null&&is_numeric($value))$sum+=(float)$value;
     }
     upbBjSetFormula($cell,substr($updated,4),$sum);
    }
    $previousTotals[$c]=$r;
   }
  }
 }
}

/** Also fix summaries above the data and SUM denominators inside Pareto percentages. */
function refreshDataParetoDetailRanges(DOMElement $table): void {
 $rows=upbBjCells($table);$blocks=[];
 $index=static function(string $letters): int {
  $n=0;foreach(str_split(strtoupper($letters)) as $letter)$n=$n*26+ord($letter)-64;return $n;
 };
 foreach($rows as $r=>$cells)foreach($cells as $c=>$cell){
  $label=strtoupper(trim(preg_replace('/\s+/u',' ',$cell->textContent)));
  if(preg_match('/^(?:NAMA\s+(?:PRODUK|BARANG)|PRODUCT\s+NAME)$/',$label))$blocks[]=['header'=>$r,'name'=>$c];
 }
 foreach($blocks as &$block){
  $block['right']=PHP_INT_MAX;$block['end']=PHP_INT_MAX;
  foreach($blocks as $next){
   if($next['header']===$block['header']&&$next['name']>$block['name'])$block['right']=min($block['right'],$next['name']-1);
   if($next['header']>$block['header']&&$next['name']===$block['name'])$block['end']=min($block['end'],$next['header']);
  }
  $block['last']=$block['header'];
  foreach($rows as $r=>$cells){
   if($r<=$block['header']||$r>=$block['end'])continue;
   $name=$cells[$block['name']]??null;if(!$name)continue;
   $text=trim($name->textContent);$label=strtoupper(preg_replace('/\s+/u',' ',$text));
   if(preg_match('/^(?:(?:SUB\s*|GRAND\s*)?TOTAL|JUMLAH|BERDASARKAN|DATA PARETO)(?:\s|$)/',$label))continue;
   if($text!==''&&$text!=='0')$block['last']=$r;
  }
 }unset($block);
 $sumCache=[];
 $pattern='/SUM\(\[\.?(\$?)([A-Z]+)(\$?)(\d+):\.?(\$?)([A-Z]+)(\$?)(\d+)\]\)/i';
 foreach($rows as $r=>$cells)foreach($cells as $c=>$cell){
  $formula=$cell->getAttributeNS(UPB_T,'formula');if($formula==='')continue;
  $changed=false;
  $formula=preg_replace_callback($pattern,static function($m)use($blocks,$index,&$changed){
   if(strtoupper($m[2])!==strtoupper($m[6]))return $m[0];
   $col=$index($m[2]);$start=(int)$m[4];$block=null;
   foreach($blocks as $candidate){
    if($col<$candidate['name']||$col>$candidate['right']||$start<=$candidate['header']||$start>=$candidate['end'])continue;
    if($block===null||$candidate['header']>$block['header'])$block=$candidate;
   }
   if($block===null||$block['last']<=$block['header'])return $m[0];
   // Only whole-detail ranges; retain intentional running totals and subgroup sums.
   if($start!==$block['header']+1)return $m[0];
   $changed=true;
   return 'SUM([.'.$m[1].$m[2].$m[3].$start.':.'.$m[5].$m[6].$m[7].$block['last'].'])';
  },$formula);
  if(!$changed)continue;
  $cell->setAttributeNS(UPB_T,'table:formula',$formula);
  $sumRange=static function(string $col,int $first,int $last)use($rows,$index,&$sumCache):float{
   $key=$col.':'.$first.':'.$last;if(isset($sumCache[$key]))return $sumCache[$key];
   $sum=0;$c=$index($col);
   foreach($rows as $r=>$row)if($r>=$first&&$r<=$last){
    $label='';foreach($row as $lc=>$labelCell){if($lc>=$c)break;$label.=' '.$labelCell->textContent;}
    if(preg_match('/\b(?:(?:SUB\s*|GRAND\s*)?TOTAL|JUMLAH)\b/i',$label))continue;
    $value=($row[$c]??null)?->getAttributeNS(UPB_O,'value');
    if($value!==null&&is_numeric($value))$sum+=(float)$value;
   }return $sumCache[$key]=$sum;
  };
  if(preg_match('/^of:=SUM\(\[\.\$?([A-Z]+)\$?(\d+):\.\$?\1\$?(\d+)\]\)$/i',$formula,$m)){
   upbBjSetFormula($cell,substr($formula,4),$sumRange($m[1],(int)$m[2],(int)$m[3]));
  }elseif(preg_match('/^of:=\[\.\$?([A-Z]+)\$?(\d+)\]\/SUM\(\[\.\$?([A-Z]+)\$?(\d+):\.\$?\3\$?(\d+)\]\)$/i',$formula,$m)){
   $numerator=($rows[(int)$m[2]][$index($m[1])]??null)?->getAttributeNS(UPB_O,'value');
   $denominator=$sumRange($m[3],(int)$m[4],(int)$m[5]);
   if(is_numeric($numerator)&&$denominator!=0)upbBjSetFormula($cell,substr($formula,4),(float)$numerator/$denominator);
  }
 }
}
