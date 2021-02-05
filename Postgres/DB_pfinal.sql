--Creación del usuario
CREATE USER manager with encrypted password 'hola123.,';

--Creación de la BD
CREATE DATABASE drupaldb WITH ENCODING='UTF8' OWNER=manager TEMPLATE=template0;

--Selección de la bd
\c drupaldb

--Creación de tablas
CREATE TABLE nivel_cvss
(
 id_nivel   int,
 tipo_nivel varchar(20) NOT NULL,
 CONSTRAINT PK_nivel_cvss PRIMARY KEY ( id_nivel )
);

CREATE TABLE dependencias
(
 id_dependencias    int,
 nombre_dependencia varchar(50) NOT NULL,
 CONSTRAINT PK_dependencias PRIMARY KEY ( id_dependencias )
);

CREATE TABLE usuarios
(
 id_usuarios    serial,
 nombre_usuario varchar(70) NOT NULL,
 apellidoP_usuario varchar(50) NOT NULL,
 apellidoM_usuario varchar(50) NULL,
 correo         varchar(70) NOT NULL,
 CHECK (nombre_usuario ~* '[\wüáéíóúñÁÉÍÓÚ]+'),
 CHECK (correo ~* '[\d\w\.\_\-]+@[\w]+(\.\w[\w]+)+'),
 CONSTRAINT PK_usuarios PRIMARY KEY ( id_usuarios )
);

CREATE TABLE status_revisiones
(
 id_status int,
 status    varchar(20) NOT NULL,
 CONSTRAINT PK_status_revisiones PRIMARY KEY ( id_status )
);

CREATE TABLE cvss
(
 id_cvss     serial,
 vector_cvss varchar(108) NOT NULL,
 enlace_cvss varchar(159) NOT NULL,
 id_nivel	int NOT NULL,
 CHECK (vector_cvss ~* 'AV:[ANLP]\/AC:[HL]\/PR:[NLH]\/UI:[NR]\/S:[UC]\/C:[NLH]\/I:[NLH]\/A:[NLH](\/E:[UPFH])?(\/RL:[OTWU])?(\/RC:[URC])?(\/CR:[LMH])?(\/IR:[LMH])?(\/AR:[LMH])?(\/MAV:[NALP])?(\/MAC:[LH])?(\/MPR:[NLH])?(\/MUI:[NR])?(\/MS:[UC])?(\/MC:[NLH])?(\/MI:[NLH])?(\/MA:[NLH])?'),
 CONSTRAINT PK_cvss PRIMARY KEY ( id_cvss ),
 CONSTRAINT FK_nivel FOREIGN KEY ( id_nivel ) REFERENCES nivel_cvss ( id_nivel )
);

CREATE TABLE sitios
(
 id_sitios           serial,
 descripción_sitios  text NOT NULL,
 dir_ip_sitios       text NOT NULL,
 dependencias_sitios text NOT NULL,
 url_sitios          varchar(2083) NOT NULL,
 id_dependencias	 int NOT NULL,
 CHECK ( dir_ip_sitios ~* '^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$ | ^(?:[A-F0-9]{1,4}:){7}[A-F0-9]{1,4}$' ),
 CONSTRAINT PK_sitios PRIMARY KEY ( id_sitios ),
 CONSTRAINT FK_dependencias FOREIGN KEY ( id_dependencias ) REFERENCES dependencias (id_dependencias)
);

CREATE TABLE hallazgos
(
 id_hallazgos                    serial,
 nombre_hallazgo                 varchar(40) NOT NULL,
 descripcion_hallazgo            varchar(70) NOT NULL,
 solucion_recomendación_hallazgo varchar(70) NOT NULL,
 referencias_hallazgo            varchar(70) NOT NULL,
 resumen_ejecutivo               varchar(70) NOT NULL,
 recomendacion_general           varchar(70) NOT NULL,
 CONSTRAINT PK_hallazgos PRIMARY KEY ( id_hallazgos )
);

CREATE TABLE cvss_hallazgos
(
 id_cvss      bigint NOT NULL,
 id_hallazgos bigint NOT NULL,
 CONSTRAINT FK_cvss FOREIGN KEY ( id_cvss ) REFERENCES cvss ( id_cvss ),
 CONSTRAINT FK_hallazgos FOREIGN KEY ( id_hallazgos ) REFERENCES hallazgos ( id_hallazgos )
);

CREATE TABLE sitios_hallazgos
(
 id_hallazgos bigint NOT NULL,
 id_sitios    integer NOT NULL,
 CONSTRAINT FK_hallazgos FOREIGN KEY ( id_hallazgos ) REFERENCES hallazgos ( id_hallazgos ),
 CONSTRAINT FK_sitios FOREIGN KEY ( id_sitios ) REFERENCES sitios ( id_sitios )
);

CREATE TABLE revisiones
(
 id_revision    serial NOT NULL,
 tipo_revisión  boolean NOT NULL,
 fecha_revision date NOT NULL,
 id_status		int NOT NULL,
 CONSTRAINT PK_revisiones PRIMARY KEY ( id_revision ),
 CONSTRAINT FK_status_revisiones FOREIGN KEY ( id_status ) REFERENCES status_revisiones ( id_status )
);

CREATE TABLE revisiones_hallazgos
(
 id_revision  bigint NOT NULL,
 id_hallazgos bigint NOT NULL,
 CONSTRAINT FK_revisiones FOREIGN KEY ( id_revision ) REFERENCES revisiones ( id_revision ),
 CONSTRAINT FK_hallazgos FOREIGN KEY ( id_hallazgos ) REFERENCES hallazgos ( id_hallazgos )
);

CREATE TABLE comentarios
(
 id_comentarios serial,
 comentarios    text NOT NULL,
 id_usuarios    integer NOT NULL,
 id_revision    bigint NOT NULL,
 CONSTRAINT PK_comentarios PRIMARY KEY ( id_comentarios ),
 CONSTRAINT FK_usuarios FOREIGN KEY ( id_usuarios ) REFERENCES usuarios ( id_usuarios ),
 CONSTRAINT FK_revisiones FOREIGN KEY ( id_revision ) REFERENCES revisiones ( id_revision )
);
