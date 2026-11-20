# Work-Identität: Ist-Stand

Read-only Audit, 20. November 2026. Das ist ein Codebefund, kein beschlossenes Identitätsformat.

## Vorhandenes Verhalten

- `PoffConfig::generateId()` in `src/includes/PoffConfig/core-helpers.php` erzeugt einen neuen zufälligen `poff_…` Wert.
- Neue Folder- und File-Configs bekommen diese ID. `PoffConfig::ensure()` und `ensureFileConfig()` behalten die bestehende ID, solange die betreffende Metadatenkonfiguration weiterverwendet wird.
- Folder-Metadaten liegen in `poff.config.json`; Datei-Metadaten werden dateinamenbezogen als `.works/<filename>.config.json` gehalten.
- Die Dateibaum-Einträge führen aktuell Namen und Pfade; die untersuchten Tree-Projektionen sind keine unabhängige Work-ID-Auflösung.
- Kein verwalteter Rename- oder Move-Endpunkt wurde im untersuchten Code gefunden. Die Edit-Aktionen schreiben Datei- und Config-Änderungen, aber kein identifiziertes Move-Protokoll.
- Der MCP-Copy-Pfad für Verzeichnisse rekursiv kopiert deren Konfigurationen mit den Dateien. Darin enthaltene IDs können so dupliziert werden.
- Der Copy-Pfad für einzelne Dateien kopiert die Quelldatei; kein fokussierter Test belegt, wie eine vorhandene `.works/`-ID behandelt wird.
- Bisherige fokussierte Tests decken keine ID-Stabilität für Rename/Move und keine neue ID bei Copy ab.

## Konsequenz für das Zielmodell

Die zufällige Metadaten-ID ist ein nützlicher Ausgangspunkt, aber aktuell keine sichere, eindeutige dauerhafte Identität über Kopien hinweg. Vor einer öffentlichen Auflösung braucht es eine Regel für ID-Konflikte und eine explizite Kopiersemantik. Ein verwaltetes Verschieben muss Metadaten samt Identität erhalten. Eine lokale Kopie auf demselben Node braucht ebenfalls eine definierte Regel; die Zielanforderung bestimmt eine neue ID für Kopien auf einem anderen Node, legt das Verhalten innerhalb desselben Nodes aber nicht fest.

## Nächste Entscheidungen

1. Gilt Copy immer als neues Work, auch innerhalb desselben Nodes? Empfohlener Default: ja; Ursprung optional als `copiedFrom` dokumentieren.
2. Bleibt die Work-ID im Datei-Metadatensatz, wenn eine Datei umbenannt wird, obwohl der Metadaten-Dateiname aktuell an den Work-Namen gekoppelt ist? Rename-Operation muss Quellmetadaten atomar mitbewegen.
3. Wie werden bestehende doppelte IDs migriert, ohne Work-Links stillschweigend auf das falsche Ziel zu zeigen?
4. Ist `Node-Adresse + Work-ID` vorerst die öffentliche Referenz, und wie reagiert der Resolver auf temporäre Adresse oder Domainwechsel?
5. Ein ID-zu-Pfad-Index darf nur abgeleitet und neu aufbaubar sein; die Metadaten bleiben maßgeblicher Bestand.

Quelle: `src/includes/PoffConfig/core-helpers.php`, `src/includes/PoffConfig/layout-helpers.php`, `src/mcp/routes/create.php`, `src/includes/nav-render.php`, `tests/mcp.create.test.js`.
