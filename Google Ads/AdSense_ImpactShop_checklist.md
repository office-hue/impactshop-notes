# AdSense × Impact Shop – Beállítási checklist (1 oldal)

**Cél:** minimál, szabálytiszta hirdetés-bevétel → **Központi Adományalap**, majd elosztás az **NGO-kártyákról érkező forgalom** arányában.

---

## 0) Előkészületek
- Google-fiókhoz hozzáférés (AdSense, GA4).
- WordPress admin hozzáférés.
- Domain-gyökér elérés az `ads.txt`-hez.

## 1) Site Kit ↔ AdSense összekapcsolás
- Telepítsd és aktiváld: *Site Kit by Google* (WP bővítmény).
- Site Kit varázsló: jelentkezz be, válaszd az **AdSense** modult → engedélyezés.
- Ha új az AdSense, add hozzá a domaint, indítsd el a webhely-ellenőrzést.

## 2) Auto ads bekapcsolása (induló profil)
- Kapcsold be az **Auto ads**-t „konzervatív” sűrűséggel.
- Első körben csak az **/impactshop** és a kapcsolódó cikkoldalakon jelenjen meg.
- Később: finomhangolás (in‑page, in‑article, anchor/vignette formátumok).

## 3) `ads.txt` a domain gyökerében
- Hozz létre/egészíts ki `ads.txt`‑t az AdSense által jelzett sorokkal.
- Ellenőrizd: `https://SAJAT-DOMAIN.hu/ads.txt` nyilvánosan elérhető.

## 4) EU-megfelelés: tanúsított CMP + Consent Mode v2
- Kapcsold be a **Google‑tanúsított CMP** megoldást (IAB TCF támogatással).
- Consent Mode v2 jelek: `ad_storage`, `ad_user_data`, `ad_personalization` (és Analytics).
- Teszt: Tag Assistant / GA4 DebugView – megvannak a hozzájárulási jelek.

## 5) Brand safety / blokkolások
- AdSense **Blocking controls**: érzékeny kategóriák tiltása; szükség szerint hirdető‑URL blokkolás.
- Jegyezd fel a tiltásokat (log), később felülvizsgálható.

## 6) Elhelyezési szabályok (policy-proof)
- NINCS ösztönzés: nem írjuk ki, hogy „kattints” / „nézd meg a reklámot és adományozol”.
- Nem tesszük a hirdetést félrevezető helyre (gombok/letöltés/CTA közvetlen közelébe).
- Nem manipuláljuk a véletlen kattintást (nincs trükk, nincs popup‑kényszer).

## 7) GA4 mérés és attribúció
- GA4 ↔ AdSense **összelinkelés** (Publisher ads riportok).
- GA4 egyedi dimenziók: `ngo_code`, `embed_host` (UTM/d1 alapján).
- Havi export: Sessions (vagy Engaged Sessions) **ngo_code** szerint → elosztási táblába.

## 8) Indítás előtti gyors QA
- Hirdetés tényleg megjelenik az `/impactshop` oldalakon (privát ablak, engedélyezett cookie-k).
- `ads.txt` elérhető; CMP banner megjelenik EEA/UK/CH IP‑knél; Tag Assistant zöld.
- Oldalsebesség és CLS rendben (nincs „ugráló” elrendezés).

## 9) Kommunikáció (minta)
> *„Hirdetések jelennek meg. A bevétel meghatározott részét havi rendszerességgel a Sharity Központi Adományalapjába tesszük, és az NGO‑kártyákról érkező forgalom arányában osztjuk szét.”*

## 10) Havi rituálé
- AdSense havi bevétel × **donation_share** (pl. 80%) = **Adományalap**.
- GA4 export `ngo_code` szerint → **elosztási CSV** (lásd melléklet).
- Kifizetés és nyilvános összefoglaló (átláthatósági oldal).

*Változásnapló:* generálva: **2025-12-01**.