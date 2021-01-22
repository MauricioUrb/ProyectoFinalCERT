--Creación del usuario
CREATE USER manager with encrypted password 'hola123.,';

--Creación de la BD
CREATE DATABASE drupaldb WITH ENCODING='UTF8' OWNER=manager TEMPLATE=template0;

--Selección de la bd
\c drupaldb

--Creación de tablas
CREATE TABLE cvss
(
 id_cvss     bigserial NOT NULL,
 nivel_cvss  char(15) NOT NULL,
 vector_cvss char(108) NOT NULL,
 enlace_cvss char(159) NOT NULL,
 CHECK (vector_cvss ~* 'AV:[ANLP]\/AC:[HL]\/PR:[NLH]\/UI:[NR]\/S:[UC]\/C:[NLH]\/I:[NLH]\/A:[NLH](\/E:[UPFH])?(\/RL:[OTWU])?(\/RC:[URC])?(\/CR:[LMH])?(\/IR:[LMH])?(\/AR:[LMH])?(\/MAV:[NALP])?(\/MAC:[LH])?(\/MPR:[NLH])?(\/MUI:[NR])?(\/MS:[UC])?(\/MC:[NLH])?(\/MI:[NLH])?(\/MA:[NLH])?'),
 CONSTRAINT PK_cvss PRIMARY KEY ( id_cvss )
);

CREATE TABLE sitios
(
 id_sitios           bigserial NOT NULL,
 descripción_sitios  char(70) NOT NULL,
 dir_ip_sitios       text NOT NULL,
 dependencias_sitios text NOT NULL,
 url_sitios          char(2083) NOT NULL,
 CONSTRAINT PK_sitios PRIMARY KEY ( id_sitios )
);

CREATE TABLE usuarios
(
 id_usuarios    serial NOT NULL,
 nombre_usuario char(70) NOT NULL,
 correo         char(70) NOT NULL,
 CHECK (nombre_usuario ~* '[\wüáéíóúñÁÉÍÓÚ]+'),
 CHECK (correo ~* '[\d\w\.\_\-]+@[\w]+(\.\w[\w]+)+'),
 CONSTRAINT PK_usuarios PRIMARY KEY ( id_usuarios )
);

CREATE TABLE hallazgos
(
 id_hallazgos                    bigserial NOT NULL,
 nombre_hallazgo                 char(40) NOT NULL,
 descripcion_hallazgo            char(70) NOT NULL,
 solucion_recomendación_hallazgo char(70) NOT NULL,
 referencias_hallazgo            char(70) NOT NULL,
 id_sitios                       bigint NOT NULL,
 id_cvss                         bigint NOT NULL,
 reporte_ejecutivo               char(70) NOT NULL,
 recomendacion_general           char(70) NOT NULL,
 CONSTRAINT PK_hallazgos PRIMARY KEY ( id_hallazgos ),
 CONSTRAINT FK_sitios FOREIGN KEY ( id_sitios ) REFERENCES sitios ( id_sitios ),
 CONSTRAINT FK_cvss FOREIGN KEY ( id_cvss ) REFERENCES cvss ( id_cvss )
);

CREATE TABLE revisiones
(
 id_revision    bigserial NOT NULL,
 tipo_revisión  boolean NOT NULL,
 id_hallazgos   bigint NOT NULL,
 status         char(70) NOT NULL,
 fecha_revision date NOT NULL,
 CONSTRAINT PK_revisiones PRIMARY KEY ( id_revision ),
 CONSTRAINT FK_hallazgos FOREIGN KEY ( id_hallazgos ) REFERENCES hallazgos ( id_hallazgos )
);

CREATE TABLE comentarios
(
 id_comentarios bigserial NOT NULL,
 comentarios    char(70) NOT NULL,
 id_usuarios    integer NOT NULL,
 id_revision    bigint NOT NULL,
 CONSTRAINT PK_comentarios PRIMARY KEY ( id_comentarios ),
 CONSTRAINT FK_usuarios FOREIGN KEY ( id_usuarios ) REFERENCES usuarios ( id_usuarios ),
 CONSTRAINT FK_revisiones FOREIGN KEY ( id_revision ) REFERENCES revisiones ( id_revision )
);

