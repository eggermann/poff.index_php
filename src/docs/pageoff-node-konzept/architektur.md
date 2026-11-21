# PageOff – Oberste Architektur

**Konzeptstand: 20. November 2026**

## Grundidee

PageOff ist ein dezentrales, dateibasiertes Content-Management-System. Jede Installation ist ein eigenständiger Node, der seine Dateien, Works, Metadaten und Layouts selbst verwaltet. Nodes können sich verbinden und bilden einen gerichteten Graphen, keinen zentral verwalteten Verzeichnisbaum.

> Ein Node besitzt seine Ressourcen. Ein Host verwaltet seine Verbindungen. Ein Frontend-Kit bestimmt deren Darstellung.

Es gibt keine verpflichtende zentrale Instanz. `off.page` ist ein Host nach denselben Regeln wie andere Hosts.

## Vier logische Schichten

### Dateisystem und Metadaten

- Normale Dateien und Ordner bleiben die Inhalte.
- `poff.config.json` speichert Ordnerkonfigurationen, Work-Einträge und Einstellungen.
- `.works/` enthält Metadaten einzelner Works.
- Layouts können lokal definiert oder aus übergeordneten Ordnern geerbt werden.
- Eine zentrale Datenbank ist nicht erforderlich.

### PageOff-Core

Der Core verwaltet Works, Ordner, Ressourcen, dauerhafte Work-IDs, ID-zu-Pfad-Auflösung, lokale Parentfolder-Beziehungen, Metadaten, Layout-Vererbung, öffentliche Bereitstellung und grundlegende Zugriffsprüfungen.

Eine lokale Work-ID liegt bei Foldern in `poff.config.json`, bei Files in `.works/<Dateiname>.config.json`. Die stabile Referenz ist `nodeId + workId`; Domain und URL sind nur veränderliche Adressen. `poff.node.json` speichert die Node-ID. Der lesende Resolver akzeptiert eine eindeutige sichtbare Work-ID und meldet Mehrdeutigkeit ausdrücklich. Der authentifizierte `edit=move`-Pfad bewegt Folder-Metadaten beziehungsweise File und filename-bound Sidecars gemeinsam. Kopien erhalten neue Work-IDs. Ein Pfadindex wird derzeit nicht persistiert.

### Node API und MCP-Adapter

Die bestehenden JSON-Routen unter `?mcp=1&route=…` sind PageOffs rückwärtskompatible Node API mit historischem Namen. Der eigenständige `src/mcp/server.php` ist ein weiterer JSON-Einstieg. Das Streamable-MCP-Tool-Protokoll liegt separat in `src/mcp/http-server.php`. Diese drei Oberflächen haben verschiedene Routen, Initialisierung und Seiteneffekte; sie sind keine austauschbaren Dispatches.

Zielrichtung: Core-Operationen bleiben ohne Netzwerk oder MCP verfügbar. Die Node API adaptiert Requests in die vorhandenen JSON-Routen; das Streamable-MCP-Protokoll ist ein optionaler Adapter. Der Einzeldatei-Builder behält die bestehende JSON-Node-API bei, damit vorhandene Clients weiter funktionieren. Heute ist nur der Streamable-HTTP-Einstieg außerhalb des Single-File-Artifacts; seine Bibliothek `php-mcp/server` bleibt noch eine verpflichtende Root-Composer-Dependency. Vollständige Installations-Optionalität ist deshalb ein offener Build-/Dependency-Schritt, kein bereits erreichter Zustand.

### Optionales Host-Modul

Das Host-Modul ist das Ziel für eine optionale Fähigkeit eines Nodes. Es soll bestimmte Ordner als Hosts aktivieren, direkte Graph-Verbindungen verwalten und offene oder kuratierte Einreichungen sowie Host-Ordnerrechte bereitstellen. Dieses Modul ist noch nicht implementiert. Die bestehenden `externalSubmissions`-Einstellungen und Link-Upload-Funktionen sind Vorläufer, aber kein vollständiges Host-Modell.

Ein Host kann einen ganzen Node oder einzelne Works verknüpfen. Die Originalressource bleibt beim Ursprungs-Node. Eine Verbindung erstellt weder automatisch eine Dateikopie noch erfordert sie Synchronisation. Der lokale Host-Eintrag liegt im eigenen Parentfolder. Mehrere Hosts können dieselbe externe Ressource in unterschiedlichen Parentfoldern führen.

### Frontend-Kit

Das Frontend-Kit rendert Works, stellt Layouts und Gestaltung bereit, bietet Ordner- und Netzwerknavigation, zeigt externe Ressourcen als normale Works und kann gemountete Nodes durchsuchbar machen. Es kann unerreichbare Ressourcen als offline anzeigen.

Ob ein fremder Node wie ein Ordner durchsuchbar ist oder als Einstiegspunkt erscheint, entscheidet das Frontend-Kit. Core stellt dafür die nötigen öffentlichen Informationen und Zugriffe bereit.

**Code-Stand:** Viewer/Rendering und Navigation liegen derzeit in `src/includes/viewer/`, `src/includes/nav-render.php` und `src/assets/js/`; ein eigenständiges Frontend-Kit-Paket existiert nicht. Remote Einbettung nutzt überwiegend `linkUrl`/`pageLink`, Hash-Routen und Snapshot-/HTML-Fallbacks. Die UI verwendet noch keine dauerhafte Remote-Auflösung über `(nodeId, workId)` und hat keinen expliziten Offline-/Reconnect-Zustand. Der lokale Resolver und der neue Move-Endpunkt sind vorhanden; für `edit=move` gibt es noch keine UI.

**Frontend-Zielregel:** Ein lokales Layout darf die Präsentation einer Verbindung ändern, aber weder Quell-Work noch Identität verändern. Die UI folgt einer Verbindung über ihre stabile Referenz, zeigt Offline-Verfügbarkeit als Zustand der Verbindung und bewahrt sie bei Fehlern. Navigation in einen fremden Node bleibt eine bewusste Frontend-Entscheidung; direkte Graph-Kanten werden nicht stillschweigend zu rekursivem Crawling.

### Erweiterbare Fähigkeiten

Worktypes beschreiben, wie erkannte Ressourcen fachlich eingeordnet und gerendert werden. Layout-Pakete bestimmen die Präsentation eines Works. Converter transformieren Ressourcen ausdrücklich und können daraus neue Works erzeugen. Diese Fähigkeiten bleiben getrennt: Ressourcenerkennung löst weder Rendering noch Konvertierung aus; ein Layout ändert weder Ressource noch Work-Identität; ein entdeckter Remote-Converter erlaubt keine lokale Ausführung seines Codes.

Der heutige Code registriert Worktypes und Converter über Dateien, Bundles und Build-Einbettung; einen gemeinsamen Plugin-Manager gibt es nicht. Architektur-Ziel führt deshalb keinen generischen Plugin-Rahmen ein. Exakte Matching-, Fallback-, Template- und Builder-Regeln stehen in der [Erweiterbarkeits-Baseline](erweiterbarkeit-baseline.md) und müssen als Kompatibilitätsverträge erhalten bleiben.

## Node, Host, Work und Verbindung

- **Node:** unabhängige PageOff-Installation mit eigener Adresse und eigenen Ressourcen.
- **Host:** aktivierter Ordner innerhalb einer Installation. Ein Node kann mehrere Hosts betreiben.
- **Work:** identifizierbare lokale oder externe Ressource beziehungsweise ein Eintrag.
- **Parentfolder:** Ordner, der einen Work-Eintrag enthält und dessen lokalen Kontext bestimmt.
- **Verbindung:** lokaler Eintrag, der auf einen externen Node oder ein externes Work verweist.

### Graph-Vertrag: Ist-Stand und Zielregel

Heute bestehen externe Einträge aus zwei kompatiblen Formen: frei konfigurierte Links wie `linkUrl`, sowie importierte Feed-Einträge im `tree`. Ein Feed-Eintrag kann den Ursprung als `remoteNodeId` + `remoteWorkId`, den direkt abfragbaren Feed getrennt als `remoteFeedNodeId` und die aktuelle Adresse als `remoteFeedUrl` speichern. Auf Folder-Ebene hält `remoteSources` die Feed-Adresse und – sofern bekannt – die Node-ID. Ältere Einträge ohne stabile IDs bleiben gültig. Das sind Speicherfelder für die bestehende Importfunktion, noch kein vollständiges Mount-/Unmount-Protokoll.

Für den Graphen gilt als Zielregel:

- Eine Verbindung gehört dem lokalen Parentfolder und ist ein eigener lokaler Tree-Eintrag. Derselbe entfernte Work darf in mehreren Parentfoldern unabhängig präsentiert werden.
- Wenn bekannt, bezeichnet `target = (nodeId, workId)` eindeutig den ursprünglichen Work. Node-Adresse, Domain, Pfad und `pageLink` sind änderbare Erreichbarkeitshinweise und ändern die Identität nicht. Die Adresse muss dem jeweiligen Node zugeordnet sein; `remoteFeedUrl` kann bei einem Relay nur den Vermittler erreichen und reicht nicht zwingend, um den ursprünglichen Node aufzulösen.
- `remoteFeedNodeId` beschreibt bei weitergereichten Feeds den unmittelbaren Vermittler; `remoteNodeId` + `remoteWorkId` bleiben die Herkunft. Relay darf die Ursprungsreferenz nicht in eine neue Identität umschreiben.
- Eine stabile Node-ID löst keine Adresse auf. Ohne zentrale Pflichtinstanz muss eine neue Adresse über eine bekannte Verbindung, einen direkten erneuten Austausch oder ein freiwilliges Verzeichnis gelernt werden. Es gibt noch keine beschlossene Address-Discovery-Regel.
- Ein nicht erreichbarer Node löscht keine Verbindung. `offline` soll aus Erreichbarkeitsprüfung und Abrufzeit abgeleitet werden, nicht zur Identität gehören. Ein solches Status-/Cachemodell ist aktuell noch nicht implementiert.
- Mounten soll nur eine lokale Parentfolder-Verbindung anlegen. Unmounten darf nur diesen Eintrag entfernen, niemals die Ressource am Ursprungs-Node. Transitive Verbindungen werden nicht automatisch importiert.
- Links ohne stabile Node-/Work-Paarung bleiben normale URL-Verbindungen und dürfen nicht fälschlich als dauerhafte Work-Referenzen behandelt werden.

Noch zu entscheiden sind das konkrete Add/Remove-API, Erreichbarkeitsspeicher und Cache-Frische, Umgang mit mehreren URLs für dieselbe Node-ID, Address-Discovery nach Domainwechseln, sowie wann ein Feed-Eintrag aus `remoteSources` entfernt werden darf, wenn weitere Works ihn noch referenzieren. Vor dieser Entscheidung werden keine neuen Graph-Felder eingeführt.

Ein Host speichert nur direkte Verbindungen; indirekte Verbindungen fremder Nodes werden nicht automatisch übernommen. Mounten erstellt eine Verbindung. Unmounten entfernt diese Verbindung, nicht die Ursprungsressource. Bei Nichterreichbarkeit bleibt die Verbindung bestehen und kann offline angezeigt werden.

## Hosting und Redaktion

Hosting wird für einen Ordner ausdrücklich über eine `host`-Konfiguration in der vorhandenen `poff.config.json` aktiviert. Eine zusätzliche `.allowhost`-Datei ist nicht vorgesehen.

Jeder Host legt die Behandlung von Einreichungen fest:

- **Open:** zulässige Einreichungen werden unmittelbar aufgenommen.
- **Curated:** Einreichungen bleiben bis zur redaktionellen Freigabe verborgen.

Andere Nodes können sich selbst anmelden; Redaktionen können Nodes und Works direkt hinzufügen.

- **Ordnerinhaber:** aktiviert Hosts und vergibt Rechte.
- **Redaktion:** verwaltet Einreichungen und bearbeitet lokale Works im zugewiesenen Bereich.
- **Einreichende:** bieten Ressourcen zur Aufnahme an, sofern der Eingang dies zulässt.

Redaktionsrechte gelten in Unterordnern weiter, bis eine neue Host-Grenze mit eigenen Rechten beginnt. Das Aufnehmen eines fremden Works gewährt keine Bearbeitungsrechte am Original.

## `index.php` und Host-Modul

Logische Module und ausgelieferte PHP-Dateien sind getrennte Entscheidungen. `index.php` bleibt der einzige ausgelieferte Einstieg. Ein Node funktioniert ohne aktivierten Host und stellt öffentliche Works über stabile IDs bereit. Der Builder bündelt heute Viewer, Editor und die historisch `mcp` genannten JSON-Node-API-Routen gemeinsam. Das Streamable-MCP-Tool-Protokoll läuft separat; es gibt noch keinen optionalen Build-Schalter für seine weitere Adapterassemblierung.

Host-Funktionen werden im Quellcode eigenständig organisiert. Vorläufig bleibt es bei einer ausgelieferten `index.php`; ein zusätzliches `host.php` ist nur sinnvoll, wenn es Auslieferung, Wartung oder Funktionstrennung verbessert. `server.php` ist kein festgelegter Architekturbaustein.

## Netzwerk

Nodes nehmen einander auf, ohne ihre Unabhängigkeit aufzugeben. Jeder Host bestimmt seine Verbindungen, Einreichungen, Rechte und Layouts. `off.page` kann gemeinsamer Einstieg und kuratierter Host sein, hat aber keine Sonderrechte. Netzwerkbereiche können unabhängig erreichbar, zeitweise offline oder neu verbunden sein.

## Stand und offene Punkte

**Bereits vorhanden:** dateibasiertes CMS, Viewer, Editor, Worktypes, Layout-Vererbung, externe Links, Remote-Exporte, lokale Kopien, Node-Anmeldungen, Einreichungs- und Freigabefunktionen, stabile Node- und lokale Work-IDs, additive Remote-Referenzen, eindeutige Work-ID-Auflösung und ein authentifizierter Move-Pfad. Diese Funktionen liegen noch teilweise in gemischten Schichten.

**Beschlossen, aber noch nicht vollständig implementiert:** transportfreie Node-API-Services, MCP als optionaler Adapter, einheitliche Graph-Verbindungen, ein dokumentierter und getesteter Klon-Betriebsablauf, hostbezogene Redaktionsrechte, klare Host-Grenzen und optionales Host-Modul. Ein Offline-Rotationswerkzeug für Node-IDs besteht; der Dienststart eines geklonten Nodes wird dadurch nicht automatisch blockiert.

**Noch offen:** Migration echter Work-ID-Kollisionen, ob ein Node-Fork zusätzlich alle Work-IDs erneuert, wie ein Rotationsschritt in Klonabläufen verpflichtend wird, genaues Host-Konfigurationsschema, endgültiger PHP-Dateisplit und Schnittstelle für dynamische Netzwerknavigation. Domainwechsel ändern die Identität nicht; Node-ID-Rotation ist dagegen ein expliziter Identitätswechsel.

## Architekturprinzip

PageOff soll als einzelner Node einfach und unabhängig bleiben und durch freiwillige Verbindungen zu einem dezentralen, redaktionell gestaltbaren Netzwerk wachsen. Erweiterbarkeit entsteht durch gemeinsame Ressourcenidentitäten, lokale Parentfolder-Beziehungen und ein optionales Host-Modul – nicht durch eine zentrale Plattform.
