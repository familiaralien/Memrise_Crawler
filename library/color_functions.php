<?php
$color_array = array(
	"#f16c23",
	"#cc1e44",
	"#851a54",
	"#295a9c",
	"#3aa9d7",
	"#C0C0C0",
	"#808080",
	"#000000",
	"#ADD8E6",
	"#800080",
	"#A52A2A",
	"#FFFFFF",
	"#FFFF00",
	"#800000",
	"#00FF00",
	"#008000",
	"#FF00FF",
	"#808000",
	"#FF0000",
	"#00FFFF",
	"#0000A0",	
	"#FFA500",
	"#0000FF",
);

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
?>