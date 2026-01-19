# 324. Beszélgetés összefoglaló: Pseudo-ID részletek (Impact Shop + NGO card + social ticker)

Kérés: a célok alapján dolgozzam ki a részletes rendszerszintű leírást, illesztve
az Impact Shop, NGO card és social ticker működéséhez.

- Bővítés: `impact-hub-system-v1.3.md` 4.1 szekció új alfejezetekkel
  (cél/alapelv, kliens tárolás, PIN recovery, attribúció, adatvédelem/UX).
- Fő döntések: 10–12 karakteres base36 pseudo‑ID, cookie‑ban tárolva;
  `/go` automatikus generálás és affiliate átadás (Dognet `d2`, CJ `sid`).
- Owner logika: ledger `pseudo_id` + social ticker `can_share` pseudo‑egyezés.
