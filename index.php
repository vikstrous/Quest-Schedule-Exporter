<?php
/**
 * Quest Schedule Exporter
 * 
 * Takes your quest schedule and gives you an iCalendar file!
 * 
 * by Viktor Stanchev
 * 
 * This code is chaos and crap. Just a hack... just a hack...
 * Why did I use "_uw_StripExtraSpace" instead of just string replace? This file is 300 lines too long.
 * 
 * Quest does this crazy thing where for some people it's in 24 hour format with the month and day switched in the
 * date field... Don't know why, but I search for AM or PM to determine if it's 12 hour time with mm/dd/yyyy or
 * 24 hour time with dd/mm/yyyy
 * 
 * todo:
 *  - formatting
 *  - rename functions
 *  - add comments
 *  - write api documentation? not that anyone would use it as an API... how do you get this formatted
 * text without doing a ctrl a ctrl c? mabe jquery's .text()?
 * */

$input['data'] = '';
$input['summary'] = '@code @type in @location';
$input['description'] = '@code-@section: @name (@type) in @location with @prof';

if(isset($_POST['data'])){
  $input['data'] = $_POST['data'];
}
if(isset($_POST['summary'])){
  $input['summary'] = $_POST['summary'];
}
if(isset($_POST['summary'])){
  $input['description'] = $_POST['description'];
}

if($input['data'] != ''){
  header("Content-Type: text/Calendar");
  header("Content-Disposition: inline; filename=schedule.ics");
  echo uw_waterloo_quest_schedule($input['data'], 'icalendar', $input['summary'], $input['description']);
  die();
}

$placeholders = array(
    '@code',
    '@section',
    '@name',
    '@type',
    '@location',
    '@prof',
);

// needs to return DD/MM/YYYY
function normalize_date($time_string, $ampm){
  if(!$ampm){
    $arr = explode('/', $time_string);
    // YYYY/MM/DD
    if(count($arr[0]) == 4) {
      $tmp = $arr[2];
      $arr[2] = $arr[1];
      $arr[1] = $arr[0];
      $arr[0] = $tmp;
      return implode('/', $arr);
    } else {
      // MM/DD/YYYY
      $tmp = $arr[1];
      $arr[1] = $arr[0];
      $arr[0] = $tmp;
      return implode('/', $arr);
    }
  } else {
    return $time_string;
  }
}

//This will parse the waterloo schedule
function uw_waterloo_quest_schedule($input, $format, $summary = '@code @type in @location', $description = '@code-@section: @name (@type) in @location with @prof') {
  
  //icalendar day formats
  $arr_days = array('m'=>'MO', 't'=>'TU', 'w'=>'WE', 'h'=>'TH', 'f'=>'FR', 's'=>'SA', 'u'=>'SU');
  $arr_num_days = array('m'=>1, 't'=>2, 'w'=>3, 'h'=>4, 'f'=>5, 's'=>6, 'u'=>7);
        
  //info that Quest gives us and schedule field it's useful for
  $code='';
  $name='';
  $section='';
  $type='';
  $days='';
  $time_start='';
  $time_end='';
  $location='';
  $prof='';
  $date_start='';
  $date_end='';
  
  //the result of this madness will be stored here
  $ical_array=array();
  $js_array=array();
  
  //start at the beginning of the string
  $pos=0;
  //this is how long our quest schedule is
  $total_length=strlen($input);
  
  //start the algorithm
  while ( $pos < $total_length && $pos >= 0) {
    //assume we didn't find anything
    $found_what_we_need=false;
    
    //the regex will match 1 of 3 completely different things - title, body, or partial body
    //we have to process what we get in order
    
    //detect if it's 24h time or 12h time!
    $ampm = false;
    if(preg_match('/(AM)|(PM)/', $input)){
      $ampm = true;
    }
    
    $time = $ampm ?
       '([1]{0,1}\d\:[0-5]\d[AP]M)\ -\ 
        ([1]{0,1}\d\:[0-5]\d[AP]M)\s+'
      :'(\d{2}\:\d{2})\ -\ (\d{2}\:\d{2})\s+';
    
    $regex = '/
    (
      (\w{2,5}\ \w{3,4})\ -\                        #code
      ([^\r\n]+)                                    #name
    )
    |
    (
      \d{4}\s+
      (\d{3})\s+
      (\w{3})\s+
      ([MThWF]{0,6})\s+
      '.$time.'
      ([\w\ ]+\s+[0-9]{1,5}[A-Z]?)\s+
      ([\w\ \-\,\r\n]+)\s+
      (\d{2,4}\/\d{2,4}\/\d{2,4})\ -\ 
      (\d{2,4}\/\d{2,4}\/\d{2,4})
    )
    |
    (
      ([MThWF]{0,6})\s+
      '.$time.'
      ([\w\ ]+\s+[0-9]{1,5}[A-Z]?)\s+
      ([\w\ \-\,\r\n]+)\s+
      (\d{2,4}\/\d{2,4}\/\d{2,4})\ -\ 
      (\d{2,4}\/\d{2,4}\/\d{2,4})
    )/x';
    
    
    if(preg_match($regex, $input, $matches, PREG_OFFSET_CAPTURE, $pos)){
      //check what we found by seeing where the regex stopped
      
      $number=count($matches);
      switch($number){
        //found title
        case 4:
          
          //get the strings and put them in the variables
          $code=$matches[2][0];
          $name=trim($matches[3][0]);
          
          //reset the rest of the variables so if something goes wrong, the error doesn't perpetuate
          $section='';
          $type='';
          $days='';
          $time='';
          $location='';
          $prof='';
          
          break;
          
        //found body
        case 14:
          $found_what_we_need=true;
          
          $section    = $matches[5][0];
          $type       = $matches[6][0];
          $days       = strtolower(str_replace('Th','h',$matches[7][0]));
          $time_start = _uw_build_time_string($matches[8][0], $ampm);
          $time_end   = _uw_build_time_string($matches[9][0], $ampm);
          $location   = trim(_uw_StripExtraSpace($matches[10][0]));
          //$prof =substr(strrchr(' '.trim($matches[11][0]), ' '),1);//get only the last name
          $prof       = trim($matches[11][0]);
          $date_start = strtotime(normalize_date($matches[12][0], $ampm));
          $date_end   = strtotime(normalize_date($matches[13][0], $ampm));
          
          break;
          
        //found partial body
        case 22:
          $found_what_we_need=true;
          
          $days       = strtolower(str_replace('Th','h',$matches[15][0]));
          $time_start = _uw_build_time_string($matches[16][0], $ampm);
          $time_end   = _uw_build_time_string($matches[17][0], $ampm);
          $location   = trim(_uw_StripExtraSpace($matches[18][0]));
          //$prof=substr(strrchr(' '.trim($matches[17][0]), ' '),1);//get only the last name
          $prof       = trim($matches[19][0]);
          $date_start = strtotime(normalize_date($matches[20][0], $ampm));
          $date_end   = strtotime(normalize_date($matches[21][0], $ampm));
          
          break;
      }
      
      //move to the end of what was matched and continue
      $pos=$matches[0][1]+strlen($matches[0][0]);
    } else $pos=-1; //this exits the while because we are done if we can't find anything we're looking for
    
    //add to the array if we have all the info.
    if($found_what_we_need && $days != ''){
      
      if($format == 'icalendar'){
        //format the days of the week
        $formatted_days = '';
        $number_days = array();
        foreach(str_split($days) as $day){
          $formatted_days .= ','.$arr_days[$day];
          $number_days[] = $arr_num_days[$day];
        }
        $formatted_days = substr($formatted_days,1);
        
        //move the start date to the first valid day of the week
        while(!in_array(date('N',$date_start), $number_days)){
          $date_start = strtotime('+1 day', $date_start);
        }
        
        //build the result
        $string_data = array(
          '@code' => $code,
          '@section' => $section,
          '@name' => $name,
          '@type' => $type,
          '@location' => $location,
          '@prof' => $prof,
        );
        $result = array(
          'summary'     =>  strtr($summary, $string_data),
          'description' =>  strtr($description, $string_data),
          'start'       =>  date('Ymd',$date_start).'T'.$time_start,
          'end'         =>  date('Ymd',$date_start).'T'.$time_end,
          'location'    =>  $location,
        );
        //this information is only relevant for repeating events
        if($date_start != $date_end){
          $result['until'] = date('Ymd',$date_end).'T'.$time_end;
          $result['days'] = $formatted_days;
        }
        //escape commas
        //see rfc2445 section 4.1
        $result['description'] = str_replace("\r\n", "\r\n ", str_replace(',', '\,', $result['description']));
        $result['summary'] = str_replace("\r\n", "\r\n ", str_replace(',', '\,', $result['summary']));
        
        //add this class to the list
        $ical_array[] = $result;
      } elseif ($format == 'js'){
        $location = explode(' ', $location);
        $result = array(
          'code' => $code,
          'name' => $name,
          'section' => $section,
          'type' => $type,
          'days' => $days,
          'time_start' => $time_start,
          'time_end' => $time_end,
          'location' => $location[0],
          'prof' => $prof,
          'date_start' => $date_start,
          'date_end' => $date_end,
        );
        $js_array[] = $result;
      }
    }
  }
  
  if($format == 'icalendar'){
    $result_str = '';
    foreach($ical_array as $class){
      $result_str .= "\r\nBEGIN:VEVENT";
      $result_str .= "\r\nDTSTART:$class[start]";
      $result_str .= "\r\nDTEND:$class[end]";
      if(array_key_exists('until', $class) && $class['until']){
        $result_str .= "\r\nRRULE:FREQ=WEEKLY;UNTIL=$class[until];WKST=SU;BYDAY=$class[days]";
      }
      $result_str .= "\r\nSUMMARY:$class[summary]";
      $result_str .= "\r\nLOCATION:$class[location]";
      $result_str .= "\r\nDESCRIPTION:$class[description]";
      $result_str .= "\r\nEND:VEVENT";
    }
    $result_str = "BEGIN:VCALENDAR".
                  "\r\nVERSION:2.0".
                  "\r\nPRODID:-//hacksw/handcal//NONSGML v1.0//EN".
                  $result_str.
                  "\r\nEND:VCALENDAR";
    return $result_str;
  } elseif ($format='js') {
    return $js_array;
  }
}

function _uw_StripExtraSpace($s){
  $newstr = '';
  for($i = 0; $i < strlen($s); $i++)
  {
    $newstr = $newstr . substr($s, $i, 1);
    if(substr($s, $i, 1) == ' ')
      while(substr($s, $i + 1, 1) == ' ')
        $i++;
  }
  return $newstr;
}

function _uw_build_time_string($time, $ampm){
  if($ampm){
    $pm = strpos($time, 'PM') !== false;
    $time = substr($time, 0, -2);
    $parts = explode(':', $time);
    if(strlen($parts[0]) != 2){
      $parts[0] = '0'.$parts[0];
    }
    if(strlen($parts[1]) != 2){
      $parts[1] = '0'.$parts[1];
    }
    $time = implode($parts).'00';
    if($pm && $parts[0] != 12){
      $time += 120000;
    }
    return $time;
  } else {
    $parts = explode(':', $time);
    return implode($parts).'00';
  }
}

?>
<style type="text/css">
  label { cursor:pointer; }
  .hint { color:#666666; margin-top:10px; }
  .footer { margin-top:20px; }
  body { margin: 20px; }
  .label { margin-top:10px; }
</style>

<h1>Quest Schedule Exporter</h1>
<p>Allows UWaterloo students to export their schedule from Quest! The result is an iCalendar file you can import anywhere.</p>
<h2>How to use:</h2>
<ol>
<li>Login at https://quest.pecs.uwaterloo.ca/psp/SS/?cmd=login</li>
<li>Click Enroll</li>
<li>Choose your term and click Continue</li>
<li>It should be in "list view". Copy the whole page by pressing ctrl+A and then ctrl+C</li>
<li>Come back here and paste everything into the text field by pressing ctrl+V</li>
<li>Click Submit</li>
<li>Use the iCalendar file to import your schedule into any calendar software such as google calendar, outlook, etc.</li>
</ol>

<div class="form">
  <form action="" method="POST">
  <div class="label">
    <label for="data">Paste Area:</label>
  </div>
  <div>
    <textarea name="data" id="data"><?php echo htmlentities($input['data']);?></textarea>
  </div>
  <div class="label">
    <label for="summary">Summary:</label>
  </div>
  <div>
    <input type="text" class="summary" name="summary" id="summary" size="100" value="<?php echo htmlentities($input['summary']);?>" />
  </div>
  <div class="label">
    <label for="description">Description:</label>
  </div>
  <div>
    <input type="text" class="description" name="description" id="description" size="100" value="<?php echo htmlentities($input['description']);?>"/>
  </div>
  <div class="hint">
    Possible placeholders: <?php echo implode(', ',$placeholders);?>
  </div>
  <div>
    <input type="submit" value="Export!" />
  </div>
  </form>
</div>
<div class="footer">
Made by <a href="http://viktorstanchev.com"/>Viktor Stanchev</a>. See <a href="http://wattools.com"/>more tools</a>!
</div>
