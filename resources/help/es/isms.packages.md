---
title: "Paquetes de auditoría y enlaces para auditores"
topic: isms.packages
version: 1
audience: []
related:
    - isms.audits
    - isms.conformity
    - isms.overview
    - glossary.core
---

Los **paquetes de auditoría** congelan el estado de datos del SGSI a
una fecha de corte como instantánea, base fiable para auditores
externos. Flujo: crear el paquete como borrador (título, fecha de
corte, alcance, norma opcional), **finalizarlo** (genera la instantánea
JSON con hash SHA-256 y registra quién y cuándo), verificar la
integridad contra el hash cuando se desee y crear un **enlace para
auditores** con acceso de descarga limitado en el tiempo (1–90 días,
revocable). Los paquetes finalizados son **inmutables** y el enlace
completo solo se muestra una vez al crearlo. Lectura con permisos de
lectura ISMS; creación y gestión con permisos de gestión ISMS.
