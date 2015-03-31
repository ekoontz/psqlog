-- COPYRIGHT 2007 Eugene Koontz <ekoontz@hiro-tan.org>
-- This file is part of Psqlog : an 
-- implementation of Prolog in PostgreSQL
--
-- Licenced under the GNU General Public License version 3.
-- 
--

SELECT unify_arg1 AS A, unify_arg2 AS B FROM 
       unify2( 'management' , 'A' , 'B' , NULL,NULL );

-- who's lex the boss of?
SELECT unify_arg1 AS A, unify_arg2 AS B FROM 
       unify2( 'superior' , 'A' , 'B' , 'Lex',NULL );


-- who are bob's bosses?
SELECT unify_arg1 AS A, unify_arg2 AS B FROM 
       unify2( 'superior' , 'A' , 'B' , NULL,'Bob' );

-- does Bob work for Gustavo? (yes)
SELECT unify_arg1 AS A, unify_arg2 AS B FROM 
       unify2( 'superior' , 'A' , 'B' , 'Gustavo','Bob' );

-- does Greg work for Eugene? (no)
SELECT unify_arg1 AS A, unify_arg2 AS B FROM 
       unify2( 'superior' , 'A' , 'B' , 'Greg','Eugene' );