--Seleccion de la BD
\c drupaldb_segundo manager

--Creación de tablas
CREATE TABLE dependencias (
	id_dependencia serial PRIMARY KEY,
	nombre_dependencia text NOT NULL
);

CREATE TABLE dir_ip (
	id_ip serial PRIMARY KEY,
	dir_ip_sitios varchar(32) NOT NULL,
	CHECK (dir_ip_sitios ~* '((^\s*((([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]))\s*$)|(^\s*((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?\s*$))')
);

CREATE TABLE sitios (
	id_sitio serial PRIMARY KEY,
	descripcion_sitio varchar(70) NOT NULL,
	url_sitio varchar(2083) NOT NULL
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
	nombre_hallazgo_vulnerabilidad varchar(40) NOT NULL,
	descripcion_hallazgo varchar(70) NOT NULL,
	solucion_recomendacion_halazgo varchar(70) NOT NULL,
	referencias_hallazgo varchar(70) NOT NULL,
	recomendacion_general_hallazgo varchar(70) NOT NULL,
	nivel_cvss varchar(15) NOT NULL, --3.2 BAJO
	vector_cvss varchar(108) NOT NULL, --AV:A/AC:H/PR:H/UI:R/S:C/C:H/I:H/A:H/E:H/RL:U/RC:C/CR:H/IR:H/AR:H/MAV:P/MAC:H/MPR:H/MUI:R/MS:C/MC:L/MI:H/MA:H
	enlace_cvss varchar(159) NOT NULL,
	r_ejecutivo_hallazgo varchar(70) NOT NULL,
	CHECK (vector_cvss ~* 'AV:[ANLP]\/AC:[HL]\/PR:[NLH]\/UI:[NR]\/S:[UC]\/C:[NLH]\/I:[NLH]\/A:[NLH](\/E:[UPFH])?(\/RL:[OTWU])?(\/RC:[URC])?(\/CR:[LMH])?(\/IR:[LMH])?(\/AR:[LMH])?(\/MAV:[NALP])?(\/MAC:[LH])?(\/MPR:[NLH])?(\/MUI:[NR])?(\/MS:[UC])?(\/MC:[NLH])?(\/MI:[NLH])?(\/MA:[NLH])?')
);

CREATE TABLE estatus_revisiones (
	id_estatus serial PRIMARY KEY,
	estatus varchar(10)
);

CREATE TABLE revisiones (
	id_revision serial PRIMARY KEY,
	tipo_revision boolean NOT NULL,
	id_estatus serial NOT NULL,
	fecha_inicio_revision date NOT NULL,
	fecha_fin_revision date NULL,
	FOREIGN KEY (id_estatus) REFERENCES estatus_revisiones(id_estatus)
);

CREATE TABLE comentarios (
	id_comentario serial PRIMARY KEY,
	id_revision serial NOT NULL,
	comentario varchar(70) NOT NULL,
	FOREIGN KEY (id_revision) REFERENCES revisiones(id_revision)
);

CREATE TABLE revisiones_asignadas (
	id_revision serial NOT NULL,
	id_usuario int NOT NULL,
	FOREIGN KEY (id_revision) REFERENCES revisiones(id_revision)
);

CREATE TABLE revisiones_sitios (
	id_rev_sitio serial PRIMARY KEY,
	id_revision serial NOT NULL,
	id_sitio serial NOT NULL,
	FOREIGN KEY (id_sitio) REFERENCES sitios(id_sitio),
	FOREIGN KEY (id_revision) REFERENCES revisiones(id_revision)
);

CREATE TABLE revisiones_hallazgos (
	id_rev_sitio serial NOT NULL,
	id_hallazgo serial NOT NULL,
	descripcion_hall_rev varchar(70) NOT NULL,
	recursos_afectador text NOT NULL,
	impacto_hall_rev varchar(12),
	cvss_hallazgos varchar(108) NOT NULL,
	FOREIGN KEY (id_rev_sitio) REFERENCES revisiones_sitios(id_rev_sitio),
	FOREIGN KEY (id_hallazgo) REFERENCES hallazgos(id_hallazgo)
);

insert into estatus_revisiones(estatus) values('Asignado');
insert into estatus_revisiones(estatus) values('En proceso');
insert into estatus_revisiones(estatus) values('Concluido');
insert into estatus_revisiones(estatus) values('Aprobado');
