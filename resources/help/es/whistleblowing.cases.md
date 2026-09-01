---
title: "Canal de denuncias – Gestión de casos"
topic: whistleblowing.cases
version: 1
audience: []
modules:
    - module.compliance
related:
    - whistleblowing.portal
    - whistleblowing.report
    - admin.security
    - privacy.overview
---

Aquí gestionas las denuncias recibidas de informantes internos y
externos (`/compliance/meldungen`). El permiso del canal está
deliberadamente **separado** de la administración: un administrador
global sin asignación al caso no tiene acceso y cada acceso se
comprueba mediante la política del caso, sin bypass de administrador;
además se exige una autenticación de dos factores propia. La lista
muestra solo datos maestros, **sin vista previa del contenido**, ya que
cada caso se cifra con su propia clave (DEK). En el detalle puedes
**confirmar la recepción** (plazo de 7 días), cambiar el **estado** a
lo largo del ciclo de vida, **asignar tramitadores**, registrar **notas
internas**, enviar **mensajes al informante** y descargar **adjuntos**
cifrados. Declarar un conflicto de intereses te excluye del caso,
marcar a una persona afectada la bloquea de forma permanente, y la
eliminación al final de la retención se realiza por **crypto-shredding**
(destrucción de la clave), de forma irreversible.
