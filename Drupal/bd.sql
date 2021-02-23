--Creación del usuario
CREATE USER manager with encrypted password 'hola123.,';

--Creación de la BD
CREATE DATABASE drupaldb WITH ENCODING='UTF8' OWNER=manager TEMPLATE=template0;
CREATE DATABASE drupaldb_segundo WITH ENCODING='UTF8' OWNER=manager TEMPLATE=template0;--Selección de la bd

--Seleccion de la BD
\c drupaldb_segundo

--Creación de tablas
CREATE TABLE dependencias (
	id_dependencia serial PRIMARY KEY,
	nombre_dependencia text NOT NULL
);

CREATE TABLE dir_ip (
	id_ip serial PRIMARY KEY,
	dir_ip_sitios char(32) NOT NULL,
	CHECK (dir_ip_sitios ~* '((^\s*((([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]))\s*$)|(^\s*((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?\s*$))')
);

CREATE TABLE sitios (
	id_sitio serial PRIMARY KEY,
	descripcion_sitio char(70) NOT NULL,
	url_sitio char(2083) NOT NULL
);

CREATE TABLE dependencias_sitios (
	id_dependencia serial NOT NULL,
	id_sitio serial NOT NULL,
	FOREIGN KEY (id_dependencia) REFERENCES dependencias(id_dependencia),
	FOREIGN KEY (id_sitio) REFERENCES sitios(id_sitio)
);

CREATE TABLE ip_sitios (
	id_ip serial NOT NULL,
	id_sitio serial NOT NULL,
	FOREIGN KEY (id_ip) REFERENCES dir_ip(id_ip),
	FOREIGN KEY (id_sitio) REFERENCES sitios(id_sitio)
);

/*
Vector: 
		CVSS:3.0/AV:A/AC:H/PR:H/UI:R/S:C/C:H/I:H/A:H/E:H/RL:U/RC:C/CR:H/IR:H/AR:H/MAV:P/MAC:H/MPR:H/MUI:R/MS:C/MC:L/MI:H/MA:H
		CVSS:3.0/AV:A/AC:H/PR:L/UI:R/S:C/C:N/I:N/A:N/E:U/RL:T/RC:U
		CVSS:3.0/AV:A/AC:H/PR:L/UI:R/S:C/C:N/I:N/A:N 	<- estos son los obligatorios, los demás pueden no ir
URL: 	
		https://www.first.org/cvss/calculator/3.0#CVSS:3.0/AV:A/AC:H/PR:H/UI:R/S:C/C:H/I:H/A:H/E:H/RL:U/RC:C/CR:H/IR:H/AR:H/MAV:P/MAC:H/MPR:H/MUI:R/MS:C/MC:L/MI:H/MA:H
		https://www.first.org/cvss/calculator/3.0#CVSS:3.0/AV:A/AC:H/PR:L/UI:R/S:C/C:N/I:N/A:N/E:U/RL:T/RC:U
		https://www.first.org/cvss/calculator/3.0#CVSS:3.0/AV:A/AC:H/PR:L/UI:R/S:C/C:N/I:N/A:N

El conteo de caracteres del vector es sin el inicio CVSS:3.0/, dado que así viene especificado en el documento

*/
CREATE TABLE hallazgos (
	id_hallazgo serial PRIMARY KEY,
	nombre_hallazgo_vulnerabilidad char(40) NOT NULL,
	descripcion_hallazgo char(70) NOT NULL,
	solucion_recomendacion_halazgo char(70) NOT NULL,
	referencias_hallazgo char(70) NOT NULL,
	recomendacion_general_hallazgo char(70) NOT NULL,
	nivel_cvss char(15) NOT NULL, --3.2 BAJO
	vector_cvss char(108) NOT NULL, --AV:A/AC:H/PR:H/UI:R/S:C/C:H/I:H/A:H/E:H/RL:U/RC:C/CR:H/IR:H/AR:H/MAV:P/MAC:H/MPR:H/MUI:R/MS:C/MC:L/MI:H/MA:H
	enlace_cvss char(159) NOT NULL,
	r_ejecutivo_hallazgo char(70) NOT NULL,
	CHECK (vector_cvss ~* 'AV:[ANLP]\/AC:[HL]\/PR:[NLH]\/UI:[NR]\/S:[UC]\/C:[NLH]\/I:[NLH]\/A:[NLH](\/E:[UPFH])?(\/RL:[OTWU])?(\/RC:[URC])?(\/CR:[LMH])?(\/IR:[LMH])?(\/AR:[LMH])?(\/MAV:[NALP])?(\/MAC:[LH])?(\/MPR:[NLH])?(\/MUI:[NR])?(\/MS:[UC])?(\/MC:[NLH])?(\/MI:[NLH])?(\/MA:[NLH])?')
);

CREATE TABLE revisiones (
	id_revision serial PRIMARY KEY,
	tipo_revision boolean NOT NULL,
	estatus_revision char(15) NOT NULL,
	fecha_inicio_revision date NOT NULL,
	fecha_fin_revision date NULL
);

CREATE TABLE comentarios (
	id_comentario serial PRIMARY KEY,
	id_revision serial NOT NULL,
	comentario char(70) NOT NULL,
	FOREIGN KEY (id_revision) REFERENCES revisiones(id_revision)
);

CREATE TABLE revisiones_asignadas (
	id_revision serial NOT NULL,
	id_usuario int NOT NULL,
	FOREIGN KEY (id_revision) REFERENCES revisiones(id_revision)
);

CREATE TABLE revisiones_sitios_hallazgos (
	id_revision serial NOT NULL,
	id_sitio serial NOT NULL,
	id_hallazgo serial NOT NULL,
	id_rev_sitio serial NOT NULL,
	descripcion_hall_rev char(70) NOT NULL,
	recursos_afectador text NOT NULL,
	impacto_hall_rev char(12),
	cvss_hallazgos char(108) NOT NULL,
	FOREIGN KEY (id_sitio) REFERENCES sitios(id_sitio),
	FOREIGN KEY (id_hallazgo) REFERENCES hallazgos(id_hallazgo),
	FOREIGN KEY (id_revision) REFERENCES revisiones(id_revision)
);

grant all on database drupaldb to manager;
grant all on database drupaldb_segundo to manager;
