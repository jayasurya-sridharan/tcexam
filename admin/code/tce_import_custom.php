<?php
//============================================================+
// File name   : tce_import_custom.php
// Begin       : 2008-12-01
// Last Update : 2009-11-10
//
// Description : Class to import questions from a custom-format file.
//
// Note: To avoid timeout, please setup a very high limit
//       for memory (512MB) and execution time (300) on php.ini
//
// Author: Nicola Asuni
//
// (c) Copyright:
//               Nicola Asuni
//               Tecnick.com LTD
//               www.tecnick.com
//               info@tecnick.com
//
// License:
//    Copyright (C) 2004-2010  Nicola Asuni - Tecnick.com LTD
//    See LICENSE.TXT file for more information.
//============================================================+

/**
 * @file
 * Class to import questions from a custom file.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2000-12-01
 */

// customize this file to import questions from your custom format file.

function startsWith($haystack, $needle)
{
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

function import_questions($questions, $module, $topic_ids){
  global $l, $db;
  require_once('../config/tce_config.php');
  require_once('../../shared/code/tce_functions_auth_sql.php');

  foreach($questions as $q){
    foreach($q["Topics"] as $t){
      $sql = 'START TRANSACTION';
      if (!$r = F_db_query($sql, $db)) {
          F_display_db_error();
      }
      // insert question
      $question_difficulty = 1;
      $question_enabled = 1;
      $question_position = "NULL";
      $question_timer = 0;
      $question_fullscreen = 0;
      $question_inline_answers = 0;
      $question_auto_next = 0;
      $sql = 'INSERT INTO '.K_TABLE_QUESTIONS.' (
        question_subject_id,
        question_description,
        question_explanation,
        question_type,
        question_difficulty,
        question_enabled,
        question_position,
        question_timer,
        question_fullscreen,
        question_inline_answers,
        question_auto_next
      ) VALUES (
        '.$topic_ids[$t].',
        \''.$q["Question"].'\',
        \''.$q["Explanation"].'\',
          '.$q["Question Type"].',
          '.$question_difficulty.',
          '.$question_enabled.',
          '.$question_position.',
          '.$question_timer.',
          '.$question_fullscreen.',
          '.$question_inline_answers.',
          '.$question_auto_next.'
      )';

      // echo "<pre>";
      // echo $sql;
      // echo "</pre>";

      if (!$r = F_db_query($sql, $db)) {
          F_display_db_error(false);
      } else {
          // get new question ID
          $current_question_id = F_db_insert_id($db, K_TABLE_QUESTIONS, 'question_id');
          // if (K_DATABASE_TYPE == 'MYSQL') {
          //     $questionhash[] = md5(strtolower(substr($topic_ids[$t].$q["Question"], 0, $strkeylimit)));
          // }
      }
      $sql = 'COMMIT';
      if (!$r = F_db_query($sql, $db)) {
          F_display_db_error();
      }

      foreach($q["Answers"] as $a){
        $sql = 'START TRANSACTION';
        if (!$r = F_db_query($sql, $db)) {
            F_display_db_error();
        }
        $answer_explanation = "NULL";
        $answer_enabled = 1;
        $answer_position = "NULL";
        $answer_keyboard_key = "NULL";
        $sql = 'INSERT INTO '.K_TABLE_ANSWERS.' (
                  answer_question_id,
                  answer_description,
                  answer_explanation,
                  answer_isright,
                  answer_enabled,
                  answer_position,
                  answer_keyboard_key
                ) VALUES (
                  '.$current_question_id.',
                  \''.$a[1].'\',
                  '.$answer_explanation.',
                  '.(int)($a[0]).',
                  '.$answer_enabled.',
                  '.$answer_position.',
                  '.$answer_keyboard_key.'
                  )';

        // echo "<pre>";
        // echo $sql;
        // echo "</pre>";
        if (!$r = F_db_query($sql, $db)) {
            F_display_db_error(false);
            F_db_query('ROLLBACK', $db);
        } else {
            // get new answer ID
            $current_answer_id = F_db_insert_id($db, K_TABLE_ANSWERS, 'answer_id');
        }

        $sql = 'COMMIT';
        if (!$r = F_db_query($sql, $db)) {
            F_display_db_error();
        }
      }

      echo "<hr>";

    }
  }

  echo "<pre style='font-size:30px; color: red'>";


  $sql = 'SELECT COUNT(question_id) as a FROM '.K_TABLE_QUESTIONS. ' LIMIT 1';
  if ($r = F_db_query($sql, $db)) {
    if ($m = F_db_fetch_array($r)) {
      echo "There are currently " . $m["a"] .  " questions in the database" . PHP_EOL;
      echo "</pre>"; return;
    }
  } else {
    F_display_db_error();
    echo "</pre>"; return;
  }
}

function F_EasyTextQuestionImporter($filepath)
{
    global $l, $db;
    require_once('../config/tce_config.php');
    require_once('../../shared/code/tce_functions_auth_sql.php');
    echo "<pre style='font-size:20px; color: red'>";


    $sql = 'SELECT COUNT(question_id) as a FROM '.K_TABLE_QUESTIONS. ' LIMIT 1';
    if ($r = F_db_query($sql, $db)) {
      if ($m = F_db_fetch_array($r)) {
        echo "There are currently " . $m["a"] .  " questions in the database" . PHP_EOL;
      }
    } else {
      F_display_db_error();
      echo "</pre>"; return;
    }

    $textfile = fopen($filepath, 'r');
    $topics = array();
    $VERIFIED = false;

    $verified_line = stream_get_line($textfile, PHP_INT_MAX, "\n");
    preg_match('/@Verified[\s]*\[(.*)\]/', $verified_line, $verified_matches);
    if(count($verified_matches) > 1){
      if(strtolower($verified_matches[1]) == "true"){
        $VERIFIED = true;
        echo "Input file VERIFIED! Questions will be imported" . PHP_EOL;
      }
      else{
        echo "Input file NOT VERIFIED! Output is for verification only." . PHP_EOL;
      }
    } else{
      echo "Verified flag missing!" . PHP_EOL;
      echo "</pre>"; return;
   }

    $module_line = stream_get_line($textfile, PHP_INT_MAX, "\n");
    preg_match('/@Module[\s]*\[(.*)\]/', $module_line, $module_matches);
    if(count($module_matches) > 1){
      $module_name = $module_matches[1];
      echo "Module: " . $module_name . PHP_EOL;
    } else{
      echo "Module not specified. First line of the file must be @Module[module_name]"  . PHP_EOL;
      echo "</pre>"; return;
    }


    $sql = 'SELECT module_id FROM '.K_TABLE_MODULES.' WHERE module_name=\''.$module_name.'\' LIMIT 1';
    $module_id = -1;

    if ($r = F_db_query($sql, $db)) {
      if ($m = F_db_fetch_array($r)) {
        // get existing module ID
        if (!F_isAuthorizedUser(K_TABLE_MODULES, 'module_id', $m['module_id'], 'module_user_id')) {
          // unauthorized user
          echo "You are not authorized to add questions to module '". $module_name . "'" . PHP_EOL;
        } else {
          $module_id = $m['module_id'];
        }
      } else { 
        echo "Module '". $module_name . "' does not exist. Please add this module before importing." . PHP_EOL;
        echo "</pre>"; return;
      }
    } else {
      F_display_db_error();
      echo "</pre>"; return;
    }

    while ($line = stream_get_line($textfile, PHP_INT_MAX, "\n")) {
      if(startsWith($line, "@")){
        $matches = array();
        preg_match('/@Topic[\s]*\[(.*)\]/', $line, $matches);
        if(count($matches) > 1){
          $topics_in_line = explode(",", $matches[1]);
          $trimmed_topics_in_line = array_map('trim', $topics_in_line);
          if($trimmed_topics_in_line[0] == ''){
            $topics["General"] = -1;
          }
          else{
            foreach ($trimmed_topics_in_line as $t){
              $topics[$t] = -1;
            }  
          }
        }
      }
    }

    $nonexistent_topics = false;
    foreach($topics as $t => $v){
      // echo $t . PHP_EOL;
      $sql = 'SELECT subject_id
              FROM ' . K_TABLE_SUBJECTS . '
              WHERE subject_name=\''.$t.'\'
              AND subject_module_id='.$module_id.'
              LIMIT 1';

      if ($r = F_db_query($sql, $db)) {
        if ($m = F_db_fetch_array($r)) {
          // get existing subject ID
          $topics[$t] = $m['subject_id'];
        } else {
          echo "Topic '" .$t. "' in Module '". $module_name . "' does not exist. Please add this topic before importing." . PHP_EOL;
          $nonexistent_topics = true;
        }
      } else {
          F_display_db_error();
      }
    }
    rewind($textfile);
    $line_number = 1;
    $line = stream_get_line($textfile, PHP_INT_MAX, "\n"); // skip verified line
    $line = stream_get_line($textfile, PHP_INT_MAX, "\n"); // skip module line

    $currently_reading = "TOPIC";
    $question_struct = array();
    $all_questions = array();
    while ($line = stream_get_line($textfile, PHP_INT_MAX, "\n")) {
      $line = trim($line);
      switch($currently_reading){
        case "TOPIC" : {
          TOPIC:
            if(!startsWith($line, "@Topic")){
              echo $line;
              echo "Unexpected Format on line " . $line_number . ". Expected @Topic" . PHP_EOL;
              echo "</pre>"; return;
            }
            else{
              $matches = array();
              preg_match('/@Topic[\s]*\[(.*)\]/', $line, $matches);
              if(count($matches) > 1){
                $topics_in_line = explode(",", $matches[1]);
                $trimmed_topics_in_line = array_map('trim', $topics_in_line);
                if($trimmed_topics_in_line[0] == ''){
                  $question_struct["Topics"] = array("General");
                  $currently_reading = "QUESTION_START";
                }
                else{
                  $question_struct["Topics"] = $trimmed_topics_in_line;
                  $currently_reading = "QUESTION_START";
                }
              }
              else{
                echo "Unexpected Format on line " . $line_number . ". Expected @Topic [topic_name, topic_name, ...]" . PHP_EOL;
                echo "</pre>"; return;
              }
            }
          break;
        }
        case "QUESTION_START" : {
          QUESTION_START: 
          if(!startsWith($line, "#")){
            echo "Unexpected Format on line " . $line_number . ". Expected #" . PHP_EOL;
            echo "</pre>"; return;
          }
          else{
            $question_struct["Question"] = ltrim($line, "#");
            $currently_reading = "QUESTION_CONT";
          }
          break;
        }
        case "QUESTION_CONT" : {
          QUESTION_CONT: 
          if(!startsWith($line, "$)") && !startsWith($line, "*)")){
            $question_struct["Question"] .= "\r\n" . $line;
            $currently_reading = "QUESTION_CONT";
            break;
          }
          else{
            $currently_reading = "ANSWERS";
            goto ANSWERS;
          }
        }
        case "ANSWERS" : {
          ANSWERS:
          if(startsWith($line, "%)")){
             $currently_reading = "EXPLANATION_START";
             goto EXPLANATION_START; 
          }
          else if (startsWith($line, "=====")){
            $currently_reading = "END";
            goto END;
          }
          else if(!startsWith($line, "$)") && !startsWith($line, "*)")){
            echo "Unexpected Format on line " . $line_number . ". Expected $) or *)" . PHP_EOL;
            echo "</pre>"; return;
          }
          else{
            $right = startsWith($line, "$");
            if(array_key_exists("Answers", $question_struct)){
              array_push($question_struct["Answers"], array($right, substr($line, 2)));
            }
            else{
              $question_struct["Answers"] = array();
              array_push($question_struct["Answers"], array($right, substr($line, 2)));
            }
            break;
          }
        }
        case "EXPLANATION_START" : {
           EXPLANATION_START:
           if(!startsWith($line, "%)")){
             echo "Unexpected Format on line " . $line_number . ". Expected %" . PHP_EOL;
             echo "</pre>"; return;
           }
           else{
             $question_struct["Explanation"] = substr($line, 2);
             $currently_reading = "EXPLANATION_CONT";
           }
           break;         
        }
        case "EXPLANATION_CONT" : {
          EXPLANATION_CONT:
          if(!startsWith($line, "=====")){
            $question_struct["Explanation"] .= "\r\n" . $line;
            $currently_reading = "EXPLANATION_CONT";
            break;
          }
          else{
            $currently_reading = "END";
            goto END;
          }
        }
        case "END" : {
          END:
          if(!array_key_exists("Answers", $question_struct)){
            echo "No answers provided for question '" . $question_struct["Question"] . "'" . PHP_EOL;
            echo "</pre>"; return;
          }
          $num_correct_answers = 0;
          foreach($question_struct["Answers"] as $a){
            if($a[0]){
              $num_correct_answers += 1;
            }
          }
          if($num_correct_answers  == 0){
            echo "No correct answers provided for question '" . $question_struct["Question"] . "'" . PHP_EOL;
            echo "</pre>"; return;            
          }
          else if($num_correct_answers == 1){
            $question_struct["Question Type"] = 1;
          }
          else{
            $question_struct["Question Type"] = 2;
          }


          preg_match_all('/@Image\(<(.*)>\)/', $question_struct["Question"], $matches, PREG_SET_ORDER);
          foreach ($matches as $match){
            if(!file_exists(K_PATH_CACHE . $match[1])){
              echo "File Doesnt Exist! : " . K_PATH_CACHE . $match[1] . PHP_EOL;
              echo "</pre>"; return;            
            }
            $size = getimagesize(K_PATH_CACHE . $match[1]);
            $height = "300";
            $width = strval(round($size[0] * (300 / $size[1])));
            $question_struct["Question"] =
               str_replace(
                  $match[0],
                  "\r\n[object]".$match[1]."[/object:".$width.":".$height.":]\r\n",
                  $question_struct["Question"]
                );
          }
          preg_match_all('/@Image\(<(.*)>\)/', $question_struct["Explanation"], $matches, PREG_SET_ORDER);
          foreach ($matches as $match){
            if(!file_exists(K_PATH_CACHE . $match[1])){
              echo "File Doesnt Exist! : " . K_PATH_CACHE . $match[1] . PHP_EOL;
              echo "</pre>"; return;            
            }
            $size = getimagesize(K_PATH_CACHE . $match[1]);
            $height = "300";
            $width = strval(round($size[0] * (300 / $size[1])));
            $question_struct["Explanation"] =
               str_replace(
                  $match[0],
                  "\r\n[object]".$match[1]."[/object:".$width.":".$height.":]\r\n",
                  $question_struct["Explanation"]
                );
          }

          foreach($question_struct["Answers"] as $index => $a){
             preg_match_all('/@Image\(<(.*)>\)/', $a[1], $matches, PREG_SET_ORDER);
             foreach ($matches as $match){
              if(!file_exists(K_PATH_CACHE . $match[1])){
                echo "File Doesnt Exist! : " . K_PATH_CACHE . $match[1] . PHP_EOL;
                echo "</pre>"; return;            
              }
               $size = getimagesize(K_PATH_CACHE . $match[1]);
               $height = "300";
               $width = strval(round($size[0] * (300 / $size[1])));
               $question_struct["Answers"][$index][1] =
                  str_replace(
                     $match[0],
                     "\r\n[object]".$match[1]."[/object:".$width.":".$height.":]\r\n",
                     $question_struct["Answers"][$index][1]
                   );
             } 
          }
          array_push($all_questions, $question_struct);
          $question_struct = array();
          $currently_reading = "TOPIC";
          break;
        }
      }
      $line_number += 1;
    }

    $question_format = "
    <div style='width: 50%'>
      <p>TOPICS</p>
      <p style='font-weight: bold'>QUESTION</p>
      <ul>
        ANSWERS
      </ul>
      <p style='font-style: italic;'>EXPLANATION</p>
      <hr />
    </div>";

    echo "</pre>";

    foreach($all_questions as $q){
      $answers = "";
      foreach($q["Answers"] as $a){
        $color = "red";
        $bold = "";
        if($a[0]){
          $color = "green";
          $bold = "font-weight:bold;";
        }
        $answers .= "<li style='color:".$color.';'.$bold."'>" . str_replace("\r\n","<br/>",$a[1]) . "</li>";
      }
      $q_out = $question_format;
      $q_out = str_replace("TOPICS", join(", ", $q["Topics"]) , $q_out);
      $q_out = str_replace("QUESTION", str_replace("\r\n","<br/>",$q["Question"]), $q_out);
      $q_out = str_replace("ANSWERS", $answers, $q_out);
      $q_out = str_replace("EXPLANATION", str_replace("\r\n","<br/>",$q["Explanation"]), $q_out);
      echo($q_out);
    }

    if($VERIFIED){
      import_questions($all_questions, $module_id, $topics);
    }

    fclose($textfile);
}


// ...

//============================================================+
// END OF FILE
//============================================================+
