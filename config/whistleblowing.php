<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : whistleblowing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    /*
    | Modul-Schluessel (base64-kodierte 32 Byte). BEWUSST getrennt vom globalen
    | APP_KEY (Blast-Radius). Aus ihm wird der Key Encryption Key (KEK) abgeleitet,
    | der die per-Fall-DEKs wrappt. Fehlt er, verweigert der CryptoService den
    | Dienst (fail closed). Erzeugen: `base64_encode(random_bytes(32))`.
    */
    'key' => env('WHISTLEBLOWING_KEY'),

    /*
    | Separater Schluessel fuer die HMAC-Lookups (access_code_lookup). Getrennt
    | vom Verschluesselungs-Key gehalten; faellt auf einen abgeleiteten Wert
    | zurueck, wenn nicht gesetzt.
    */
    'lookup_key' => env('WHISTLEBLOWING_LOOKUP_KEY'),

    /*
    | Privater Storage-Disk fuer Meldeanhaenge (ausserhalb des Public-Pfads).
    */
    'disk' => 'whistleblowing',

    /*
    | Malware-Scanner-Treiber. 'none' (Default) = kein Scanner konfiguriert →
    | Anhaenge bleiben in Quarantaene (fail-safe, werden nie ausgeliefert), bis
    | ein echter Scanner (z. B. ClamAV) eingerichtet und das Ergebnis gesetzt
    | wird. Das Parsen attacker-kontrollierter Dateien gehoert in einen
    | gesandboxten Worker (Abschnitt 25).
    */
    'scanner' => env('WHISTLEBLOWING_SCANNER', 'none'),

    /*
    | Binary fuer den ClamAV-Treiber (wenn scanner = 'clamav').
    */
    'clamav_binary' => env('WHISTLEBLOWING_CLAMAV_BINARY', 'clamdscan'),

    /*
    | Aufbewahrung in Monaten nach Abschluss. Default 36 (3 Jahre, HinSchG §11);
    | pro Organisation ueber whistleblowing_portals.retention_months ueberschreibbar.
    */
    'retention_months' => (int) env('WHISTLEBLOWING_RETENTION_MONTHS', 36),

    /*
    | Gueltigkeitsdauer einer Notfallfreigabe in Minuten (Abschnitt 7.4/25):
    | zeitlich begrenzt, laeuft automatisch ab.
    */
    'emergency_ttl_minutes' => (int) env('WHISTLEBLOWING_EMERGENCY_TTL_MINUTES', 240),

    /*
    | Lebensdauer einer anonymen Postfachsitzung in Minuten (Abschnitt 7.2/25):
    | kurzlebig, serverseitig gebunden, gleitend verlaengert bei Aktivitaet.
    */
    'mailbox_session_minutes' => (int) env('WHISTLEBLOWING_MAILBOX_SESSION_MINUTES', 30),

    /*
    | Gesetzliche Fristen (Tage) – beim Anlegen persistiert, nicht zur Laufzeit
    | neu berechnet.
    */
    'deadlines' => [
        'acknowledge_days' => 7,   // Eingangsbestaetigung
        'feedback_days' => 90,     // Rueckmeldung ueber Folgemassnahmen (3 Monate)
    ],

    /*
    | Upload-Grenzen (siehe Abschnitt 11/24 des Konzepts).
    */
    'uploads' => [
        'max_bytes' => 25 * 1024 * 1024, // 25 MB pro Datei
        'max_per_case' => 10,
        'max_total_bytes' => 100 * 1024 * 1024, // Gesamt-Quota pro Fall
        'allowed_mimes' => [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'image/webp',
            'text/plain',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ],
];
