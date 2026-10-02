<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mcp.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// MCP-Server (MVP-1063/1065). Die Schlüssel `redirect_domains`, `custom_schemes`,
// `authorization_server` und `tool_search` stammen aus laravel/mcp und werden mit
// dessen Vorgaben zusammengeführt; `trusted_redirect_hosts` ist workDiary-eigen.
return [
    // Erlaubte https-Rücksprungziele bei der Client-Registrierung („Schema://Host“),
    // `*` = jedes https-Ziel. http ist unabhängig davon nur auf Loopback zulässig.
    'redirect_domains' => array_values(array_filter(explode(',', (string) env('MCP_REDIRECT_DOMAINS', '*')))),

    // Eigene URI-Schemata von Desktop-Clients (RFC 8252), durch PKCE gesichert.
    'custom_schemes' => array_values(array_filter(explode(',', (string) env('MCP_CUSTOM_SCHEMES', 'claude,cursor,vscode')))),

    // Rücksprungziele, bei denen die Zustimmungsseite nicht warnt.
    'trusted_redirect_hosts' => array_values(array_filter(explode(',', (string) env('MCP_TRUSTED_REDIRECT_HOSTS', 'claude.ai,chatgpt.com,chat.openai.com')))),
];
