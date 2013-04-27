<html>
<head>
<title>Memrise Statistics</title>
<meta charset="utf-8">
</head>
<body>
<?php
ini_set('max_execution_time', 10); //300 seconds = 5 minutes
require_once "colors.php";
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

#echo "Data file last loaded: ".date("D, d-M-Y, H:i:s",filemtime("tmp/full_list.txt"))."<br> \n";

#these are going to be adjusted to client time
$today=time()+$_GET['timediff'];
echo "This page loaded: ".date("D, d-M-Y, H:i:s",$today)."<br> \n";

$datetime1=new datetime(date("D, d-M-Y, H:i:s",filemtime("tmp/full_list.txt")));
$datetime2=new datetime(date("D, d-M-Y, H:i:s",time()));
$interval=date_diff($datetime1,$datetime2);
$hours=$interval->format('%h')*1+$interval->format('%a')*24;

echo "Age of the data file at page load: ".$interval->format($hours.':'.'%i'.':'.'%s').".<br> \n";
echo "<a href='crawler.php'>Update file</a><br> \n <br> \n";

$fp = fopen("tmp/full_list.txt", "r");
$entry=array();
$courses=array();
$time_next_asked=array();
if ($fp){
	$k=0;
	while (($line = fgets($fp, 4096)) !== false){
		#$fields = array ('course','level','text1','text2','ask_next');
		#added new fields
		$fields = array ('course', 'level','text1','text2','ask_next','asknextdate','askedlast','interval');
		$entry[$k] = array_combine ( $fields, explode ( "|", $line ) );
		#adjust fields ##TODO Problem: fields will get overwritten on fresh load!
		#add interval
		if($entry[$k]['askedlast']!="unknown" && $entry[$k]['interval']=="unknown"){
			$entry[$k]['interval']=round($entry[$k]['asknextdate']-$entry[$k]['askedlast']/(3600*24));
		}
		#add asklast
		if($entry[$k]['ask_next']=="now" || $entry[$k]['asknextdate'] < $today){
			$entry[$k]['askedlast']=$today;
		}
		##

		$courses[$k]=$entry[$k]['course'];
		$time_next_asked[$k]=$entry[$k]['ask_next'];
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
function makecolors($n){
	global $color_array;
	$c_size=sizeof($color_array);
	if($n>$c_size){
		for($i=0;$i=$n-$c_size;$i++){
			array_push($color_array, rnd_color());
		}
	}
	return $color_array;
}
function random_color_part() {
    return str_pad( dechex( mt_rand( 0, 255 ) ), 2, '0', STR_PAD_LEFT);
}
function rnd_color(){
    return "#".random_color_part() . random_color_part() . random_color_part();
}

function sortValues($list){
	global $minutes_array,$hours_array,$days_array,$num_of_courses,$course_size,$days_labels;
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
			$asknext=$list[$i]['ask_next'];
			#put values into the days1 array
			if(strpos($asknext,"day")!=FALSE){
				array_push($days1[$key],getNumber($asknext,"a day"));
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
	
	#loop through courses
	$i=0;
	$previous_value=0;
	foreach($course_size as $key=>$value){
		$minutes[$key]=array();
		$hours[$key]=array();
		$days[$key]=array();
		for(;$i<$value+$previous_value;$i++){
			$asknext=$list[$i]['ask_next'];
			#put values into the minutes array
			#echo $asknext."[".strpos($asknext,"now")."]<br> \n";
			if(strpos($asknext,"now").""=="0"){array_push($minutes[$key],0);}
			if(strpos($asknext,"minute")!=FALSE){array_push($minutes[$key],getNumber($asknext,"a minute"));}
			#put values into the hours array
			if(strpos($asknext,"hour")!=FALSE){array_push($hours[$key],getNumber($asknext,"an hour"));}
			#put values into the days array
			if(strpos($asknext,"day")!=FALSE){array_push($days[$key],getNumber($asknext,"a day"));}
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
		for($k=1;$k<=$maxdays;$k++){
			array_push($days_array[$key],0);
			foreach($days[$key] as $nkey=>$nvalue){
				if($nvalue==$k){
					$days_array[$key][$k]++;
				}
			}
		}
	}
}

function getNumber($string,$one){
	$number=filter_var($string, FILTER_SANITIZE_NUMBER_INT);
	if(strpos($string,$one)!=FALSE){$number=1;}
	return $number;
}
function makeScale($num){
	if($num<=20){$scale=20;}
	elseif($num>20 && $num <= 100){$scale=100;}
	elseif($num>100 && $num <= 200){$scale=200;}
	elseif($num>200 && $num <= 300){$scale=300;}
	elseif($num>300 && $num <= 400){$scale=400;}
	elseif($num>400 && $num <= 500){$scale=500;}
	elseif($num>500 && $num <= 1000){$scale=1000;}
	elseif($num>1000 && $num <= 5000){$scale=5000;}
	elseif($num>5000){$scale=20000;}
	return $scale;
}
function drawGraph($array,$labels,$xlabel){
	#break apart dataset differently #transform
	foreach($array as $k1 => $v1){
		foreach($v1 as $k2 => $v2){
			$newArray[$k2][$k1] = $v2;
		}
	}
	$array=$newArray;
	$maxsum=0;
	#this needs to happen after rearrangement
	foreach ($array as $line){
		$sum=0;
		foreach($line as $value){
			$sum+=$value;
		}
		if($sum>$maxsum){
			$maxsum=$sum;
		}
	}	
	$xmax=sizeof($labels);
	$ymax=makeScale($maxsum);
	$GraphObject='<div style="position:relative;left:30px;height:220px;">'." \n";
	$GraphObject.=drawXAxisLabel($xlabel, $xmax, $ymax);
	$GraphObject.=drawYAxis($ymax);
	$i=0;
	
	
	
	foreach($array as $dataset){
		$GraphObject.=drawData($dataset,$i,$ymax,$labels[$i]);
		$i++;
	}	
	$GraphObject.="</div> \n";
	echo $GraphObject;
}
function drawData($array,$pos,$ymax,$label){
	global $colors;
	$factor=200/$ymax;
	$sum=getSum($array);
	#DataObject
	$DataObject='<div style="position:absolute;left:'.(getOffset($ymax)+$pos*50).'px;top:199px;">'." \n";
	#xline
	$DataObject.='<div style="position:absolute;left:0px;width:50px;height:1px;background-color:#000000"></div>'." \n";
	#container
	$DataObject.='<div style="position:absolute;bottom:-1px;left:15px;width:20;height:'.round($factor*$sum).'px;border:1px solid black;">'." \n";
	#dataset
	$i=0;
	foreach($array as $key=>$value){
		$DataObject.='<div style="position:static;background-color:'.$colors[$i].';height:'.round($value*$factor).'px;width:20px;">'."</div> \n";
		$i++;
	}
	#label
	$DataObject.='<div style="position:absolute;bottom:'.round($sum*$factor).'px;left:0px;">'.$sum."</div> \n";
	#axis label
	$DataObject.='<div style="position:absolute;">'.$label."</div> \n";
	$DataObject.="</div> \n";
	$DataObject.="</div> \n";
	return $DataObject;
}

function getSum($array){
	$sum=0;
	foreach($array as $value){
		$sum+=$value;
	}
	return $sum;
}
function getOffset($ymax){
	$digit_width=strlen($ymax."");
	$offset=$digit_width*10+18;
	return $offset;
}
function drawYAxis($max){
	$AxisObject_width=getOffset($max);
	$LabelPos=getOffset($max)-25;
	$AxisObject='	<div style="position:absolute;top:0px;left:0px;height:200px;width:'.$AxisObject_width.'px">'." \n";
	#line
	$AxisObject.='		<div style="position:absolute;top:0px;right:5px;height:200px;width:1px;background-color:#000000;"></div>'." \n";
	#ticks
	$AxisObject.='		<div style="position:absolute;top:0px;right:0px;height:1px;width:5px;background-color:#000000;"></div>'." \n";
	$AxisObject.='		<div style="position:absolute;top:50px;right:0px;height:1px;width:5px;background-color:#000000;"></div>'." \n";
	$AxisObject.='		<div style="position:absolute;top:100px;right:0px;height:1px;width:5px;background-color:#000000;"></div>'." \n";
	$AxisObject.='		<div style="position:absolute;top:150px;right:0px;height:1px;width:5px;background-color:#000000;"></div>'." \n";
	$AxisObject.='		<div style="position:absolute;top:199px;right:0px;height:1px;width:5px;background-color:#000000;"></div>'." \n";
	#numbers
	$AxisObject.='		<div style="position:absolute;top:-10px;right:10px;">'.$max.'</div>'." \n";
	$AxisObject.='		<div style="position:absolute;top:40px;right:10px;">'.round($max*0.75).'</div>'." \n";
	$AxisObject.='		<div style="position:absolute;top:90px;right:10px;">'.round($max*0.5).'</div>'." \n";
	$AxisObject.='		<div style="position:absolute;top:140px;right:10px;">'.round($max*0.25).'</div>'." \n";
	$AxisObject.='		<div style="position:absolute;top:189px;right:10px;">0</div>'." \n";
	#axis label
	$AxisObject.='		<div style="position:absolute;top:90px;right:'.$LabelPos.'px;-moz-transform: rotate(-90deg);s-transform: rotate(-90deg);-o-transform: rotate(-90deg);-ms-transform: rotate(-90deg);transform: rotate(-90deg);">Items</div>'." \n";
	$AxisObject.="	</div> \n";
	return $AxisObject;
}
function drawXAxisLabel($label,$xmax,$ymax){
	$datawidth=15;
	$pos=getOffset($ymax)+$xmax*$datawidth;
	$AxisObject='	<div style="position:absolute;top:220px;left:'.$pos.'px;">'." \n";
	$AxisObject.=$label;
	$AxisObject.="	</div> \n";
	return $AxisObject;
}
?>
<h2>Next Hour</h2>
<?php
drawGraph($minutes_array,$minutes_labels,"Minutes");
?>
<br>
<h2>Next 24 Hours</h2>
<?php
drawGraph($hours_array,$hours_labels,"Hours");
?>
<h2>Everything</h2>
<?php
drawGraph($days_array,$days_labels,"Days");
?>

<br>


<h2>Raw Data</h2>
<textarea cols="140" rows="10">
<?php
$filtered_data=file_get_contents("tmp/full_list.txt");
$filename = "tmp/full_list.txt";
$fp = fopen($filename, "r");
#$filtered_data="Course title|level|text 1|text2|ask next in|ask next date (seconds)|asked last date (seconds)|current interval (days) \n";
$filtered_data="Course title|level|text 1|text2|ask next in \n";
while (($line = fgets($fp, 4096)) !== false){
	$line_array=explode("|",$line);
	$ignore=$line_array[4];
	if($ignore!="Ignored"){
#		$filtered_data.=$line;
		$filtered_data.=$line_array[0]."|".$line_array[1]."|".$line_array[2]."|".$line_array[3]."|".$line_array[4]." \n";
	}
}
fclose($fp);
echo $filtered_data;
?>
</textarea>

</body>
</html>
