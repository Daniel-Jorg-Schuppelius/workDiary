---
title: "Acceso bancario EBICS"
topic: finance.ebics
version: 1
keywords:
    - conexión bancaria
    - extractos bancarios
    - extracto diario
    - importar movimientos
    - enviar remesa de pagos
    - transferencias
    - carta INI
    - claves bancarias
    - firma electrónica
    - configurar EBICS
    - interfaz bancaria
    - CAMT
audience: []
modules:
    - module.finance
related:
    - finance.reconciliation
---

Con EBICS (versión 3.0), workDiary recupera los extractos diarios
directamente del banco y envía las remesas de pago, sin descargar ni subir
archivos en la banca en línea.

**Configuración:** En cuentas bancarias, el símbolo del banco abre el
acceso EBICS de la cuenta. Introduzca la URL EBICS, el ID de host, el ID
de cliente y el ID de participante de la carta de acceso del banco.
Después, en este orden: generar las claves, enviarlas al banco (INI y
HIA), descargar la carta de inicialización, firmarla y enviarla al banco.
Cuando el banco haya activado el acceso, recupere las claves del banco;
solo entonces el acceso está activo.

**Extractos diarios:** Un acceso activado recupera cada mañana los
extractos (camt.053) y los incorpora a la conciliación de pagos;
«Recuperar extractos ahora» lo hace de inmediato. Los extractos ya
importados se reconocen y se omiten.

**Remesas de pago:** Una remesa autorizada puede enviarse al banco con
«Enviar por EBICS»: el mismo archivo que está disponible para descargar, y
exactamente una vez. El firmante autorizado concede después la
autorización del pago (firma electrónica) en el banco.

**Seguridad:** Las claves se guardan cifradas y además están protegidas
con una frase de contraseña. Cada paso y cada orden quedan registrados en
el historial del acceso. Ante sospecha de abuso, «Bloquear acceso» bloquea
las claves en el banco; después la configuración empieza de nuevo con
claves nuevas.
