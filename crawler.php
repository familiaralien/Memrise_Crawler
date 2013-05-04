<html>
<head>
<title>Memrise Statistics</title>
<meta charset="utf-8">
<script type="text/javascript">
var today = new Date();
var t = today.getTime() / 1000 - today.getTimezoneOffset()*60;
</script>
</head>
<body onload="document.myform.clienttime.value=t;">
<?php
#TODOLIST
#get lastrun date
#faster load; depending on file age;
#remove expired file format
#graph intervals;
#estimate intervals put as "more than " TIME;
#polish looks center and font;
#config file editing
#time and date format in config file
#fix graphs for low numbers 1 should be at least 1 pixel; include border
#keep a history of intervals;


require_once "library/string_functions.php";
require_once "library/pageload_functions.php";
require_once "library/display_functions.php";
require_once "library/config.php";
	
if (!isset($_POST["clienttime"])){	
	echo '<form name="myform" action="crawler.php" method="post">'." \n";
	echo '<input type="radio" name="datasource" value="remote">Force remote data collection (refresh data from memrise server, advisable if you\'ve added new courses).'."<br> \n";
	echo '<input type="radio" name="datasource" value="local" checked="checked">Use previously stored <font color="blue">local data</font> where possible.'."<br><br> \n";
	echo '<input type="hidden" name="clienttime">'." \n";
	echo '<input type="submit" value="Collect Data">'." \n";
	echo '</form><br>'." \n";
	}
else{
	$theTime=$_POST["clienttime"];
	$datasource=$_POST["datasource"];
#	echo date('Y-m-d h:i:s',$theTime);

	if($user=="placeholder"){
		echo "Edit the config.php file first!<br> \n";
		echo "<i>(You can open the file in notepad; change the placeholder values according to the instructions there.)</i>";
	}
	else{
		ini_set('max_execution_time', $PageTimeout); //300 seconds = 5 minutes
		#check if tmp exists
		if(!is_dir("tmp")){mkdir("tmp");}
		$url = "http://www.memrise.com/login/";
		$status="";
		$global_i=1;

		logout(); #to catch previous aborted runs; can't run script if already logged in
		#open memrise login page and put code into file // needed because csfrtoken goes into form
		getloginpage();
		#logging in
		login($user,$password);
		#loading home page
		outputProgress("<i>Be patient. The next few steps might take a while!</i>");
		loadpage("http://www.memrise.com/home/","tmp/home.tmp");

		#loading local data if it exists
		#similar code in stats.php
		$filename = "data/full_list.txt";
		if(file_exists($filename)){	
			$fp = fopen($filename, "r");
			$k=0;
			$entry=array();
			$courses=array();
			$time_next_asked=array();
			if ($fp){
				$updatefile=FALSE;
				while (($line = fgets($fp, 4096)) !== false){
					$fields = array ('course', 'level','text1','text2','ask_next','asknextdate','askedlast','interval');
					#checking if old file format; if new fields missing add them;
					if(count(explode("|",$line))==5){
						$updatefile=TRUE;
						$line=trim($line);
						$asknext=explode("|",$line);
						$asknext=$asknext[4];
						$line.="|".asknextToFields(getfilemtime(),$asknext);
					}
					$entry[$k] = array_combine($fields, explode("|",$line));
					$courses[$k]=$entry[$k]['course'];
					$k++;
				}
			}
			#if necessary update file with new fields;
			if($updatefile){	
				$file="data/full_list.txt";
				file_put_contents($file, "");
				foreach($entry as $line){
					$writeline=implode("|",$line);
					file_put_contents($file, $writeline, FILE_APPEND | LOCK_EX);
				}
			}
			fclose($fp);
			$local_file_exists=true;
		}
		else{
			$file="data/full_list.txt";
			file_put_contents($file, "");
			$local_file_exists=false;
		}
		
		#extract course names and links
		#loop through courses and levels & collect information
		findcourses();
		
		#merge new data with existing data
		mergeFiles();
		
		#logging out
		logout(TRUE);
		cleanupFiles();
		outputProgress("Done. <a href='stats.php?timediff=".getTZdiff()."'>View Data</a>.");
	}
}
echo "</body> \n";

#DATA OPERATIONS
function getWhitebox(){
	$point1='<h2 class="h1">All my courses</h2>';
	$point2='<div class="full-width alternate divide last-section">';
	$html=file_get_contents("tmp/home.tmp");
	$html=explode($point1,$html);
	$html=$html[1];
	$html=explode($point2,$html);
	$html=$html[0];
	return $html;
}

function getCompletedLevels($href){
	global $_COOKIE;
	#get courselevelpage
	loadpage($href,"tmp/courselevels.tmp");
	$html=file_get_contents("tmp/courselevels.tmp");
	$array1=explode('<div class="level-index">',$html);
	$i=0;
	$levelarray=array();
	foreach($array1 as $level){
		if($i>0){
			$array2=explode("</div>",$level);
			$levelnum=$array2[0];
			$array3=explode('<div class="level-icon">',$level);
			if(strstr($array3[1],'<span class="ico ico-complete ico-correct ico-m ico-green">').""!=""){$complete=true;}
			else{$complete=false;}
			if($complete){
				array_push($levelarray, $levelnum);
			}
		}
		$i++;
	}
	return $levelarray;
}

function getItems($string){
	global $theTime;
	$search1='<div class="text"></div>';
	$replace1='<div class="text">[no text]</div>';
	$search2='<span class="ico ico-grey ico-lock"></span>';
	$replace2='<div class="text">[locked]</div>';
	$string=str_replace ($search1, $replace1, $string);
	$string=str_replace ($search2, $replace2, $string);
	
	$d="</div>";
	$status=explode('"status">',$string);
	$status=explode($d,$status[1]);
	$status=$status[0];
	$status.="|".asknextToFields($theTime,$status);
	$text=explode('<div class="text">',$string);
	$text1=explode($d,$text[1]);
	$text1=$text1[0];
	$text2=explode($d,$text[2]);
	$text2=$text2[0];	
	return $text1."|".$text2."|".$status;
}

function getCharList($charlistfile, $course, $level){
	$point1='data-thing-id';
	$html=file_get_contents($charlistfile);
	$html=explode($point1,$html);
	$i=1;
	$list="";
	for($i=1;$i<sizeof($html);$i++){
		$line=$course."|".$level."|".getItems($html[$i]);
		$list.=$line;
	}
	return $list;
}

function getLocalCharList($course,$level){
	global $entry, $theTime;
	$list="";
	foreach ($entry as $line){
		if($line['course']==$course && $line['level']==$level){
			if($line['ask_next']!=="Ignored"){
				$line['ask_next']="expired";
				if($line['asknextdate']<$theTime){ #this sets the "askedlastdate" to the current date/time, if "asknext" is "now" or in the past
					$line['asknextdate']=$theTime;
					$line['askedlast']=$theTime;
				}
				if($line['askedlast']!=="unknown"){
					$line['interval']=timeToText($line['asknextdate']-$line['askedlast'])." \n";
				}
				else{
					$line['interval']="unknown \n";
				}
			}
			
			$list.=implode("|",$line);
		}
	}
	return $list;
}

function asknextToFields($ref_time,$str){
	global $theTime;
	$asknextdate=getSeconds($ref_time, $str);
	if($str=="now"){
		$askedlast=$theTime;
	}
	else{
		$askedlast="unknown";
	}
	return $asknextdate."|".$askedlast."|unknown \n";
}

#MANIPULATING FILES
function getfilemtime(){
	$filename = 'data/full_list.txt';
	$fmt=filemtime($filename);
	$fmt+=getTZdiff();
	return $fmt;
}

function mergeFiles(){ #file
	#merging the files so that the script can remember when an item was last asked; old and new data are needed for this
	$fp1 = fopen("data/full_list.txt", "r"); #file1: this is the local file with data from the previous run
	$fp2 = fopen("tmp/full_list2.txt", "r"); #file2: this is the acquired updated data
	$entry=array(); #the temporary array the data gets written to
	$k=0; #counter for $entry array
	$getnextline1=true; #setting a flag for the reading of file1

	while(($line2 = fgets($fp2, 4096))!==false){ #reading file2 line by line
		if($getnextline1){ #only get next line of file1 if the previous line was used
			if(!feof($fp1)){ #catch if EOF	
				$line1 = fgets($fp1, 4096);
			}
			else{
				$line1="EOF";
			}
		}
		#find identifiers for current line in both files
		#catch line1 becoming empty for new levels;
		if($line1.""==""){
			$identifiers1="EOF";
		}
		else{
			$identifiers1=getID($line1);
		}
		$identifiers2=getID($line2);
		
		if($identifiers1 == $identifiers2){ #entry exists in both files
			$entry[$k]=mergeLines($line1,$line2);
			$getnextline1=true; #set the flag to advance file1 to the next line
		}
		else{ #entry only exists since update
			$entry[$k]=$line2; #new file line becomes line2
			$getnextline1=false; #set the flag to advance file2 but stay at the same line in file1
		}
		$k++;
	}
	fclose($fp1);
	fclose($fp2);
	#write $entry array into file1;
	$file="data/full_list.txt";
	file_put_contents($file, "");	
	foreach($entry as $line){
		file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
	}
}

function mergeLines($L1, $L2){ #file
	global $theTime;
	#L2: remotely collected data
	#L1: data from local file
	$L1A=explode("|",$L1);
	$L2A=explode("|",$L2);
	#[0]'course'	#[4]'ask_next'
	#[1]'level'		#[5]'asknextdate'
	#[2]'text1'		#[6]'askedlast'
	#[3]'text2'		#[7]'interval'	
	$asknextdate=$L2A[5]; #set asknextdate to remotely collected value
	if($L1A[5]<=$theTime){ #set askedlastdate (if asknextdate is <=now set to now; otherwise set to local value of askedlastdate)
		$askedlastdate=$theTime;
	}
	else{
		$askedlastdate=$L1A[6]; #this could also have "unknown" as a value;
	}
	
	if($askedlastdate!=="unknown" && $askedlastdate<$L1A[5]){ #only calculate a new interval if the new askedlastdate is before the new asknextdate
		$interval=timeToText($asknextdate-$askedlastdate);
	}
	else{ #if askedlast is unknown or >$L1A[5]
		if($askedlastdate=="unknown"){
			$interval="unknown";
		}
		else{
			$interval=trim($L1A[7]);
		}
	}
	
	#DEBUGGING START
	#not sure how this happens, but catch interval set to 0;
	if($interval.""=="0"){
		$debugtext="L1|".$L1A[0]."|".$L1A[1]."|".$L1A[2]."|".$L1A[3]."|".$L1A[4]."|".$L1A[5]."|".$L1A[6]."|".$L1A[7]." \n";
		$debugtext.="L2|".$L2A[0]."|".$L2A[1]."|".$L2A[2]."|".$L2A[3]."|".$L2A[4]."|".$L2A[5]."|".$L2A[6]."|".$L2A[7]." \n";
		$debugtext.="asknextdate,askedlastdate,interval|".$asknextdate."|".$askedlastdate."|".$interval." \n";
		debug("debug.txt",$debugtext);
		$interval=$L1A[7];
	}
	#DEBUGGING END
	
	$ML=$L2A[0]."|".$L2A[1]."|".$L2A[2]."|".$L2A[3]."|expired|".$asknextdate."|".$askedlastdate."|".$interval." \n";
	return $ML;
}

#DEBUGGING
function debug($file,$str){
	file_put_contents($file, $str." \n", FILE_APPEND | LOCK_EX);
}

?>
</body>
</html> 
