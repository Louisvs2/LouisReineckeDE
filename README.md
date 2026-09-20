# louisreinecke.de

Portfolio-Seite von Louis Reinecke — Video und Fotografie.
Statisches HTML/CSS/JS ohne Build-Schritt, damit es sowohl auf Vercel als
auch später per FTP auf Strato unverändert läuft.

## Struktur

```
index.html          Startseite: Titel, Projektliste, Thumbnail-Raster
projekt.html        Projektseite, wird über ?p=<slug> aufgerufen
info.html           Kurzbio und Kontakt
data/projects.json  Alle Inhalte — die einzige Datei, die du zum Pflegen brauchst
media/<slug>/       Bilder des jeweiligen Projekts
css/style.css
js/app.js
```

## Neues Projekt anlegen

1. Ordner `media/<slug>/` anlegen und die Bilder hochladen (bei Strato per FTP,
   sonst hier ins Repo). Benenne sie durchnummeriert: `01.jpg`, `02.jpg`, …
2. In `data/projects.json` einen Eintrag im Array `projects` ergänzen:

```json
{
  "slug": "neues-projekt",
  "title": "Neues Projekt",
  "client": "Kundenname",
  "year": "2026",
  "type": "Video",
  "tags": ["Portrait"],
  "excerpt": "Ein Satz, der auf der Projektseite oben steht.",
  "text": "Zwei bis vier Sätze zum Projekt.",
  "cover": "media/neues-projekt/01.jpg",
  "video": "https://www.youtube.com/embed/VIDEO_ID",
  "images": ["media/neues-projekt/01.jpg", "media/neues-projekt/02.jpg"]
}
```

Die Reihenfolge im Array ist die Reihenfolge auf der Seite. `video` leer lassen,
wenn es ein reines Fotoprojekt ist — dann wird kein Player gerendert.

Wichtig: Die Bilder müssen in `images` aufgelistet werden. Ein Browser kann den
Inhalt eines Ordners nicht selbst auslesen, deshalb führt die JSON-Datei Regie.

## Lokal ansehen

`fetch` funktioniert nicht über `file://`, es braucht einen lokalen Server:

```
npx serve .
```

Dann http://localhost:3000 öffnen.

## Deploy

**Vercel:** Repository verbinden. Kein Framework, kein Build-Command,
Output-Directory ist das Wurzelverzeichnis. `vercel.json` schaltet `cleanUrls`
ein, sodass `/info` statt `/info.html` funktioniert.

**Strato:** Den kompletten Ordnerinhalt per FTP ins Web-Verzeichnis legen
(meist `/`). Es ist kein PHP und keine Datenbank nötig. Bei Strato gibt es kein
`cleanUrls` — die internen Links zeigen deshalb auf die vollen `.html`-Pfade
und funktionieren dort unverändert.

## Projekte aus Ordnern einlesen

`tools/import.py` baut die Inhalte aus einem Ordner mit Projektordnern.
Erwartete Struktur:

```
Projekte/
  Nordsee, 2025, Kurtains, Berlin/
    DSC_0421.jpg
    DSC_0433.jpg
    youtube.txt          (optional, enthaelt nur den Link)
```

Der Ordnername wird an Kommas zerlegt: Projektname, Jahr, Kuenstler, Ort.
Aufruf im Projektverzeichnis:

```
python3 tools/import.py ~/Desktop/Projekte
```

Das Skript verkleinert jedes Bild auf 2200 Pixel laengste Kante, speichert es
als `media/<slug>/01.jpg`, `02.jpg` … und schreibt `data/projects.json` neu.
Die Originale bleiben unangetastet. Beschreibungstexte bleiben leer und werden
danach von Hand in der JSON ergaenzt.

Verkleinert wird mit `sips`, das auf jedem Mac vorhanden ist. Fehlt es, werden
die Bilder unveraendert kopiert — dann sollte man sie vorher selbst verkleinern.

## Software: Dateien zum Herunterladen

Der Menuepunkt Software listet Dateien, die Besucher frei herunterladen
koennen. Zwei Schritte pro Datei:

1. Die Datei per FTP nach `files/` legen. ZIP-Archive sind am
   verlaesslichsten, weil der Browser sie sicher herunterlaedt statt sie
   anzuzeigen.
2. In `data/projects.json` im Abschnitt `downloads` einen Eintrag ergaenzen:

```json
{
  "name": "Kodak 2383 LUT",
  "kind": "LUT",
  "note": "Ein Satz dazu, wofuer das gut ist.",
  "file": "files/kodak-2383.zip"
}
```

Die Dateigroesse steht nicht in der JSON — sie wird beim Laden der Seite
vom Server erfragt. Fehlt eine Datei auf dem Server, bleibt der Eintrag
sichtbar und zeigt „Bald" statt einer Groesse.

### Achtung bei data/projects.json

Diese Datei enthaelt die echte Projektliste. Wer sie mit dem Importskript
neu erzeugt, ueberschreibt sie lokal — dann muss die neue Fassung sowohl
hochgeladen als auch hier eingecheckt werden, damit Repository und Server
nicht auseinanderlaufen.

## Impressum

Die Angaben stehen in `data/projects.json` im Abschnitt `impressum`.
Auszufuellen sind mindestens `strasse` und `ort` — eine ladungsfaehige
Anschrift ist Pflicht, ein Postfach genuegt nicht.

- `firma` nur setzen, wenn die Seite unter einer Firma laeuft
  (z. B. "CultTwenty GbR"). Dann gehoeren auch die Gesellschafter genannt.
- `ustid` eintragen, falls vorhanden. Sonst `kleinunternehmer` auf `true`
  setzen, dann erscheint der Hinweis nach § 19 UStG.
- `telefon` ist freiwillig; eine E-Mail-Adresse reicht als zweiter
  Kontaktweg aus.

## Locations

Der Menuepunkt Locations zeigt Drehorte mit Adresse und Bildern. Aufbau
des Quellordners:

```
Locations/
  Alte Münze/
    adresse.txt        (enthaelt nur die Anschrift, gern mehrzeilig)
    IMG_001.jpg
    IMG_002.jpg
```

Der Ordnername ist der Name des Orts, die Textdatei die Adresse.
Einlesen mit demselben Skript, nur mit Schalter:

```
python3 tools/import.py --locations ~/Desktop/Locations
```

Die Bilder landen in `locations/<slug>/`, der Abschnitt `locations` in
`data/projects.json` wird neu geschrieben. Projekte, Downloads und
Impressum bleiben unberuehrt. Die Adresse verlinkt auf eine Kartensuche
bei OpenStreetMap — ohne eingebettete Karte, damit die Seite keine Daten
an Dritte weitergibt.

## Verwaltung über admin.php

`admin.php` ist ein passwortgeschuetztes Formular, ueber das sich Projekte,
Orte und Software direkt im Browser anlegen lassen. Es laeuft nur auf
Strato — auf Vercel gibt es kein PHP. Die oeffentliche Seite bleibt
unveraendert statisch.

**Einrichten:** `admin.php` hochladen, `deine-domain.de/admin.php` aufrufen
und beim ersten Besuch ein Passwort vergeben. Es landet als Hash in
`admin-config.php`; diese Datei nie ins Repository uebernehmen. Zum
Zuruecksetzen einfach loeschen.

**Was das Formular tut:** Bilder werden auf 2200 Pixel verkleinert, als
JPEG unter `media/<slug>/` bzw. `locations/<slug>/` durchnummeriert und in
`data/projects.json` eingetragen. Archive landen in `files/`. Ein Eintrag
mit gleichem Titel ersetzt den vorhandenen; ohne neue Bilder bleiben die
alten erhalten.

**Sicherheit:** Passwort als Hash, Sitzungs-Token gegen fremde Formulare,
Sperre nach fuenf Fehlversuchen, Pruefung der Dateien anhand ihres Inhalts
statt der Endung, und eine `.htaccess` in jedem Upload-Ordner, die das
Ausfuehren von Code dort unterbindet.

**Achtung:** Was ueber das Formular entsteht, liegt nur auf dem Server.
Wer danach `data/projects.json` aus dem Repository hochlaedt, ueberschreibt
es. Entweder nur noch das Formular nutzen oder die Datei gelegentlich vom
Server ziehen und einchecken.

## Kundengalerien

Im Backend lässt sich pro Auftrag eine Galerie anlegen: Titel, Kunde, ein
Link zu den Originaldateien und die Vorschaubilder. Daraus entsteht eine
Adresse der Form `galerie.php?k=<32 Hexzeichen>`, die nirgends verlinkt und
fuer Suchmaschinen gesperrt ist.

Der Kunde sieht die Vorschauen auf schwarzem Grund, auf Ringen im Raum
angeordnet; beim Scrollen faehrt er durch die Ringe hindurch. Ein Klick
oeffnet die Grossansicht mit Pfeilen und Escape. Der Knopf am unteren Rand
fuehrt ueber `galerie.php?k=…&dl=1` zum Uebertragungslink — die Adresse
steht dadurch nirgends im Quelltext und der Kunde sieht sie nicht.

Bilder lassen sich nachtraeglich anlegen: „Bilder nachlegen" haengt an,
ersetzt also nicht. So kommen auch groessere Auftraege in mehreren
Durchgaengen durch Stratos Zeitlimit.

Die Daten liegen in `data/galerien.json`, die Bilder unter
`kunden/<schluessel>/`. Beides gehoert nicht ins Repository und steht
deshalb in `.gitignore`.
