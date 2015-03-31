-- COPYRIGHT 2007 Eugene Koontz <ekoontz@hiro-tan.org>
-- This file is part of Psqlog : an 
-- implementation of Prolog in PostgreSQL
--
-- Licenced under the GNU General Public License version 3.
-- 
--

CREATE OR REPLACE FUNCTION superior_rules ()
       RETURNS integer AS $$
       DECLARE pred1_id text;
       DECLARE pred2_id text;
       DECLARE pred3_id text;

       BEGIN

-- rule 1
	pred1_id := psqlog_insert2('superior','X','Y',NULL);

	pred2_id := psqlog_insert2('management','X','Z',pred1_id);
	pred3_id := psqlog_insert2('superior','Z','Y',pred1_id);

-- rule 2
	pred1_id := psqlog_insert2('superior','X','Y',NULL);
	pred2_id := psqlog_insert2('management','X','Y',pred1_id);

	RETURN 1;
	

       END;
$$ LANGUAGE plpgsql;

SELECT superior_rules();