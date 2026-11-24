<?php
require_once __DIR__.'/tm_bod_analysis_map.php';

/** Split only explicitly labelled sections; never infer destinations from prose. */
function tmBodSplitAchievementAnalysis(array $template,string $text): array {
    $text=str_replace(["\r\n","\r"],"\n",$text);
    preg_match_all('/^\h*SELL\h+(OUT|IN)\h*:\h*/mi',$text,$matches,PREG_OFFSET_CAPTURE);
    if(count($matches[0])!==2||strtoupper($matches[1][0][0])===strtoupper($matches[1][1][0])||trim(substr($text,0,$matches[0][0][1]))!==''){
        throw new InvalidArgumentException('Pisahkan analisis dengan judul SELL OUT: dan SELL IN: pada baris masing-masing.');
    }
    $parts=[];
    foreach($matches[0] as $i=>$match){
        $section='SELL '.strtoupper($matches[1][$i][0]);
        $start=$match[1]+strlen($match[0]);$end=$matches[0][$i+1][1]??strlen($text);
        $body=trim(substr($text,$start,$end-$start));
        if($body==='')throw new InvalidArgumentException('Isi analisis bagian '.$section.' terlebih dahulu.');
        // Crossed destinations explicitly requested and confirmed by the user.
        $destination=$section==='SELL OUT'?'KX Revenue Selling In':'KX Revenue Selling Out';
        $ids=tmBodExactAnalysisTargetIds($template,$destination);
        $parts[]=['split_section'=>$section,'destination'=>$destination,'destination_ids'=>$ids,'analysis_text'=>$section.":\n".$body];
    }
    return $parts;
}

function tmBodSplitFoodAnalysis(array $template,string $text): array {
    $text=str_replace(["\r\n","\r"],"\n",$text);
    preg_match_all('/^\h*GB\h*(5|6)\h*:\h*/mi',$text,$matches,PREG_OFFSET_CAPTURE);
    if(count($matches[0])!==2||$matches[1][0][0]===$matches[1][1][0]||trim(substr($text,0,$matches[0][0][1]))!==''){
        throw new InvalidArgumentException('Pisahkan analisis Food dengan judul GB 5: dan GB 6: pada baris masing-masing.');
    }
    $parts=[];
    foreach($matches[0] as $i=>$match){
        $section='GB '.$matches[1][$i][0];$start=$match[1]+strlen($match[0]);$end=$matches[0][$i+1][1]??strlen($text);
        $body=trim(substr($text,$start,$end-$start));
        if($body==='')throw new InvalidArgumentException('Isi analisis bagian '.$section.' terlebih dahulu.');
        $destination=$section.' Revenue (Rp)';
        $parts[]=['split_section'=>$section,'destination'=>$destination,'destination_ids'=>tmBodExactAnalysisTargetIds($template,$destination),'analysis_text'=>$section.":\n".$body];
    }
    return $parts;
}

/** Expand one stored split transfer into independently targeted incoming notes. */
function tmBodExpandSplitTransfer(array $row): array {
    $value=json_decode($row['analysis_text']??'',true);
    if(!is_array($value)||($value['tm_bod_split']??null)!==1||!is_array($value['parts']??null))return [$row];
    $result=[];
    foreach($value['parts'] as $part){
        if(!is_array($part)||!in_array($part['split_section']??null,['SELL OUT','SELL IN','GB 5','GB 6'],true)||!is_array($part['destination_ids']??null)||!is_string($part['analysis_text']??null))continue;
        $result[]=array_merge($row,$part);
    }
    return $result;
}
