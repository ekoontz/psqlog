<?php

   /* This file is part of psqlog : 
   an implementation of prolog in PHP and SQL.
   COPYRIGHT (c) 2006 Eugene Koontz
   <ekoontz@hiro-tan.org>
   Licenced under the General Public License v2.
  */

require("print.php");
require("dpred.php");

function unify($connect,$pred,$arg_list,$bindings) {

  /*
   UNIFY()
   -----
   INPUT

   pred: string
   arg_list: list(string)
   bindings: hash(string=>string)
   
   OUTPUT

   bindings: < hash(string=>string) , list( list(string) ) >

  */

  print_psql("<unify pred='$pred'>");

  print_psql("<arglist>");
  print_r($arg_list);
  print_psql("</arglist>");

  print_psql("<bindings>");
  print_r($bindings);
  print_psql("</bindings>");


  // get_satisfiers returns a triple:
  //
  // a) pred id
  // b) arglist
  // c) bindings set

  print_psql("<get_sat_rows>");
  $satisfier_rows = get_sat_rows($connect,$pred,$arg_list,$bindings);

  $solutions = array();
  $solutions_arg_lists = array();
  $soln_i = 0;

  while($sat_row = pg_fetch_array($satisfier_rows,NULL,PGSQL_ASSOC)) {
    $pred_id = $sat_row['pred_id'];

    print_psql("<foundrow id='{$sat_row['pred_id']}'>");
    
    $sat_arg_list = array();
    $sat_bindings = array();
    
    $i = 1;
    while($sat_row['arg'.$i]) {
      // note that $arg_list is the list of
      // arguments that is for this unify() call,
      // not the list of arguments that this row is returning.
      // we are setting sat_bindings with keys being the former 
      // ie, the unify() call's arguments, not the arguments in $sat_row.
      $arg = $arg_list[$i-1];

      $sat_arg_list[] = $sat_row['arg'.$i];

      if ($sat_row["arg".$i."type"] == "c") {
	$key = $arg;
	$val = $sat_row["arg".$i];
      }
      else {
	$key = $sat_row["arg".$i];
	$val = $bindings[$arg];
      }

      // check to see if we've already set a value variable $arg in $bindings
      if (isset($sat_bindings[$key])) {
	
	// already set : is it consistent?
	if ($sat_bindings[$arg] != $val) {
	  // no: unify fails.
	  // (fixme: error handling: replace die with continue when done testing)
	  die("inconsistent binding: tried to set to : {$bindings[$arg]}, but already set to : {$bindings[$arg]}");
	}
	else {
	  // $sat_bindings equal to old bindings; nothing needed.
	}
      }
      else {
	$sat_bindings[$key] = $val;
      }
      $i++;
    }

    $solutions[] = $sat_bindings;

    print_psql("<sat_arg_list>");
    print_r($sat_arg_list);
    print_psql("</sat_arg_list>");

    print_psql("<sat_bindings>");
    print_r($sat_bindings);
    print_psql("</sat_bindings>");

    $right_side_tuple = right_side($connect,$pred_id);

    $right_side_rows = $right_side_tuple[1];

    // FIXME: unify treatment of right_side being empty or not: 

    if (count($right_side) == 0) {
      $solutions_arg_lists[$soln_i] = $arg_list;
      $soln_i++;
      $solutions[] = $sat_bindings;
      $right_side_sets = array();
    }
    else {
      $right_side_rows = unify_right_side_rows($connect,$right_side_rows,$sat_bindings);
    }

    $soln_i = 1;
    while($right_side_row = pg_fetch_array($right_side_rows,NULL, PGSQL_ASSOC)) {
    // add a arg-list for each solution.
      print_psql("<rs_set num=\"{$soln_i}\">");
      print_r($right_side_row);
      print_psql("</rs_set>");
      $soln_i++;
    }

    // create UNION ALL sql to create a row set to return.
    // http://www.postgresql.org/docs/8.1/interactive/sql-select.html#SQL-UNION

    // "The result of UNION does not contain any duplicate rows unless the ALL option 
    // is specified. ALL prevents elimination of duplicates. 
    // (Therefore, UNION ALL is usually significantly quicker than UNION; 
    //    use ALL when you can.)"

    // eg: 
    /* 

company=# SELECT 'Lex' AS a1 , 'X' AS a1_from, 'A' AS a1_to, 'Bob' AS a2, 'Y' AS a2_from, 'B' AS a2_to UNION 
          SELECT 'Lex' AS a1 , 'X' AS a1_from, 'A' AS a1_to, 'Greg' AS a2, 'Y' AS a2_from, 'B' AS a2_to UNION ALL
          SELECT 'Lex' AS a1 , 'X' AS a1_from, 'A' AS a1_to, 'Eugene' AS a2, 'Y' AS a2_from, 'B' AS a2_to UNION SELECT 'Lex' AS a1 , 'X' AS a1_from, 'A' AS a1_to, 'Salman' AS a2, 'Y' AS a2_from, 'B' AS a2_to UNION ALL
          SELECT 'Lex' AS a1 , 'X' AS a1_from, 'A' AS a1_to, 'Gustavo' AS a2, 'Y' AS a2_from, 'B' AS a2_to UNION ALL
          SELECT 'Lex' AS a1 , 'X' AS a1_from, 'A' AS a1_to, 'Lei' AS a2, 'Y' AS a2_from, 'B' AS a2_to UNION ALL
          SELECT 'Lex' AS a1 , 'X' AS a1_from, 'A' AS a1_to, 'Xavier' AS a2, 'Y' AS a2_from, 'B' AS a2_to;

 a1  | a1_from | a1_to |   a2     | a2_from | a2_to 
-----+----------+--------+----------+----------+-------
 Lex | X       | A      | Bob      | Y        | B
 Lex | X       | A      | Eugene   | Y        | B
 Lex | X       | A      | Greg     | Y        | B
 Lex | X       | A      | Gustavo  | Y        | B
 Lex | X       | A      | Lei      | Y        | B
 Lex | X       | A      | Salman   | Y        | B
 Lex | X       | A      | Xavier   | Y       | B
(7 rows)

company=# 


     */

    print_psql("</foundrow>");
  }

  $n = count($solutions);
  print_psql("<solutions pred='$pred' count='$n'>");

  $soln_i = 0;

  foreach($solutions as $solution) {
    print_psql("<solution id='$soln_i'>");

    print_r($solution);

    $arg_i = 0;
    foreach($solutions_arg_lists[$soln_i] as $arg) {
      $val = $solution[$arg];
      $num = $arg_i+1;
      $to_name = $arg_list[$arg_i];
      print_psql("<arg num='$num' from_name='$arg' to_name='$to_name' val='$val'/>");
      $arg_i++;
      $solutions_arg_lists[$soln_i][$arg_i] = $to_name;
      $solutions[$soln_i][$to_name] = $val;
    }

    print_psql("</solution>");
    $soln_i++;

  }

  print_psql("</solutions>");

  print_psql("</get_sat_rows>");

  $solutions = array();
  $solutions_arg_lists = array();
  $soln_i = 0;

  print_psql("<get_satisfiers>");
  $satisfiers_set = get_satisfiers($connect,$pred,$arg_list,$bindings);

  foreach($satisfiers_set as $sat_tuple) {

    // each $sat_tuple is a triple: < pred_id , list of (formal) parameters, bindings >

    print_psql("<sat_arg_list>");
    print_r($sat_tuple[1]);
    print_psql("</sat_arg_list>");
    print_psql("<sat_bindings>");
    print_r($sat_tuple[2]);
    print_psql("</sat_bindings>");

    $pred_id = $sat_tuple[0];
    $sat_arg_list = $sat_tuple[1];
    $sat_bindings = $sat_tuple[2];

    // each $sat_bindings is a set of 
    // variable -> value bindings.

    $right_side_tuple = right_side($connect,$pred_id);

    $right_side = $right_side_tuple[0];
    $right_side_rows = $right_side_tuple[1];

    // FIXME: unify treatment of right_side being empty or not: 

    if (count($right_side) == 0) {
      $solutions_arg_lists[$soln_i] = $arg_list;
      $soln_i++;
      $solutions[] = $sat_bindings;
      $right_side_sets = array();
    }
    else {
      $right_side_sets = unify_right_side($connect,$right_side,$sat_bindings);
    }

    // add a arg-list for each solution.
    foreach($right_side_sets as $set) {
      $solutions_arg_lists[$soln_i] = $sat_arg_list;
      $soln_i++;

      print_psql("<set>");
      print_r($set);
      print_psql("</set>");

    }
    $solutions = array_merge($solutions,$right_side_sets);
  }
  print_psql("</get_satisfiers>");

  $n = count($solutions);
  print_psql("<solutions pred='$pred' count='$n'>");

  $soln_i = 0;

  foreach($solutions as $solution) {
    print_psql("<solution id='$soln_i'>");

    $new_solutions_arg_lists[$soln_i] = array();

    $arg_i = 0;
    foreach($solutions_arg_lists[$soln_i] as $arg) {
      $val = $solution[$arg];
      $num = $arg_i+1;
      $to_name = $arg_list[$arg_i];
      print_psql("<arg num='$num' from_name='$arg' to_name='$to_name' val='$val'/>");
      $arg_i++;
      $new_solutions_arg_lists[$soln_i][$arg_i] = $to_name;
      $new_solutions[$soln_i][$to_name] = $val;
    }

    print_psql("</solution>");
    $soln_i++;

  }

  print_psql("</solutions>");
  print_psql("</unify>");

  return array($new_solutions,$new_solutions_arg_lists);
}

function get_satisfiers($connect,$pred,$arg_list,$bindings) {

  /* 
   GET_SATISFIERS()
   -----
   INPUT

   pred: string
   arg_list: list(string)
   bindings: hash(string=>string)

   OUTPUT

   list( < pred_id,list(string),hash(string=>string) > )

  */
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
     }
     $i++;
   }
   $retval[] = array($sat_row['pred_id'],$pred_arg_list,$new_bindings);
   
 }

 return $retval;
}

function right_side($connect,$pred_id) {

  /* 
   RIGHT_SIDE()
   -----
   INPUT

   pred: index into pred table

   OUTPUT

   list( pred : string, pred_id : index, list(string) : args )

  */

  $new_sql = <<<Q
    SELECT pred,antecedent.pred_id,name,position
       FROM pred AS antecedent,arg 
     WHERE 
         antec_of='{$pred_id}' AND arg.pred_id = antecedent.pred_id 
     ORDER BY antecedent.pred_id,arg.position;
Q;

  // rather than returning a list, simply return the $rows, which 
  // unify_right_side() will then iterate through.

  // return a list of conjuncts that 
  //  compose the right side for rule with id $pred_id. 
  //  The list might be empty, in which case $left_side is simply a fact, not a rule. 
  $retval = array();
  $sql = "SELECT pred,pred_id FROM pred WHERE antec_of='$pred_id'";
  $right_side = array();

  print_psql("<right_side_query>");
  print_psql($sql);
  print_psql("</right_side_query>");

  $rows = pg_query($connect,$sql);
  $i = 0;
  while($conjunct = pg_fetch_array($rows,NULL,PGSQL_ASSOC)) {
    $pred_struct = array();
    $pred_struct['pred'] = $conjunct['pred'];
    
    $query = <<<Q
      SELECT name FROM arg WHERE arg.pred_id = '{$conjunct['pred_id']}' ORDER BY position
Q;
    
    print_psql("<rs_conjunct_query>");
    print_psql($query);
    print_psql("</rs_conjunct_query>");

    $arg_result = pg_query($connect,$query);
     
    $pred_struct['arg_list'] = array();
    
    while ($arg_array = pg_fetch_array($arg_result,NULL,PGSQL_ASSOC)) {
      $pred_struct['arg_list'][] = $arg_array['name'];
    }
    $retval[] = $pred_struct;
  }
  return array($retval, pg_query($connect,$new_sql));
}

function unify_right_side($connect,$right_side,$bindings) {

  /* 
   UNIFY_RIGHT_SIDE()
   -----
   INPUT

   right_side:    list( pred:string, list(string) : args )
   bindings:      hash(string=>string)

   OUTPUT

   list( hash(string => string) ) : list of binding sets

  */

  if (count($right_side) == 0) {
    // done; return successful set of bindings
    return array($bindings);
  }

  $conjunct = array_shift($right_side);
    
  $conj_arg_list = $conjunct['arg_list'];
  
  $conj_bindings = bind($bindings,array(),$conj_arg_list);
  
  $Conjunct_tuple = unify($connect,$conjunct['pred'],$conj_arg_list,$conj_bindings);
  // $Conjunct_binding_sets is a set of binding sets.

  $Conjunct_binding_sets = $Conjunct_tuple[0];
  $Conjunct_arg_lists = $Conjunct_tuple[1];

  // return list of binding sets.
  $retval = array();

  // CHECKME: sometimes $Conjunct_binding_sets is NULL; is it ok?
  if ($Conjunct_binding_sets) {
    // merge existing with each set $b_ in $Conjunct_binding_sets.
    // NOTE USE OF &$b HERE : REQUIRES PHP5 : http://www.php.net/manual/en/control-structures.foreach.php
    foreach($Conjunct_binding_sets as &$Conjunct_binding_set) {
      foreach ($bindings as $key => $val) {
	if (!isset($Conjunct_binding_set[$key])) {
	  // assign value of the $i'th element in $bindings to the $i'th element in $Conjunct_binding_set.
	  $Conjunct_binding_set[$key] = $val;
	}
      }
      
      $result = unify_right_side($connect,
				 $right_side,
				 $Conjunct_binding_set);
      
      // final_set := union( final_set ,this conjunct's results)
      // (actually appending 2 lists together, but should really be a union of sets)
      foreach($result as $res) {
	$retval[] = $res;
      }
    }
  }

  return $retval;
}

function unify_right_side_rows($connect,$right_side,$bindings) {

  /* 
   UNIFY_RIGHT_SIDE()
   -----
   INPUT

   right_side:    database handle to rows of ( pred:string, list(string) : args )
   bindings:      hash(string=>string)

   OUTPUT

   handle to rows of (string => string) (each row is a binding set)

   list( hash(string => string) ) : list of binding sets

  */

  print_psql("<unify_right_side_rows>");
  print_psql("</unify_right_side_rows>");
}


function bind($bindings_a,$bindings_b,$arg_list_b) {

  /* 
   BIND()
   -----
   INPUT

   hash(string=>string): binding set A
   hash(string=>string): binding set B
   list(string): arguments

   OUTPUT

   hash(string=>string) | "FAIL" : resultant binding set

  */

  // return a list of conjuncts that 

  if (count($arg_list_b) > 0) {

    $arg_b = array_shift($arg_list_b);

    if (isset($bindings_a[$arg_b])) {

      $val = $bindings_a[$arg_b];

      if (isset($bindings_b[$arg_b])) {

	if ($bindings_b[$arg_b] != $val) {
	  // binding failed.
	  return "FAIL";
	}
      }
      else {
	$bindings_b[$arg_b] = $val;
      }
    }
    // do rest of $arg_list_b.
    return bind($bindings_a,$bindings_b,$arg_list_b);
  }
  return $bindings_b;
}

function get_sat_rows($connect,$pred,$arg_list,$bindings) {

  /* 
   GET_SAT_ROWS()
   -----
   INPUT

   pred: string
   arg_list: list(string)
   bindings: hash(string=>string)

   OUTPUT

   row handle


  */
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
 return pg_query($connect,$query);
}

?>
