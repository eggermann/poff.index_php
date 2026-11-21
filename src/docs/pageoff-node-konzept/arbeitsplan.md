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

`PoffConfig::newWorkId()` hält die ID-Vergabe im Core. Folder-Kopien vergeben neue IDs für kopierte Folder- und Datei-Metadaten über `PoffConfig::reassignWorkIdForCopy()`; Remote-Import nutzt ebenfalls den Core. MCP-Routen bleiben Adapter. Quell-IDs bleiben bestehen. Das ist eine branch-lokale Kopierregel, noch kein öffentlich beschlossenes Identitätsformat. Move/Rename und Node-Adresse bleiben offen.

## Reihenfolge für die Ausarbeitung

1. **Identität klären:** Speicherort der Work-ID, Vergabe und Verhalten bei Umbenennen, Verschieben und Kopieren beschreiben. Domainwechsel und mögliche Node-ID gesondert entscheiden.
2. **Auflösung skizzieren:** öffentlichen ID-Zugriff und Fehlerfälle definieren; entscheiden, ob ein wiederherstellbarer Index nötig ist.
3. **Verbindungen beschreiben:** Eintrag im Parentfolder, direkte Node- und Work-Verbindungen, Offline-Zustand und Unmount festlegen.
4. **Host und Rechte ausarbeiten:** `host`-Konfiguration, Open/Curated, Rollen, Vererbung und Host-Grenzen festlegen. Bestehende Eingänge dabei anhand des Quellcodes abgleichen.
5. **Frontend und API ableiten:** Navigation, Darstellung und öffentliche Daten aus den zuvor vereinbarten Regeln ableiten.
6. **Build und Einführung planen:** Änderungen inkrementell in den vorhandenen Ein-Datei-Build aufnehmen; Dateisplit und Release-Prüfungen gesondert entscheiden.
7. **Erweiterbarkeit absichern:** bestehende Worktype-, Layout- und Converter-Verträge durch Tests schützen; erst danach capability-spezifische Core-Grenzen festlegen. [Audit-Baseline](erweiterbarkeit-baseline.md)

Für jeden Schritt: erst Beispiele und Akzeptanzkriterien mit dem Nutzer durchgehen, dann Quellstand prüfen und eine kleine Änderung planen. Die Reihenfolge ist ein Vorschlag; sie verpflichtet noch nicht auf ein Datenformat oder eine öffentliche API.
