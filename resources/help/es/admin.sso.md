---
title: "SSO y servicios de directorio"
topic: admin.sso
version: 1
audience:
    - admin
modules:
    - module.sso
related:
    - admin.integrations
---

En esta página gestiona la conexión con el proveedor de identidad de
su organización: aprovisionamiento SCIM, inicio de sesión único OIDC y
SAML 2.0. El módulo forma parte del plan Enterprise.

**Aprovisionamiento SCIM:** su proveedor de identidad (Entra ID,
Keycloak, Okta …) crea, actualiza y desactiva cuentas a través del
endpoint SCIM. Para ello emite un token bearer — el texto en claro se
muestra exactamente una vez. Una cuenta desactivada en el directorio
pierde de inmediato el acceso, las sesiones y los tokens de API. SCIM
nunca concede roles; puede asignar deliberadamente grupos SCIM a un
equipo.

**Inicio de sesión único OIDC:** guarde el issuer, el ID de cliente y
el secreto de cliente del registro de aplicación de su proveedor de
identidad y registre allí la URL de callback mostrada. Los empleados
inician la sesión a través de la URL de inicio SSO o del enlace
«Iniciar sesión con inicio de sesión único» en la página de acceso
(que solicita el identificador de la organización).

**SAML 2.0:** guarde el entity ID, la URL SSO y el certificado de
firma del proveedor de identidad; facilite al IdP la URL de metadatos
del SP. Las respuestas deben contener aserciones firmadas; los accesos
iniciados por el IdP (no solicitados) se rechazan. Para la rotación de
certificados puede guardar un certificado sucesor en paralelo.

**Vinculación de cuentas:** una identidad del IdP se vincula a una
cuenta de WorkDiary exclusivamente mediante issuer + subject. El SSO
nunca crea cuentas ni concede roles — las cuentas llegan por SCIM o se
crean manualmente. Opcionalmente, la vinculación inicial por correo
puede conectar una cuenta existente mediante su dirección de correo en
el primer acceso (solo con exactamente una coincidencia).

**SSO obligatorio y break-glass:** el SSO obligatorio bloquea en el
servidor el inicio de sesión con contraseña para todas las cuentas de
la organización. Defina antes al menos una cuenta de emergencia: una
cuenta no federada que puede seguir iniciando sesión localmente — de
lo contrario, una caída del proveedor de identidad bloquea a toda la
organización. Cada acceso de emergencia queda auditado.

**Multifactor:** tras un acceso SSO, la verificación multifactor es
responsabilidad del proveedor de identidad. El acceso local, incluidos
los métodos de dos factores, permanece sin cambios para las cuentas
sin SSO.
