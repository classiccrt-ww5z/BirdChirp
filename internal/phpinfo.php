<?php
require_once "header.php";
?>

<div class="box">
<div class="box-inner" style="padding:14px;">
  <p><b>PHP Version:</b> <?=phpversion()?></p>
  <p><b>Loaded Extensions:</b> <?=implode(', ', get_loaded_extensions())?></p>
</div></div>

<?php
ob_start();
phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES | INFO_ENVIRONMENT | INFO_VARIABLES);
$phpinfo = ob_get_clean();
$phpinfo = preg_replace('/^.*?<body>/is', '', $phpinfo);
$phpinfo = preg_replace('/<\/body>.*$/is', '', $phpinfo);
$phpinfo = preg_replace('/style=".*?"/is', '', $phpinfo);
$phpinfo = str_replace('<table', '<table style="margin-bottom:16px;"', $phpinfo);
$phpinfo = str_replace('<td class="e">', '<td style="font-weight:600;width:200px;background:#fafafa;padding:4px 8px;">', $phpinfo);
$phpinfo = str_replace('<td class="v">', '<td style="padding:4px 8px;">', $phpinfo);
$phpinfo = str_replace('<td>', '<td style="padding:4px 8px;">', $phpinfo);
$phpinfo = str_replace('<tr class="h">', '<tr style="background:#f0f0f0;font-weight:700;">', $phpinfo);
echo $phpinfo;
?>

<?php require_once "footer.php"; ?>