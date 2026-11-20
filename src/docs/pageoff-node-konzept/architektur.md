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

Jedes Work hat eine dauerhafte ID, die Umbenennen und Verschieben übersteht. Eine Identität besteht zunächst aus Node-Adresse plus Work-ID. Eine Kopie auf einem anderen Node erhält eine neue ID; `copiedFrom` kann ihre Herkunft festhalten.

### Optionales Host-Modul

Das Host-Modul ist eine optionale Fähigkeit eines Nodes. Es aktiviert bestimmte Ordner als Hosts, nimmt externe Nodes und Works per URL auf, verwaltet direkte Graph-Verbindungen, offene oder kuratierte Einreichungen, Host-Ordnerrechte und öffentliche Informationen zu direkten Verbindungen.

Ein Host kann einen ganzen Node oder einzelne Works verknüpfen. Die Originalressource bleibt beim Ursprungs-Node. Eine Verbindung erstellt weder automatisch eine Dateikopie noch erfordert sie Synchronisation. Der lokale Host-Eintrag liegt im eigenen Parentfolder. Mehrere Hosts können dieselbe externe Ressource in unterschiedlichen Parentfoldern führen.

### Frontend-Kit

Das Frontend-Kit rendert Works, stellt Layouts und Gestaltung bereit, bietet Ordner- und Netzwerknavigation, zeigt externe Ressourcen als normale Works und kann gemountete Nodes durchsuchbar machen. Es kann unerreichbare Ressourcen als offline anzeigen.

Ob ein fremder Node wie ein Ordner durchsuchbar ist oder als Einstiegspunkt erscheint, entscheidet das Frontend-Kit. Core stellt dafür die nötigen öffentlichen Informationen und Zugriffe bereit.

### Erweiterbare Fähigkeiten

Worktypes beschreiben, wie erkannte Ressourcen fachlich eingeordnet und gerendert werden. Layout-Pakete bestimmen die Präsentation eines Works. Converter transformieren Ressourcen ausdrücklich und können daraus neue Works erzeugen. Diese Fähigkeiten bleiben getrennt: Ressourcenerkennung löst weder Rendering noch Konvertierung aus; ein Layout ändert weder Ressource noch Work-Identität; ein entdeckter Remote-Converter erlaubt keine lokale Ausführung seines Codes.

Der heutige Code registriert Worktypes und Converter über Dateien, Bundles und Build-Einbettung; einen gemeinsamen Plugin-Manager gibt es nicht. Architektur-Ziel führt deshalb keinen generischen Plugin-Rahmen ein. Exakte Matching-, Fallback-, Template- und Builder-Regeln stehen in der [Erweiterbarkeits-Baseline](erweiterbarkeit-baseline.md) und müssen als Kompatibilitätsverträge erhalten bleiben.

## Node, Host, Work und Verbindung

- **Node:** unabhängige PageOff-Installation mit eigener Adresse und eigenen Ressourcen.
- **Host:** aktivierter Ordner innerhalb einer Installation. Ein Node kann mehrere Hosts betreiben.
- **Work:** identifizierbare lokale oder externe Ressource beziehungsweise ein Eintrag.
- **Parentfolder:** Ordner, der einen Work-Eintrag enthält und dessen lokalen Kontext bestimmt.
- **Verbindung:** lokaler Eintrag, der auf einen externen Node oder ein externes Work verweist.

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

Logische Module und ausgelieferte PHP-Dateien sind getrennte Entscheidungen. `index.php` bleibt der zentrale Einstieg und stellt Viewer, Editor und Core-Funktionen bereit. Ein Node funktioniert ohne aktivierten Host und stellt öffentliche Works über stabile IDs bereit.

Host-Funktionen werden im Quellcode eigenständig organisiert. Vorläufig bleibt es bei einer ausgelieferten `index.php`; ein zusätzliches `host.php` ist nur sinnvoll, wenn es Auslieferung, Wartung oder Funktionstrennung verbessert. `server.php` ist kein festgelegter Architekturbaustein.

## Netzwerk

Nodes nehmen einander auf, ohne ihre Unabhängigkeit aufzugeben. Jeder Host bestimmt seine Verbindungen, Einreichungen, Rechte und Layouts. `off.page` kann gemeinsamer Einstieg und kuratierter Host sein, hat aber keine Sonderrechte. Netzwerkbereiche können unabhängig erreichbar, zeitweise offline oder neu verbunden sein.

## Stand und offene Punkte

**Bereits vorhanden:** dateibasiertes CMS, Viewer, Editor, Worktypes, Layout-Vererbung, externe Links, Remote-Exporte, lokale Kopien, Node-Anmeldungen sowie grundlegende Einreichungs- und Freigabefunktionen.

**Beschlossen, aber noch nicht vollständig implementiert:** dauerhafte Work-ID-Auflösung, einheitliche Graph-Verbindungen, hostbezogene Redaktionsrechte, klare Host-Grenzen und optionales Host-Modul.

**Noch offen:** Domainwechsel eines Nodes, mögliche dauerhafte Node-IDs, genaues Host-Konfigurationsschema, endgültiger PHP-Dateisplit und Schnittstelle für dynamische Netzwerknavigation.

## Architekturprinzip

PageOff soll als einzelner Node einfach und unabhängig bleiben und durch freiwillige Verbindungen zu einem dezentralen, redaktionell gestaltbaren Netzwerk wachsen. Erweiterbarkeit entsteht durch gemeinsame Ressourcenidentitäten, lokale Parentfolder-Beziehungen und ein optionales Host-Modul – nicht durch eine zentrale Plattform.
