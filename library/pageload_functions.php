<?php
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

function getloginpage(){
	global $url, $_COOKIE, $names, $values;
	outputProgress("getting login page info.");
	$ch = curl_init($url);
	$file="tmp/login.tmp";
	$fp = fopen($file, "w");
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
	$fp = fopen($file, "r");
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
	#set POST variables
	$fields = array(
		$names[0] => urlencode($_COOKIE['csrftoken']),
		$names[1] => urlencode($usr),
		$names[2] => urlencode($pw),
		$names[3] => urlencode($values[3]),
	);

	#url-ify the data for the POST
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

	#set the url, number of POST vars, POST data
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

function getTZdiff(){
	global $theTime;
	$diff=round(($theTime-time())/3600)*3600;
	return $diff;
}

function cleanupFiles(){
	$dir="tmp/";
	foreach(glob($dir . '/*') as $file) { 
		if(is_dir($file)) rrmdir($file); else unlink($file); 
	}
	rmdir($dir); 
}

function findcourses(){
	global $entry, $theTime, $datasource, $local_file_exists;
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
			$levelarray=getCompletedLevels($href);
			$max1=sizeof($levelarray);
			outputProgress('Collecting data from '.$max1.' levels of <b>"'.$title.'"</b>');
			#check local file. If smallest asknext >=24h use local file; generate list of levels to check locally
			unset($useremote);
			unset($line);
			$useremote=array();
			#TODO use file age to determine which levels to load; if fileage<1day only reload levels that have asknext <=fileage but not "now" (this is wrong; nows need to be loaded to check if they've been asked!!)
			#useremote if:
			if($local_file_exists){
				foreach($entry as $line){
					if($line['course']==$title){
						if(!isset($useremote[$line['level']])){
							$useremote[$line['level']]=0;
						}
						if($line['asknextdate']!="Ignored" && ($line['asknextdate']-$theTime)<(24*3600)){ #TODO maybe adjust this limit
							$useremote[$line['level']]++;
						}
					}
				}
			}
			reset($useremote);

			#run through level pages and collect data
			for($j=0;$j<$max1;$j++){
			
				#load local level pages
				if(!$local_file_exists){$useremote[$levelarray[$j]]=1;} # if no local file exists create "1" value to force remote collection
				if(!isset($useremote[$levelarray[$j]])){$useremote[$levelarray[$j]]=0;} #create null value for empty levels (i.e. the pinyin explanation levels without words in Chinese courses)
				if($datasource=="remote"){$useremote[$levelarray[$j]]=1;}
				if($useremote[$levelarray[$j]]<1){
					outputProgress("<font color='blue'>".$levelarray[$j]."</font>, ", FALSE);
					$levellist=getLocalCharList($title,$levelarray[$j]);
					usleep(10000); #sleeping 0.01 seconds
				}
				#load remote level pages
				else{
					$levelpage=$href.$levelarray[$j]."/";
					$tmplevel="tmp/level.tmp";
					loadpage($levelpage,$tmplevel);
					outputProgress($levelarray[$j].", ", FALSE);
					$levellist=getCharList($tmplevel,$title,$levelarray[$j]);					
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


?>