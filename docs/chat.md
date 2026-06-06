# Chat (Kanäle, Threads, Reaktionen, Umfragen)

Mitarbeiter-Chat mit Kanälen (öffentlich/privat), Gruppen, Direktnachrichten,
Datei-/Bild-Anhängen, Threads (Kommentaren), Reaktionen (Likes), angepinnten
Nachrichten und Umfragen. Echtzeit über Laravel Reverb (WebSockets) mit
Polling-Fallback.

## Architektur

- **Modelle** (`app/Models/Chat/`): `Channel`, `Message` (Threads via `parent_id`,
  SoftDeletes, polymorphe `Attachment`-Anhänge), `MessageReaction`, `Poll`,
  `PollOption`, `PollVote`. `Channel`/`Message` sind tenant-scoped
  (`BelongsToOrganization`); die Child-Modelle erben die Mandantengrenze
  transitiv (siehe `docs/security/tenant-audit-2026.md`).
- **Zugriff**: `ChannelPolicy`/`MessagePolicy` + Kanal-Mitgliedschaft. Private
  Kanäle = nur Mitglieder; öffentliche Kanäle = alle der Organisation.
- **Echtzeit**: Broadcast-Events (`app/Events/Chat/*`) auf dem privaten Channel
  `chat.channel.{id}` (Auth in `routes/channels.php`). Der Client lädt bei
  jedem Event die betroffene Nachricht inkrementell nach → eine Render-Quelle
  (Server-Blade `chat/_message.blade.php`).
- **Frontend**: `resources/js/chat.js` (Echo-Subscriptions + Event-Delegation +
  Polling-Fallback alle 6 s, falls keine WS-Verbindung), `resources/js/echo.js`
  (lazy `initEcho()`).

## Betrieb / Echtzeit aktivieren

`.env` (bereits gesetzt):

```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=…  REVERB_APP_KEY=…  REVERB_APP_SECRET=…
REVERB_HOST=…    REVERB_PORT=…     REVERB_SCHEME=…
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}" …
```

### Aktualisierung der Nachrichten

Der Chat aktualisiert sich **immer ohne Reload** — auch ganz ohne Daemons:

- **Polling (eingebaut, kein Setup):** Ohne aktive Echtzeit holt das Frontend
  alle ~3 s neue Nachrichten; bei aktiver Echtzeit nur als seltener Backstop.
  Das genügt für den Alltag und braucht **keine** Hintergrunddienste.
- **Echtzeit (sofort, optional):** Für sub-sekündliche Updates muss der
  WebSocket-Server **Reverb** laufen und die Broadcast-Events müssen verschickt
  werden.

Die Broadcast-Events laufen über die Queue (`QUEUE_CONNECTION=database`).
Möglichkeiten, sie zu verschicken:

```bash
php artisan reverb:start      # WebSocket-Server (persistenter Dienst)
php artisan queue:work        # verschickt Broadcasts sofort (persistenter Dienst)
```

**Per Cron statt Dauer-Dienst:** Der Queue-Worker kann minütlich per Cron laufen
(siehe `docs/server-cron-queue.md`) — Broadcasts werden dann aber erst bis zu
~1 min später zugestellt, also langsamer als das 3-s-Polling:

```cron
* * * * * cd /pfad/zur/app && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

**Wichtig zu Reverb:** Reverb ist ein *persistenter* WebSocket-Server und lässt
sich nicht sinnvoll „per Cron starten". Entweder unter einem Prozess-Manager
(**Supervisor/systemd**, empfohlen) betreiben oder per Cron-**Watchdog**
(minütlich neu starten, falls abgestürzt). Wer auf Reverb verzichtet, bleibt
beim Polling — der Chat funktioniert vollständig, nur eben mit ~3 s statt sofort.

## Offen / Ausbaustufen

- @Mentions inkl. Web-Push-Benachrichtigung (Web-Push via `minishlink/web-push`
  ist vorhanden), Volltextsuche über Nachrichten.
- i18n der Chat-UI-Strings für en/fr/it/es (Deutsch ist Quellsprache).
