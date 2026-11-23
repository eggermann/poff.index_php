# Umgebungen und Testordner

Inventar aktualisiert am 22. November 2026. Lokale Pfade, vorhandene Einstiegspunkte und effektive Deploy-Ziele wurden auf der Platte bzw. anhand des Deploy-Resolvers geprüft. Öffentliche Websites wurden nicht live geprüft. Zugangsdaten sind hier nicht enthalten.

## 10 lokale PageOff-Einstiegspunkte

Alle liegen unter `/Applications/MAMP/htdocs/`. Die Zahl zählt Pfade mit eigener `index.php` im bekannten PageOff-Bestand. Einige teilen denselben Projektbaum. Die lokalen Demos laufen unter demselben MAMP-Webserver.

| Anzahl | Wo | Zweck |
| --- | --- | --- |
| 4 | `pageoff-artist`, `pageoff-writer`, `pageoff-curator`, `pageoff-civic` | Eigenständige Studien mit eigenen Layouts, Werken und Nachbarlinks. Curator hat auch übernommene Werke. |
| 2 | `node-1`, `node-2` | Eigenständige Minimalumgebungen für Node-Tests. Historisch als leere, getrennte Starts angelegt. |
| 1 | `pageoff` | Lokale PageOff-Umgebung. `pageoff/actual` ist interner Symlink ins Repo unter `pages/pageoff.de`; der Datenordner hat keinen eigenen `index.php`-Einstieg und ist kein separates Build-Ziel. |
| 1 | `MAUSMAUS` | Eigenständiger lokaler Bestandswebspace. |
| 1 | `dominikeggermann-project` | Ein flacher MAMP-Einstieg in die Projektwurzel. `page` bleibt Projektinhalt über Symlink-Alias, bekommt aber keinen eigenen Einstieg und kein separates Build-Ziel. |
| 1 | `dominikeggermann.com` | Lokaler Symlink auf die primäre Build-Ausgabe des Repos. |

Damit ergeben sich acht eigenständige Kopierziele, ein zusätzliches Kopierziel und ein Pfad zur primären Build-Ausgabe. Die vier Demos und zwei Minimalumgebungen belegen eigenständige Node-Umgebungen; sie sind nicht automatisch als Hosts im Sinn des aktuellen Konzepts aktiviert. [Build-Konfiguration](../../../build/BuildConfig.shared.json)

## 5 dokumentierte öffentliche Umgebungen, plus ein Zielbild

| Umgebung | Beleg und Einordnung |
| --- | --- |
| `dominikeggermann.com` | Portfolio auf eggman1, laut früherem Review deployt und damals im Browser geprüft. |
| `pageoff.de` | Bisherige Adresse für PageOff-Konverter und Node-Anmeldungen im Code. `pageoff` ist ein dokumentiertes Deploy-Ziel auf eggman4. |
| `dodi` | Dokumentierter eggman4-Webspace `/dodi/` für den Copy-Baum und frühere Converter-Versuche. `dodi1` bezeichnet laut Doku denselben Webspace. |
| `dodi2` | Historisch als separate öffentliche Seite dokumentiert. `dodi_2` ist der Deploy-Name für die zweite Profilfamilie. |
| `customer` | Dokumentiertes öffentliches Deploy-Ziel auf eggman4. |
| `off.page` | Geplanter offizieller Host im neuen Modell. Ob und wie die Domain zur bisherigen Installation passt, ist offen. |

[Früherer Produktionsreview](../../../.mlms/memory-bank/sprintReview.md) · [Deploy-Pfade](../copilot-chat-and-pages.md) · [dodi1](../dodi1-webspace.md) · [dodi und dodi2](../dodi-current-state.md) · [Deploy-Profile](../../../scripts/deploy/deploy-targets.js)

## Testordner

Gezählt werden **3 dedizierte Testbereiche im Repo**. Verschachtelte Fälle und Dateien gehören jeweils zu ihrem Bereich.

| Pfad | Verwendung |
| --- | --- |
| `tests/datas/` | Quelldaten für Ordner- und Kopiertests mit verschachtelten Markdown-Dateien. |
| `tests/poff-tests/` | Testbaum für Viewer, Layout, Upload, Links und Ordner. Die MCP-Suite legt den Baum beim Start neu an. |
| `poff/test-converter/` | Converter-Beispiele für Text und Bild. `dodi1/` ist ein Unterordner mit `scan-001.tiff`. |

Dazu kommen **28 Jest-Suites und 52 PHP-Testhilfen** unter `tests/`. Einzelne Tests erzeugen eigene temporäre Verzeichnisse, darunter zwei getrennte Node-Verzeichnisse für den PHP-HTTP-Provider-Test; diese sind keine dauerhaften Webspaces. Mehrere Converter-Tests lesen außerdem `archive/scan-001.tiff` als Fixture. `poff/converters/` enthält zwei Converter-Beispielpakete: `convert-image` und `convert-text`. Der MAMP-Ordner `test-poff/` hat keinen PHP-Einstieg und ist in den elf PHP-Einstiegen nicht enthalten. [MCP-Testaufbau](../../../tests/mcp.create.test.js)

## Aktuell tatsächlich aufgelöste Deploy-Ziele

Die Profile lesen aus `.env` und Prozessvariablen. Für die konfigurierten Profile verwendet der Index-Modus `pages/dominikeggermann.com`. Server ist `neso.uberspace.de` und die Zielordner liegen unter `/var/www/virtual/eggman4/html/pages/`. Die Tabelle beruht auf der lokalen Profilauflösung.

| Kommando / Profil | Effektiver Zielpfad unter `pages/` | Einordnung |
| --- | --- | --- |
| `npm run deploy` / default | `dodi/actual/` | Standardziel |
| `npm run deploy:dodi` | `dodi/actual/` | Dasselbe Ziel |
| `npm run deploy:dodi1` | `dodi/actual/` | Eigene Variablen mit Suffix `1`, derselbe Webspace |
| `npm run deploy:dodi_2` | `dodi2/actual/` | Variablen mit Suffix `2` |
| `npm run deploy:customer` | Abbruch vor Verbindung | Eigenes Profil fehlt |
| `npm run deploy:pageoff` | Abbruch vor Verbindung | Eigenes Profil fehlt |

`customer` und `pageoff` sind in der Doku als getrennte Webspaces beschrieben, aktuell aber lokal nicht als getrennte Deploy-Ziele konfiguriert. Fehlende benannte Profile werden jetzt vor einer Verbindung abgewiesen. Es gibt keinen Rückfall auf Standardwerte; das gilt auch für Authentifizierung und Quellpfad. Der dokumentierte eggman1-Deploy von `dominikeggermann.com` ist ebenfalls kein Ziel der aktuellen Standardkonfiguration.

## Build, Watch und Upload

1. `npm run build` baut JS/CSS und die PHP-Ausgabe nach `pages/dominikeggermann.com/index.php`. Der normale Build verteilt die Ausgabe zusätzlich auf die acht MAMP-Standalone-Ziele und zwei Projektziele. Dabei werden generierte Unterordner-Einstiege bereinigt und Converter-Sidecars gespiegelt. Ein Build wirkt somit auf mehrere lokale Umgebungen.
2. `npm run watch` überwacht `src/`, startet beim Aufruf bereits einen Build und baut bei Änderungen erneut. Er pflegt den MAMP-Symlink und fordert Browser-Reload an. Lokale Standard-URL: `http://localhost:8888/dominikeggermann.com/`; Port 8888 ist die Watcher-Vorgabe, nicht durch einen Live-Check bestätigt.
3. `npm run build:production` erzeugt `dist/index.php` und legt die mitgelieferten Converter-Dateien unter `dist/worktypes/templates/converter/` ab. Die mitgelieferte Profilgrafik liegt unter `dist/.layout/poff.profile.jpg`. Zusätzliche lokale Verteilungskopien bleiben dem normalen Build vorbehalten.
4. `npm run deploy -- dodi --release` verwendet fest `dist/`. Deploy prüft lokales und entferntes PHP, vergleicht die Prüfsummen und veröffentlicht die Converter-Dateien vor `index.php`. Die Profilgrafik ergänzt der Release im Zielordner, falls dort noch keine vorhanden ist. Der einzelne Dateiaustausch ist atomar, ein abgebrochener Release kann bereits aktualisierte Converter-Dateien hinterlassen.
5. `npm run deploy` verwendet die konfigurierte Quelle. Im Standardmodus wird nur eine neuere `index.php` ausgetauscht. Beide Modi verwenden SSH/SFTP über `node-ssh` und erwarten einen vorhandenen Build.
6. `npm run deploy -- dodi --full` überträgt neue öffentliche Inhalte und `.layout`-Dateien aus der konfigurierten Quelle. Symlinks, `.works`, versteckte Dateien, `poff.node.json`, `poff.config.json`, `poff.config.toon` und die mitgelieferten Converter-Dateien sind dabei ausgeschlossen. Anschließend wird die oberste `index.php` separat geprüft und ausgetauscht. Transferfehler brechen den Lauf ab. Entfernte Inhalte werden nicht gelöscht.

## Backups und Wiederherstellung

Der Austausch legt die Kandidatendatei und die vorherige Version im privaten Serverordner `~/.poff-deploy-backups/` an. Der Ordner muss außerhalb des Zielordners und auf demselben Dateisystem liegen. Der Linux-Host benötigt PHP CLI, `sha256sum`, GNU Coreutils und `flock`. Bei einem Fehler vor dem Rename bleibt die jeweilige bisherige Datei aktiv.

Für einen Index-Rollback den ausgegebenen absoluten Backup-Pfad mit demselben Ziel verwenden. Der Restore prüft die Datei erneut und sichert die bis dahin aktive Version. Der Befehl stellt nur den Einstieg wieder her. Für einen vollständigen Rückwechsel die vorherige Release-Ausgabe erneut veröffentlichen.

```sh
npm run deploy -- dodi --rollback=/absolute/private/index-backup.bak
```

## Sicherer Zielplan ohne Verbindung

Vor dem Upload kann der aufgelöste Plan gelesen werden:

```sh
node scripts/deploy/deploy.js dodi1 --dry-run
node scripts/deploy/deploy.js dodi_2 --full --dry-run
node scripts/deploy/deploy.js dodi --release --dry-run
```

Der Plan prüft Zielwerte und vorhandene lokale Dateien ohne Verbindung. Zugangsdaten, Remote-Pfade und PHP-Syntax werden beim eigentlichen Upload geprüft.

Für jedes benannte Profil müssen Host, Benutzer, Ziel, Quelle und Authentifizierung explizit mit seinem Suffix konfiguriert sein. Beispiel ohne echte Zugangsdaten:

```dotenv
DEPLOY_HOST_customer=server.example
DEPLOY_USERNAME_customer=account
DEPLOY_REMOTE_PATH_customer=/absolute/public/path
DEPLOY_SOURCE_PATH_customer=pages/customer
DEPLOY_PRIVATE_KEY_customer=/absolute/private/key
```

`dodi1` verwendet Suffix `1`, `dodi_2` Suffix `2`; `dodi` und der Standardaufruf verwenden bewusst das unsuffigierte Grundprofil. Der neue Profilcheck ändert keine `.env`-Werte.

Die interaktive Übersicht wird jetzt durch [das Instanzbuch](instanzbuch.md) beschrieben. Der aktuelle SSH-Scan bestätigt vier Webordner mit `index.php`: dominikeggermann.com auf eggman1 sowie dodi, dodi2 und pageoff auf eggman4. Der dokumentierte customer-Zielordner fehlt. Weitere PHP-Einstiege sind als unbestätigte Kandidaten in der JSON-Datei aufgeführt.
