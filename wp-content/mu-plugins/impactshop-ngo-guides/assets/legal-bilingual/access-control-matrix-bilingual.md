<!-- 🌐 Nyelv / Language: [Magyar](#hu) | [English](#en) -->

<div id="hu">

# 🇭🇺 MAGYAR VERZIÓ

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

</div>

---

<div id="en">

# 🇬🇧 ENGLISH VERSION

# Access Control Matrix

> **Version**: 1.1  
> **Date**: 2026-02-25  
> **Status**: IN EFFECT  
> **Applies to**: ImpactShop full infrastructure  
> **Related**: GDPR data processor registry, Operations handbook §1

---

## 1. Overview

This document records who has access to which systems, at what privilege level, and with what restrictions. Access is assigned based on the **principle of least privilege**.

---

## 2. Infrastructure Access Control Matrix

### 2.1 Server (shared hosting)

| Access | User | Privilege | Restriction |
|--------|------|-----------|-------------|
| **SSH** | `sharityh` | User-level shell (zsh) | ⛔ No root / sudo / WHM access |
| **cPanel** | `sharityh` | File manager, cron, PHP config, email | Shared hosting limits |
| **FTP/SFTP** | `sharityh` | File upload/download | Only directories under `~/` |
| **MySQL** | `sharityh_wp` | Full access to WordPress DB | No access to other databases |
| **Cron** | `sharityh` | User-level crontab | No system-level cron |

> **Important**: No root access. Any host-level changes (PHP version, Apache modules, SSL certificates) must be requested via a hosting ticket.

### 2.2 WordPress (app.sharity.hu / shariteam.com)

| Role | Who | Privileges | Notes |
|------|-----|-----------|-------|
| **Super Admin** | Sharity core team (max 2 people) | Full WP admin, MU-plugin management, WP-CLI | Multisite network admin |
| **Admin** | Ops Squad (max 3 people) | Settings, user management, content | NGO card approval, shortcode editing |
| **Editor** | Content manager | Page/post editing | No plugin/settings access |
| **NGO Partner** | Registered NGOs | Own profile editing | No admin area access |
| **WP-CLI** | `sharityh` via SSH | Full WP-CLI command line | On server, in SSH session |

### 2.3 Stripe Dashboard

| Role | Who | Privileges | Notes |
|------|-----|-----------|-------|
| **Account Owner** | Sharity finance officer (1 person) | Full control: payout, settings, API keys | 2FA mandatory |
| **Developer** | Sharity developer (max 2 people) | Webhook config, API logs, test mode | No payout/bank access |
| **View Only** | Ops Squad | Transaction viewing, dispute reading | No modification rights |

### 2.4 GitHub Repository

| Role | Who | Privileges | Notes |
|------|-----|-----------|-------|
| **Owner** | Sharity core team (1 person) | Repo settings, branch protection, secrets | Private repo |
| **Maintainer** | Developers (max 2 people) | Push, merge, PR review | Branch protection: main → PR required |
| **Read** | Ops Squad | Clone, issue management | No push rights |

### 2.5 Cloudflare

| Role | Who | Privileges | Notes |
|------|-----|-----------|-------|
| **Super Admin** | Sharity core team (1 person) | DNS, WAF, Page Rules, Rate Limit | 2FA mandatory |
| **Admin** | Developer (1 person) | WAF rules, Turnstile config, cache purge | No billing access |

### 2.6 Dognet Partner Portal

| Role | Who | Privileges | Notes |
|------|-----|-----------|-------|
| **Partner Admin** | Sharity affiliate manager (1 person) | Campaign management, report download, API token | |
| **Viewer** | Ops Squad | View reports | No modifications |

### 2.7 CJ (Commission Junction)

| Role | Who | Privileges | Notes |
|------|-----|-----------|-------|
| **Publisher Admin** | Sharity affiliate manager (1 person) | Campaign management, link generation, reports, API access | Publicis Groupe platform; 2FA recommended |
| **Viewer** | Ops Squad | View reports | No modifications |

### 2.8 TradeTracker

| Role | Who | Privileges | Notes |
|------|-----|-----------|-------|
| **Publisher Admin** | Sharity affiliate manager (1 person) | Campaign management, link generation, reports | |
| **Viewer** | Ops Squad | View reports | No modifications |

### 2.9 Tradedoubler

| Role | Who | Privileges | Notes |
|------|-----|-----------|-------|
| **Publisher Admin** | Sharity affiliate manager (1 person) | Campaign management, link generation, reports | |
| **Viewer** | Ops Squad | View reports | No modifications |

### 2.10 Offerwall Providers (Bitlabs, Pollfish, Cint, ayeT-Studios, CPX Research)

| Role | Who | Privileges | Notes |
|------|-----|-----------|-------|
| **Dashboard Admin** | Sharity developer / ops (1 person) | Offerwall configuration, revenue reports, API keys | Separate account per provider; 2FA where available |
| **Viewer** | Ops Squad | View reports | No modifications |
---

## 3. Secret Management

| Secret type | Storage location | Who has access | Rotation frequency |
|-------------|-----------------|----------------|-------------------|
| **Stripe API keys** (sk_live, pk_live) | Environment variables (gitignored) | SSH: `sharityh`; Developers: via env file | Semi-annually or upon compromise |
| **Stripe webhook secret** | Environment variables (gitignored) | SSH: `sharityh` | Semi-annually |
| **Dognet API token** | Environment variables / WP options | SSH: `sharityh`; WP Admin: options | Annually |
| **Cloudflare API token** | Environment variables | SSH: `sharityh` | Annually |
| **WordPress salts** | `wp-config.php` | SSH: `sharityh` | Only upon compromise |
| **MySQL password** | `wp-config.php` | SSH: `sharityh` | Via cPanel, as needed |
| **Google Service Account key** | Dedicated key file (gitignored) | SSH: `sharityh`; AI Agent application | Annually |
| **CJ API credentials** | Environment variables (gitignored) | SSH: `sharityh`; Developers: via env file | Annually |
| **TradeTracker API credentials** | Environment variables (gitignored) | SSH: `sharityh` | Annually |
| **Tradedoubler API credentials** | Environment variables (gitignored) | SSH: `sharityh` | Annually |
| **Offerwall API keys** (Bitlabs, Pollfish, Cint, ayeT-Studios, CPX Research) | Environment variables (gitignored) | SSH: `sharityh`; Developers: via env file | Annually |
| **SSH key** | `~/.ssh/authorized_keys` | Developer machines | Annual rotation, ed25519 |

> **Rule**: Secrets must NEVER appear in git commits, logs, or planning documents.

---

## Related Documents and Guides

- [Terms of Service (ÁSZF)](../ÁSZF/Sharity_ASZF_2026.md)
- [GDPR Data Processors](./gdpr-data-processors.md)
- [SLA](./sla-policy.md)
- [Stripe Responsibility Matrix](./stripe-responsibility-matrix.md)
- [About Us](https://app.sharity.hu/rolunk/)


---

## 4. Guard System Access

| Guard | Execution | Who can trigger | Logs |
|-------|-----------|----------------|------|
| **Central guard runner** | Cron (15–30 min) + manual | SSH: `sharityh` | Central guard event log |
| **Individual guard scripts** | Cron / manual | SSH: `sharityh` | Guard-specific logs |
| **Discord webhook** (alerts) | Automatic guard output | Guard scripts | Dedicated alert channel |

---

## 5. Access Request and Revocation

### 5.1 New Access Request

1. **Requester** → email/issue to the core team
2. **Core team approval** — based on principle of least privilege
3. **Implementation** — created by the respective system admin
4. **Documentation** — this matrix must be updated
5. **2FA mandatory** for all critical systems (Stripe, Cloudflare, GitHub, cPanel)

### 5.2 Access Revocation

| Trigger | Action | Timeframe |
|---------|--------|-----------|
| **Team member departure** | Remove access from all systems | ≤ 24 hours |
| **Compromised credential** | Immediate rotation + audit log review | ≤ 1 hour |
| **Role change** | Privilege reduction/expansion | ≤ 48 hours |

### 5.3 Audit

- **Frequency**: quarterly
- **What we check**: active access list vs. current team members, 2FA status, last login
- **Responsible**: Ops Squad lead

---

## 6. Hosting-Specific Restrictions

Due to the shared hosting environment, the following restrictions apply:

| Not available | Alternative |
|--------------|-------------|
| Root / sudo | Hosting ticket |
| Custom daemon / service | Cron-based idempotent scripts |
| Package install (apt/yum) | None — only pre-installed software |
| PHP version change | cPanel PHP Selector or hosting ticket |
| SSL cert management | AutoSSL (cPanel) or Cloudflare |
| Firewall rules | Cloudflare WAF |

---

## 7. Review

- **Frequency**: quarterly, or immediately upon team changes
- **Responsible**: Core team lead
- **Checklist**: active users, 2FA status, key rotation, expired access

---

## Changelog

| Version | Date | Change |
|---------|------|--------|
| 1.0 | 2026-02-25 | Initial version — full infrastructure matrix |
| 1.1 | 2026-02-25 | Finalization: CJ, TradeTracker, Tradedoubler, offerwall providers (Bitlabs, Pollfish, Cint, ayeT-Studios, CPX Research) added (§2.7–2.10), internal file paths removed, secret management extended to affiliate/offerwall keys (§3) |

</div>
