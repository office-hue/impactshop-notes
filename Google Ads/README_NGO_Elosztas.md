# NGO elosztási sablon – képlet és lépések

**Bemenet:** GA4 export `Sessions` (vagy `Engaged sessions`) **ngo_code** szerint az adott hónapra.  
**Alap‑képlet:**

- **Adományalap (EUR)** = AdSense havi bevétel × **donation_share** (pl. 0.80)
- **weight_quality**: indulásnak **1.0** (egyszerű osztás). Később lehet pl. `engaged_sessions / sessions`, ha minőséget is szeretnél súlyozni.
- **weighted_sessions** = `sessions × weight_quality`
- **share_percent** = `weighted_sessions / Σ(weighted_sessions)`
- **payout_eur** = `Adományalap × share_percent`

## Munkafolyamat (hónapzárás)
1. GA4 → Lekérdezés: Sessions by `ngo_code` (időablak: adott hónap).
2. Export → illeszd a CSV sablon oszlopaiba (`month`, `ngo_code`, `ngo_name`, `sessions`, opcionális minőségi mezők).
3. Számold ki a `weighted_sessions`, `share_percent`, `payout_eur` értékeket.
4. Kerekítés: javaslat **0,01 EUR** pontosság, min. összeg: **5 EUR** (alatta gyűjtő számlán maradhat).
5. Archiválás: mappa/hónap/`NGO_Elosztas_2025-12.csv` + összefoglaló.

## Megjegyzés
Ez az elosztás **AdSense rev‑share nélkül**, átlátható **adományalapon** történik.