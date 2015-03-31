#!/bin/sh
#-- COPYRIGHT 2007 Eugene Koontz <ekoontz@hiro-tan.org>
#-- This file is part of Psqlog : an 
#-- implementation of Prolog in PostgreSQL
#--
#-- Licenced under the GNU General Public License version 3.
#-- 
#--

#start from scratch..
dropdb -U www company || exit
createdb -U www company || exit

echo "CREATE LANGUAGE plpgsql" | psql -U www company

#load company's native schema
psql -U www company < company.sql

#create psqlog's views
xsltproc view2sql.xsl employee.xml | psql -U www company

# load psqlog's unify functionality
psql -U www company < unify.plpgsql

#load domain-specific psqlog rules.
psql -U www company < company_rules.sql

#run test
psql -U www company < test.sql

