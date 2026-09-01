---
title: "Exportación GoBD (entrega de soporte de datos)"
topic: finance.gobd
version: 1
audience:
    - admin
modules:
    - module.finance
related:
    - invoices.manage
    - audit.log
---

Para las inspecciones fiscales, WorkDiary genera la entrega de soporte
de datos según el tipo de acceso Z3: un paquete de inspección conforme
al estándar de descripción GDPdU que el inspector puede cargar
directamente en su software de análisis.

**Contenido del paquete:** El paquete es un archivo ZIP con un
index.xml que describe de forma legible por máquina las tablas, los
campos y los formatos, además de archivos de datos CSV separados por
punto y coma. Las áreas de datos son seleccionables individualmente:
facturas emitidas, partidas de factura, datos maestros de deudores y
justificantes de tiempo del período de inspección.

**Período y comprobación previa:** Por defecto se preselecciona el año
anterior como período de inspección; las fechas desde/hasta son de libre
elección. Antes de la exportación, una comprobación previa muestra el
número de registros por área y advierte de anomalías — por ejemplo, si
en el período aún hay facturas en borrador o si no se encuentra ninguna
factura.

**Juego de caracteres:** Los archivos CSV se generan a elección en
CP1252 («ANSI», el estándar y la vía más segura del lado del
inspector), ISO-8859-15 o UTF-8; el archivo de descripción indica el
juego de caracteres elegido.

**Hash reproducible:** Todos los datos se ordenan y formatean de forma
determinista. El hash del paquete se calcula sobre el contenido de los
archivos (no sobre el binario ZIP, que contiene marcas de tiempo) — el
mismo período con las mismas áreas y el mismo juego de caracteres
produce por tanto, de forma reproducible, el mismo hash. Además se
documenta un hash propio por archivo. Con ello puede demostrarse
posteriormente, sin lugar a dudas, que un paquete entregado está
inalterado.

**Lista de constancias de exportación:** Cada exportación crea
automáticamente una constancia a prueba de revisión: quién exportó
cuándo qué período con qué áreas, incluidos los hashes del paquete y de
los archivos, así como el número de registros. Las últimas exportaciones
son visibles directamente en la página; el historial completo se
conserva de forma permanente y complementa el registro de auditoría.

La exportación lee exclusivamente datos existentes — no modifica ni
documentos ni datos maestros y puede repetirse tantas veces como se
desee.
