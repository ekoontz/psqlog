<?
$connect = pg_connect("user=www host=/tmp dbname=family");

print_psql("<session>");

clear_db($connect);

// facts
psqlog_insert($connect,"male(carl)");
psqlog_insert($connect,"male(rob)");
psqlog_insert($connect,"male(doc)");
psqlog_insert($connect,"male(joe)");
psqlog_insert($connect,"male(steve)");
psqlog_insert($connect,"parent(carl,barb)");
psqlog_insert($connect,"parent(carl,doc)");
psqlog_insert($connect,"parent(doc,annie)");
psqlog_insert($connect,"parent(doc,carl_iii)");
psqlog_insert($connect,"parent(doc,mary)");
psqlog_insert($connect,"parent(carl,sally)");
psqlog_insert($connect,"parent(carl,steve)");
psqlog_insert($connect,"parent(barb,joe)");
psqlog_insert($connect,"parent(rob,joe)");
psqlog_insert($connect,"parent(rob,mimi");
psqlog_insert($connect,"parent(barb,mimi)");

// rules
psqlog_insert($connect,"father(X,Y):- male(X) , child(Y,X)");
psqlog_insert($connect,"ancestor(X,Y):- parent(X,Y)");
psqlog_insert($connect,"ancestor(X,Y):- parent(X,Z) , ancestor(Z,Y)");
psqlog_insert($connect,"child(X,Y):- parent(Y,X)");
psqlog_insert($connect,"son(X,Y):- child(X,Y) , male(X)");

print_database($connect);

// "who is a father of anyone, and who are they a father of?"
$arg_list = array('A','B');
$bindings = array();
unify($connect,"father",$arg_list,$bindings);

// "who is a son of anyone, and who are they a son of?"
$arg_list = array('A','B');
$bindings = array();
unify($connect,"son",$arg_list,$bindings);

// "who is barb a child of?"
$arg_list = array('A','B');
$bindings = array(
		  'A' => 'barb',
		  );
unify($connect,"child",$arg_list,$bindings);

// "who is a child of barb?"
$arg_list = array('A','B');
$bindings = array(
		  'B' => 'barb',
		  );
unify($connect,"child",$arg_list,$bindings);


// "is carl an ancestor of joe?"
$arg_list = array('A','B');
$bindings = array(
		  'A' => 'carl',
		  'B' => 'joe'
		  );
unify($connect,"ancestor",$arg_list,$bindings);

// "who is a parent of mimi?"
$arg_list = array('A','B');
$bindings = array(
		  'B' => 'joe'
		  );
unify($connect,"parent",$arg_list,$bindings);

// "who is carl an ancestor of?"
$arg_list = array('A','B');
$bindings = array(
		  'A' => 'carl'
		  );
unify($connect,"ancestor",$arg_list,$bindings);


// "who is an ancestor of anyone, and who are they an ancestor of?"
$arg_list = array('A','B');
$bindings = array();
unify($connect,"ancestor",$arg_list,$bindings);

print_psql("</session>");

?>

