<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mcp.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// MCP-Server für KI-Assistenten (MVP-1063/1064).
return [
    'error' => [
        'forbidden' => 'No access to this tool.',
        'scope' => 'This token is not enabled for AI assistants (MCP). Create a token with the “AI assistant (MCP)” permission.',
        'not_found' => 'Not found.',
        'invalid_number' => 'Line :position: quantity, price or tax rate is not a number.',
        'conflicts' => 'Not moved — conflicts: :list',
    ],
    'oauth' => [
        'title' => 'Connect AI assistant',
        'intro' => ':client wants to access the data of :organization in workDiary on behalf of :user.',
        'scope' => [
            'read' => 'Read: customers, projects, orders, quotes, invoices, open items, time, appointments, key figures and search — with your permissions.',
            'write' => 'Create drafts: quote and invoice drafts, create customers, move assignments. Issuing and sending remain with you.',
        ],
        'target' => 'After you approve, access goes to',
        'untrusted_warning' => 'This target does not belong to any known AI assistant. Only approve if you set up this connection yourself just now — otherwise a stranger gains access to your data.',
        'untrusted_confirm' => 'I set up the connection to :target myself.',
        'untrusted_required' => 'Please confirm that you set up the connection to this target yourself.',
        'revoke_hint' => 'You can revoke access at any time under Profile → API tokens.',
        'approve' => 'Allow access',
        'deny' => 'Deny',
        'error' => [
            'title' => 'Connection not possible',
            'client' => 'Unknown client — please set up the connector again.',
            'redirect_uri' => 'The redirect address is not registered for this client or not allowed (only https or local addresses).',
            'response_type' => 'Only the response type “code” is supported.',
            'pkce' => 'PKCE with S256 is required.',
            'resource' => 'The requested resource is not this MCP server.',
            'scope' => 'No valid permission requested (mcp:read, mcp:write).',
            'grant' => 'Code or refresh token is invalid, expired or already used.',
            'grant_type' => 'This grant type is not supported.',
            'disabled' => 'Your organization has not enabled AI assistants (MCP). Administrators enable this in the organization settings.',
            'no_organization' => 'Your account does not belong to an organization.',
        ],
    ],
];
