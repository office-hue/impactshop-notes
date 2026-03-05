# Bastion Guard Status

Last updated: 2026-03-05

## Purpose
Ez a fájl a kötelező evidencianapló minden új modulhoz tartozó bástya/guard kiterjesztéshez.

## Kötelező szabály
- Minden új modul (`wp-content/mu-plugins/`, `wp-content/plugins/`, `mu-plugins/`, `scripts/`, `bin/`) esetén ebben a fájlban is frissítést kell rögzíteni ugyanabban a change setben.
- A strict pre-push audit ezt automatikusan ellenőrzi.

## Kiterjesztési napló
| Dátum | Modul | Guard kiterjesztés | Evidencia |
| --- | --- | --- | --- |
| 2026-03-05 | Baseline policy bevezetés | Safe repo audit kötelezővé teszi az új modulok bastion frissítését | `scripts/safe-repo-audit.sh` |
