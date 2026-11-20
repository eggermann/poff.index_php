# Umgebungen und Testordner

Inventar vom 20. November 2026. Lokale Pfade und vorhandene Einstiegspunkte wurden auf der Platte geprüft. Öffentliche Ziele stammen aus Deploy-Doku, Quellcode und früheren Betriebsnotizen. Ihr Live-Stand wurde für dieses Inventar nicht geprüft.

## 11 lokale PageOff-Einstiegspunkte

Alle liegen unter `/Applications/MAMP/htdocs/`. Die Zahl zählt Pfade mit eigener `index.php` im bekannten PageOff-Bestand. Einige teilen denselben Projektbaum. Die lokalen Demos laufen unter demselben MAMP-Webserver.

| Anzahl | Wo | Zweck |
| --- | --- | --- |
| 4 | `pageoff-artist`, `pageoff-writer`, `pageoff-curator`, `pageoff-civic` | Eigenständige Studien mit eigenen Layouts, Werken und Nachbarlinks. Curator hat auch übernommene Werke. |
| 2 | `node-1`, `node-2` | Eigenständige Minimalumgebungen für Node-Tests. Historisch als leere, getrennte Starts angelegt. |
| 1 | `pageoff` | Lokale PageOff-Umgebung. `pageoff/actual` ist ein Symlink ins Repo unter `pages/pageoff.de` und hat dort keinen eigenen `index.php`-Einstieg. |
| 1 | `MAUSMAUS` | Eigenständiger lokaler Bestandswebspace. |
| 2 | `dominikeggermann-project`, `dominikeggermann-project/page` | Zwei Einstiegspunkte im selben Projektbaum. Beide sind zusätzliche Kopierziele des Builds. |
| 1 | `dominikeggermann.com` | Lokaler Symlink auf die primäre Build-Ausgabe des Repos. |

Damit ergeben sich acht eigenständige Kopierziele, zwei zusätzliche Kopierziele und ein Pfad zur primären Build-Ausgabe. Die kürzere Liste in der README stammt aus einem älteren Stand. Die vier Demos und zwei Minimalumgebungen belegen eigenständige Node-Umgebungen; sie sind nicht automatisch als Hosts im Sinn des aktuellen Konzepts aktiviert. [Build-Konfiguration](../../../build/BuildConfig.shared.json)

## 5 dokumentierte öffentliche Umgebungen, plus ein Zielbild

| Umgebung | Beleg und Einordnung |
| --- | --- |
| `dominikeggermann.com` | Portfolio auf eggman1, laut früherem Review deployt und damals im Browser geprüft. |
| `pageoff.de` | Bisherige Adresse für PageOff-Konverter und Node-Anmeldungen im Code. `pageoff` ist ein dokumentiertes Deploy-Ziel auf eggman4. |
| `dodi` | Dokumentierter eggman4-Webspace `/dodi/` für den Copy-Baum und frühere Converter-Versuche. `dodi1` bezeichnet laut Doku denselben Webspace. |
| `dodi2` | Historisch als separate öffentliche Seite dokumentiert. `dodi_2` ist der Deploy-Name für die zweite Profilfamilie. |
| `customer` | Dokumentiertes öffentliches Deploy-Ziel auf eggman4. |
| `off.page` | Geplanter offizieller Host im neuen Modell. Ob und wie die Domain zur bisherigen Installation passt, ist offen. |

[Früherer Produktionsreview](../../../.mlms/memory-bank/sprintReview.md) · [Deploy-Pfade](../copilot-chat-and-pages.md) · [dodi1](../dodi1-webspace.md) · [dodi und dodi2](../dodi-current-state.md) · [Deploy-Profile](../../../scripts/deploy-targets.js)

## Testordner

Gezählt werden **3 dedizierte Testbereiche im Repo**. Verschachtelte Fälle und Dateien gehören jeweils zu ihrem Bereich.

| Pfad | Verwendung |
| --- | --- |
| `tests/datas/` | Quelldaten für Ordner- und Kopiertests mit verschachtelten Markdown-Dateien. |
| `tests/poff-tests/` | Testbaum für Viewer, Layout, Upload, Links und Ordner. Die MCP-Suite legt den Baum beim Start neu an. |
| `poff/test-converter/` | Converter-Beispiele für Text und Bild. `dodi1/` ist ein Unterordner mit `scan-001.tiff`. |

Dazu kommen **23 Jest-Suites und 44 PHP-Testhilfen** unter `tests/`. Einzelne Tests erzeugen eigene temporäre Verzeichnisse. Mehrere Converter-Tests lesen außerdem `archive/scan-001.tiff` als Fixture. `poff/converters/` enthält zwei Converter-Beispielpakete. Der gleichnamig klingende MAMP-Ordner `test-poff/` enthält eine statische JS-App mit `index.html` und ist in den elf PHP-Einstiegen nicht enthalten. `pageoff/actual/test` ist eine Datei. [MCP-Testaufbau](../../../tests/mcp.create.test.js)
