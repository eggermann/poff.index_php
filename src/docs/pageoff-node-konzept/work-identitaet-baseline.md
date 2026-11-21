# Work-Identität: Ist-Stand

Codebefund 20. November 2026; Folgeänderung 21. November 2026. Das ist ein Codebefund und eine branch-lokale Kopierregel, kein beschlossenes öffentliches Identitätsformat.

## Vorhandenes Verhalten

- `PoffConfig::generateId()` in `src/includes/PoffConfig/core-helpers.php` erzeugt einen neuen zufälligen `poff_…` Wert.
- Neue Folder- und File-Configs bekommen diese ID. `PoffConfig::ensure()` und `ensureFileConfig()` behalten die bestehende ID, solange die betreffende Metadatenkonfiguration weiterverwendet wird.
- Folder-Metadaten liegen in `poff.config.json`; Datei-Metadaten werden dateinamenbezogen als `.works/<filename>.config.json` gehalten.
- Die Dateibaum-Einträge führen aktuell Namen und Pfade; die untersuchten Tree-Projektionen sind keine unabhängige Work-ID-Auflösung.
- Kein verwalteter Rename- oder Move-Endpunkt wurde im untersuchten Code gefunden. Die Edit-Aktionen schreiben Datei- und Config-Änderungen, aber kein identifiziertes Move-Protokoll.
- Der MCP-Copy-Pfad für Verzeichnisse kopiert deren Konfigurationen mit den Dateien. Seit 21. November 2026 erhalten kopierte Folder- und Datei-Metadaten anschließend neue IDs; die Quell-IDs bleiben erhalten. Der Codebefund oben beschreibt den vorherigen Stand.
- Der Copy-Pfad für einzelne Dateien kopiert die Quelldatei; kein fokussierter Test belegt, wie eine vorhandene `.works/`-ID behandelt wird.
- Tests decken jetzt stabile IDs bei wiederholtem `ensure()`/`ensureFileConfig()` und neue IDs bei rekursiver Folder-Kopie ab. Rename/Move bleibt mangels verwalteter Operation ungeprüft.

## Konsequenz für das Zielmodell

Die zufällige Metadaten-ID ist ein nützlicher Ausgangspunkt, aber aktuell keine sichere, eindeutige dauerhafte Identität über Kopien hinweg. Vor einer öffentlichen Auflösung braucht es eine Regel für ID-Konflikte und eine explizite Kopiersemantik. Ein verwaltetes Verschieben muss Metadaten samt Identität erhalten. Eine lokale Kopie auf demselben Node braucht ebenfalls eine definierte Regel; die Zielanforderung bestimmt eine neue ID für Kopien auf einem anderen Node, legt das Verhalten innerhalb desselben Nodes aber nicht fest.

## Nächste Entscheidungen

1. Gilt Copy immer als neues Work, auch innerhalb desselben Nodes? Empfohlener Default: ja; Ursprung optional als `copiedFrom` dokumentieren.
2. Bleibt die Work-ID im Datei-Metadatensatz, wenn eine Datei umbenannt wird, obwohl der Metadaten-Dateiname aktuell an den Work-Namen gekoppelt ist? Rename-Operation muss Quellmetadaten atomar mitbewegen.
3. Wie werden bestehende doppelte IDs migriert, ohne Work-Links stillschweigend auf das falsche Ziel zu zeigen?
4. Ist `Node-Adresse + Work-ID` vorerst die öffentliche Referenz, und wie reagiert der Resolver auf temporäre Adresse oder Domainwechsel?
5. Ein ID-zu-Pfad-Index darf nur abgeleitet und neu aufbaubar sein; die Metadaten bleiben maßgeblicher Bestand.

Quelle: `src/includes/PoffConfig/core-helpers.php`, `src/includes/PoffConfig/layout-helpers.php`, `src/mcp/routes/create.php`, `src/includes/nav-render.php`, `tests/mcp.create.test.js`.

## Folgeänderung: Identität bei Folder-Kopie

Branch-lokale Regel: Eine Folder-Kopie erzeugt für jeden mitkopierten `poff.config.json`- und `.works/*.config.json`-Datensatz eine neue `poff_…` Work-ID. Quelldateien und Quell-IDs bleiben unverändert. Kopierte Einzeldateien enthalten keine Metadaten-Sidecars; bei einem neuen Ziel wird eine ID später wie bisher erzeugt. Wird eine bestehende Zieldatei ersetzt, bleiben ihre vorhandenen Metadaten und ID erhalten. Ein Move-Endpunkt existiert nicht und ist von dieser Regel nicht abgedeckt.

`PoffConfig::newWorkId()` zentralisiert die ID-Vergabe im Core. `PoffConfig::reassignWorkIdForCopy()` nutzt dieselbe Vergabe für kopierte Metadaten. Der MCP-Copy-Pfad und der Remote-Import verwenden den gemeinsamen Core-Vertrag; bestehende Route-Parameter bleiben gleich. Provenienz (`copiedFrom`), Konfliktmigration bereits duplizierter IDs und öffentliche Node-ID-Auflösung bleiben offen.

Regressionstest kopiert isolierte Root- und Nested-Folder- sowie Datei-Metadaten, bestätigt vier neue IDs und prüft, dass Quell-Metadaten unverändert bleiben. Ein weiterer Test bestätigt, dass wiederholte `PoffConfig::ensure()`- und `ensureFileConfig()`-Aufrufe Folder- und File-IDs erhalten. Der temporäre Single-File-Build bestätigt, dass die Core-Methode in der gebündelten `PoffConfig`-Klasse enthalten ist.
