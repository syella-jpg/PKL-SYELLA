<?php
/** Accept historical names while new downloads use the requested names. */
function ibInputSheetAliases(string $name): array {
 $groups=[['IB FARMA','YFR - IBFM','YRU','IBFM'],['IB FOOD','IAP'],['GM IB','HRU']];
 foreach($groups as $aliases)if(in_array(strtoupper(trim($name)),$aliases,true))return $aliases;
 return [$name];
}
function getIbInputSheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,string $name): ?\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet {
 foreach(ibInputSheetAliases($name) as $alias)foreach($book->getWorksheetIterator() as $sheet)if(strtoupper(trim($sheet->getTitle()))===$alias)return $sheet;
 return null;
}
