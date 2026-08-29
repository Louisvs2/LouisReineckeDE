#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Baut aus einem Ordner mit Projektordnern die Website-Inhalte.

Erwartete Struktur der Quelle:

    Projekte/
      Nordsee, 2025, Kurtains, Berlin/
        DSC_0421.jpg
        DSC_0433.jpg
        youtube.txt          (optional, enthaelt den Link)

Der Ordnername wird an Kommas zerlegt: Projektname, Jahr, Kuenstler, Ort.
Fehlende Angaben sind erlaubt, dann bleiben die Felder leer.

Aufruf auf dem Mac:

    python3 tools/import.py ~/Desktop/Projekte

Ergebnis: media/<slug>/01.jpg ... und eine neue data/projects.json.
Die Originale werden nicht veraendert.
"""

import json
import os
import unicodedata
import re
import shutil
import subprocess
import sys
from pathlib import Path

# Laengste Kante in Pixeln. Reicht fuer Vollbild auf Retina-Displays.
MAX_EDGE = 2200
JPEG_QUALITY = 78
BILD_ENDUNGEN = {".jpg", ".jpeg", ".png", ".heic", ".tif", ".tiff", ".webp"}

UMLAUTE = {"ä": "ae", "ö": "oe", "ü": "ue", "ß": "ss", "é": "e", "è": "e", "à": "a"}


def slugify(text):
    # macOS speichert Umlaute zerlegt (u + Trema). Ohne diese Zusammenfuehrung
    # wuerde aus DUENEN ein "du-nen" statt "duenen".
    text = unicodedata.normalize("NFC", text).lower().strip()
    for k, v in UMLAUTE.items():
        text = text.replace(k, v)
    text = re.sub(r"[^a-z0-9]+", "-", text)
    return text.strip("-") or "projekt"


def ordner_zerlegen(name):
    """Zerlegt 'BUSY, 2026, JIVI, DUISBURG' in seine Felder.

    Die Reihenfolge der Felder schwankt in der Praxis, deshalb wird das
    Jahr an seiner vierstelligen Form erkannt statt an seiner Position.
    Von den uebrigen Feldern ist das erste der Titel, das letzte der Ort
    und ein verbleibendes mittleres der Kuenstler.
    """
    teile = [t.strip() for t in name.split(",") if t.strip()]

    jahr = ""
    rest = []
    for t in teile:
        if not jahr and re.fullmatch(r"(19|20)\d{2}", t):
            jahr = t
        else:
            rest.append(t)

    # Jahr ohne eigenes Feld, etwa 'Lauf 2023'
    if not jahr and rest:
        m = re.search(r"\b((19|20)\d{2})\b", rest[0])
        if m:
            jahr = m.group(1)
            rest[0] = rest[0].replace(jahr, "").strip(" -,")

    titel = rest[0] if rest else name
    ort = rest[-1] if len(rest) >= 2 else ""
    kuenstler = rest[1] if len(rest) >= 3 else ""

    return titel, jahr, kuenstler, ort


def youtube_embed(url):
    """Macht aus jeder gaengigen YouTube-Adresse eine Einbett-Adresse."""
    url = url.strip()
    if not url:
        return ""
    m = re.search(r"(?:v=|youtu\.be/|/embed/|/shorts/)([A-Za-z0-9_-]{6,})", url)
    if m:
        return "https://www.youtube.com/embed/" + m.group(1)
    m = re.search(r"vimeo\.com/(?:video/)?(\d+)", url)
    if m:
        return "https://player.vimeo.com/video/" + m.group(1)
    print(f"    ! Link nicht erkannt, wird uebernommen: {url}")
    return url


def hat_sips():
    return shutil.which("sips") is not None


def bild_verkleinern(quelle, ziel):
    """Verkleinert mit sips (auf jedem Mac vorhanden). Kopiert notfalls nur."""
    if not hat_sips():
        shutil.copy2(quelle, ziel)
        return False
    try:
        subprocess.run(
            ["sips", "-s", "format", "jpeg",
             "-s", "formatOptions", str(JPEG_QUALITY),
             "-Z", str(MAX_EDGE),
             str(quelle), "--out", str(ziel)],
            check=True, capture_output=True,
        )
        return True
    except subprocess.CalledProcessError as e:
        print(f"    ! konnte {quelle.name} nicht umwandeln: {e.stderr.decode()[:120]}")
        shutil.copy2(quelle, ziel)
        return False


def main():
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(1)

    quelle = Path(sys.argv[1]).expanduser()
    if not quelle.is_dir():
        print(f"Ordner nicht gefunden: {quelle}")
        sys.exit(1)

    wurzel = Path(__file__).resolve().parent.parent
    media = wurzel / "media"
    ziel_json = wurzel / "data" / "projects.json"

    # Bestehende Kopf-Angaben uebernehmen, damit Name und Kontakt erhalten bleiben.
    if ziel_json.exists():
        site = json.loads(ziel_json.read_text(encoding="utf-8"))["site"]
    else:
        site = {"name": "LOUIS REINECKE", "role": "Video & Fotografie",
                "location": "Berlin", "email": "", "intro": "", "links": []}

    ordner = sorted([p for p in quelle.iterdir()
                     if p.is_dir()
                     and not p.name.startswith(".")
                     and p.name != "__MACOSX"])
    if not ordner:
        print(f"Keine Projektordner in {quelle} gefunden.")
        sys.exit(1)

    if not hat_sips():
        print("Hinweis: sips nicht gefunden — Bilder werden unveraendert kopiert.\n")

    projekte = []
    for p in ordner:
        titel, jahr, kuenstler, ort = ordner_zerlegen(p.name)
        slug = slugify(titel)
        print(f"» {titel}  |  {kuenstler or '—'}  |  {jahr or 'ohne Jahr'}  |  {ort or '—'}")

        # YouTube-Link aus der Textdatei lesen, falls vorhanden
        video = ""
        for txt in p.glob("*.txt"):
            inhalt = txt.read_text(encoding="utf-8", errors="ignore").strip()
            if inhalt:
                video = youtube_embed(inhalt.splitlines()[0])
                print(f"    Video: {video}")
            break

        bilder = sorted([f for f in p.iterdir()
                         if f.is_file() and f.suffix.lower() in BILD_ENDUNGEN
                         and not f.name.startswith(".")])

        # Ordner ohne Bilder sind keine Projekte, etwa Systemordner.
        if not bilder and not video:
            print("    uebersprungen: keine Bilder gefunden")
            continue

        zielordner = media / slug
        zielordner.mkdir(parents=True, exist_ok=True)
        for alt in zielordner.glob("*.jpg"):
            alt.unlink()

        pfade = []
        for i, bild in enumerate(bilder, start=1):
            name = f"{i:02d}.jpg"
            bild_verkleinern(bild, zielordner / name)
            pfade.append(f"media/{slug}/{name}")
        print(f"    {len(pfade)} Bilder")

        projekte.append({
            "slug": slug,
            "title": titel,
            "client": kuenstler,
            "year": jahr,
            "type": "Video" if video else "Fotografie",
            "tags": [t for t in [ort] if t],
            "excerpt": "",
            "text": "",
            "cover": pfade[0] if pfade else "",
            "video": video,
            "images": pfade,
        })

    # Neueste zuerst
    projekte.sort(key=lambda x: x["year"], reverse=True)

    ziel_json.parent.mkdir(parents=True, exist_ok=True)
    ziel_json.write_text(
        json.dumps({"site": site, "projects": projekte}, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8")

    groesse = sum(f.stat().st_size for f in media.rglob("*.jpg")) / 1_000_000
    print(f"\nFertig: {len(projekte)} Projekte, {groesse:.0f} MB in media/")
    print("Beschreibungen stehen noch leer — die traegst du in data/projects.json nach.")


if __name__ == "__main__":
    main()
