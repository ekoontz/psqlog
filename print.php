<?php

   /* This file is part of psqlog : 
   an implementation of prolog in PHP and SQL.
   COPYRIGHT (c) 2006 Eugene Koontz
   <ekoontz@hiro-tan.org>
   Licenced under the General Public License v2.
  */

function print_psql($print) {
  static $level = 0;

  if (preg_match("/<[^\/].*\/>/",$print)) {
    // open-close: eg <foo/>
  }
  elseif (preg_match("/<[^\/]/",$print)) {
    // open: eg <foo>
  }
  elseif (preg_match("/<\//",$print)) {
    // close: eg </foo>
    $level--;
  }

  for($i = 0; $i < $level; $i++) {
    print("\t");
  }
  print $print."\n";

  if (preg_match("/<[^\/].*\/>/",$print)) {
    // open-close: eg <foo/>
  }
  elseif (preg_match("/<[^\/]/",$print)) {
    // open: eg <foo>
    $level++;
  }
  elseif (preg_match("/<\//",$print)) {
    // close: eg </foo>
  }
}

function print_rule($connect,$pred,$pred_id) {

  $get_args = <<<Q
     SELECT * FROM arg WHERE pred_id = '$pred_id' ORDER BY position
Q;

  $arg_result = pg_query($connect,$get_args);
  $args = "";
  $i = 0;
  while($arg_array = pg_fetch_array($arg_result,NULL,PGSQL_ASSOC)) {
    if ($i++ > 0) {
      $args .=",";
    }
    $args .= $arg_array["name"];
  }

  $text = "$pred($args)";

  
  $get_right_side = <<<Q
    SELECT * FROM pred WHERE antec_of = '$pred_id'
Q;

  $right_side = "";
  $j = 0;
  $right_side_result = pg_query($connect,$get_right_side);
  while($right_side_array = pg_fetch_array($right_side_result,NULL,PGSQL_ASSOC)) {
    if ($j++ > 0) {
      $right_side .=", ";
    }
    $right_side .= $right_side_array['pred']."(";
    
    $right_side_pred = $right_side_array['pred_id'];
    
    $get_args = <<<Q
      SELECT * FROM arg WHERE pred_id = '$right_side_pred' ORDER BY position
Q;
    
    $arg_result = pg_query($connect,$get_args);
    $args = "";
    $i = 0;
    while($arg_array = pg_fetch_array($arg_result,NULL,PGSQL_ASSOC)) {
      if ($i++ > 0) {
	$args .=",";
      }
      $args .= $arg_array["name"];
    }
    $right_side .= $args;
    
    $right_side .= ")";
    
  }
  
  if ($right_side) {
    $text .= " :- $right_side";
  }
  return $text;
}


function print_rule_by_id($connect,$pred,$pred_id) {

  $get_args = <<<Q
     SELECT * FROM arg WHERE pred_id = '$pred_id' ORDER BY position
Q;

  $arg_result = pg_query($connect,$get_args);
  $args = "";
  $i = 0;
  while($arg_array = pg_fetch_array($arg_result,NULL,PGSQL_ASSOC)) {
    if ($i++ > 0) {
      $args .=",";
    }
    $args .= $arg_array["name"];
  }

  $text = "$pred_id";

  
  $get_right_side = <<<Q
    SELECT * FROM pred WHERE antec_of = '$pred_id'
Q;

  $right_side = "";
  $j = 0;
  $right_side_result = pg_query($connect,$get_right_side);
  while($right_side_array = pg_fetch_array($right_side_result,NULL,PGSQL_ASSOC)) {
    if ($j++ > 0) {
      $right_side .=", ";
    }
    $right_side .= $right_side_array['pred_id'];
  }
  
  if ($right_side) {
    $text .= " :- $right_side";
  }
  return $text;
}


function print_database($connect) {
  $get_rules = <<<Q
    SELECT pred, pred_id FROM pred AS rule WHERE EXISTS ( SELECT '' FROM pred WHERE pred.antec_of = rule.pred_id)
Q;
 
 $result = pg_query($connect,$get_rules);
 print_psql("<rules>");

 while($rule_array = pg_fetch_array($result,NULL,PGSQL_ASSOC)) {
   $pred = $rule_array['pred'];
   $rule_id = $rule_array['pred_id'];
 
   print_psql("<rule id='$rule_id'>");
   print print_rule($connect,$pred,$rule_id)."\n";
   print_psql("</rule>");
 }
 print_psql("</rules>");
}

function print_binding_set($set,$elem) {
  print_psql("<$elem>");
  foreach($set as $key => $val) {
    print_psql("<var name='$key' val='$val'/>");
  }
  print_psql("</$elem>");

}

function print_binding_sets($sets,$elem) {
  foreach($sets as &$set) {
    print_binding_set($set,$elem);
  }
}


?>