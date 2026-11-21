# Work-Identität: Ist-Stand

Codebefund 20. November 2026; Folgeänderungen 21. November 2026. Die beobachtete Kopierregel war zunächst branch-lokal; die Zielrichtung für Node- und Work-Identität ist seit 21. November 2026 bestätigt.

## Bestätigte Zielrichtung — 21. November 2026

Die dauerhafte Referenz soll aus stabiler Node-ID und stabiler Work-ID bestehen. Domain und URL sind erreichbare Adressen eines Nodes und dürfen wechseln, ohne die Identität zu ändern. Verschieben erhält die Work-ID; Kopieren erzeugt eine neue Work-ID. Remote-Referenzen speichern künftig die Quell-Node-ID und Quell-Work-ID. Ein Pfadindex bleibt abgeleitet und neu aufbaubar.

Die Umsetzung speichert die Node-ID in `poff.node.json` am Instanz-Root und veröffentlicht sie zusätzlich zum bestehenden `source.id` im Export. Exportierte Folder und physische Files erhalten `workId`; Remote-Works werden als `reference: { nodeId, workId }` weitergereicht. Beim Import werden IDs als `remoteNodeId`/`remoteWorkId` abgelegt, nicht als lokale Work-ID. Dadurch bleibt das Exportformat für bestehende Leser kompatibel. Identitätserzeugung beim Export braucht Schreibrecht im Node-Root; vollständige Serverkopien übernehmen die Node-ID zunächst mit und benötigen vor dem Betrieb eine explizite Identitätsrotation.

## Vorhandenes Verhalten

- `PoffConfig::generateId()` in `src/includes/PoffConfig/core-helpers.php` erzeugt einen neuen zufälligen `poff_…` Wert.
- Neue Folder- und File-Configs bekommen diese ID. `PoffConfig::ensure()` und `ensureFileConfig()` behalten die bestehende ID, solange die betreffende Metadatenkonfiguration weiterverwendet wird.
- Folder-Metadaten liegen in `poff.config.json`; Datei-Metadaten werden dateinamenbezogen als `.works/<filename>.config.json` gehalten.
- Die Dateibaum-Einträge führen aktuell Namen und Pfade; die untersuchten Tree-Projektionen sind keine unabhängige Work-ID-Auflösung.
- Kein verwalteter Rename- oder Move-Endpunkt wurde im untersuchten Code gefunden. Die Edit-Aktionen schreiben Datei- und Config-Änderungen, aber kein identifiziertes Move-Protokoll.
- Der MCP-Copy-Pfad für Verzeichnisse kopiert deren Konfigurationen mit den Dateien. Seit 21. November 2026 erhalten kopierte Folder- und Datei-Metadaten anschließend neue IDs; die Quell-IDs bleiben erhalten. Der Codebefund oben beschreibt den vorherigen Stand.
- Der Remote-Feed-Import legt virtuelle Einträge im `tree` ohne lokale Work-ID an. Er bewahrt die Quell-Identität als `remoteNodeId` + `remoteWorkId` und notiert den direkten Feed-Node getrennt als `remoteFeedNodeId`. Ältere Feeds ohne `nodeId`/`workId` bleiben gültig und erzeugen diese Felder nicht. Der separate Remote-Folder-Copy erzeugt eine neue lokale Folder-ID.
- Der Copy-Pfad für einzelne Dateien kopiert die Quelldatei; kein fokussierter Test belegt, wie eine vorhandene `.works/`-ID behandelt wird.
- Tests decken jetzt stabile IDs bei wiederholtem `ensure()`/`ensureFileConfig()` und neue IDs bei rekursiver Folder-Kopie ab. Rename/Move bleibt mangels verwalteter Operation ungeprüft.

## Konsequenz für das Zielmodell

Die zufällige Metadaten-ID ist ein nützlicher Ausgangspunkt, aber aktuell keine eindeutige dauerhafte Identität innerhalb eines Nodes, wie der lokale Snapshot zeigt. Vor einer öffentlichen Auflösung braucht es weiterhin eine Regel für bestehende ID-Konflikte. Verwaltetes Verschieben muss Metadaten samt Identität erhalten; jede Kopie wird als neues Work behandelt und erhält eine neue ID.

## Resolver- und Kollisionsaudit (Workspace-Snapshot 21. November 2026)

Statische Suche in `src/includes` und `src/mcp` fand keinen Work-ID-zu-Pfad-Resolver oder ID-Index. Vorhandene Viewer-Auflösung arbeitet über Pfade und Slugs. Eine read-only Zählung von 249 `poff.config.json`- und `*.config.json`-Metadatendateien unter dem lokalen `pages/`-Baum fand eine doppelt vergebene ID in zwei Konfigurationen: `.layout/poff.config.json` und `.layout copy/poff.config.json` unter `dominikeggermann.comX`. Das deutet auf eine kopierte lokale Layout-Konfiguration, beweist aber weder Herkunft noch Auswirkungen für andere Nodes. Keine Migration wurde ausgeführt; der Snapshot muss vor einer Migrationsplanung geprüft und gegen weitere Node-Bestände neu erhoben werden.

## Nächste Entscheidungen

1. Wie bleibt die Work-ID beim Umbenennen erhalten, obwohl der Datei-Metadatensatz aktuell an den Dateinamen gekoppelt ist? Eine Rename-Operation muss Quelldaten atomar mitbewegen.
2. Wie werden bestehende doppelte IDs migriert, ohne Work-Links stillschweigend auf das falsche Ziel zu zeigen?
3. Wie wird eine vollständige Serverkopie vor dem Betrieb mit einer neuen Node-ID versehen?
4. Ein ID-zu-Pfad-Index darf nur abgeleitet und neu aufbaubar sein; die Metadaten bleiben maßgeblicher Bestand.

Quelle: `src/includes/PoffConfig/core-helpers.php`, `src/includes/PoffConfig/layout-helpers.php`, `src/mcp/routes/create.php`, `src/includes/nav-render.php`, `tests/mcp.create.test.js`.

## Folgeänderung: Identität bei Folder-Kopie

Branch-lokale Regel: Eine Folder-Kopie erzeugt für jeden mitkopierten `poff.config.json`- und `.works/*.config.json`-Datensatz eine neue `poff_…` Work-ID. Quelldateien und Quell-IDs bleiben unverändert. Kopierte Einzeldateien enthalten keine Metadaten-Sidecars; bei einem neuen Ziel wird eine ID später wie bisher erzeugt. Wird eine bestehende Zieldatei ersetzt, bleiben ihre vorhandenen Metadaten und ID erhalten. Ein Move-Endpunkt existiert nicht und ist von dieser Regel nicht abgedeckt.

`PoffConfig::newWorkId()` zentralisiert die ID-Vergabe im Core. `PoffConfig::reassignWorkIdForCopy()` nutzt dieselbe Vergabe für kopierte Metadaten. Der MCP-Folder-Copy und der Remote-Folder-Copy verwenden den gemeinsamen Core-Vertrag; der virtuelle Remote-Feed-Import erstellt keine lokalen Work-IDs. Bestehende Route-Parameter bleiben gleich. Provenienz (`copiedFrom`), Konfliktmigration bereits duplizierter IDs und öffentliche Node-ID-Auflösung bleiben offen.

Regressionstest kopiert isolierte Root- und Nested-Folder- sowie Datei-Metadaten, bestätigt vier neue IDs und prüft, dass Quell-Metadaten unverändert bleiben. Ein weiterer Test bestätigt, dass wiederholte `PoffConfig::ensure()`- und `ensureFileConfig()`-Aufrufe Folder- und File-IDs erhalten. Der temporäre Single-File-Build bestätigt, dass die Core-Methode in der gebündelten `PoffConfig`-Klasse enthalten ist.
