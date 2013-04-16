<html>
<head>
<title>Memrise Statistics</title>
<meta charset="utf-8">
</head>
<body>
<?php
#crawler v 0.3
require_once "config.php";
$ani=0;
if($user=="placeholder"){
	echo "edit the config.php file first!<br> \n";
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
	logout();
	#open memrise login page and put code into file // needed because csfrtoken goes into form
	getloginpage();
	#logging in
	login($user,$password);
	#loading home page
	loadpage("http://www.memrise.com/home/","tempfile.html");

	outputProgress("<i>Be patient. The next few steps might take a while!</i>");

	#extract course names and links
	#loop through courses and levels
	#collect information
	findcourses();
	#logging out
	logout(TRUE);
	outputProgress("Done. <a href='stats.php'>View Data</a>.");
}

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
    sleep(0);
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
	$html=getWhitebox();
	$point1='<div class="course-box-wrapper">';
	$html=explode($point1,$html);

	$max = sizeof($html);
	$file="tmp/full_list.txt";
	file_put_contents($file, "");
	
	for ($i=1; $i<$max; $i++) {
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
		//outputProgress($href);
		#outputProgress($title);
		//outputProgress($progress);
		if (courseStarted($progress)==1){
			//outputProgress("Started.");
			$max1=getLevels($href)*1;
			outputProgress('Collecting data from '.$max1.' levels of <b>"'.$title.'"</b>');
			#run through level pages and collect data!
			for($j=1;$j<=$max1;$j++){
				#load each level page
				$levelpage=$href.$j."/";
				outputProgress($j.", ", FALSE);
				$tmplevel="tmp/tmplevel.txt";
				loadpage($levelpage,$tmplevel);
				$levellist=getCharList($tmplevel,$title,$j);
				file_put_contents($file, $levellist, FILE_APPEND | LOCK_EX);
				sleep(1);
			}
			outputProgress(" ");
		}
		else{	
			outputProgress("No data to collect from <b>".$title."</b> (not yet started)");		
		}		
	}	
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
	$search="level-ico level-ico-s  level-ico-seed"; ## add these to fix harvesting bug
	$html=str_replace ($search, $point1, $html); ## add these to fix harvesting bug
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
		$line=$course."|".$level."|".getItems($html[$i])." \n";
		$list.=$line;
	}
	return $list;
}
function getItems($string){
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
		echo "didn't read anything.<br> \n";
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
$cookie_value=explode(";",$cookie_tmp_val)[1];
return $cookie_value;
}
function getCookieName($input){
$cookie_tmp_val=str_replace("=",";",$input);
$cookie_name=explode(";",$cookie_tmp_val)[0];
return $cookie_name;
}

?>
</body>
</html> 