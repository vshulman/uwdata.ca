<?php

// UW course prereqs calculation

include_once COMMON_PATH.'db.php';
include_once COMMON_PATH.'scraper_tools.php';
include_once COMMON_PATH.'simple_html_dom.php';
include_once COMMON_PATH.'Database.class.php';

define('WORD_TYPE_FACULTY', 1);
define('WORD_TYPE_CNUM', 2);

if( sizeof($argv) < 2 ) {
  $calendar_years = '20092010';
} else {
  $calendar_years = $argv[1];
}

if (!isset($calendar_urls[$calendar_years])) {
  echo "Unknown calendar years: $calendar_years\n";
  echo "Try something like 20092010 or 20042005\n";
  exit;
}

$calendar_url = $calendar_urls[$calendar_years];

$dbName = 'uwdata_'.str_replace('-', '', $calendar_years);

echo 'Calculating prereqs from the '.$calendar_years.' calendar year'."\n";
echo '  db: '.$dbName."\n";

///////////////////////////////////////////////////////////////////////////////////////////////////
// Now that we know which calendar year we're working with, let's ensure that the tables exist.
///////////////////////////////////////////////////////////////////////////////////////////////////
$db = new Database(DB_HOST, DB_USER, DB_PASS, $dbName);
$db->connect();

// Create the schemas.
foreach (explode(';', trim($schema, ';')) as $query) {
  if (trim($query)) {
    $db->query($query);
  }
}

function get_cid($acr, $num) {
  global $db;
  $results = $db->query('SELECT cid FROM courses WHERE faculty_acronym LIKE "'.mysql_escape_string($acr).'" AND course_number = "'.mysql_escape_string($num).'";');
  while ($row = mysql_fetch_assoc($results)) {
    return $row['cid'];
  }
  return null;
}

class course {
  public $fac = null;
  public $num = null;
  //public $cid = null;

  public function __construct($fac, $num) {
    $this->fac = $fac;
    $this->num = $num;
    //$this->cid = get_cid($fac, $num);
  }
}

$university_courses = array(
  'OAC German' =>    'HSGERMAN 11O',
  '4U German' =>     'HSGERMAN 11U',
  '4U Math' =>       'HSMATH 11U',
  'OAC Math' =>      'HSMATH 11O',
  '4U Spanish' =>    'HSSPANISH 11U',
  'OAC Spanish' =>   'HSSPANISH 11O',
  '4U Chemistry' =>  'HSCHEM 11U',
  'OAC Chemistry' => 'HSCHEM 11O',
  '4U Physics' =>    'HSPHYS 10U',
  'OAC Physics' =>   'HSPHYS 1O0',
  '4U Advanced Functions' =>   'HSFUNC 10U',
  '4U Calculus and Vectors' => 'HSCALC 10U',
  '4U Advanced Functions and Introductory Calculus' => 'HSFUNC 10U',
  '4U Calculus and Vectors' => 'HSCALC 10U',
  '4U Geometry and Discrete Mathematics' => 'HSGEO 10U',
);

  


function parse_part($part) {
  //echo 'Parsing part: '.$part."\n";
  // Let's start breaking down the reqs into logical units.
  // XXX ###(, ###, ###, ...)
  //    Example: PHARM 129, 131, 220
  //    Means: You must take all three courses.
  // XXX ### or ###
  //    Example: AFM 101 or 128
  //    Means: You must take one or the other.

  $operators = array();
  $state_machine = 0;
  $depth = 0;
  $brackets = array();
  $grouped_operators = array();
  $in_word = false;
  $word_buffer = '';
  $last_faculty = null;
  $course_group = array(); //groups courses that shape same operator
  $group_operator = 'and'; //operator applying to groups
  $last_operand = false;
  $last_number = null;
  $last_numerical_count = null;
  $is_pairing = false;
  $last_word_type = 0;

  $numbers = array(
    'one',
    'two',
    'three',
    'four',
    'five',
    'six',
    'seven',
    'eight',
    'nine'
  );

  $part = ' '.$part.' ';

  for ($ix = 0; $ix < strlen($part); ++$ix) {
    $letter = $part[$ix];
    if ($letter == '(') {
      ++$depth;
      $brackets []= $ix; //Add char position to end of $brackets
    } else if ($letter == ')') {
      --$depth;
      $start_bracket = array_pop($brackets);
      if (!$depth) { //if we just closed bracket and not at depth 0
        $sub_operators = parse_reqs(substr($part, $start_bracket + 1, $ix - $start_bracket - 1)); //parse inside the brackets
        $course_group = array_merge($course_group, $sub_operators);
      }
    } else if ($depth == 0) {
      if (eregi('[a-z0-9]', $letter)) { //we run into non-cap letter or #
        $word_buffer .= $letter;
        $in_word = true;
      } else if ($in_word) { 

        if ($letter == ',') {
          //echo "found a comma...";
          if ($last_operand) { //last operand in a list
            if (!empty($course_group)) { //we already have courses in our list
              $operators []= array_merge(array($group_operator), $course_group); // merge everything up until now
              $course_group = array();
            }
            $last_operand = false; //something must follow
          }
        } //end of comma

        $new_word_type = 0;
        if (strtolower($word_buffer) == 'or') { //if we ran into an or
          if ($last_operand && $group_operator != strtolower($word_buffer)) { //we had *and* operator before, this one is different
            // We've already added the last value for the previous operator, so let's
            // group everything up to this point.
            $course_group = array(array($group_operator, $course_group));
          }
          //echo "found an or operator\n";
          $group_operator = 'or';
          $last_operand = true;

        } else if (strtolower($word_buffer) == 'and') {
          //echo "found an and operator\n";
          $group_operator = 'and';
          $last_operand = true;

        } else if (ereg('^[A-Z]+$', $word_buffer)) {
          //echo 'found a faculty: '.$word_buffer."\n";
          if ($is_pairing && $last_word_type == WORD_TYPE_FACULTY) { //when faculty only appears once, CS230/MATH330
            $last_faculty = array_merge((array)$last_faculty, (array)$word_buffer); //array of faculties? huh?
            $is_pairing = false;
          } else {
            $last_faculty = $word_buffer;
          }
          $new_word_type = WORD_TYPE_FACULTY; //we just addressed a faculty

        } else if (ereg('^[0-9]{2,}[A-Z]?$', $word_buffer) && $letter != '%') { //need to address what's a course
          foreach ((array)$last_faculty as $faculty) {
            //echo 'found a course: '.$faculty.' '.$word_buffer."\n";
            $course_group []= new course($faculty, $word_buffer); //add course to group
          }

          if ($is_pairing) { //if course pair
            $course2 = array_pop($course_group);
            $course1 = array_pop($course_group);

            $course_group []= array(
              'pair',
              $course1,
              $course2
            );

            $is_pairing = false;
          }

          $new_word_type = WORD_TYPE_CNUM;

        } else if (in_array(strtolower($word_buffer), $numbers)) {
          $value = array_search(strtolower($word_buffer), $numbers) + 1;
          $last_number = $value; //such as 3 of..
          //echo 'found a number: '.$value."\n";

        } else if ($last_number && strtolower($word_buffer) == 'of') {  
          $last_numerical_count = $last_number;
          $last_number = null;

        } else {
          //echo 'found a word: '.$word_buffer."\n";

        }
        
        $last_word_type = $new_word_type;

        if ($letter == '/') {
          //echo "found a pairing operator\n";
          $is_pairing = true;
        }

        $word_buffer = '';
        $in_word = false;
      } //done that we're in a word (new word now?)
    }// done depth 0 actions
  } // done for loop

  if (!empty($course_group)) {
    if ($last_numerical_count) {
      $group_info = array($last_numerical_count); //ie they are connected by one of
    } else {
      $group_info = array($group_operator); //they are connected by operator
    }
    $operators []= array_merge($group_info, $course_group);
  }
  //echo "done\n";
  return $operators;
}

function parse_reqs($reqs) {
  $parts = explode(';', $reqs);
  //echo 'Parsing: '.$reqs."\n";
  foreach ($parts as &$xxx) {
    $xxx = trim($xxx);
  }
  unset($xxx);

  // Let's start breaking down the reqs into logical units.
  // XXX ###(, ###, ###, ...)
  //    Example: PHARM 129, 131, 220
  //    Means: You must take all three courses.
  // XXX ### or ###
  //    Example: AFM 101 or 128
  //    Means: You must take one or the other.

  $operators = array();
  foreach ($parts as $part) {
    $sub_operators = parse_part($part);
    $operators = array_merge($operators, $sub_operators);
  }

  return $operators;
}

// Extracts faculty restrictions, such as Not Open To... or "Open Only To"
function extract_faculty_restrictions($reqs) {
  $words_to_exclude = array("students","in","in the",".");
  $restrictions = array();

  if (strpos($reqs,"Not open to")) {
    preg_match('/Not open to (.+)/i',$reqs,$match);
    if (strpos(".",$match[1]))
      $match[1] = substr($match[1],0,strpos(".",$match[1])); 
    $match = trim(str_replace($words_to_exclude,"",$match[1]));
    $match = explode(" or ",$match);
    //echo "Not Open To:\n";
    foreach($match as &$restr) {
      $restr = trim($restr);
    }
    print_r($match);
   
   $restrictions["notopento"] = $match; 
  }

  if (strpos($reqs, "students only")) {
    preg_match('/;(.+?)students only/i',$reqs,$match); //when restrictions following ";" 
    if (empty($match[1]))
      preg_match('/(.+?)students only/i',$reqs,$match); //when restrictions appear in the beginning
    $match = trim(str_replace(array(",")," or ",$match[1])); //sometimes the "and" should also be replaced. need list of degrees to check against
    $match = explode(" or ",$match);
    foreach($match as &$restr) {
      $restr = trim($restr);
    }
    //echo "Only Open To:\n";
    //print_r($match);
   $restrictions["onlyopento"] = $match; //Should store in seperate table, trim
  }
  
  return $restrictions;
}

/* quick and dirty way of extracting course names from a string
 * when the relationship between them or context don't matter  */
function extract_courses($reqs) {
  $success = preg_match_all('/(([A-Z]{2,}+)\s[0-9]{2,3}[A-Z]{0,1})/',$reqs,$result);
  $antireqs = array();
  if ($success > 0) {
    $antireqs = $result[1];
    $split = preg_split('/[A-Z]{2,}+\s[0-9]{2,3}[A-Z]{0,1}/',$reqs);
    $items = array();
    for($ix = 1; $ix<sizeof($split); $ix++) { //for all sections, after we split about full course code
      $localAntireqs = array();
      preg_match_all('/[0-9]{2,3}[A-Z]{0,1}/',$split[$ix],$items); //match numbers without code
      foreach($items[0] as $nr) {
        $localAntireqs []= $result[2][$ix-1] . " " . $nr; //append course code to number
      }
      $antireqs = array_merge($antireqs,$localAntireqs);
    }
  }
  return $antireqs;
}

$results = $db->query('SELECT cid, antireq_desc, coreq_desc, prereq_desc, title, faculty_acronym, course_number FROM courses;');
while ($row = mysql_fetch_assoc($results)) {
  $row['prereq_desc'] = trim(str_replace('Prereq:', '', $row['prereq_desc']));
  $antireqs = trim(str_replace('Antireq:','',$row['antireq_desc']));
  $coreqs = trim(str_replace('Coreqs:','',$row['coreq_desc']));

  $reqs = $row['prereq_desc'];
  $cid = $row['cid'];
  if (ereg('[A-Z]{2,}', $reqs) || ereg('[A-Z]{2,}',$antireqs) || ereg('[A-Z]{2,}',$coreqs)) {

    // HACK: replacing highschool requirements with something that's treated like a course
    if ((strpos($reqs,'OAC' !== false)) || (strpos($reqs,'4U') !== false)) {
      //echo $reqs . "\n";
      $reqs = str_replace(array_keys($university_courses),$university_courses,$reqs);
      //echo $reqs . "\n";
    }
    $operators = parse_reqs($reqs);

    $faculty_restrictions = extract_faculty_restrictions($reqs,$cid);
    $antireqs = extract_courses($antireqs);
    $coreqs = extract_courses($coreqs);

    if (!empty($faculty_restrictions["notopento"])) {
      $db->query('DELETE FROM courses_restrictions WHERE cid='.$cid.' AND restriction_type=0');
      foreach($faculty_restrictions["notopento"] as $restriction) { //type 0
        $db->query('INSERT INTO courses_restrictions (cid,restriction_type,restriction_description) VALUES ('.$cid.',0,\''.mysql_escape_string($restriction).'\')');
      }
    }

    if (!empty($faculty_restrictions["onlyopento"])) {
      $db->query('DELETE FROM courses_restrictions WHERE cid='.$cid.' AND restriction_type=1');
      foreach($faculty_restrictions["onlyopento"] as $restriction) { //type 1
        $db->query('INSERT INTO courses_restrictions (cid,restriction_type,restriction_description) VALUES ('.$cid.',1,\''.mysql_escape_string($restriction).'\')');
      }
    }
    
    //echo "logic:\n";
   // echo json_encode($operators)."\n";
    $fields = 0;
    $db_query = 'UPDATE courses SET ';
    if (!empty($operators)) {
      $db_query .= ' prereqs = "'.mysql_escape_string(json_encode($operators)) . '" ,';
      $fields++;
    }
    if (!empty($coreqs)) {
      $db_query .= 'coreqs="'. mysql_escape_string(json_encode($coreqs)) . '" ,';
      $fields++;
    }
    if (!empty($antireqs)) {
      $db_query .= 'antireqs="'. mysql_escape_string(json_encode($antireqs)) . '" ,';
      $fields++;
    }
    if ($db_query[strlen($db_query)-1] == ",")
      $db_query = substr($db_query,0,strlen($db_query)-2);

    $db_query .= ' WHERE cid="'.$cid.'"';

    if ($fields > 0)
      $db->query($db_query);
   // $db->query('UPDATE courses SET prereqs = "'.mysql_escape_string(json_encode($operators)).'",coreqs="'.
   // mysql_escape_string(json_encode($coreqs)) .'",antireqs="'.
   // mysql_escape_string(json_encode($antireqs)) .'" WHERE cid="'.$cid.'";');
  }
}

$db->close();

?>
