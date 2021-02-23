--Creación de la BD
--Creación del usuario
CREATE USER manager with encrypted password 'hola123.,';

--Creación de la BD
CREATE DATABASE drupaldb WITH ENCODING='UTF8' OWNER=manager TEMPLATE=template0;
CREATE DATABASE drupaldb_segundo WITH ENCODING='UTF8' OWNER=manager TEMPLATE=template0;

