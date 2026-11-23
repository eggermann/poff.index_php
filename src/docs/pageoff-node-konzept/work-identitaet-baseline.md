# Work-Identität: Ist-Stand

Codebefund 20. November 2026; Folgeänderungen 21. November 2026. Die beobachtete Kopierregel war zunächst branch-lokal; die Zielrichtung für Node- und Work-Identität ist seit 21. November 2026 bestätigt.

## Bestätigte Zielrichtung — 21. November 2026

Die dauerhafte Referenz besteht aus stabiler Node-ID und stabiler Work-ID. Domain und URL sind erreichbare Adressen eines Nodes und dürfen wechseln, ohne die Identität zu ändern. Verschieben erhält die Work-ID; Kopieren erzeugt eine neue Work-ID. Remote-Referenzen speichern die Quell-Node-ID und Quell-Work-ID. Ein Pfadindex bleibt abgeleitet und neu aufbaubar.

Die Umsetzung speichert die Node-ID in `poff.node.json` am Instanz-Root und veröffentlicht sie zusätzlich zum bestehenden `source.id` im Export. Exportierte Folder und physische Files erhalten `workId`; Remote-Works werden als `reference: { nodeId, workId }` weitergereicht. Beim Import werden IDs als `remoteNodeId`/`remoteWorkId` abgelegt, nicht als lokale Work-ID. Dadurch bleibt das Exportformat für bestehende Leser kompatibel. Identitätserzeugung beim Export braucht Schreibrecht im Node-Root. Eine vollständige Serverkopie übernimmt zunächst die Node-ID; das neue CLI-Werkzeug `php scripts/runtime/rotate-node-identity.php <node-root>` rotiert sie explizit, ohne Work-Metadaten anzufassen. Es wurde nicht auf einer realen Node ausgeführt.

Die Formatprüfung von Node-IDs ist in `PoffNodeIdentity::normalizeId()` zentralisiert. Import-Core, Exportadapter und lokale Identitätsdatei nutzen denselben Validator; unpassende Werte werden verworfen beziehungsweise bei einer ungültigen lokalen Identitätsdatei als Fehler behandelt. Work-IDs bleiben opaque Zeichenketten und werden durch diesen Node-ID-Validator nicht eingeschränkt.

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

## Rename und Move: vorhandene Dateisystem-Grenze

Der Edit-Dispatcher bietet jetzt `move` als authentifizierte POST-Aktion an. Der Request nimmt den bestehenden Quellpfad sowie `destination` (relativer Folder-Pfad, leer für Root) und optional `name` entgegen. Die Aktion bewegt nur physische Works, folgt keinen Symlinks, lehnt vorhandene Ziele ab und unterstützt keine virtuelle Layout-Targets.

- **Folder:** Die ID liegt in `poff.config.json` im Folder. Wird der ganze Folder innerhalb desselben Dateisystems verschoben oder umbenannt, wandert diese Datei samt ID mit. Verschieben aktualisiert aber nicht automatisch die Tree-Konfiguration des alten und neuen Parents; `PoffConfig::ensure()` projiziert den physischen Bestand und erhält nur passende bestehende Einträge bzw. konfigurierte virtuelle Links.
- **File:** Die ID und File-Overrides liegen in `.works/<Dateiname>.config.json`; ein eigener Layout-Override kann in `.works/<Dateiname>.layout/` liegen. Nur die Ressource umzubenennen oder zu verschieben lässt diese Sidecars am alten Namen zurück. Beim neuen Namen kann später eine neue ID entstehen; der alte Layout-Override wird dem neuen Namen nicht zugeordnet.
- **Move-Vertrag:** dieselbe Node, Rename oder Move ohne Überschreiben eines bestehenden Ziels; Folder-Metadaten bewegen sich mit dem ganzen Folder. Bei Files werden Ressource, Config-Sidecar und vorhandener Layout-Sidecar zusammen bewegt, danach werden beide Parent-Trees aktualisiert. Schlägt ein Teil-Rename fehl, werden bereits bewegte Teile zurückgesetzt. Eine Kopie bleibt eine neue Work-ID.

Damit ist die Identitätsregel für den verwalteten Move-Pfad umgesetzt. Es gibt noch keine Edit-UI für diese Aktion. Für direkte Dateisystemoperationen hängt die Identität weiterhin davon ab, ob alle zugehörigen Sidecars mitbewegt werden.

## Resolver- und Kollisionsaudit (Workspace-Snapshot 21. November 2026)

Eine read-only Zählung von 249 `poff.config.json`- und `*.config.json`-Metadatendateien unter dem lokalen `pages/`-Baum fand eine doppelte ID in `.layout/poff.config.json` und `.layout copy/poff.config.json` unter `dominikeggermann.comX`. Beide liegen in versteckten Layout-Verzeichnissen, die keine sichtbaren Works sind; das war eine Metadaten-Kollision, kein Beleg für doppelte öffentliche Work-IDs. Keine Migration wurde ausgeführt. Der Befund gilt nur für diesen Workspace-Snapshot.

`PoffConfig::findWorkLocationsById()` sucht lesend nach lokalen Folder- und File-IDs. `?ajax=resolve&nodeId=…&workId=…` prüft zuerst die lokale Node-ID. Ein Treffer liefert den relativen Pfad; kein Treffer liefert 404; mehrere sichtbare Treffer liefern 409 `ambiguous` mit allen Kandidaten. Versteckte Works bleiben ohne authentifizierten Edit-Modus unauflösbar. Der Resolver schreibt keine Konfiguration und baut keinen dauerhaften Index.

## Nächste Entscheidungen

1. Move-Regressionen für partielle Dateisystemfehler ergänzen und den Request in die Edit-UI integrieren.
2. Wie werden künftige echte ID-Kollisionen migriert, ohne Work-Links stillschweigend auf das falsche Ziel zu zeigen?
3. Wie wird der Offline-Rotationsschritt in Klon-, Staging- und Restore-Abläufen verpflichtend gemacht? `PoffNodeIdentity::rotate()` und `scripts/runtime/rotate-node-identity.php` sind implementiert und getestet, blockieren aber den Start eines Klons nicht automatisch.
4. Wenn aus einem ganzen Node ein unabhängiger Fork entsteht: genügt die neue Node-ID als neue globale Work-Identität, oder sollen zusätzlich alle kopierten Work-IDs neu vergeben werden? Das Werkzeug ändert derzeit nur die Node-ID.
4. Bei größerem Bestand messen, ob der lesende Scan genügt oder ein abgeleiteter, neu aufbaubarer Index nötig wird.

Quelle: `src/includes/PoffConfig/core-helpers.php`, `src/includes/PoffConfig/layout-helpers.php`, `src/mcp/routes/create.php`, `src/includes/nav-render.php`, `tests/mcp.create.test.js`.

## Folgeänderung: Identität bei Folder-Kopie

Branch-lokale Regel: Eine Folder-Kopie erzeugt für jeden mitkopierten `poff.config.json`- und `.works/*.config.json`-Datensatz eine neue `poff_…` Work-ID. Quelldateien und Quell-IDs bleiben unverändert. Kopierte Einzeldateien enthalten keine Metadaten-Sidecars; bei einem neuen Ziel wird eine ID später wie bisher erzeugt. Wird eine bestehende Zieldatei ersetzt, bleiben ihre vorhandenen Metadaten und ID erhalten. Ein Move-Endpunkt existiert nicht und ist von dieser Regel nicht abgedeckt.

`PoffConfig::newWorkId()` zentralisiert die ID-Vergabe im Core. `PoffConfig::reassignWorkIdForCopy()` nutzt dieselbe Vergabe für kopierte Metadaten. Der MCP-Folder-Copy und der Remote-Folder-Copy verwenden den gemeinsamen Core-Vertrag; der virtuelle Remote-Feed-Import erstellt keine lokalen Work-IDs. Bestehende Route-Parameter bleiben gleich. Provenienz (`copiedFrom`), Reparatur echter ID-Kollisionen und Clone-Rotation bleiben offen.

Regressionstest kopiert isolierte Root- und Nested-Folder- sowie Datei-Metadaten, bestätigt vier neue IDs und prüft, dass Quell-Metadaten unverändert bleiben. Ein weiterer Test bestätigt, dass wiederholte `PoffConfig::ensure()`- und `ensureFileConfig()`-Aufrufe Folder- und File-IDs erhalten. Der temporäre Single-File-Build bestätigt, dass die Core-Methode in der gebündelten `PoffConfig`-Klasse enthalten ist.
