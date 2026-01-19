# 346 – Identity panel: compact blokkból ID/kód eltávolítás

- A felső (compact) blokkból kivettem az azonosító + recovery sorokat, hogy felül ne látszódjanak, alul a fiók kezelésnél megmaradjanak.
- Módosított fájl: `wp-content/mu-plugins/impactshop-identity-panel.php`.
- Deploy: prod + staging rsync, cache flush mindkettőn.
- Megjegyzés: a `notes.md` frissítve az állapot mentéshez.

Nyitott: ha az Elementorban más shortcode van felül, ellenőrizni kell, de kódszinten a compact blokk már nem tartalmaz ID/kód sort.
