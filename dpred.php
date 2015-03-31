<?php

   /* This file is part of psqlog : 
   an implementation of prolog in PHP and SQL.
   COPYRIGHT (c) 2006 Eugene Koontz
   <ekoontz@hiro-tan.org>
   Licenced under the General Public License v2.
  */

function insert_pred($connect,$pred,$arg_list,$conseq_id = NULL) {

  if (isset($conseq_id)) {
    $insert_pred = <<<Q
      INSERT INTO d_pred (pred,antec_of) VALUES ('$pred','$conseq_id')
Q;
  }
  else {
    $insert_pred = <<<Q
    INSERT INTO d_pred (pred) VALUES ('$pred')
Q;
  }

  // see view2sql.xsl: inserting into d_pred returns the pred_id of the inserted row.
  $result = pg_fetch_array(pg_query($connect,$insert_pred));

  $pred_id = $result[0];

  // now insert all args, using this $pred_id.
  $position = 1;
  foreach ($arg_list as $arg) {
    // prolog convention is that uppercase first letter argument -> argument is a variable; otherwise, constant.
    $type = "v";
    if (strtolower($arg[0]) == $arg[0]) {      
      $type = "c";
    }
    $arg_q = <<<Q
      INSERT INTO d_arg (pred_id,type,name,position) VALUES ('$pred_id','$type','$arg','$position')
Q;
    $result = pg_query($connect,$arg_q);
    $position++;
  }
  return $pred_id;
}

function psqlog_insert($connect,$rule_text) {

  $lsrs = preg_match("/^\s*([a-z]+)\(([a-zA-Z0-9_, ]+)\)\s*(:-)?\s*(.*)/",$rule_text,$matches);
  $pred = $matches[1];
  $args = $matches[2];
  $rs = $matches[4];

  $arg_list = preg_split("/,/",$args);

  $conseq_id = insert_pred($connect,$pred,$arg_list);

  if ($rs) {

    // a rule rather than simply a fact : insert a rule:
    $pred_list = preg_split("/\)\s*,\s*/",$rs);

    foreach($pred_list as $pred_and_args) {
      $p_and_a = preg_match("/\s*([a-z]+)\(([a-zA-Z0-9, ]+)/",$pred_and_args,$matches);
      
      $pred = $matches[1];
      $args = $matches[2];

      $arg_list = preg_split("/,/",$args);

      insert_pred($connect,$pred,$arg_list,$conseq_id);
      
    }
    
  }
}

function clear_db($connect) {
  $max_q = <<<Q
    DELETE FROM d_pred
Q;
  $result = pg_query($connect,$max_q);
  $max_q = <<<Q
    DELETE FROM d_arg
Q;
  $result = pg_query($connect,$max_q);

}

?>
