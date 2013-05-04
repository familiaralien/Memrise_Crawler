<?php
function outputProgress($message, $newline=TRUE){
	global $status, $global_i;
	if ($newline){$linebreak="<br> \n";}
	else{$linebreak="";}
	$status=$status.$message.$linebreak;
	$global_i=$global_i+1;
	echo "<span style='position:absolute;z-index:$global_i;background:#FFF;'> \n" . $status. " \n"."</span>";
    myFlush();
}

function myFlush(){
    echo(" \n");
    if (@ob_get_contents()) {
        @ob_end_flush();
    }
    flush();
}
?>