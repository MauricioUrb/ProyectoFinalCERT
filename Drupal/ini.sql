--Creación del usuario
CREATE USER manager with encrypted password 'hola123.,';

--Creación de la BD
CREATE DATABASE drupaldb WITH ENCODING='UTF8' OWNER=manager TEMPLATE=template0;
CREATE DATABASE drupaldb_segundo WITH ENCODING='UTF8' OWNER=manager TEMPLATE=template0;--Selección de la bd

grant all on database drupaldb to manager;
grant all on database drupaldb_segundo to manager;
