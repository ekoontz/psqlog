<?php
$connect = pg_connect("user=www host=/tmp dbname=company");

print_psql("<session>");

/*
// "is eugene a telecommuter?" (yes)
unify($connect,"telecommutes",array('A'),array('A'=>'Eugene'));

// "is gustavo a telecommuter?" (no)
unify($connect,"telecommutes",array('A'),array('A'=>'Gustavo'));
// "does Gustavo manage Eugene?" (yes)
unify($connect,"management",array('A','B'),array('A'=> 'Gustavo','B' => 'Eugene'));
// "who does Gustavo manage?" (Salman and Eugene)
unify($connect,"management",array('A','B'),array('A'=> 'Gustavo'));

// "who is Gustavo a superior of?" (Salman, Eugene, Greg)
unify($connect,"superior",array('A','B'),array('A'=> 'Gustavo'));
*/
// "who is Lex a superior of? (Salman, Gustavo, Eugene, Lei, Greg and Xavier)
unify($connect,"superior",array('A','B'),array('A'=> 'Lex'));
/*
// "who are Salman's superiors? (Gustavo and Lex)
unify($connect,"superior",array('A','B'),array('B'=> 'Salman'));
*/
print_psql("</session>");

?>