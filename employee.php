<?

$connect = pg_connect("user=www host=/tmp dbname=company");

clear_db($connect);

// Example (A) is an example of a rule-writer's error: 
// the error is that it is not general enough,
// because it only defines superior in terms a two-level management hierarchy,
// whereas, it should be any level.
// Example (B) is the correct rule.
// We must create diagnostic tools that would help to debug
// this kind of mistake so the user can diagnose and fix them.
// (A) psqlog_insert($connect,"superior(X,Y):- management(X,Z) , management(Z,Y)");

// (B) psqlog_insert($connect,"superior(X,Y):- management(X,Z) , superior(Z,Y)");
psqlog_insert($connect,"superior(X,Y):- management(X,Z) , superior(Z,Y)");
psqlog_insert($connect,"superior(X,Y):- management(X,Y)");

print_database($connect);

?>