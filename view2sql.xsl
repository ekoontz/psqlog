<?xml version="1.0" encoding="utf-8"?>

<!-- 
 COPYRIGHT 2007 Eugene Koontz <ekoontz@hiro-tan.org>
 This file is part of Psqlog : an 
 implementation of Prolog in PostgreSQL

 Licenced under the GNU General Public License version 3.
 
-->

<xsl:stylesheet 
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                version="1.0">

  <xsl:output method="text" encoding="utf-8"/>

  <xsl:template match="/psqlog">

    CREATE SEQUENCE instance_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

    CREATE TABLE instance ();
    ALTER TABLE instance ADD COLUMN arg varchar;
    ALTER TABLE instance ADD COLUMN value varchar;
    ALTER TABLE instance ADD COLUMN pred varchar;
    ALTER TABLE instance ADD COLUMN position int;
    ALTER TABLE instance ADD COLUMN id text;
    
 CREATE SEQUENCE pred_sequence;
 
 CREATE TABLE d_pred (
              pred_id varchar(256) DEFAULT 'd_'||NEXTVAL('pred_sequence') PRIMARY KEY,
 	      pred varchar(256),
 	      antec_of varchar(256) REFERENCES d_pred (pred_id) DEFAULT NULL
 
);

 CREATE RULE get_pkey_on_insert AS ON INSERT TO d_pred DO SELECT 'd_' || currval('pred_sequence') AS pred_id;
 
 CREATE TABLE d_arg (
       	      type char,
 	      name varchar(256),
 	      pred_id varchar(256),
 	      position int
 );

    <xsl:apply-templates select="pred1|pred2"/>

    CREATE VIEW pred AS SELECT * FROM d_pred 
      <xsl:apply-templates select="pred1|pred2" mode="union-pred"/>;

    CREATE VIEW arg AS SELECT * FROM d_arg 
      <xsl:apply-templates select="pred1|pred2" mode="union-arg"/>;

  </xsl:template>

  <xsl:template match="pred1|pred2" mode="union-pred">
    UNION SELECT * FROM <xsl:value-of select="@name"/>_pred <xsl:text> </xsl:text>
  </xsl:template>

  <xsl:template match="pred1|pred2" mode="union-arg">
    UNION SELECT * FROM <xsl:value-of select="@name"/>_arg <xsl:text> </xsl:text>
  </xsl:template>

  <xsl:template match="pred1">
    <xsl:variable name="wide_table"><xsl:value-of select="@name"/>_wide_pred</xsl:variable>
    <xsl:variable name="pred_table"><xsl:value-of select="@name"/>_pred</xsl:variable>
    <xsl:variable name="arg_table"><xsl:value-of select="@name"/>_arg</xsl:variable>

    CREATE VIEW <xsl:value-of select="$wide_table"/> AS
          SELECT '<xsl:value-of select="@table"/>_'||pred1_table.<xsl:value-of select="@table"/>_id AS pred_id, 
	    '<xsl:value-of select="arg[1]/@column"/>' AS pred, 
            'c' AS arg1_type, pred1_table.name AS arg1_name
          FROM <xsl:value-of select="@table"/> AS pred1_table WHERE pred1_table.<xsl:value-of select="arg[1]/@column"/> = 'true';

    CREATE VIEW <xsl:value-of select="$pred_table"/> AS 
          SELECT pred_id, pred, NULL AS antec_of FROM <xsl:value-of select="$wide_table"/>;   

    CREATE VIEW <xsl:value-of select="$arg_table"/> AS
          SELECT <xsl:value-of select="$wide_table"/>.arg1_type AS "type", 
            <xsl:value-of select="$wide_table"/>.arg1_name AS name, 
            <xsl:value-of select="$wide_table"/>.pred_id, 1 AS "position"
          FROM <xsl:value-of select="$wide_table"/>;

  </xsl:template>

  <xsl:template match="pred2">

    <xsl:variable name="wide_table"><xsl:value-of select="@name"/>_wide_pred</xsl:variable>
    <xsl:variable name="pred_table"><xsl:value-of select="@name"/>_pred</xsl:variable>
    <xsl:variable name="arg_table"><xsl:value-of select="@name"/>_arg</xsl:variable>

    CREATE OR REPLACE VIEW <xsl:value-of select="$wide_table"/> AS

      SELECT '<xsl:value-of select="@table"/>_'||<xsl:value-of select="@table"/>.<xsl:value-of select="@primary_key"/> AS pred_id, '<xsl:value-of select="@table"/>' AS pred, 

         'c' AS arg1_type, arg1.name AS arg1_name, 
         'c' AS arg2_type, arg2.name AS arg2_name 

        FROM <xsl:value-of select="@table"/>

	<xsl:apply-templates select="arg">
	  <xsl:with-param name="pred_table" select="@table"/>
	</xsl:apply-templates>;

	CREATE VIEW <xsl:value-of select="$pred_table"/> AS 
	  SELECT pred_id, pred, NULL AS antec_of FROM <xsl:value-of select="$wide_table"/>;

	CREATE VIEW <xsl:value-of select="$arg_table"/> AS
          SELECT 
             <xsl:value-of select="$wide_table"/>.arg1_type AS "type", 
             <xsl:value-of select="$wide_table"/>.arg1_name AS name, 
             <xsl:value-of select="$wide_table"/>.pred_id, 1 AS "position"

          FROM <xsl:value-of select="$wide_table"/>

        UNION 

          SELECT 
             <xsl:value-of select="$wide_table"/>.arg2_type AS "type", 
             <xsl:value-of select="$wide_table"/>.arg2_name AS name, 
             <xsl:value-of select="$wide_table"/>.pred_id, 2 AS "position"

          FROM <xsl:value-of select="$wide_table"/>;

  </xsl:template>

  <xsl:template match="arg">
    <xsl:param name="pred_table"/>
    LEFT JOIN <xsl:value-of select="@table"/> AS arg<xsl:value-of select="position()"/> 
         ON <xsl:value-of select="$pred_table"/>.<xsl:value-of select="@column"/> = arg<xsl:value-of select="position()"/>.<xsl:value-of select="@equals"/>
  </xsl:template>

  <xsl:template match="@*|node()"/>

</xsl:stylesheet>
