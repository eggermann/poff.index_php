# PageOff: klarer Architekturansatz

Arbeitsstand: 20. November 2026. Grundlage ist [die oberste Architektur](architektur.md). Die Bereiche unten sind logische Verantwortlichkeiten und noch keine verbindliche Ordnerstruktur.

| Bereich | Verantwortung | Anknüpfung im Quellstand |
| --- | --- | --- |
| Core | Works, Ordner, Metadaten, Identität und Zugriff | `src/includes/PoffConfig/`, `src/includes/Worktype/`, `src/includes/auth.php` |
| Frontend-Kit | Viewer, Editor, Layouts und Navigation | `src/includes/viewer/`, `src/assets/` |
| Host | Einreichungen, Redaktion und direkte Verbindungen je Host-Ordner | Bestehender Link-Eingang und Node-Anmeldung sind Anknüpfungspunkte; das vollständige Host-Modul fehlt. |
| API und Protokoll | Öffentliche Auflösung, Austausch und Automatisierung | `src/mcp/server.php`, `src/mcp/routes/` |
| Storage | Dateien, Ordnerkonfigurationen und Work-Metadaten | `poff.config.json`, `.works/`; ein ID-Index ist eine offene Option. |
| Build und Distribution | Quellen und Assets zur auslieferbaren Installation zusammenstellen | `build/`, `scripts/build-assets.js`, `scripts/watch-build.js` |
| Erweiterbare Fähigkeiten | Ressourcen erkennen, Works rendern, Layout-Pakete auflösen und Konverter ausführen | `src/includes/MediaType.php`, `src/includes/Worktype/`, `src/includes/Converter.php` |

Die Bereiche überlappen im heutigen Code teilweise. Die Zuordnung beschreibt Zuständigkeiten, keine Behauptung, dass sie bereits vollständig getrennt sind. Graph und Identität gehören fachlich zum Core; das Host-Modul verwaltet seine eigenen Verbindungen. Die Ressource bleibt beim Ursprung, ihr Verbindungseintrag beim Host. Das Frontend entscheidet über die Darstellung der vom Protokoll bereitgestellten Daten.

## Bisherige Core-Schritte

Die Namensvergabe bei Remote-Import-Kollisionen liegt jetzt als `PoffConfig::uniqueImportedName()` im Core. `mcpRemoteUniqueImportedName()` bleibt als Kompatibilitätsadapter in der MCP-Route. Der PHP-Builder flacht die Core-Methode mit den bestehenden `PoffConfig`-Traits in `index.php` ein. Geprüft sind normale und wiederholte Namenskollisionen sowie leere Namen. Das ist ein kleiner transportfreier Baustein; die übrige Remote-Import-Logik bleibt vorerst in der Route.

`PoffConfig::newWorkId()` hält die Work-ID-Vergabe im Core. Folder-Kopien vergeben neue IDs für kopierte Folder- und Datei-Metadaten über `PoffConfig::reassignWorkIdForCopy()`. `PoffNodeIdentity` hält die Node-ID separat in `poff.node.json`; Export ergänzt `source.nodeId` und `workId` pro lokaler Work, ohne `source.id` zu entfernen. Feed-Import speichert Quell-IDs getrennt von lokalen IDs und nutzt eine gültige Node-ID als stabilen Feed-Schlüssel. `PoffNodeIdentity::rotate()` plus `scripts/rotate-node-identity.php` bieten eine explizite Offline-Rotation für Klone; der Start eines Klons wird nicht automatisch blockiert. Pfade und Domains bleiben Adressen, keine Identitäten. Copy-/Move-Regeln sind umgesetzt; echte Work-ID-Kollisionen und die verpflichtende Klonprozedur bleiben offen.

Der read-only Work-ID-Resolver gibt nur eindeutige Treffer zurück und meldet mehrere Treffer als mehrdeutig. `edit=move` erhält IDs bei Folder- und File-Moves, bewegt die dateinamengebundenen File-Sidecars mit und aktualisiert Parent-Tree-Metadaten. Remote-Feed-Regeln liegen transportfrei in `PoffRemoteFeedImport`: Eintragsnormalisierung, Rekursion/Flattening der Copy-Dateien sowie sichere und kollisionsfreie Zielnamen. Die bisherigen `mcpRemote…`-Funktionen bleiben Kompatibilitätsadapter; die Route behält Netzwerkzugriff, Schreiben und Berechtigungsprüfung. Regressionen prüfen Legacy-Felder, ungültige Node-IDs, verschachtelte Assets und die bisherigen Dateinamensregeln.

## Reihenfolge für die Ausarbeitung

1. **Transport/Core trennen:** weitere reine Konfiguration und Import-Operationen aus MCP-Routen in den Core verschieben; vorhandene Routennamen als Adapter erhalten.
2. **Node API spezifizieren:** stabile ID-Auflösung, Export, Remote-Referenzen, Fehlerstatus, Auth und Dateisystemeffekte als unabhängige Verträge festhalten.
3. **Graph-Verbindungen:** Speicher-Ist-Stand und Zielregeln für Parentfolder, direkte Node-/Work-Paare, Relay und Offline-Zustand sind jetzt dokumentiert. Offen vor Implementierung: Add/Remove-API, URL-/Cache-Frische und Bereinigung gemeinsam genutzter Feed-Abos.
4. **MCP optional machen:** die Streamable-Laufzeit ist bereits separat und fehlt im Einzeldatei-Artifact; die Handler bleiben wegen der JSON-Node-API eingebettet. Offen ist die optionale Composer-Dependency und der Startpfad. Beide ändern, ohne den bisherigen Einzeldatei-Standard oder vorhandene API-Routen zu entfernen.
5. **Frontend-Kit entkoppeln:** Move-API ist über eine Sidebar-Aktion erreichbar; stabile Remote-Work-Referenzen und Offline/Reconnect sind noch nicht in der UI. Rendering/Layout bleibt eine lokale Präsentationsentscheidung.
6. **Host und Rechte ausarbeiten:** `host`-Konfiguration, Open/Curated, Rollen, Vererbung und Grenzen festlegen. Bestehende Eingänge anhand des Quellcodes abgleichen; Host bleibt bis zur abgestimmten Umsetzung unimplementiert.
7. **Build und Einführung absichern:** jede Änderung in temporärer Einzeldatei prüfen; generierte Inhaltsausgaben und bestehende lokale Build-Dateien unangetastet lassen.
8. **Erweiterbarkeit erhalten:** Worktype-, Layout- und Converter-Verträge beibehalten; nur capability-spezifische Core-Grenzen extrahieren. [Audit-Baseline](erweiterbarkeit-baseline.md)

Für jeden Schritt: erst Beispiele und Akzeptanzkriterien mit dem Nutzer durchgehen, dann Quellstand prüfen und eine kleine Änderung planen. Die Reihenfolge ist ein Vorschlag; sie verpflichtet noch nicht auf ein Datenformat oder eine öffentliche API.

## Priorisierter Rest-Backlog

1. **P0 — Streamable MCP wirklich optional installieren:** `php-mcp/server` aus der Root-Dependency in eine separat installierbare MCP-Abhängigkeit bewegen und den Startpfad darauf ausrichten. Dafür müssen `composer.json` und `composer.lock` geändert werden; diese Dateien liegen derzeit außerhalb der Edit-Allowlist in `AGENTS.md`. Bis dahin bleibt nur der HTTP-Einstieg aus dem Single-File-Artifact herausgelöst.
2. **P1 — Adressfindung nach Relay/Domainwechsel rubberducken:** entscheiden, ob ein letzter Node-Adresshinweis pro Node-ID mitgeführt wird, ob mehrere Adressen zulässig sind und ob ein freiwilliges Verzeichnis angeboten wird. Die Identität bleibt in jedem Fall `(nodeId, workId)`; Adresswechsel ändern sie nicht.
3. **P1 — Work-ID-Forkregel entscheiden:** Node-ID-Rotation eines Klons ist implementiert; klären, ob beim unabhängigen Fork zusätzlich sämtliche Work-IDs erneuert werden müssen und wie Rotation vor dem ersten Betrieb erzwungen wird.
4. **P1 — Move-Rollback abdecken:** partielle Dateisystemfehler gezielt simulieren und prüfen, dass Ressourcen und Sidecars gemeinsam wiederhergestellt werden.
5. **P2 — Remote-Referenz im Frontend-Kit:** nach Adressvertrag ID-basiertes Öffnen, Reconnect und Offline-Zustand ergänzen. Offline darf keinen gespeicherten Verweis löschen.
6. **P2 — Host-Modell abstimmen:** Ordnergrenzen, Open/Curated, Rollen und vererbte Redaktionsrechte präzisieren. Implementierung bleibt ausdrücklich ausgesetzt, bis das Modell abgestimmt ist.
7. **P3 — Physische Modulgrenzen:** nach den Verträgen Core, Node API, Frontend-Kit und optionale Adapter schrittweise organisatorisch trennen; `index.php`-Bundle-Kompatibilität bleibt bei jedem Schritt getestet.
