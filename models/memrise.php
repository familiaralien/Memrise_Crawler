<?php

class Memrise { 
	public $courses = array();
    public $aMemberVar = 'aMemberVar Member Variable'; 
    public $aFuncName = 'aMemberFunc'; 
    
    
   /**
    * Reads the data in our temp file, and saves it in this class
    */
    function read_temp_file() { 
        $fp = fopen("tmp/full_list.txt", "r");
		$k=0;
		$entry=array();
		$time_next_asked=array();
		if ($fp){
			while (($line = fgets($fp, 4096)) !== false){
				$fields = array ('course', 'level','text1','text2','ask_next');
				$entry = array_combine ( $fields, explode ( "|", $line ) );
				//$courses[$k]=$entry[$k]['course'];
				$time_next_asked[$k]=$entry[$k]['ask_next'];
				$k++;
				$course_name = $entry['course'];
				//add it to the class structure
				$course = $this->get_course($course_name);
				if($course != false)
				{
					//add the phrase to the course
					$this->courses[$course_name]->add_phrase($entry);
				}
				else
				{ 
					//create a new course
					$this->courses[$course_name] = new Course($entry);
				}
			}
		}
    }
    
    function get_course($course_name)
    {
    	if(array_key_exists($course_name, $this->courses))
    	{
    		return $this->courses[$course_name];
    	}
    	else
    	{
    		return false;
    	}
    }
} //end of Memrise class


class Course {
	public $details = array();
	public $phrases = array();

	/**
	 * When a new course is created, this function runs
	 */
	public function __construct($new_phrase=null)
	{
		if($new_phrase != null)
		{
			$this->add_phrase($new_phrase);
			$this->details['course_name'] = $new_phrase['course'];
		}
	}
	
	public function add_phrase($new_phrase)
	{
		$this->phrases[ $new_phrase['text1'] ] = $new_phrase;
		$this->details['phrases_learning'] = count($this->phrases) - 1;
	}

}


?>
