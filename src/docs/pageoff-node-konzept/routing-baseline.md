# Routing und Build: Baseline

Stand: 20. November 2026. Bestandsaufnahme aus dem Quellcode, kein Nachweis einer vollständigen Laufzeitgleichheit. Die Tabellen nennen die wichtigsten Eingaben und Ergebnisse; Details und Sonderfälle stehen in den Handlern.

## Einstiegspunkte

| Einstieg | Verhalten |
| --- | --- |
| `src/includes/viewer.php` | JSON-Routen der normalen PageOff-Laufzeit unter `?mcp=1`; Wurzel aus `mcpRuntimeContext()`. Enthält 18 Fälle plus `info`-Standard. |
| `src/mcp/server.php` | Separater JSON-Einstieg mit eigenem Dispatch; Wurzel aus `getcwd()`. Baut vor jedem Dispatch einen Dateibaum und erstellt oder aktualisiert `poff.config.toon`. |
| `build/build.php` | Erzeugt Dispatch in der Einzeldatei aus der Routerlogik des Viewers. Wurzel stammt aus `POFF_BASE_DIR`, `base` oder Verzeichnis des Einstiegspunkts. Fügt Fatal-Handler und `Throwable`-Behandlung hinzu. |
| `src/mcp/http-server.php` | Streamable MCP über `127.0.0.1:8080/mcp`; registriert eine Teilmenge als Tools mit eigenen Namen und Schemas. |

Der primäre Router in `viewer.php` und der Build-Dispatch haben denselben Routenumfang, unterscheiden sich aber im `create`-Default (`$runtime['poffDir']` gegenüber `mcpPoffDirOverride()`). Beide liefern `rootDir` bei `info`; der Build hat zusätzliche Pfadvalidierung und Fehlerbehandlung. Der separate `src/mcp/server.php` liefert eine andere `info`-Antwort (`configPath`, Erstellungs-/Updateflags, Config), synchronisiert die TOON-Datei vor allen Routen und hat einen anderen Routenumfang. Eine Zusammenlegung muss diese Verträge ausdrücklich berücksichtigen.

## JSON-Routen

Alle 18 genannten Handler-Routen sind in `viewer.php` und dem Build-Rezept enthalten. Der eigenständige `src/mcp/server.php` enthält 18 Routen mit eigener Initialisierung und separater `style`-Konfiguration. `rootDir` wird intern übergeben. „Editor“ bedeutet Prüfung über `mcpEditorAccessState`, gegebenenfalls ergänzt durch weitere Pfad- und Modusprüfungen. Ohne eigene Statusangabe sendet `mcpJsonResponse` HTTP 200, auch bei manchen Fehlerobjekten; einzelne Handler verwenden explizite Fehlerstatus.

| Route → Handler | Haupteingabe | Zugriff, Ergebnis und mögliche Seiteneffekte |
| --- | --- | --- |
| `workprompt` → `handleWorkPrompt` | `file`, `style` | Editor; Prompt-/Layout-Ergebnis; kann Dateien schreiben. |
| `create` → `handleCreate` | `dest`, optional `path`, `url` | Editor; `created`, `copied`, `downloaded`, Fehler; erstellt, kopiert oder lädt Inhalt. |
| `edit-config` → `handleEditConfig` | `path`, POST-Daten | Editor und Edit-Modus; Konfiguration oder Fehler; POST kann Konfiguration schreiben. |
| `prompt-template` → `handlePromptTemplate` | `path`, Requestdaten | Editor; generiertes Template oder Fehler; Provider-Anfrage möglich. |
| `export-content` → `handleExportContent` | `path` | Öffentlicher Pfad wird geprüft; JSON-Export mit `source` und Inhalten; liest Konfiguration und Viewer-Daten. |
| `import-remote` → `handleImportRemote` | `path`, `url`, `sourceId`, `replace`, `mode`, `destinationName`, `createPlayer` | Editor; Ergebnis oder Fehler; Netzwerkzugriff, Konfiguration und optional lokale Dateikopie. |
| `remote-work-presentation` → `handleRemoteWorkPresentation` | Requestdaten | Editor; Ergebnis oder Fehler; Netzwerkzugriff und mögliche Layout-Änderung. |
| `converters` → `handleConverters` | Filter wie `path`, `mimeType`, `kind` | Editor; Converter-Liste; liest lokale Angebote. |
| `convert` → `handleConvert` | Payload mit `source`, `converter` | Editor; Konvertierungsergebnis; führt Converter aus. |
| `converter-offers` → `handleConverterOffers` | Requestdaten | Öffentliches Angebot; `ok`, `route`, Anbieter und Converter; liest lokale Angebote. |
| `remote-converters` → `handleRemoteConverters` | Requestdaten, insbesondere Node-URL und Pfad | Editor; Angebote oder Fehler; fragt entfernten Node ab. |
| `register-node` → `handleRegisterPoffNode` | Requestdaten, Pfad und Ziel | Editor; Registrierungsantwort; liest Work-Konfiguration und sendet Netzwerk-Anfrage. |
| `convert-offered` → `handleConvertOffered` | Payload | Öffentlich angebotene Konvertierung; Ergebnis oder Fehler; führt Converter aus. Grenzen im Handler prüfen. |
| `create-converter` → `handleCreateConverter` | `path`, Payload | Editor; Ergebnis oder Fehler; erstellt Converter-Dateien. |
| `save-converted-work` → `handleSaveConvertedWork` | `sourcePath`, `conversion` | Editor; Ergebnis oder Fehler; speichert generiertes Work. |
| `bulk-convert` → `handleBulkConvert` | Ordner und Converter-Payload | Editor; Liste von Ergebnissen und Fehlern; konvertiert und aktualisiert Sichtbarkeit. |
| `converter-prompt` → `handleConverterPrompt` | `path` | Editor; Prompt und Grain-Metadaten; liest Dateien. |
| `style` → `handleStyleRoute` | `prompt` | Ergebnis mit Style-Informationen; liest den konfigurierten Kontext. |
| unbekannt / `info` | keine | Unterschiedliche Standardantworten der beiden Router; Quellrouter initialisiert die TOON-Datei. |

`http-server.php` bietet `workprompt`, `create`, `export_content`, `import_remote`, `converters`, `convert`, `save_converted_work`, `create_converter` und `converter_prompt`. Das ist keine vollständige Abbildung aller JSON-Routen. Dazu kommt der eigenständige `src/mcp/server.php`-Einstieg; seine Adapterrolle muss von der normalen `viewer.php`-Laufzeit getrennt bewertet werden.

## Build-Abhängigkeiten und nächste Prüfung

Der Builder fügt Viewer- und Editor-Quellen in Reihenfolge ein, führt `cmsHandleEditAction()` aus und hängt danach MCP-Helfer und Routen an. Er entfernt dafür ausgewählte `require_once`-Zeilen. `PoffConfigBuilder` transformiert Traits; Worktype-Definitionen, Templates und Assets werden zusätzlich eingebettet. Daher reicht das Verschieben einer Datei allein nicht: Source-Imports und Build-Reihenfolge müssen gemeinsam angepasst werden.

Die Reihenfolge beginnt mit eingebetteten LightnCandy-Quellen, gefolgt von Funktionen, `MediaType`, `Converter`, Worktype-Traits und `Worktype`. Worktype-Definitionen und Templates werden beim Build geladen und als Daten eingesetzt. Danach folgen Konfigurations- und Auth-Helfer, Viewer- und Editor-Dateien sowie die MCP-Liste. Der Builder setzt anschließend Routing und HTML zusammen; `<!-- NAV_PLACEHOLDER -->` wird mit der Navigation ersetzt. Diese Skizze ersetzt nicht die detaillierte Reihenfolge in `build/build.php`.

`BuildConfig.php` verankert `POFF_OUTPUT_DIR` am Repository und entfernt führende Schrägstriche. Ein vermeintlich absoluter temporärer Pfad wird daher nicht direkt als absoluter Pfad verwendet. `POFF_SKIP_DISTRIBUTION_COPIES=1` überspringt Kopien und deren Bereinigung, aber die Ausgabe wird weiterhin direkt mit `file_put_contents` geschrieben. Ein isolierter Kandidat benötigt deshalb einen vorher geprüften, eigenen relativen Zielpfad; `npm run build` erzeugt zusätzlich Assets und darf die vorhandenen Änderungen nicht überschreiben. Keine Kandidatenerzeugung wurde hier ausgeführt.

`tests/mcp.routing-contract.test.js` vergleicht statisch Routennamen, Handler und die Argumentverdrahtung zwischen `viewer.php` und Build-Rezept. Die abweichende `create`-Verdrahtung wird gesondert erfasst. Der Test belegt kein HTTP-Verhalten und keine Seiteneffekte und behauptet keine Gleichheit mit dem Standalone-Router. Als nächstes isolierte Kompatibilitätsfälle für Standardantwort, Export, Import und verweigerten Editorzugriff vorbereiten; Build-Ausgabe und Runtime-Einstiege nur innerhalb der erlaubten Grenzen prüfen.

Erster Core-Schritt: `mcpRemoteUniqueImportedName()` delegiert jetzt an `PoffConfig::uniqueImportedName()`. Der Route-Adaptername bleibt erhalten. Trait-Flattening wird durch `tests/build.poffconfig.test.js` plus `tests/php_build_poff_config.php` geprüft; direkte Collision-Fälle deckt `tests/php_poffconfig_unique_imported_name.php` ab.
