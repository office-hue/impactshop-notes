# Biztonsági megfontolások

- Privát kulcsot **soha** ne commitolj a repóba; kizárólag GitHub Secrets-ben tárold.
- A `current-task.json` parancsai teljes hozzáférést kapnak a megadott szerveren, ezért csak ellenőrzött feladatokat írj bele.
- A futtatás eredményei (stdout/stderr) a `last-run.json` fájlban publikusak lesznek; kerüld az érzékeny adatokat.
- Timeout alapértelmezés szerint 600 másodperc – szükség esetén emeld, de így megelőzhetők a végtelenül futó parancsok.
- A workflow `StrictHostKeyChecking no` módot használ; ha stabil a host key, érdemes később fix kulcsot konfigurálni.

