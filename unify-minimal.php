<?php

   /* This file is part of psqlog : 
   an implementation of prolog in PHP and SQL.
   COPYRIGHT (c) 2006 Eugene Koontz
   <ekoontz@hiro-tan.org>
   Licenced under the General Public License v2.
  */

require("print.php");

function load_views($filename,$connect) {
  $fh = fopen($filename,"r");

  $view_xml = fread($fh,filesize($filename));
  
  $xsl = new XSLTProcessor();
  $xsl->importStyleSheet(DOMDocument::load("view2sql.xsl"));

  $view_sql = $xsl->transformToXML(DOMDocument::loadXML($view_xml));

  $fh = fopen("views.sql","w");
  fwrite($fh,$view_sql);
  fclose($fh);

  pg_query($connect,$view_sql);

  }

function right_side($connect,$pred_id,$bindings) {
  // return a list of conjuncts that 
  //  compose the right side for rule with id $pred_id. 
  //  The list might be empty, in which case $left_side is simply a fact, not a rule. 
  //  print_psql("<rightside of='{$pred_id}'>");
  $retval = array();
  $sql = "SELECT * FROM pred WHERE antec_of='$pred_id'";
  $right_side = array();
  $rows = pg_query($connect,$sql);
  $i = 0;
  while($conjunct = pg_fetch_array($rows,NULL,PGSQL_ASSOC)) {
    //    print_psql("<conjunct pred='{$conjunct['name']}' id='{$conjunct['pred_id']}' >");
    $pred_struct = array();
    $pred_struct['pred'] = $conjunct['pred'];
    $pred_struct['pred_id'] = $conjunct['pred_id'];
    
    $query = <<<Q
      SELECT type,name FROM arg WHERE arg.pred_id = '{$conjunct['pred_id']}' ORDER BY position
Q;
    
    $arg_result = pg_query($connect,$query);
     
    $pred_struct['args'] = array();
    $pred_struct['arg_list'] = array();
    
    while ($arg_array = pg_fetch_array($arg_result,NULL,PGSQL_ASSOC)) {
      //      print_psql("<arg name='{$arg_array['name']}' type='{$arg_array['type']}' binding='{$bindings[$arg_array['name']]}'/>");
      $arg_struct = array();
       
      $arg_name = $arg_array['name'];
      $arg_struct['name'] = $arg_array['name'];
      $arg_struct['type'] = $arg_array['type'];
      $pred_struct['args'][] = $arg_struct;
      $pred_struct['arg_list'][] = $arg_array['name'];
      
    }
    
    //    print_psql("</conjunct>");
    
    $retval[] = $pred_struct;
  }
  //  print_psql("</rightside>");
  return $retval;
}

function get_satisfiers($connect,$pred,$arg_list,$bindings) {
  // find all rules (or statements)
  // for which $pred ($arg_list) with $bindings is true.
  // append values to these as needed, according to length of arg_list.
  $table_sql = "";
  $where_sql = "";
  $select_sql = "pred.pred_id AS pred_id";

  // get number of arguments : this determines how we'll fetch args from the db.
  for($i = 1; $i <= count($arg_list); $i++) {
    $arg = $arg_list[$i];

    $select_sql .= ", arg$i.name AS arg$i , arg$i.type AS arg{$i}type ";

    $table_sql .= <<<SQL
, arg AS arg$i
SQL;
    
    $where_sql .= <<<SQL
      (arg$i.pred_id = pred.pred_id) AND ( arg$i.position = $i ) 
SQL;

       // array indexing starts at 0 (c-style);
       // but we store arg positions starting at 1 (non-c-style).
    if (isset($bindings{$arg_list[$i-1]})) {
      $where_sql .= <<<SQL
      AND (( arg$i.type = 'v' ) OR
       ( arg$i.type = 'c' AND arg$i.name = '{$bindings{$arg_list[$i-1]}}' ))
SQL;
    }
    if ($i < count($arg_list)) {
      $where_sql .= " AND ";
    }

  }

  $retval = array();
  $query = <<<QUERY
    SELECT $select_sql FROM pred $table_sql WHERE antec_of IS NULL AND pred.pred='$pred' AND ( $where_sql) ;
QUERY;

 print_psql("<satquery>");
 print_psql($query);
 print_psql("</satquery>");
 $result = pg_query($connect,$query);
 
 while($sat_row = pg_fetch_array($result,NULL,PGSQL_ASSOC)) {

   $pred_arg_list = array();

   $rule = print_rule($connect,$sat_row['pred'],$sat_row['pred_id']);

   $new_bindings = array();
   // annotate sat_row with additional argument info.
   $i = 1;
   foreach($arg_list as $arg) {

     $pred_arg_list[] = $sat_row["arg".$i];
     if ($sat_row["arg".$i."type"] == "c") {
       $key = $arg;
       $val = $sat_row["arg".$i];
     }
     else {
       $key = $sat_row["arg".$i];
       $val = $bindings[$arg];
     }

     // check to see if we've already set a value variable $arg in $new_bindings
     if (isset($new_bindings[$key])) {

	 // already set : is it consistent?
       if ($new_bindings[$arg] != $val) {
	 // no: unify fails.
	 // (fixme: replace die with continue when done testing)
	     die("inconsistent binding: tried to set to : {$bindings[$arg]}, but already set to : {$new_bindings[$arg]}");
       }
       else {
	 // $new_bindings equal to old bindings; nothing needed.
       }
     }
     else {
       $new_bindings[$key] = $val;
       //       print_psql("<setnew key='$key' val='$val' type='{$sat_row["arg".$i."type"]}'/>");
     }

     //     print_psql("</from-arg>");
     $i++;
   }
   $retval[] = array($sat_row['pred_id'],$pred_arg_list,$new_bindings);
   
 }

 return $retval;
}

function unify($connect,$pred,$arg_list,$bindings) {
  print_psql("<unify pred='$pred'>");

  print_psql("<arglist>");
  foreach($arg_list as $arg) {
    $val = $bindings[$arg];
    if (isset($val)) {
      print_psql("<arg name='$arg' val='$val'/>");
    }
    else {
      print_psql("<arg name='$arg'/>");
    }
  }
  print_psql("</arglist>");
  
  // get_satisfiers returns a triple:
  //
  // a) pred id
  // b) arglist
  // c) bindings set

  $satisfiers_set = get_satisfiers($connect,$pred,$arg_list,$bindings);

  $solutions = array();
  $solutions_arg_sets = array();

  if (count($satisfiers_set) == 0) {
    print_psql("<nosatisfiers/>");
  }

  $soln_i = 0;

  foreach($satisfiers_set as $sat_tuple) {

    // each $sat_tuple is a < pred_id , list of (formal) parameters, bindings >

    $pred_id = $sat_tuple[0];
    $sat_arg_list = $sat_tuple[1];
    $satisfier = $sat_tuple[2];

    // each $satisfier is a set of 
    // variable -> value bindings.

    $rule = print_rule($connect,$pred,$pred_id);
    $rule_by_id = print_rule_by_id($connect,$pred,$pred_id);
  
    print_psql("<left-side id='{$pred_id}' rule='$rule' rule-by-id='$rule_by_id'>");

    print_psql("<left-side-args>");
    foreach($sat_arg_list as $arg) {
      $val = $satisfier[$arg];
      print_psql("<arg name='$arg' val='$val'/>");
    }
    print_psql("</left-side-args>");

    $right_side = right_side($connect,$pred_id,$satisfier);

    if ($right_side) {
      print_psql("<rightside>");
      $sets = unify_right_side($connect,$right_side,$sat_tuple);

      // add a arg-list for each solution.
      foreach($sets as $set) {
	$solutions_arg_sets[$soln_i] = $sat_arg_list;
	$soln_i++;
      }

      $solutions = array_merge($solutions,$sets);
      print_psql("</rightside>");
    }
    else {
      // no right side: simply a fact.
      // add this binding to set of satisfying binding sets.

      // add a arg-list for this solution.
      //      $solutions_arg_sets[$soln_i] = $sat_arg_list;
      $solutions_arg_sets[$soln_i] = $arg_list;
      $soln_i++;

      $solutions[] = $satisfier;

      print_psql("<fact>");

      print_psql("<satisfying-bindings>");
      foreach($satisfier as $var => $val) {
	print_psql("<var name='$var' val='$val'/>");
      }
      print_psql("</satisfying-bindings>");

      print_psql("</fact>");
    }
    $n = count($sets);
    print_psql("<successful-rightsides count='$n'/>");
    print_psql("</left-side>");
  }
  $n = count($solutions);
  print_psql("<solutions pred='$pred' count='$n'>");

  $soln_i = 0;

  foreach($solutions as $solution) {
    print_psql("<solution id='$soln_i'>");

    print_psql("<soln-args>");

    $new_solutions_arg_sets[$soln_i] = array();

    $arg_i = 0;
    foreach($solutions_arg_sets[$soln_i] as $arg) {
      $val = $solution[$arg];
      $num = $arg_i+1;
      $to_name = $arg_list[$arg_i];
      print_psql("<arg num='$num' name='$to_name' val='$val'/>");
      $arg_i++;
      $new_solutions_arg_sets[$soln_i][$arg_i] = $to_name;
      $new_solutions[$soln_i][$to_name] = $val;
    }


    print_psql("</soln-args>");
    print_psql("</solution>");
    $soln_i++;

  }

  print_psql("</solutions>");
  print_psql("</unify>");

  // return $solutions;

  //  return array($solutions,$solutions_arg_sets);
  return array($new_solutions,$new_solutions_arg_sets);
}

function bind($bindings_a,$bindings_b,$arg_list_b) {
  print_psql("<binds-a>");
  foreach($bindings_a as $key=>$val) {
    print_psql("<var name='$key' val='$val'/>");
  }
  print_psql("</binds-a>");

  print_psql("<arg_list_b>");
  foreach($arg_list_b as $arg) {
    $val = $bindings_a[$arg];
    print_psql("<var name='$arg' val='$val'/>");
  }
  print_psql("</arg_list_b>");

  return bind_r($bindings_a,$bindings_b,$arg_list_b);
}

function bind_r($bindings_a,$bindings_b,$arg_list_b) {
  if (count($arg_list_b) > 0) {

    $arg_b = array_shift($arg_list_b);

    print_psql("<bind_r arg='$arg_b'>");

    if (isset($bindings_a[$arg_b])) {
      $val = $bindings_a[$arg_b];

      if (isset($bindings_b[$arg_b])) {
	if ($bindings_b[$arg_b] != $val) {
	  // binding failed.
	  print_psql("<fail arg='$arg_b' existing='{$bindings_b[$arg_b]}' new='$val'/>");
	  return "FAIL";
	}
	else {
	  print_psql("<match arg='$arg_b' existing='{$bindings_b[$arg_b]}' new='$val'/>");
	}
      }
      else {
	print_psql("<assign arg='$arg_b' val='$val'/>");
	$bindings_b[$arg_b] = $val;
      }
    }
    else {
      print_psql("<free arg='$arg_b'/>");
    }
    print_psql("</bind_r>");
    // do rest of $arg_list_b.
    return bind_r($bindings_a,$bindings_b,$arg_list_b);
  }

  return $bindings_b;
}

function unify_right_side($connect,$right_side,$sat_tuple) {
  $left_pred_id = $sat_tuple[0];
  $left_arg_list = $sat_tuple[1];
  $bindings = $sat_tuple[2];

  $size = count($right_side);

  if ($size == 0) {
    // done; return successful set of bindings
  
    print_psql("<right-side-succeeded>");

    foreach($left_arg_list as $arg) {
      $val = $bindings[$arg];

      print_psql("<arg name='$arg' val='$val'/>");
    }


    print_psql("</right-side-succeeded>");

    return array($bindings);
  }

  // tag this binding set with an 'id':
  $bindings['id'] = get_uid();

  print_psql("<conjuncts remaining='$size'>");

  $conjunct = array_shift($right_side);
  
  print_psql("<conjunct pred='{$conjunct['pred']}' id='{$conjunct['pred_id']}'>");
  
  $conj_arg_list = $conjunct['arg_list'];
  
  print_psql("<bind-pre-unify>");
  $conj_bindings = bind($bindings,array(),$conj_arg_list);
  print_psql("<bind-result>");
  foreach($conj_bindings as $key => $val) {
    print_psql("<var name='$key' val='$val'/>");
  }
  print_psql("</bind-result>");
  
  print_psql("</bind-pre-unify>");
  
  $Conjunct_tuple = unify($connect,$conjunct['pred'],$conj_arg_list,$conj_bindings);
  // $Conjunct_binding_sets is a set of binding sets.

  $Conjunct_binding_sets = $Conjunct_tuple[0];
  $Conjunct_arg_lists = $Conjunct_tuple[1];

  $num = count($Conjunct_binding_sets);
  print_psql("<unify-returned-results num='$num'>");

  if ($num > 0) {
    
    // merge existing with each set $b_ in $Conjunct_binding_sets.
    // NOTE USE OF &$b HERE : REQUIRES PHP5 : http://www.php.net/manual/en/control-structures.foreach.php
    $i = 1;
    
    foreach($Conjunct_binding_sets as &$Conjunct_binding_set) {
      
      print_psql("<binding-soln-set i='$i'>");
      
      foreach ($Conjunct_binding_set as $key => $val) {
	print_psql("<binding name='$key' val='$val'/>");
      }
      
      foreach ($bindings as $key => $val) {
	if ($key == "id") {
	  continue;
	}
	
	if (!isset($Conjunct_binding_set[$key])) {
	  
	  print_psql("<assign-in-b_ name='$key' to='$val'/>");
	  // assign value of the $i'th element in $bindings to the $i'th element in $Conjunct_binding_set.
	  $Conjunct_binding_set[$key] = $val;
	  
	}
      }
      print_psql("</binding-soln-set>");
      $i++;
    }

    print_binding_set($Conjunct_binding_sets,"binding-set-in-b_post-merge");
  }
  print_psql("</unify-returned-results>");

  
  print_psql("</conjunct>");
  
  // return list of binding sets.
  $retval = array();
  if (count($Conjunct_binding_sets) > 0) {
    foreach ($Conjunct_binding_sets as &$Conjunct_binding_set) {
      // do rest of conjuncts.

      print_psql("<next-conjunct-bindings>");
      foreach($Conjunct_binding_set as $key => $val) {
	print_psql("<var name='$key' val='$val'/>");
      }
      print_psql("</next-conjunct-bindings>");

      $result = unify_right_side($connect,
		       $right_side,
		       array(
			     $sat_tuple[0],
			     $sat_tuple[1],
			     $Conjunct_binding_set));
      
      foreach($result as $res) {
	$retval[] = $res;
      }
    }
    print_psql("</conjuncts>");
  }
  else {
    print_psql("<failed at='{$conjunct['pred']}'/>");
    print_psql("</conjuncts>");
  }
  return $retval;
}

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

function get_uid() {
  static $i;

  if (!isset($i)) {
    // 0,1,2,3,... will be returned:
    // if 1,2,3,4,... is desired, then
    // set $i = 1.    
    $i = 0;
  }

  return $i++;
}


?>
