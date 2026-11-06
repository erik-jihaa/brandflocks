# Pilotstart A1 — neutrale testposts (geen categorietaal-waarschuwing)

**Commit:** `4090651` (UI) + `85b448d` (A4, geen scoring)  
**Staging:** na deploy van frontend — klantpreview `?hbaKlant=1` op 1280px.

| Merk | Post | slotId | URL (staging) |
| --- | --- | --- | --- |
| Brandpunt Koffie | Ontkalktips | `6c0fe947-9ed6-4096-8c57-82f9f937d432` | `https://staging.igisnext.com/article/6c0fe947-9ed6-4096-8c57-82f9f937d432?hbaKlant=1` |
| Duinroos Thuiszorg | Vacature (werving) | `662a4f49-a661-4caa-ac22-00a0c26792bc` | `https://staging.igisnext.com/article/662a4f49-a661-4caa-ac22-00a0c26792bc?hbaKlant=1` |
| Stelwerk | Webinar AI | `00faf917-4d8c-4b25-b9df-7d1d7f36f921` | `https://staging.igisnext.com/article/00faf917-4d8c-4b25-b9df-7d1d7f36f921?hbaKlant=1` |

**Verwacht (klantpreview):** roze blok **Neutraal** + neutrale zin; **geen** “Dit kan iedere concurrent zo zeggen.” Bij Brandpunt (heeft `category_fallback` in data): optioneel grijs advies “Deze post zegt niets over je merk…”.

Screenshots: `01-brandpunt-ontkalk.png`, `02-duinroos-vacature.png`, `03-stelwerk-webinar.png` (na deploy).

**Replay:** `php scripts/hba-v1.13-tegenkracht-diagnose.php` → 19/19 (na A1 ongewijzigd).
