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
#get lastrun date #TODO

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
	
	require_once "config.php";
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

		#outputProgress("Starting script.");
		logout(); #to catch previous aborted runs; can't run script if already logged in
		#open memrise login page and put code into file // needed because csfrtoken goes into form
		getloginpage();
		#logging in
		login($user,$password);
		#loading home page
		outputProgress("<i>Be patient. The next few steps might take a while!</i>");
		loadpage("http://www.memrise.com/home/","tempfile.html");
		outputProgress("Data source: ".$datasource);

		#loading local data if it exists
		#similar code in stats.php
		$filename = "tmp/full_list.txt";
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
					#checking if old file format; new fields missing; add them;
					if(count(explode("|",$line))==5){
						$updatefile=TRUE;
						$line=trim($line);
						$asknext=explode("|",$line);
						$asknext=$asknext[4];
						$line.="|".asknextToFields(getfilemtime(),$asknext);
					}
					$entry[$k] = array_combine($fields, explode("|",$line));
					$courses[$k]=$entry[$k]['course'];
					#$time_next_asked[$k]=$entry[$k]['ask_next'];
					$k++;
				}
			}
			#if necessary update file with new fields;
			if($updatefile){	
				$file="tmp/full_list.txt";
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
			$file="tmp/full_list.txt";
			file_put_contents($file, "");
			$local_file_exists=false;
		}
		
		#getting the local courselist; if it doesn't exist, get remote courselist;
		#$courselist = array_unique($courses);
		
		#extract course names and links
		#loop through courses and levels & collect information
		findcourses();
		
		#merge new data with existing data
		mergeFiles();
		
		#logging out
		logout(TRUE);
#		outputProgress("Done. <a href='stats.php'>View Data</a>.");
		outputProgress("Done. <a href='stats.php?timediff=".getTZdiff()."'>View Data</a>.");
	}
}
echo "</body> \n";

function beautify($input){
	$output = trim($input);
	$output = str_replace("'", '"', $output);	
	return $output;
}
function getValNam($input,$field){
	$output = explode($field.'="', $input);
	$output = explode('"',$output[1]);
	return $output[0];
}

function outputProgress($message, $newline=TRUE){
	global $status, $global_i;
	if ($newline){$linebreak="<br> \n";}
	else{$linebreak="";}
	$status=$status.$message.$linebreak;
	$global_i=$global_i+1;
	echo "<span style='position:absolute;z-index:$global_i;background:#FFF;'> \n" . $status. " \n"."</span>";
    myFlush();
    #sleep(0);
}
function myFlush(){
    echo(" \n");
#    echo(str_repeat(' ', 256));
    if (@ob_get_contents()) {
        @ob_end_flush();
    }
    flush();
}

function findcourses(){
	global $entry, $theTime, $datasource, $local_file_exists; #$courselist, 
	$html=getWhitebox();
	$point1='<div class="course-box-wrapper">';
	$html=explode($point1,$html);

	$max = sizeof($html);
	$file="tmp/full_list2.txt";
	file_put_contents($file, "");
	
	for($i=1; $i<$max; $i++){		
		$point2='<a class="inner-wrap"';
		$point3='<div class="progress"';
		
		$href=explode($point2,$html[$i]);
		$href=explode(">",$href[1]);
		$href=explode('ref="',$href[0]);
		$title=explode('"',$href[1]);
		$href="http://www.memrise.com".$title[0];
		$title=$title[2];
		
		$progress=explode($point3,$html[$i]);
		$progress=explode(">",$progress[1]);
		$progress=explode('"',$progress[0]);
		$progress=$progress[1];
		if(courseStarted($progress)==1){
			$max1=getLevels($href)*1;
			outputProgress('Collecting data from '.$max1.' levels of <b>"'.$title.'"</b>');
			
			#check local file. If smallest asknext >=24h use local file; generate list of levels to check locally
			unset($useremote);
			unset($line);
			$useremote=array();
	
			if($local_file_exists){
				foreach($entry as $line){ #ERROR: no entry if no local file
					if($line['course']==$title){
						if(!isset($useremote[$line['level']])){
							$useremote[$line['level']]=0;
						}
						if($line['asknextdate']!="Ignored"){
							if(($line['asknextdate']-$theTime)<(24*3600)){
								$useremote[$line['level']]++;
							}
						}
					}
				}
			}
			reset($useremote);

			#run through level pages and collect data
			for($j=1;$j<=$max1;$j++){				
				#load local level pages
				if(!$local_file_exists){$useremote[$j]=1;} # if no local file exists create "1" value to force remote collection
				if(!isset($useremote[$j])){$useremote[$j]=0;} #create null value for empty levels (i.e. the pinyin explanation levels without words in Chinese courses)
				if($datasource=="remote"){$useremote[$j]=1;}
				if($useremote[$j]<1){
					outputProgress("<font color='blue'>".$j."</font>, ", FALSE);
					$levellist=getLocalCharList($title,$j);
					usleep(10000); #sleeping 0.01 seconds
				}
				#load remote level pages
				else{
					$levelpage=$href.$j."/";
					$tmplevel="tmp/tmplevel.txt";
					loadpage($levelpage,$tmplevel);
					outputProgress($j.", ", FALSE);
					$levellist=getCharList($tmplevel,$title,$j);					
					usleep(10000); #sleeping 0.01 seconds
				}
				if(trim($levellist).""!=""){
				file_put_contents($file, $levellist, FILE_APPEND | LOCK_EX);
				}
			}
			outputProgress(" ");
		}
		else{	
			outputProgress("No data to collect from <b>".$title."</b> (not yet started)");		
		}		
	}	
}

function mergeFiles(){ #merging the files so that the script can remember when an item was last asked; old and new data are needed for this
	$fp1 = fopen("tmp/full_list.txt", "r"); #file1: this is the local file with data from the previous run
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
		#file_put_contents("debug.txt", "getIDs:".$k." || ".$line1." \n", FILE_APPEND | LOCK_EX);
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
	$file="tmp/full_list.txt";
	file_put_contents($file, "");	
	foreach($entry as $line){ # errors
		file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
	}
}

function mergeLines($L1, $L2){
	#new file line becomes line2 with asknextdate of line1 is put as askedlastdate in line2
	$L1A=explode("|",$L1);
	$L2A=explode("|",$L2);
	$ML=$L2A[0]."|".$L2A[1]."|".$L2A[2]."|".$L2A[3]."|".$L2A[4]."|".$L2A[5]."|".$L1A[5]."|".$L2A[7];
	return $ML;
}

function getID($line){ # errors
	if($line=="EOF"){ #catching EOF for file1;
		$IDs="EOF";
	}
	else{#find identifiers for current line in both files; identifiers: course, level, text1
		#file_put_contents("debug.txt", ":: ".$line, FILE_APPEND | LOCK_EX);
		$IDA=explode("|",$line); #IDs-Array; all the fields in the line
		$IDs=$IDA[0]."|". $IDA[1]."|".$IDA[2]; #only use the relevant fields; ##TODO what happens to locked texts!? #ERROR HERE for run #1
	}
	return $IDs;
}

function getWhitebox(){
	$point1='<h2 class="h1">All my courses</h2>';
	$point2='<div class="full-width alternate divide last-section">';
	$html=file_get_contents("tempfile.html");
	$html=explode($point1,$html);
	$html=$html[1];
	$html=explode($point2,$html);
	$html=$html[0];
	return $html;
}
function courseStarted($progressreport){
	$started=1;
	if(substr($progressreport,0,2)=="0%"){
		$started=0;
	}
	return $started;
}
function getLevels($href){
	global $_COOKIE;
	#get courselevelpage
	loadpage($href,"tmp/courselevels.txt");
	$html=file_get_contents("tmp/courselevels.txt");
	$point1="level-ico level-ico-s  level-ico-plant-inactive";
	$search="level-ico level-ico-s  level-ico-seed"; ## added these to fix harvesting bug
	$html=str_replace ($search, $point1, $html); ## added these to fix harvesting bug
	$point2='div class="level-title"';
	$items1=explode($point1,$html);
	$items1=sizeof($items1);
	$items2=explode($point2,$html);
	$items2=sizeof($items2);
	$max=$items2-1;
	$completed=$items2-$items1;
	#return "[".$completed."|".$max."]";
	return $completed;
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
	global $entry;
	$list="";
	foreach ($entry as $line){
		if($line['course']==$course && $line['level']==$level){
			$list.=implode("|",$line);
		}
	}
	return $list;
}

function getItems($string){
	global $theTime;
	//make this work if text1 is locked or empty
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

function getloginpage(){
	global $url, $_COOKIE, $names, $values;
	outputProgress("getting login page info.");
	$ch = curl_init($url);
	$fp = fopen("tmp/tempfile.txt", "w");
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_COOKIESESSION,true);
	curl_setopt($ch, CURLOPT_COOKIEJAR, "cookies.txt");
	curl_setopt($ch, CURLOPT_COOKIEFILE, "cookies.txt"); 
	curl_exec($ch);
	curl_close($ch);
	fclose($fp);

	#extract relevant information for login
	$lines = array();
	$fp = fopen("tmp/tempfile.txt", "r");
	$names= array();
	$values=array();
	$results="";
	$cookies=array();

	if ($fp){
		#extracting form fields and cookies
		while (($line = fgets($fp, 4096)) !== false){
			if(strpos($line,'Set-Cookie:') !== false){
				array_push($cookies, str_replace("Set-Cookie: ","",$line));
			}
			if(strpos($line,'<input') !== false){
				$results=$results.$line."<br> \n";
				$line = beautify($line);
				array_push($lines, $line);

				if (strpos($line,'type="submit"') == false){
					array_push($names,getValNam($line,"name"));
					if (strpos($line,'name="password"') == false){
						$tmp=getValNam($line,"value");
					}
					else{$tmp = "";}
					array_push($values,$tmp);		
				}
			}
		}
		if (!feof($fp)){
			echo "Error: unexpected fgets() fail\n";
		}
		fclose($fp);
	}
	else{
		echo "Didn't read anything.<br> \n";
	}
	$_COOKIE[getCookieName($cookies[0])]=getCookieValue($cookies[0]);
	$_COOKIE[getCookieName($cookies[1])]=getCookieValue($cookies[1]);
}
function login($usr,$pw){
	global $_COOKIE, $values,$names, $url;
	//set POST variables
	$fields = array(
		$names[0] => urlencode($_COOKIE['csrftoken']),
		$names[1] => urlencode($usr),
		$names[2] => urlencode($pw),
		$names[3] => urlencode($values[3]),
	);

	//url-ify the data for the POST
	$fields_string="";
	foreach($fields as $key=>$value) {$fields_string .= $key.'='.$value.'&'; }
	rtrim($fields_string, '&');

	#posting login information
	outputProgress("posting information.");
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_COOKIESESSION,true);
	curl_setopt($ch, CURLOPT_COOKIEJAR, "cookies.txt");
	curl_setopt($ch, CURLOPT_COOKIEFILE, "cookies.txt"); 

	//set the url, number of POST vars, POST data
	curl_setopt($ch,CURLOPT_URL, $url);
	curl_setopt($ch,CURLOPT_POST, count($fields));
	curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
	curl_exec($ch);
	curl_close($ch);
}
function logout($display=FALSE){
	if($display){outputProgress("logging out.");}
	$url = "http://www.memrise.com/logout/";
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_COOKIESESSION,true);
	curl_setopt($ch, CURLOPT_COOKIEJAR, "cookies.txt");
	curl_setopt($ch, CURLOPT_COOKIEFILE, "cookies.txt"); 
	curl_exec($ch);
	curl_close($ch);
}
function loadpage($url,$file){
	global $_COOKIE, $LoadTimeout;
	$retry=false;
	$ch = curl_init($url);
	$fp = fopen($file, "w");
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_COOKIE, "sessionid=".$_COOKIE['sessionid']);
	curl_setopt($ch, CURLOPT_COOKIE, "csrftoken=".$_COOKIE['csrftoken']);
	curl_setopt($ch, CURLOPT_COOKIESESSION,true);
	curl_setopt($ch, CURLOPT_COOKIEJAR, "cookies.txt");
	curl_setopt($ch, CURLOPT_COOKIEFILE, "cookies.txt");
	curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, $LoadTimeout);
	curl_exec($ch);
	if(curl_errno($ch)){
		$retry=TRUE;
	}
	curl_close($ch);
	fclose($fp);
	if($retry){
		outputProgress(".",FALSE);
		loadpage($url,$file);
	}
}
function getCookieValue($input){
	$cookie_tmp_val=str_replace("=",";",$input);
	$cookie_tmp_val=explode(";",$cookie_tmp_val);
	$cookie_value=$cookie_tmp_val[1];
	return $cookie_value;
}
function getCookieName($input){
	$cookie_tmp_val=str_replace("=",";",$input);
	$cookie_tmp_val=explode(";",$cookie_tmp_val);
	$cookie_name=$cookie_tmp_val[0];
	return $cookie_name;
}

function asknextToFields($ref_time,$str){
	$asknextdate=getSeconds($ref_time, $str);
	$askedlast="unknown"; #TODO use asknext from file
	$interval="unknown"; #TODO asknext-askedlast, if interval >1day use, otherwise keep previous interval
	return $asknextdate."|".$askedlast."|".$interval." \n";
}

function getSeconds($ref_time,$str){
	$seconds=$ref_time;
	#similar code in stats.php
#	if(strpos($str,"now").""=="0"){$seconds+=0;}
	if(strpos($str,"now")!=FALSE){$seconds+=0;}
	if(strpos($str,"minute")!=FALSE){$seconds+=getNumber($str,"a minute")*60;}
	if(strpos($str,"hour")!=FALSE){$seconds+=getNumber($str,"an hour")*3600;}
	if(strpos($str,"day")!=FALSE){$seconds+=getNumber($str,"a day")*3600*24;}
	else{$seconds="Ignored";}
	return $seconds;	
}

function getTZdiff(){
	global $theTime;
	$diff=round(($theTime-time())/3600)*3600;
	return $diff;
}

function getfilemtime(){
#	global $theTime;
	$filename = 'tmp/full_list.txt';
	$fmt=filemtime($filename);
#	$diff=round(($theTime-time())/3600)*3600;
#	$fmt+=$diff;
	$fmt+=getTZdiff();
	return $fmt;
}

function getNumber($string,$one){
	#this function also exists in stats.php
	$number=filter_var($string, FILTER_SANITIZE_NUMBER_INT);
	if(strpos($string,$one)!=FALSE){$number=1;}
	return $number;
}
?>
</body>
</html> 
