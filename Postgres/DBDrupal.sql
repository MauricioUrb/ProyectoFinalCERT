--Creación del usuario
CREATE USER manager with encrypted password 'hola123.,';

--Creación de la BD
CREATE DATABASE drupaldb WITH ENCODING='UTF8' OWNER=manager TEMPLATE=template0;

--Selección de la bd con el owner (el usuario manager ya debe de estar en el archivo pg_hba con md5)
-- al agregar al usuario 'manager' se evita que las tablas se creen con el usuario 'postgres' y 
-- haya problemas de permisos al momento de insertar datos
\c drupaldb manager

--Creación de tablas
CREATE TABLE hallazgos
(
 id_hallazgo              serial,
 nombre                   varchar(40) NOT NULL,
 descripcion              varchar(70) NOT NULL,
 solucion_recomendacion_1 varchar(70) NOT NULL,
 referencias              varchar(70) NOT NULL,
 resumen_ejecutivo        varchar(70) NOT NULL,
 recomendacion_general    varchar(70) NOT NULL,
 nivel_cvss               varchar(20) NOT NULL,
 vector_cvss              varchar(108) NOT NULL,
 enlace_cvss              varchar(159) NOT NULL,
 CHECK (vector_cvss ~* 'AV:[ANLP]\/AC:[HL]\/PR:[NLH]\/UI:[NR]\/S:[UC]\/C:[NLH]\/I:[NLH]\/A:[NLH](\/E:[UPFH])?(\/RL:[OTWU])?(\/RC:[URC])?(\/CR:[LMH])?(\/IR:[LMH])?(\/AR:[LMH])?(\/MAV:[NALP])?(\/MAC:[LH])?(\/MPR:[NLH])?(\/MUI:[NR])?(\/MS:[UC])?(\/MC:[NLH])?(\/MI:[NLH])?(\/MA:[NLH])?'),
 CONSTRAINT PK_hallazgo_revision_clone PRIMARY KEY ( id_hallazgo )
);

CREATE TABLE ip
(
 id_ip        serial,
 direccion_ip text NOT NULL,
 CHECK ( direccion_ip ~* '((^\s*((([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]))\s*$)|(^\s*((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?\s*$))' ),
 CONSTRAINT PK_ip PRIMARY KEY ( id_ip )
);

CREATE TABLE hallazgo_revision
(
 id_hallazgo_rev          serial,
 nombre                   varchar(40) NOT NULL,
 descripcion              varchar(70) NOT NULL,
 solucion_recomendacion_1 varchar(70) NOT NULL,
 referencias              varchar(70) NOT NULL,
 resumen_ejecutivo        varchar(70) NOT NULL,
 recomendacion_general    varchar(70) NOT NULL,
 nivel_cvss               varchar(20) NOT NULL,
 vector_cvss              varchar(108) NOT NULL,
 enlace_cvss              varchar(159) NOT NULL,
 CHECK (vector_cvss ~* 'AV:[ANLP]\/AC:[HL]\/PR:[NLH]\/UI:[NR]\/S:[UC]\/C:[NLH]\/I:[NLH]\/A:[NLH](\/E:[UPFH])?(\/RL:[OTWU])?(\/RC:[URC])?(\/CR:[LMH])?(\/IR:[LMH])?(\/AR:[LMH])?(\/MAV:[NALP])?(\/MAC:[LH])?(\/MPR:[NLH])?(\/MUI:[NR])?(\/MS:[UC])?(\/MC:[NLH])?(\/MI:[NLH])?(\/MA:[NLH])?'),
 CONSTRAINT PK_hallazgo_revision PRIMARY KEY ( id_hallazgo_rev )
);

CREATE TABLE sitios
(
 id_sitios         serial NOT NULL,
 descripcion_sitio varchar(50) NOT NULL,
 url_sitio         varchar(2083) NOT NULL,
 CONSTRAINT PK_sitios PRIMARY KEY ( id_sitios )
);

CREATE TABLE revisiones
(
 id_revision           serial NOT NULL,
 tipo_revision         boolean NOT NULL,
 estatus_revision      varchar(20) NOT NULL,
 fecha_inicio_revision date NOT NULL,
 fecha_fin_revision    date NOT NULL,
 CONSTRAINT PK_revisiones PRIMARY KEY ( id_revision )
);

CREATE TABLE dependencias
(
 id_dependencias    serial,
 nombre_dependencia varchar(50) NOT NULL,
 CONSTRAINT PK_dependencias PRIMARY KEY ( id_dependencias )
);

CREATE TABLE dependencia_sitio
(
 id_dependencias integer NOT NULL,
 id_sitios       integer NOT NULL,
 CONSTRAINT PK_dependencia_sitio PRIMARY KEY ( id_dependencias, id_sitios ),
 CONSTRAINT FK_dependencias FOREIGN KEY ( id_dependencias ) REFERENCES dependencias ( id_dependencias ),
 CONSTRAINT FK_sitios FOREIGN KEY ( id_sitios ) REFERENCES sitios ( id_sitios )
);

CREATE TABLE ip_sitio
(
 id_ip     integer NOT NULL,
 id_sitios integer NOT NULL,
 CONSTRAINT PK_ip_sitio PRIMARY KEY ( id_ip, id_sitios ),
 CONSTRAINT FK_ip FOREIGN KEY ( id_ip ) REFERENCES ip ( id_ip ),
 CONSTRAINT FK_sitios FOREIGN KEY ( id_sitios ) REFERENCES sitios ( id_sitios )
);

CREATE TABLE revision_hallazgo
(
 id_revision     integer NOT NULL,
 id_hallazgo_rev integer NOT NULL,
 CONSTRAINT PK_revision_hallazgo PRIMARY KEY ( id_revision, id_hallazgo_rev ),
 CONSTRAINT FK_hallazgo_revision FOREIGN KEY ( id_hallazgo_rev ) REFERENCES hallazgo_revision ( id_hallazgo_rev ),
 CONSTRAINT FK_revision FOREIGN KEY ( id_revision ) REFERENCES revisiones ( id_revision )
);

CREATE TABLE comentarios
(
 id_comentario serial,
 id_usuario    integer NOT NULL,
 comentario    text  NOT NULL,
 CONSTRAINT PK_comentarios PRIMARY KEY ( id_comentario, id_usuario ),
);

CREATE TABLE revision_comentario
(
 id_comentario integer NOT NULL,
 id_revision   integer NOT NULL,
 CONSTRAINT PK_revision_comentario PRIMARY KEY ( id_comentario, id_revision ),
 CONSTRAINT FK_118 FOREIGN KEY ( id_comentario ) REFERENCES comentarios ( id_comentario ),
 CONSTRAINT FK_122 FOREIGN KEY ( id_revision ) REFERENCES revisiones ( id_revision )
);

CREATE TABLE revision_sitio
(
 id_sitios   integer NOT NULL,
 id_revision integer NOT NULL,
 CONSTRAINT PK_revision_sitio PRIMARY KEY ( id_sitios, id_revision ),
 CONSTRAINT FK_sitios FOREIGN KEY ( id_sitios ) REFERENCES sitios ( id_sitios ),
 CONSTRAINT FK_revision FOREIGN KEY ( id_revision ) REFERENCES revisiones ( id_revision )
);

CREATE TABLE revisiones_usuarios
(
 id_revision integer NOT NULL,
 id_usuario  integer NOT NULL,
 CONSTRAINT PK_revisiones_usuarios PRIMARY KEY ( id_revision, id_usuario ),
 CONSTRAINT FK_revision FOREIGN KEY ( id_revision ) REFERENCES revisiones ( id_revision ),
 CONSTRAINT FK_usuario FOREIGN KEY ( id_usuario ) REFERENCES usuarios ( id_usuario )
);





