<?php
// Run the access gate in a child process; any continuation would reach the sentinel.
$gate=realpath(__DIR__.'/../includes/report_access.php');
$code='$_SERVER["REQUEST_METHOD"]="OPTIONS"; $_SERVER["HTTP_ORIGIN"]="null"; require '.var_export($gate,true).'; register_shutdown_function(function(){echo "STATUS=".http_response_code();}); enforceReportAccess("report_input_template"); echo "UNEXPECTED_TEMPLATE_WORK";';
$command=escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code);
exec($command,$output,$status);
if($status!==0||implode("\n",$output)!=='STATUS=204')throw new RuntimeException('Preflight did not terminate before database/template work: '.implode("\n",$output));
echo "PASS OPTIONS exits with HTTP 204 before database authentication or template generation. CLI only, no HTTP or DB.\n";
