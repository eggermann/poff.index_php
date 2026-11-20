# PageOff / off.page

PageOff ist ein dezentrales CMS auf Basis von Ordnern und Dateien. Jede Installation verwaltet ihren eigenen Inhalt und bestimmt, wie er aussieht, was öffentlich wird und mit wem sie ihn teilt. `index.php` bündelt Viewer und Editor. Titel, Sichtbarkeit und andere Eigenschaften liegen in `poff.config.json` oder unter `.works/`. Layouts können beim Inhalt liegen oder aus einem übergeordneten Ordner kommen.

## Der Node-Ansatz ist schon da

Der Commit `4f42aef` und die frühere Node-Studie dokumentieren drei eigenständige Webroots für Artist, Writer und Curator. Civic kam als vierter hinzu. Jede Demo hat eigene Inhalte und ein eigenes Layout. Curator kann Artist live verlinken und besitzt eine eigene Kopie eines Artist-Layouts sowie einen übernommenen Writer-Text. Ein Live-Link bleibt beim Ursprung. Eine lokale Kopie wird am Ziel eigenständig weiterbearbeitet. [Review der Node-Studie](../../../.mlms/memory-bank/sprintReview.md) · [Entscheidungen der Node-Studie](../../../.mlms/memory-bank/decisionLog.md) · [Civic-Demo](../designer-ui-ux-review.md)

| Fähigkeit | Stand |
| --- | --- |
| Eigenständige Seiten | Getrennte Verzeichnisse mit eigener `index.php`, Inhalten und Layouts. |
| Inhalte austauschen | Verlinken, einbetten, Export lesen oder lokal kopieren. Die Node-Studie belegte lokale Links, Einbettung und explizite Kopien. Der HTTP-Import ins Dateisystem hat eigene Regeln und sperrt localhost. |
| Dienste nutzen | `converter-offers` und `convert-offered` bilden das Protokoll `poff-converter/1`. Ein Editor kann einen kompatiblen Anbieter per URL auswählen. |
| Einen Node anmelden | `register-node` sendet URL, Titel, Kategorien und Katalog-URLs mit `poff-node/1` an ein Verzeichnis. Im Code ist `pageoff.de/index.php` die Vorgabe. Die Gegenstelle nutzt den vorhandenen Link-Upload. |
| Eingänge sichten | `externalSubmissions.enabled` öffnet bereits den Link-Eingang eines Ordners. Er kann zulässige Absenderhosts und die Zahl offener Einträge begrenzen. Poff-Einträge können als unsichtbar und `pending` landen. Der bestehende Editor kann sie über die Sichtbarkeit freigeben. |
| Redaktion berechtigen | Der heutige Editorzugang hängt am Installationsroot und an `.poff-auth.php`. Ordner können den Edit-Modus per `.edit.not-allow` sperren. Die README-Beschreibung zu `.edit.allow` ist für den heutigen Quellcode überholt. |

[Export und Import](../../mcp/routes/remote-content.php) · [Converter und Node-Anmeldung](../../mcp/routes/converter-network.php) · [Eingang](../../includes/viewer/edit/upload/links.php) · [Freigabe](../../includes/viewer/edit/action/save/layout-tree.php) · [Auth](../../includes/auth.php) · [Edit-Sperre](../../includes/edit-mode.php)

## Architekturstand

Der verbindliche Konzeptstand ist [PageOff – Oberste Architektur](architektur.md). Er trennt Dateisystem und Metadaten, Core, optionales Host-Modul und Frontend-Kit. Hosts werden über `host` in `poff.config.json` aktiviert. `index.php` bleibt zunächst alleiniger ausgelieferter Einstieg; `server.php` ist nicht festgelegt.

Die Umsetzung bleibt teilweise offen: dauerhafte Work-IDs und ihre Auflösung, Graph-Verbindungen, Host-Grenzen und hostbezogene Redaktionsrechte fehlen noch oder sind nicht durchgängig vereinheitlicht. Das Konzept beschreibt das Ziel, nicht den bereits implementierten Funktionsstand.

- [Umgebungen und Testordner](umgebungen-und-tests.md)
- [Oberste Architektur](architektur.md)
- [Architekturansatz und Reihenfolge](arbeitsplan.md)
- [Routing- und Build-Baseline](routing-baseline.md)
- [Work-Identität: Ist-Stand](work-identitaet-baseline.md)
- [Erweiterbarkeit: Worktypes, Layouts und Converter](erweiterbarkeit-baseline.md)

## Auslieferung

Der Builder bündelt die modulare Quelle in einer PHP-Datei. Die Bestandsaufnahme dokumentiert Eingaben, korrigierte MCP-Pfade, gemessene Größen und vorgeschlagene Release-Prüfung. [Builder und Ein-Datei-Auslieferung](bundler-frage.md)
