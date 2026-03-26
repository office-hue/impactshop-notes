# Hozzáférés-kezelési Mátrix (Access Control Matrix)

> **Verzió**: 1.1  
> **Dátum**: 2026-02-25  
> **Státusz**: HATÁLYOS  
> **Vonatkozik**: ImpactShop teljes infrastruktúra  
> **Kapcsolódó**: GDPR adatfeldolgozói nyilvántartás, Üzemeltetési kézikönyv §1

---

## 1. Áttekintés

Ez a dokumentum rögzíti, hogy ki milyen rendszerekhez fér hozzá, milyen jogosultsági szinttel, és milyen korlátozásokkal. A hozzáférés a **legkisebb jogosultság elve** (principle of least privilege) alapján van kiosztva.

---

## 2. Infrastruktúra hozzáférési mátrix

### 2.1 Szerver (shared hosting)

| Hozzáférés | Felhasználó | Jogosultság | Korlátozás |
|-----------|-------------|-------------|-----------|
| **SSH** | `sharityh` | User-level shell (zsh) | ⛔ Nincs root / sudo / WHM hozzáférés |
| **cPanel** | `sharityh` | Fájlkezelő, cron, PHP konfig, email | Shared hosting korlátok |
| **FTP/SFTP** | `sharityh` | Fájl feltöltés/letöltés | Csak a `~/` alatti könyvtárakra |
| **MySQL** | `sharityh_wp` | WordPress DB teljes hozzáférés | Nincs más DB-hez hozzáférés |
| **Cron** | `sharityh` | User-level crontab | Nincs system-level cron |

> **Fontos**: Nincs root hozzáférés. Bármilyen host-szintű változtatás (PHP verzió, Apache mod, SSL cert) hosting ticket-en keresztül igénylendő.

### 2.2 WordPress (app.sharity.hu / shariteam.com)

| Szerepkör | Ki | Jogosultság | Megjegyzés |
|-----------|-----|-------------|-----------|
| **Super Admin** | Sharity core team (max 2 fő) | Teljes WP admin, MU-plugin kezelés, WP-CLI | Multisite network admin |
| **Admin** | Ops Squad (max 3 fő) | Beállítások, felhasználókezelés, tartalom | NGO card jóváhagyás, shortcode szerkesztés |
| **Editor** | Tartalom menedzser | Oldalak/bejegyzések szerkesztése | Nincs plugin/beállítás hozzáférés |
| **NGO Partner** | Regisztrált NGO-k | Saját profil szerkesztése | Nincs admin terület hozzáférés |
| **WP-CLI** | `sharityh` SSH-n keresztül | Teljes WP-CLI parancssor | Szerveren, SSH session-ben |

### 2.3 Stripe Dashboard

| Szerepkör | Ki | Jogosultság | Megjegyzés |
|-----------|-----|-------------|-----------|
| **Account Owner** | Sharity pénzügyi felelős (1 fő) | Teljes kontroll: payout, settings, API keys | 2FA kötelező |
| **Developer** | Sharity fejlesztő (max 2 fő) | Webhook konfig, API logok, test mód | Nincs payout/bank hozzáférés |
| **View Only** | Ops Squad | Tranzakció megtekintés, dispute olvasás | Nincs módosítási jog |

### 2.4 GitHub Repository

| Szerepkör | Ki | Jogosultság | Megjegyzés |
|-----------|-----|-------------|-----------|
| **Owner** | Sharity core team (1 fő) | Repo beállítások, branch protection, secrets | Private repo |
| **Maintainer** | Fejlesztők (max 2 fő) | Push, merge, PR review | Branch protection: main → PR required |
| **Read** | Ops Squad | Clone, issue kezelés | Nincs push jog |

### 2.5 Cloudflare

| Szerepkör | Ki | Jogosultság | Megjegyzés |
|-----------|-----|-------------|-----------|
| **Super Admin** | Sharity core team (1 fő) | DNS, WAF, Page Rules, Rate Limit | 2FA kötelező |
| **Admin** | Fejlesztő (1 fő) | WAF szabályok, Turnstile konfig, cache purge | Nincs billing hozzáférés |

### 2.6 Dognet Partner Portal

| Szerepkör | Ki | Jogosultság | Megjegyzés |
|-----------|-----|-------------|-----------|
| **Partner Admin** | Sharity affiliate menedzser (1 fő) | Kampány kezelés, riport letöltés, API token | |
| **Viewer** | Ops Squad | Riportok megtekintése | Nincs módosítás |

### 2.7 CJ (Commission Junction)

| Szerepkör | Ki | Jogosultság | Megjegyzés |
|-----------|-----|-------------|------------|
| **Publisher Admin** | Sharity affiliate menedzser (1 fő) | Kampánykezelés, link generálás, riportok, API hozzáférés | Publicis Groupe platform; 2FA ajánlott |
| **Viewer** | Ops Squad | Riportok megtekintése | Nincs módosítás |

### 2.8 TradeTracker

| Szerepkör | Ki | Jogosultság | Megjegyzés |
|-----------|-----|-------------|------------|
| **Publisher Admin** | Sharity affiliate menedzser (1 fő) | Kampánykezelés, link generálás, riportok | |
| **Viewer** | Ops Squad | Riportok megtekintése | Nincs módosítás |

### 2.9 Tradedoubler

| Szerepkör | Ki | Jogosultság | Megjegyzés |
|-----------|-----|-------------|------------|
| **Publisher Admin** | Sharity affiliate menedzser (1 fő) | Kampánykezelés, link generálás, riportok | |
| **Viewer** | Ops Squad | Riportok megtekintése | Nincs módosítás |

### 2.10 Offerwall szolgáltatók (Bitlabs, Pollfish, Cint, ayeT-Studios, CPX Research)

| Szerepkör | Ki | Jogosultság | Megjegyzés |
|-----------|-----|-------------|------------|
| **Dashboard Admin** | Sharity fejlesztő / ops (1 fő) | Offerwall konfiguráció, bevétel riportok, API kulcsok | Szolgáltatónként külön fiók; 2FA ahol elérhető |
| **Viewer** | Ops Squad | Riportok megtekintése | Nincs módosítás |
---

## 3. Titkos kulcsok kezelése (Secret Management)

| Titok típusa | Tárolás helye | Ki fér hozzá | Rotálás gyakorisága |
|-------------|--------------|--------------|-------------------|
| **Stripe API keys** (sk_live, pk_live) | Környezeti változókban (gitignore-olt) | SSH: `sharityh`; Fejlesztők: env file-on keresztül | Félévente vagy kompromittálás esetén |
| **Stripe webhook secret** | Környezeti változókban (gitignore-olt) | SSH: `sharityh` | Félévente |
| **Dognet API token** | Környezeti változókban / WP options | SSH: `sharityh`; WP Admin: options | Éves |
| **Cloudflare API token** | Környezeti változókban | SSH: `sharityh` | Éves |
| **WordPress salts** | `wp-config.php` | SSH: `sharityh` | Csak kompromittálás esetén |
| **MySQL password** | `wp-config.php` | SSH: `sharityh` | cPanel-en keresztül, szükség esetén |
| **Google Service Account key** | Dedikált kulcsfájlban (gitignore-olt) | SSH: `sharityh`; AI Agent alkalmazás | Éves |
| **CJ API credentials** | Környezeti változókban (gitignore-olt) | SSH: `sharityh`; Fejlesztők: env file-on keresztül | Éves |
| **TradeTracker API credentials** | Környezeti változókban (gitignore-olt) | SSH: `sharityh` | Éves |
| **Tradedoubler API credentials** | Környezeti változókban (gitignore-olt) | SSH: `sharityh` | Éves |
| **Offerwall API kulcsok** (Bitlabs, Pollfish, Cint, ayeT-Studios, CPX Research) | Környezeti változókban (gitignore-olt) | SSH: `sharityh`; Fejlesztők: env file-on keresztül | Éves |
| **SSH key** | `~/.ssh/authorized_keys` | Fejlesztő gépek | Éves rotálás, ed25519 |

> **Szabály**: Titkok SOHA nem kerülhetnek git commitba, logba, vagy tervdokumentumba.

---

## Kapcsolódó dokumentumok és guide-ok

- [Általános Szerződési Feltételek (ÁSZF)](../ÁSZF/Sharity_ASZF_2026.md)
- [GDPR adatfeldolgozók](./gdpr-data-processors.md)
- [SLA](./sla-policy.md)
- [Stripe felelősségmegosztás](./stripe-responsibility-matrix.md)
- [Rólunk](https://app.sharity.hu/rolunk/)


---

## 4. Guard rendszer hozzáférés

| Guard | Futtatás | Ki indíthatja | Naplók |
|-------|---------|--------------|--------|
| **Központi guard runner** | Cron (15-30 perc) + manuális | SSH: `sharityh` | Központi guard eseménynapló |
| **Egyedi guard scriptek** | Cron / manuális | SSH: `sharityh` | Guard-specifikus naplók |
| **Discord webhook** (alertek) | Automatikus guard kimenete | Guard scriptek | Dedikált alert csatorna |

---

## 5. Hozzáférés kérés és visszavonás

### 5.1 Új hozzáférés kérés

1. **Kérelmező** → email/issue a core team felé
2. **Core team jóváhagyás** — principle of least privilege alapján
3. **Implementáció** — az adott rendszer adminje hozza létre
4. **Dokumentáció** — ez a mátrix frissítendő
5. **2FA kötelező** minden kritikus rendszernél (Stripe, Cloudflare, GitHub, cPanel)

### 5.2 Hozzáférés visszavonás

| Trigger | Teendő | Időkeret |
|---------|--------|---------|
| **Munkatárs távozás** | Minden rendszerből hozzáférés eltávolítása | ≤ 24 óra |
| **Kompromittált credential** | Azonnali rotálás + audit log ellenőrzés | ≤ 1 óra |
| **Szerepkör változás** | Jogosultság szűkítés/bővítés | ≤ 48 óra |

### 5.3 Audit

- **Gyakoriság**: negyedévente
- **Mit ellenőrzünk**: aktív hozzáférések listája vs. aktuális csapattagok, 2FA státusz, utolsó bejelentkezés
- **Felelős**: Ops Squad lead

---

## 6. Hosting-specifikus korlátozások

A shared hosting környezet miatt az alábbi korlátozások érvényesek:

| Nem elérhető | Alternatíva |
|-------------|-------------|
| Root / sudo | Hosting ticket |
| Custom daemon / service | Cron-alapú idempotens scriptek |
| Package install (apt/yum) | Nincs — csak a meglévő szoftverek |
| PHP verzió váltás | cPanel PHP Selector vagy hosting ticket |
| SSL cert kezelés | AutoSSL (cPanel) vagy Cloudflare |
| Firewall szabályok | Cloudflare WAF |

---

## 7. Felülvizsgálat

- **Gyakoriság**: negyedévente, vagy csapatváltozás esetén azonnal
- **Felelős**: Core team lead
- **Ellenőrzőlista**: aktív felhasználók, 2FA státusz, kulcs rotálás, lejárt hozzáférések

---

## Változásnapló

| Verzió | Dátum | Változás |
|--------|-------|---------|
| 1.0 | 2026-02-25 | Kezdeti verzió — teljes infrastruktúra mátrix |
| 1.1 | 2026-02-25 | Véglegesítés: CJ, TradeTracker, Tradedoubler, offerwall szolgáltatók (Bitlabs, Pollfish, Cint, ayeT-Studios, CPX Research) hozzáadva (§2.7–2.10), belső fájlútvonalak eltávolítva, titkos kulcs kezelés kiterjesztve affiliate/offerwall kulcsokra (§3) |
