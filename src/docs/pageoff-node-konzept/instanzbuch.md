# Instanzbuch: Was läuft wo?

Die lesende Bestandsaufnahme erzeugt `output/instances/index.html` und `output/instances/instances.json`. Die HTML-Datei ist ohne Backend nutzbar; JSON ist die Datenbasis. Die Übersicht enthält interne Server- und Ordnerpfade und ist für den lokalen Gebrauch gedacht.

## Benutzen

Im Repository ausführen:

```sh
node scripts/runtime/instances.js scan
```

Der Scan liest lokale Dateimetadaten und prüft Webordner per SSH. SHA-256 dient als Dateiversion; ein Git-Commit wird nicht als Build-Version behauptet. Erfasst werden Ordnerexistenz, PHP-Existenz, Datei- und Ordnerzeit, Größe, Fingerprint und Vergleich mit der lokalen Build-Datei. Weitere PHP-Einstiege werden als Kandidaten aufgeführt und sind nicht für automatische Updates freigegeben. Der Scan startet keine PHP-Anwendung und führt keinen Build aus.

Zugang zu eggman4 wird aus dem Grundprofil der lokalen `.env` gelesen. Eggman1 verwendet die bestehende externe Datei `../config-data/eggman1.js`; `POFF_EGGMAN1_CREDENTIALS` kann einen anderen Dateipfad angeben. Nur Host/Benutzer/Authentifizierung werden daraus verwendet. Geheimnisse werden nicht in die Übersicht exportiert. Die Server-Zugänge sind getrennt von den alten Upload-Profilnamen.

Die Übersicht liest die Zielwerte der alten npm-Profile bei jedem Scan über denselben Resolver wie `scripts/deploy/deploy.js`. Am 22. November 2026 hatten `dodi1` und `dodi_2` Zielwerte, aber keine eigenen Zugangsdaten. Für `customer` und `pageoff` fehlten Profilwerte. Der direkte SSH-Scan über die separaten Serverzugänge konnte trotzdem vier vorhandene Webordner bestätigen.

## Eine vorhandene index.php verteilen

Zuerst bewusst eine bestehende Build-Datei auswählen. Ein neuer Git-Commit erzeugt keine neue Build-Datei. Aktuell ist die lokale primäre Datei vom 24. Oktober 2026; die jüngsten Quelländerungen sind damit nicht automatisch ausgerollt.

```sh
# Nur Plan und PHP-Syntaxprüfung, keine Updates:
node scripts/runtime/instances.js deploy --source pages/dominikeggermann.com/index.php --all

# Nur einzelne Instanzen planen:
node scripts/runtime/instances.js deploy --source pages/dominikeggermann.com/index.php --ids web-dominik,web-pageoff
```

Erst durch zusätzliches `--apply` werden ausgewählte Ziel-Dateien ersetzt. Das Skript baut nicht selbst. Es verteilt nur `index.php`, keine Inhalte, Layouts, Node-IDs, Konfigurationen oder Converter-Sidecars. Diese können bei bestimmten Releases zusätzlich erforderlich sein.

`--all` plant alle im Scan bestätigten und freigegebenen Ziele. Fehlende und ungeprüfte Ziele werden gemeldet und übersprungen. Die direkte Auswahl einer solchen Instanz führt zu einem Fehler. In der geprüften Liste ergibt das 14 Ziele, da der lokale Verweis auf die Quelldatei entfällt.

Die Quelldatei wird mit `php -l` geprüft. Seit dem Scan veränderte Ziele werden abgewiesen. Lokale Backups liegen unter `~/.local/state/pageoff/backups/`, Web-Backups unter `/var/www/virtual/<account>/.poff-deploy-backups/`, außerhalb der öffentlichen Ordner. Remote-Kandidaten werden zunächst temporär hochgeladen, mit PHP und SHA-256 geprüft und danach per Rename aktiviert. Der Austausch erfolgt pro Datei; es gibt keine gemeinsame Transaktion über mehrere Server. Bei einem Fehler stoppt der Lauf; vorherige erfolgreiche Ziele bleiben aktualisiert. Backups werden nicht automatisch gelöscht.

Nach einem Update erneut scannen. Der Fingerprint zeigt den Stand der Datei. Für ein konfiguriertes npm-Profil am selben Ziel kann die ausgegebene Sicherung über `--rollback=/absoluter/pfad.bak` zurückgespielt werden. Der vollständige Release-Weg mit Converter-Dateien ist im [Deploy-Inventar](umgebungen-und-tests.md) beschrieben.

## Persona-Schleife: nächster Ausbau

Die HTML-Ansicht enthält vier hypothetische Rollen mit Aufgaben: Besucherin, Künstler, Redakteurin und Node-Betreiber. Das ist ein Konzept zur lokalen Simulation, keine durchgeführte Nutzerstudie.

Am 22. November 2026 wurde die Aufgabe des Node-Betreibers einmal lokal mit Playwright durchgespielt. Ein erster Durchlauf hatte nach der Zielauswahl keinen direkten Weg zum weiter unten stehenden Befehl. Die Tabelle verlinkt jetzt direkt zum Plan. Im erneuten Durchlauf führte die Auswahl von `dominikeggermann.com` zum Befehl mit `--ids web-dominik`. Der Browserlauf hat keine Übertragung ausgelöst.

Der nächste Ausbau sollte Aufgaben mit messbaren Kriterien verbinden, Browserläufe nur lokal ausführen, Beobachtungen/Fehler und Screenshots speichern, eine kleine Änderung prüfen und denselben Ablauf wiederholen. Simulierte Agentenreaktionen sind von echten Nutzerbeobachtungen zu unterscheiden. Die Schleife veröffentlicht keine Änderungen autonom.
