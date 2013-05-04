<?php
function beautify($input){	#text auxiliary
	$output = trim($input);
	$output = str_replace("'", '"', $output);	
	return $output;
}

function getValNam($input,$field){ #text auxiliary
	$output = explode($field.'="', $input);
	$output = explode('"',$output[1]);
	return $output[0];
}

function getNumber($string,$one){
	$number=filter_var($string, FILTER_SANITIZE_NUMBER_INT);
	if(strpos($string,$one)!=FALSE){$number=1;}
	return $number;
}

function timeToText($seconds){
	if($seconds/60<=1){ #1 minute or less
		$timestr="now";
		$num=1;
	}
	elseif($seconds/3600<1&& $seconds>60){ #minutes (more than one minute less than 60 minutes)
		$num=round($seconds/60);
		$timestr=$num." minute";
	}
	elseif($seconds/86400<1&&$seconds/3600>=1){ #hours (more than one hour less than 24 hours)
		$num=round($seconds/3600);
		$timestr=$num." hour";
	}
	else{ #days
		$num=round($seconds/86400);
		$timestr=$num." day";
	}
	if($num>1){	#adding "s" for plural;
		$timestr.="s";
	}
	return $timestr;
}

function getSeconds($ref_time,$str){
	$seconds=$ref_time;
	if(strstr($str,"now").""!=""){$seconds+=0;}
	elseif(strstr($str,"minute").""!=""){$seconds+=getNumber($str,"a minute")*60;}
	elseif(strstr($str,"hour").""!=""){$seconds+=getNumber($str,"an hour")*3600;}
	elseif(strstr($str,"day").""!=""){$seconds+=getNumber($str,"a day")*3600*24;}
	else{$seconds="Ignored";}
	return $seconds;	
}

function courseStarted($progressreport){
	$started=1;
	if(substr($progressreport,0,2)=="0%"){
		$started=0;
	}
	return $started;
}

function getID($line){
	if($line=="EOF"){ #catching EOF for file1;
		$IDs="EOF";
	}
	else{#find identifiers for current line in both files; identifiers: course, level, text1
		$IDA=explode("|",$line); #IDA = IDs-Array; all the fields in the line
		$IDs=$IDA[0]."|". $IDA[1]."|".$IDA[2]; #only use the relevant fields; ##TODO what happens to locked texts!?
	}
	return $IDs;
}
?>