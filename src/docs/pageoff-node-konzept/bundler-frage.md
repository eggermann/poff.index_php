# prompt-concator und die Ein-Datei-Auslieferung

## Einschätzung zum npm-Paket

Geprüft wurde `prompt-concator@1.4.2`. Es ist ein Werkzeug, das ausgewählte Dateien per Glob zu einer Textdatei zusammenfügt. Standardziel ist `input-prompt.txt`. Es unterstützt Ausschlussregeln, `.gitignore`, Watch-Modus und TextEdit auf macOS. Damit eignet es sich, einen gezielten Quellcode-Kontext für eine LLM-Frage zu erstellen.

Es ist kein PHP-Bundler. Das Paket löst keine PHP-Imports auf, analysiert keine Namespaces oder Klassen, behandelt dynamische `include`-Aufrufe nicht semantisch und erstellt kein ausführbares, geprüftes Einzeldatei-Artefakt. Die bestehende PHP-Ausgabe entsteht im Projekt bereits mit einem eigenen, geordneten Builder.

Der Watch-Modus synchronisiert Änderungen aus der zusammengesetzten Textdatei zurück in Quelldateien. Wenn die Git-Option aktiv ist, führt der Paketcode `git add .` und anschließend `git commit` aus. Deshalb eignet sich das Werkzeug für eine gezielte, schreibgeschützte Dateiauswahl ohne Watch- oder Git-Modus, wenn es nur als LLM-Kontextsammler dienen soll. Ein pauschales Einschließen des ganzen Projekts kann außerdem vertrauliche oder generierte Dateien in den Prompt übernehmen.

Hinweis bei Verwendung: npm-Metadaten deklarieren Lizenz `ISC`, die mitgelieferte README nennt dagegen „Buy Me a Beer License“. Diesen Widerspruch vor einer Weiterverteilung des Pakets klären. Die Metadaten geben außerdem `node --test` als Tests an, obwohl der npm-Tarball keine Testdateien enthält.

Quelle: [npm prompt-concator](https://www.npmjs.com/package/prompt-concator) · [npm Registry-Metadaten, Version 1.4.2](https://registry.npmjs.org/prompt-concator)

## Bereits vorhandener Build

`build/build.php` erzeugt aus der modularen PHP-Quelle die einzelne PHP-Einstiegsdatei. Es fügt Komponenten in expliziter Reihenfolge zusammen, entfernt PHP-Tags und ausgewählte `require_once`-Zeilen, baut Worktype-Komponenten ein, bettet LightnCandy ein und schreibt die Ausgabe. `BuildConfig.php` und `BuildConfig.shared.json` bestimmen Ausgabe und lokale Verteilungsziele. `ComponentReader.php`, `FileCopier.php` und `PoffConfigBuilder.php` sind Teile der Build-Implementierung.

Asset-Build und Beobachtung laufen zusätzlich über `scripts/build-assets.js` und `scripts/watch-build.js`. Die erzeugten Asset-Dateien bleiben gebaute Ausgaben. `src/includes/header.built.php` und `src/includes/scripts.built.php` sind generiert. Sie sind keine Quellen für eine Implementierungsanalyse.

Die Builder-Logik kennt konkrete Dateireihenfolge, PHP-Tag-Entfernung, Compile-Zeit-Einbettung und Verteilung. Das ist projektspezifische Auslieferung und unterscheidet sich von einer generischen Dateikonkatenation. Das erzeugte Einzeldatei-Artefakt enthält außerdem eingebetteten LightnCandy-PHP-Code. Der ausgelieferte PHP-Code bleibt für den Betreiber lesbar.

## Relevante Projektdateien

### Direkter Build und Konfiguration

- `package.json` — Build-, Asset-Build- und Watch-Befehle.
- `scripts/build-assets.js` — JS- und CSS-Build.
- `scripts/watch-build.js` — Entwicklungs-Workflow, der auf Änderungen reagiert.
- `build/build.php` — zentrale PHP-Zusammenstellung, Komponentenreihenfolge und Output-Kopie.
- `build/BuildConfig.php` — liest gemeinsame Build-Konfiguration.
- `build/BuildConfig.shared.json` — Quellpfad, Ausgabe, Hosts und lokale Kopierziele.
- `build/ComponentReader.php` — liest Quellkomponenten.
- `build/FileCopier.php` — verwaltet lokale Kopien.
- `build/PoffConfigBuilder.php` — setzt die Methoden aus sechs Traits in die auszuliefernde Klasse `PoffConfig` ein.
- `build/SSHUploader.php` — Deployment-Helfer.

### PHP-Bündelung und externe Laufzeitabhängigkeiten

- `src/includes/Worktype.php` — modulare Worktype-Implementierung und normale Quell-Abhängigkeiten.
- `src/includes/Worktype/Render.php` — Rendervorgang über LightnCandy.
- `composer.json` und `composer.lock` — PHP-Abhängigkeiten für Entwicklung und Builder.
- `vendor/zordius/lightncandy/src/` — LightnCandy-Quellen, die der Builder in die Einzeldatei einbettet.

### Struktur und Prüfungen

- `README.md` — dokumentierter Build- und Testablauf, zum Teil kürzer als die aktuelle Build-Konfiguration.
- `tests/build.poffconfig.test.js` — Build-Konfiguration und Kopierziele.
- `tests/mcp.create.test.js` — große MCP- und PHP-Integrationssuite.
- `tests/php_build_poff_config.php` — PHP-Helfer für Config-Build-Prüfungen.
- `tests/php_file_copier_cleanup.php` — PHP-Helfer für Kopierbereinigung.

Für eine kleine, fokussierte Frage reicht zuerst `package.json`, `build/build.php`, `build/ComponentReader.php`, `build/BuildConfig.php`, `scripts/build-assets.js` und `tests/build.poffconfig.test.js`. Die Komponentenliste in `build/build.php` zeigt dann gezielt auf die jeweiligen Dateien unter `src/`.

## Bestandsaufnahme vom 20. November 2026

Für PageOff zählt die Größe des auszuliefernden Programms bei gleichem Funktionsumfang. Inhalte, Ordnerkonfigurationen und vom Betreiber angepasste Layouts gehören zum Webspace. Das optionale Host-Modul ist konzeptionell vom Core getrennt; zunächst bleibt die Auslieferung bei einer `index.php`. Die heutige Messung betrifft den vorhandenen CMS-Einstieg.

### Was in die Datei gelangt

| Eingang | Verarbeitung und Reihenfolge |
| --- | --- |
| LightnCandy | Der Builder liest 13 PHP-Quellen in festgelegter Reihenfolge. Er speichert sie als Strings. Beim Start wertet PHP sie im Namespace `LightnCandy` aus, falls die Klasse noch fehlt. Das Entfernen von Kommentaren im äußeren PHP lässt den Inhalt dieser Strings bestehen. |
| Funktionen und Klassen | Aus `functions.php` übernimmt der Builder die Funktion `extractLinkFileUrl`. Die fünf Worktype-Traits müssen beim Deklarieren der Klasse verfügbar sein. Anschließend kann der Einstieg Definitionen, Templates und Layout-Assets über die Setter registrieren. |
| Worktype-Definitionen und Templates | `worktypes.php` lädt beim Bauen per `glob` die Typdefinitionen und HBS-Dateien. Der Builder bettet die zurückgegebenen Daten ein. Eine Analyse von wörtlichen `require_once`-Pfaden allein erfasst diese Datenabhängigkeit nicht. |
| PoffConfig | Im Quellprojekt lädt ein Bootstrap die Traits. Für die Auslieferung extrahiert `PoffConfigBuilder` sechs Trait-Rümpfe und setzt sie in die Klasse ein. Die Bootstrap-Datei kann damit als eigenständige Laufzeitdatei entfallen. Ihre Methoden werden weiterhin gebraucht. |
| Viewer, Editor und MCP | Drei explizite Listen führen 10 Viewer-, 40 Editor- und 15 MCP-Dateien auf. Nach den Editor-Helfern fügt der Builder den Aufruf `cmsHandleEditAction()` ein. Das anschließende Routing steht als eigener PHP-Text im Builder. Beim Umordnen müssen Deklarationen und ausführbare Schritte unterscheidbar bleiben. |
| HTML, JS und CSS | Der Asset-Build erzeugt JS und CSS und fügt sie in die beiden generierten Includes ein. Der PHP-Builder setzt die Navigation an `<!-- NAV_PLACEHOLDER -->` ein und ergänzt Laufzeitwerte im Skript. Die Viewer-Antwort hat einen eigenen CSS-Einstieg. |

Die vollständigen geordneten Dateilisten stehen bei der Zusammenstellung. Build-Werkzeuge wie `ComponentReader` und `FileCopier` laufen beim Erzeugen und Verteilen. Ausgeführte PHP-Quellen, eingebettete Werte und zusätzlich kopierte Dateien müssen in einem Größenbericht getrennt gezählt werden. [PHP-Builder](../../../build/build.php) · [Worktype-Eingaben](../../includes/worktypes/worktypes.php) · [PoffConfig-Transformation](../../../build/PoffConfigBuilder.php)

### Korrigierte MCP-Pfade

1. **Speichern über MCP.** Der Builder bindet die Parser- und Anwendungshilfe vor der Route `edit-config` ein. Damit sind die beiden Funktionen für den POST-Zweig auch nach dem Entfernen der lokalen `require_once`-Zeilen vorgesehen. [Route](../../mcp/routes/edit-config.php) · [Build-Rezept](../../../build/build.php)
2. **Layout von einem anderen Node übernehmen.** Die MCP-Liste enthält den Handler aus `remote-presentation.php`. Der Router im Build-Rezept übergibt ihm bei `remote-work-presentation` den lokalen Wurzelordner und die eingelesenen Anfragedaten. [Quellrouter](../../mcp/server.php) · [Build-Rezept](../../../build/build.php)

Ein Regressionstest prüft diese Verdrahtung im Build-Rezept. Die unten beschriebene Prüfung am fertigen Release-Kandidaten bleibt ein eigener Schritt. [Builder-Tests](../../../tests/build.poffconfig.test.js)

### Größe und mögliche Einsparung

| Gegenstand | Bytes | Herkunft |
| --- | ---: | --- |
| Vorhandene lokale `pages/dominikeggermann.com/index.php` | 1.596.582 | Dateigröße. Ein aktuelles Vergleichsartefakt unter `dist/index.php` lag bei der Aufnahme nicht vor. |
| `build/assets/app.js` | 494.781 | Vorhandener Asset-Build |
| `build/assets/app.css` | 113.206 | Vorhandener Asset-Build |
| 13 LightnCandy-Dateien | 191.993 | Summe der Eingabedateien vor dem Einbetten |
| 10 Viewer- / 40 Editor- / 15 MCP-Dateien | 99.074 / 153.405 / 157.669 | Summen der drei expliziten PHP-Eingabelisten vor der Verarbeitung |
| 14 HBS-Templates | 11.204 | 12 Templates direkt unter `templates/` und zwei Layout-Templates |

Die beiden Assets ergeben zusammen 607.987 Bytes. Daraus lässt sich kein exakter Anteil an der fertigen PHP-Datei berechnen. Der Builder legt dasselbe App-CSS einmal im Header und nochmals als PHP-String für die eigenständige Viewer-Antwort ab. Beide Ausgabepfade brauchen das Stylesheet. **Ein gemeinsamer eingebetteter CSS-Wert** ist daher ein konkreter Kandidat. Seine tatsächliche Einsparung und die Darstellung beider Ansichten wären am selben Build zu prüfen. Für das Entfernen eines ganzen PHP-Fachmoduls liegt bislang kein entsprechender Nachweis vor. [Asset-Build](../../../scripts/build-assets.js) · [CSS im Viewer](../../includes/viewer/render/shell.php)

Development und Production sind bereits vorhanden. Production aktiviert esbuild-Minifizierung und komprimierte Sass-Ausgabe. Der UnoCSS-Anteil wird separat erzeugt und angefügt. PHP nutzt `php_strip_whitespace` auf einer temporären Datei. Das Rezept für Production wählt `dist/` und überspringt die lokalen Verteilungskopien. [npm-Skripte](../../../package.json) · [Build-Konfiguration](../../../build/BuildConfig.php)

### Vorgeschlagene Abnahme für den nächsten Builder-Schritt

1. **Größenbericht beim Zusammenstellen.** Pro Block Herkunft, Kategorie und tatsächlich angehängte Bytes ausgeben. Die zweite CSS-Einbettung separat zählen. Vor und nach dem abschließenden PHP-Strip die Gesamtgröße messen. Development und Production nur aus demselben Quellstand mit den jeweils zugehörigen Assets vergleichen.
2. **Abhängigkeiten prüfen.** Statisch auflösbare Includes samt Einbindungsart erfassen. Für dynamisch geladene Worktypes, eingebettete Templates und ausgeführte Initialisierung die bestehende Reihenfolge und Verarbeitung ausdrücklich beschreiben. Ein unbekannter Pfad bleibt ein offener Befund. Eine Streichung braucht den Nachweis, dass alle unterstützten Wege weiterhin funktionieren.
3. **Kandidaten prüfen.** Den fertigen Inhalt in eine temporäre Datei im Zielverzeichnis schreiben, vollständig geschriebenen Umfang prüfen und `php -l` darauf anwenden. Die bestehende MCP-Suite prüft viele Quellpfade. Für eine künftige Release-Prüfung zusätzlich genau den Kandidaten mit isolierten Testdaten prüfen: Ein Konfigurations-POST speichert und liefert den neuen Wert. Der Aufruf zur Layoutübernahme liefert die erwartete Routenantwort. Hauptseite und Einzelviewer erhalten beide ihre Styles.
4. **Geprüfte Datei übernehmen.** Erst nach erfolgreichen Prüfungen den Kandidaten im selben Dateisystem per `rename` übernehmen. Bei einem Fehler vor der Übernahme bleibt die bisherige Zieldatei verwendbar. Heute schreibt `file_put_contents` direkt ans Ziel. Die aktuelle temporäre Datei dient dem PHP-Strip. Für Kopien an weitere Nodes gilt die atomare Übernahme je Ziel einzeln. Der Watcher führt nach einem erfolgreichen Build eigene Kopierschritte aus und muss denselben geprüften Stand verwenden. [Schreibpfad](../../../build/build.php) · [Watcher](../../../scripts/watch-build.js) · [bestehender Builder-Test](../../../tests/build.poffconfig.test.js)

Die nächsten Messpunkte sind die beiden MCP-Pfade am fertigen Release-Kandidaten und der tatsächliche Größenbeitrag der zweiten CSS-Einbettung.
