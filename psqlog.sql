--
-- PostgreSQL database dump
--

SET client_encoding = 'SQL_ASCII';
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: postgres81
--

COMMENT ON SCHEMA public IS 'Standard public schema';


SET search_path = public, pg_catalog;

--
-- Name: arg_seq; Type: SEQUENCE; Schema: public; Owner: www
--

CREATE SEQUENCE arg_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.arg_seq OWNER TO www;

--
-- Name: arg_seq; Type: SEQUENCE SET; Schema: public; Owner: www
--

SELECT pg_catalog.setval('arg_seq', 17442, true);


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: arg; Type: TABLE; Schema: public; Owner: www; Tablespace: 
--

CREATE TABLE d_arg (
    id integer DEFAULT nextval('arg_seq'::regclass) NOT NULL,
    "type" character(1),
    name character varying(64),
    pred_id integer,
    "position" integer,
    pred character varying(64)
);


ALTER TABLE public.arg OWNER TO www;

--
-- Name: pred_seq; Type: SEQUENCE; Schema: public; Owner: www
--

CREATE SEQUENCE pred_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.pred_seq OWNER TO www;

--
-- Name: pred_seq; Type: SEQUENCE SET; Schema: public; Owner: www
--

SELECT pg_catalog.setval('pred_seq', 9762, true);


--
-- Name: pred; Type: TABLE; Schema: public; Owner: www; Tablespace: 
--

CREATE TABLE pred (
    id integer DEFAULT nextval('pred_seq'::regclass) NOT NULL,
    name character varying(64),
    antec_of integer
);


ALTER TABLE public.pred OWNER TO www;


--
-- Name: arg_pkey; Type: CONSTRAINT; Schema: public; Owner: www; Tablespace: 
--

ALTER TABLE ONLY arg
    ADD CONSTRAINT arg_pkey PRIMARY KEY (id);


--
-- Name: pred_pkey; Type: CONSTRAINT; Schema: public; Owner: www; Tablespace: 
--

ALTER TABLE ONLY pred
    ADD CONSTRAINT pred_pkey PRIMARY KEY (id);


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres81
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres81;
GRANT ALL ON SCHEMA public TO postgres81;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

