<?php
function getOffset($ymax){
	$digit_width=strlen($ymax."");
	$offset=$digit_width*10+18;
	return $offset;
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

function drawData($array,$pos,$ymax,$label){
	global $colors;
	$datawidth=50;
	$factor=200/$ymax;
	$sum=getSum($array);
	if($sum==0){$border=0;}#making zero bars invisible;
	else{$border=1;}
	#DataObject
	$DataObject='<div style="position:absolute;left:'.(getOffset($ymax)+$pos*$datawidth).'px;top:199px;">'." \n";
	#xline
	$DataObject.='<div style="position:absolute;left:0px;width:'.$datawidth.'px;height:1px;background-color:#000000"></div>'." \n";
	#container
	$DataObject.='<div style="position:absolute;bottom:-1px;left:15px;width:20;height:'.round($factor*$sum).'px;border:'.$border.'px solid black;">'." \n";
	#dataset
	$i=0;
	foreach($array as $key=>$value){
		$DataObject.='<div style="position:static;background-color:'.$colors[$i].';height:'.round($value*$factor).'px;width:20px;">'."</div> \n";
		$i++;
	}
	#label
	$DataObject.='<div style="position:absolute;bottom:'.round($sum*$factor).'px;left:0px;">'.$sum."</div> \n";
	#axis label
	$DataObject.='<div style="position:absolute;"><font size="2">'.$label."</font></div> \n";
	$DataObject.="</div> \n";
	$DataObject.="</div> \n";
	return $DataObject;
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
	$AxisObject='	<div style="position:absolute;top:220px;left:'.$pos.'px;"><font size=2>'." \n";
	$AxisObject.=$label;
	$AxisObject.="	</font></div> \n";
	return $AxisObject;
}
?>