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
