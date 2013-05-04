<html>
<head>
<title>Memrise Statistics</title>
<meta charset="utf-8">
</head>
<body>
<?php

require_once "library/color_functions.php";
require_once "library/graphing_functions.php";

$minutes_labels = array(
	"0",
	"15",
	"30",
	"45",
	"60",
);	
$hours_labels = array(
	"0",
	"3",
	"6",
	"12",
	"24",
);
$days_labels=array();

#echo "Data file last loaded: ".date("D, d-M-Y, H:i:s",filemtime("data/full_list.txt"))."<br> \n";

#these are going to be adjusted to client time
$today=time()+$_GET['timediff'];

#echo "This page loaded: ".date("D, d-M-Y, H:i:s",$today)."<br> \n";

$datetime1=new datetime(date("D, d-M-Y, H:i:s",filemtime("data/full_list.txt")));
$datetime2=new datetime(date("D, d-M-Y, H:i:s",time()));
$interval=date_diff($datetime1,$datetime2);
$hours=$interval->format('%h')*1+$interval->format('%a')*24;

#echo "Age of the data file at page load: ".$interval->format($hours.':'.'%i'.':'.'%s').".<br> \n";
echo "<a href='crawler.php'>Update file</a><br> \n <br> \n";

$fp = fopen("data/full_list.txt", "r");
$entry=array();
$courses=array();

if ($fp){
	$k=0;
	while (($line = fgets($fp, 4096)) !== false){
		$fields = array ('course', 'level','text1','text2','ask_next','asknextdate','askedlast','interval');
		$entry[$k] = array_combine ( $fields, explode ( "|", $line ) );
		$courses[$k]=$entry[$k]['course'];
		$k++;
	}
}
echo "<h2>Courses</h2> \n";
printContents($courses);
sortValues($entry);

function printContents($list){
	global $colors,$num_of_courses,$course_size;
	$output1=array_count_values($list);
	$num_of_courses=sizeof($output1);
	$course_size=$output1;
	$colors=makecolors(sizeof($output1));
	$i=0;
	echo "<table> \n";
	foreach($output1 as $key=>$value){
		echo "<tr> \n";
		echo '<td><div style="background-color:'.$colors[$i].';height:20px;width:20px;border:1px solid black;"></div></td>'." \n";
		echo "<td> \n";
		echo $key." (".$value." words) \n";
		echo "</td> \n";
		echo "</tr> \n";
		$i++;
	}
	echo "</table> \n";
}

function sortValues2($list){
	global $minutes_array, $hours_array, $days_array, $num_of_courses, $course_size, $days_labels, $today;
	$minutes=array();
	$hours=array();
	$days=array();
	$days1=array();
	#need to get max days first
	$i=0;
	$previous_value=0;
	$maxdays=0;
	foreach($course_size as $key=>$value){
		$days1[$key]=array();
		for(;$i<$value+$previous_value;$i++){
			$timediff=$list[$i]['asknextdate']-$today;
			#put values into the days1 array to find the maximum number of days; this is used to know the maximum value of the x-axis later
			if($timediff>=86400){
				array_push($days1[$key],round($timediff/86400));
			}	
		}
		$previous_value=$i;
		if(max($days1[$key])>$maxdays){
			$maxdays=max($days1[$key]);
		}
	}
	#make days labels
	for($k=0;$k<=$maxdays;$k++){
		array_push($days_labels,$k);
	}
	$days_labels[25]="&#8805&nbsp;25";
	
	#loop through courses
	$i=0;
	$previous_value=0;
	foreach($course_size as $key=>$value){
		$minutes[$key]=array();
		$hours[$key]=array();
		$days[$key]=array();
		for(;$i<$value+$previous_value;$i++){
			if($list[$i]['asknextdate']!=="Ignored"){
				$asknextdate=$list[$i]['asknextdate'];
				$timediff=$asknextdate-$today;
				$datediff=getDateDiff($asknextdate,$today);
				if($timediff<0){ #if asknextdate is in the past, set to now.
					$timediff=0;
				}
				if($timediff<=60){
					array_push($minutes[$key],0);
				}
				if($timediff<=3600){
					array_push($minutes[$key],round($timediff/60));
				}
				if($timediff<=86400){
					array_push($hours[$key],round($timediff/3600));
				}
				#array_push($days[$key],round($timediff/86400));
				array_push($days[$key],$datediff);
			}
		}
		$previous_value=$i;
		
		sort($minutes[$key]);
		sort($hours[$key]);
		sort($days[$key]);
		
		#make the minutes array;		
		$zero[$key]=0;
		$Q1[$key]=0;
		$Q2[$key]=0;
		$Q3[$key]=0;
		$Q4[$key]=0;
		for($j=0;$j<sizeof($minutes[$key]);$j++){
			if($minutes[$key][$j]==0){$zero[$key]++;}
			else if($minutes[$key][$j]>0 && $minutes[$key][$j]<16){$Q1[$key]++;}  # 1 to 15
			else if($minutes[$key][$j]>15 && $minutes[$key][$j]<31){$Q2[$key]++;} #16 to 30
			else if($minutes[$key][$j]>30 && $minutes[$key][$j]<46){$Q3[$key]++;} #31 to 45
			else if($minutes[$key][$j]>45 && $minutes[$key][$j]<61){$Q4[$key]++;} #46 to 60
		}
		$minutes_array[$key] = array(
			$zero[$key],
			$Q1[$key],
			$Q2[$key],
			$Q3[$key],
			$Q4[$key],
		);
		
		#make the hours array		
		$zeroh[$key]=$zero[$key];
		$threeh[$key]=$Q1[$key]+$Q2[$key]+$Q3[$key]+$Q4[$key]-$onehour;
		$sixh[$key]=0;
		$twelveh[$key]=0;
		$twentyfourh[$key]=0;
		for($j=0;$j<sizeof($hours[$key]);$j++){
			if($hours[$key][$j]>0 && $hours[$key][$j]<4){$threeh[$key]++;}				#1 to 3
			else if($hours[$key][$j]>3 && $hours[$key][$j]<7){$sixh[$key]++;}			#4 to 6
			else if($hours[$key][$j]>6 && $hours[$key][$j]<13){$twelveh[$key]++;}		#7 to 12
			else if($hours[$key][$j]>12 && $hours[$key][$j]<25){$twentyfourh[$key]++;}	#13 to 25
		}
		$hours_array[$key] = array(
			$zeroh[$key],
			$threeh[$key],
			$sixh[$key],
			$twelveh[$key],
			$twentyfourh[$key],
		);
		
		#make the days array
		$days_array[$key]=array();
		$limit=25;
		
		/**
		$days_array[$key]=array();
		$limit=25;
		for($k=0;$k<=$maxdays;$k++){
			if($k<=$limit){
				$k2=$k;
				array_push($days_array[$key],0);
			}
			else{$k2=$limit;}
			#array_push($days_array[$key],0);
			foreach($days[$key] as $nkey=>$nvalue){
				if($nvalue==$k){
					$days_array[$key][$k2]++;
				}
			}
		}**/
	}
}
function sortValues($list){
	global $minutes_array,$hours_array,$days_array,$num_of_courses,$course_size,$days_labels, $today;
	$minutes=array();
	$hours=array();
	$days=array();
	$days1=array();
	#need to get max days first
	$i=0;
	$previous_value=0;
	$maxdays=0;
	foreach($course_size as $key=>$value){
		$days1[$key]=array();
		for(;$i<$value+$previous_value;$i++){
			$timediff=$list[$i]['asknextdate']-$today;
			#put values into the days1 array to find the maximum number of days; this is used to know the maximum value of the x-axis later
			if($timediff>=86400){
				array_push($days1[$key],round($timediff/86400));
			}	
		}
		$previous_value=$i;
		if(!empty($days1[$key])){
			if(max($days1[$key])>$maxdays){
				$maxdays=max($days1[$key]);
			}
		}
	}
	#make days labels
	for($k=0;$k<=$maxdays;$k++){
		#array_push($days_labels,$k);
		#today +$k = date
		#get weekday for that
		#get date for that
		$datelabel=date("D",$today+$k*86400);
		$datelabel.="<br>".date("M-j",$today+$k*86400);		
		array_push($days_labels,$datelabel);
	}
	$days_labels[25]="in &#8805&nbsp;25 days";
	
	#loop through courses
	$i=0;
	$previous_value=0;
	foreach($course_size as $key=>$value){
		$minutes[$key]=array();
		$hours[$key]=array();
		$days[$key]=array();
		for(;$i<$value+$previous_value;$i++){
			if($list[$i]['asknextdate']!=="Ignored"){
				$timediff=$list[$i]['asknextdate']-$today;
				if($timediff<60){
					array_push($minutes[$key],0);
				}
				elseif($timediff>=60 && $timediff<3600){
					array_push($minutes[$key],round($timediff/60));
				}
				elseif($timediff>=3600 && $timediff<86400){
					array_push($hours[$key],round($timediff/3600));
				}
				elseif($timediff>=86400){
					array_push($days[$key],round($timediff/86400));
				}				
			}
		}
		$previous_value=$i;
		
		sort($minutes[$key]);
		sort($hours[$key]);
		sort($days[$key]);
		
		#make the minutes array;		
		$zero[$key]=0;
		$Q1[$key]=0;
		$Q2[$key]=0;
		$Q3[$key]=0;
		$Q4[$key]=0;
		for($j=0;$j<sizeof($minutes[$key]);$j++){
			if($minutes[$key][$j]==0){$zero[$key]++;}
			else if($minutes[$key][$j]>0 && $minutes[$key][$j]<16){$Q1[$key]++;}
			else if($minutes[$key][$j]>15 && $minutes[$key][$j]<31){$Q2[$key]++;}
			else if($minutes[$key][$j]>30 && $minutes[$key][$j]<46){$Q3[$key]++;}
			else if($minutes[$key][$j]>45 && $minutes[$key][$j]<60){$Q4[$key]++;}
		}
		
		#make the hours array
		$onehour=0;
		for($j=0;$j<sizeof($hours[$key]);$j++){
			if($hours[$key][$j]==1){$onehour++;}
		}
		$Q4[$key]+=$onehour;
		$minutes_array[$key] = array(
			$zero[$key],
			$Q1[$key],
			$Q2[$key],
			$Q3[$key],
			$Q4[$key],
		);		
		$zeroh[$key]=$zero[$key];
		$threeh[$key]=$Q1[$key]+$Q2[$key]+$Q3[$key]+$Q4[$key]-$onehour;
		$sixh[$key]=0;
		$twelveh[$key]=0;
		$twentyfourh[$key]=0;
		for($j=0;$j<sizeof($hours[$key]);$j++){
			if($hours[$key][$j]>0 && $hours[$key][$j]<4){$threeh[$key]++;}
			else if($hours[$key][$j]>3 && $hours[$key][$j]<7){$sixh[$key]++;}
			else if($hours[$key][$j]>6 && $hours[$key][$j]<13){$twelveh[$key]++;}
			else if($hours[$key][$j]>12 && $hours[$key][$j]<25){$twentyfourh[$key]++;}
		}
		#make the days array
		$oneday=0;
		for($j=0;$j<sizeof($days[$key]);$j++){
			if($days[$key][$j]==1){$oneday++;}
		}
		$twentyfourh[$key]+=$oneday;
		
		$hours_array[$key] = array(
			$zeroh[$key],
			$threeh[$key],
			$sixh[$key],
			$twelveh[$key],
			$twentyfourh[$key],
		);	
		$days_array[$key]=array();
		$days_array[$key][0]=$zeroh[$key]+$threeh[$key]+$sixh[$key]+$twelveh[$key]+$twentyfourh[$key]-$oneday;
		$limit=25;
		for($k=1;$k<=$maxdays;$k++){
			if($k<=$limit){
				$k2=$k;
				array_push($days_array[$key],0);
			}
			else{$k2=$limit;}
			#array_push($days_array[$key],0);
			foreach($days[$key] as $nkey=>$nvalue){
				if($nvalue==$k){
					$days_array[$key][$k2]++;
				}
			}
		}
	}
}

function getSum($array){
	$sum=0;
	foreach($array as $value){
		$sum+=$value;
	}
	return $sum;
}

function getDateDiff($date1,$date2){
	$date1_date=date("Y-m-d",$date1);
	$date2_date=date("Y-m-d",$date2);
	$date1_round=strtotime($date1_date);
	$date2_round=strtotime($date2_date);
	$diff=round(($date2_round-$date1_round)/86400);
	return $diff;
}
?>
<br>
<table>
<tr><td width=300>
<h2>Next Hour</h2>
<?php
drawGraph($minutes_array,$minutes_labels,"Minutes");
?>
</td>
<td>
<h2>Next 24 Hours</h2>
<?php
drawGraph($hours_array,$hours_labels,"Hours");
?>
</td></tr>
<tr><td colspan="2"><br>
<h2>Next 24 days</h2>
<?php
drawGraph($days_array,$days_labels,"");
?>
</td></tr>
</table>
<br>

<h2>Raw Data</h2>
<textarea cols="140" rows="10">
<?php
$filename = "data/full_list.txt";
$filtered_data=file_get_contents($filename);
$fp = fopen($filename, "r");
$filtered_data="Course title|level|text 1|text2|ask next date |asked last date|current interval \n";
while (($line = fgets($fp, 4096)) !== false){
	$line_array=explode("|",$line);
	$ignore=$line_array[5];
	$askedlastdate=$line_array[6];
	if($askedlastdate!=="unknown"){
	$askedlastdate=date("D, d M Y H:i", $line_array[6]);	
	}
	if($ignore!="Ignored"){	
		$filtered_data.=$line_array[0]."|".$line_array[1]."|".$line_array[2]."|".$line_array[3]."|".date("D, d M Y H:i", $line_array[5])."|".$askedlastdate."|".$line_array[7];
	}
}
fclose($fp);
echo $filtered_data;
?>
</textarea>

</body>
</html>
