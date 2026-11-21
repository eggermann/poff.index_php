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

Der read-only Work-ID-Resolver gibt nur eindeutige Treffer zurück und meldet mehrere Treffer als mehrdeutig. `edit=move` erhält IDs bei Folder- und File-Moves, bewegt die dateinamengebundenen File-Sidecars mit und aktualisiert Parent-Tree-Metadaten. Die Remote-Feed-Eintragsnormalisierung liegt in `PoffRemoteFeedImport::normalizeTreeEntries()` als transportfreier Core-Dienst; `mcpRemoteImportTreeEntries()` bleibt der MCP-Adapter. Integrationstest und isolierter Bundle-Test prüfen dieselben Remote-Referenzfelder.

## Reihenfolge für die Ausarbeitung

1. **Transport/Core trennen:** weitere reine Konfiguration und Import-Operationen aus MCP-Routen in den Core verschieben; vorhandene Routennamen als Adapter erhalten.
2. **Node API spezifizieren:** stabile ID-Auflösung, Export, Remote-Referenzen, Fehlerstatus, Auth und Dateisystemeffekte als unabhängige Verträge festhalten.
3. **Graph-Verbindungen beschreiben:** Parentfolder-Eintrag, direkte Node- und Work-Verbindungen, Offline-Zustand und Unmount festlegen.
4. **MCP optional machen:** getrennte Assemblierung planen und nachweisen, ohne den bisherigen Einzeldatei-Standard oder vorhandene API-Routen zu ändern.
5. **Host und Rechte ausarbeiten:** `host`-Konfiguration, Open/Curated, Rollen, Vererbung und Grenzen festlegen. Bestehende Eingänge anhand des Quellcodes abgleichen; Host bleibt bis zur abgestimmten Umsetzung unimplementiert.
6. **Build und Einführung absichern:** jede Änderung in temporärer Einzeldatei prüfen; generierte Inhaltsausgaben und bestehende lokale Build-Dateien unangetastet lassen.
7. **Erweiterbarkeit erhalten:** Worktype-, Layout- und Converter-Verträge beibehalten; nur capability-spezifische Core-Grenzen extrahieren. [Audit-Baseline](erweiterbarkeit-baseline.md)

Für jeden Schritt: erst Beispiele und Akzeptanzkriterien mit dem Nutzer durchgehen, dann Quellstand prüfen und eine kleine Änderung planen. Die Reihenfolge ist ein Vorschlag; sie verpflichtet noch nicht auf ein Datenformat oder eine öffentliche API.
