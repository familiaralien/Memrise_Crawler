<html>
	<head>
	<title>Memrise Phrase Book</title>
	<meta charset="utf-8">
	</head>
<body>
<?php
	echo "This page loaded: ".date("D, d-M-Y, H:i:s",time())."<br> \n";
	$datetime1=new datetime(date("D, d-M-Y, H:i:s",filemtime("tmp/full_list.txt")));
	$datetime2=new datetime(date("D, d-M-Y, H:i:s",time()));
	$interval=date_diff($datetime1,$datetime2);
	$hours=$interval->format('%h')*1+$interval->format('%a')*24;

	echo "Age of the data file at page load: ".$interval->format($hours.':'.'%i'.':'.'%s').".<br> \n";
	echo "<a href='crawler.php'>Update file</a><br> \n <br> \n";


	//load up the memrise data, in a nice OOP way.
	require_once('models/memrise.php');
	$memrise = new Memrise;
	$memrise->read_temp_file();
	
	//go through and print out phrases that we've learnt
	foreach($memrise->courses as $course)
	{
		echo '<h2>'. $course->details['course_name'] .'</h2>';
		$level = 0;
		foreach($course->phrases as $phrase)
		{
			if($level != $phrase['level'])
			{
				echo '&nbsp;<br />';
				echo ' ----- <strong> Level '. $phrase['level'] .'</strong> ----- <br />';
				$level = $phrase['level'];
			}
			echo '<strong>'. $phrase['text1'] .'</strong> '. $phrase['text2'] .'<br />';
		}
	}
	
?>
</body>
</html>
