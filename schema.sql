--
-- PostgreSQL database dump
--

-- Dumped from database version 9.5.14
-- Dumped by pg_dump version 9.5.14

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


--
-- Name: course_ids; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.course_ids
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: course; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.course (
    id integer DEFAULT nextval('public.course_ids'::regclass),
    name text,
    description text
);


--
-- Name: grade_ids; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.grade_ids
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: grade; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.grade (
    id integer DEFAULT nextval('public.grade_ids'::regclass),
    homework_id integer,
    problem_id integer,
    userid text,
    grade double precision,
    comment text,
    grader_id integer,
    graded boolean
);


--
-- Name: grader_ids; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.grader_ids
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: grader; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.grader (
    id integer DEFAULT nextval('public.grader_ids'::regclass),
    userid text,
    name text,
    course_id integer,
    instructor boolean
);


--
-- Name: homework_ids; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.homework_ids
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: homework; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.homework (
    id integer DEFAULT nextval('public.homework_ids'::regclass),
    course_id integer,
    name text,
    due_date text,
    directory text
);


--
-- Name: problem_ids; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.problem_ids
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: problem; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.problem (
    id integer DEFAULT nextval('public.problem_ids'::regclass),
    homework_id integer,
    points double precision,
    name text,
    extra_credit boolean
);


--
-- Data for Name: course; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.course (id, name, description) FROM stdin;
\.


--
-- Name: course_ids; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.course_ids', 1, false);


--
-- Data for Name: grade; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.grade (id, homework_id, problem_id, userid, grade, comment, grader_id, graded) FROM stdin;
\.


--
-- Name: grade_ids; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.grade_ids', 1, false);


--
-- Data for Name: grader; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.grader (id, userid, name, course_id, instructor) FROM stdin;
\.


--
-- Name: grader_ids; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.grader_ids', 1, false);


--
-- Data for Name: homework; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.homework (id, course_id, name, due_date, directory) FROM stdin;
\.


--
-- Name: homework_ids; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.homework_ids', 1, false);


--
-- Data for Name: problem; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.problem (id, homework_id, points, name, extra_credit) FROM stdin;
\.


--
-- Name: problem_ids; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.problem_ids', 1, false);


--
-- PostgreSQL database dump complete
--

