# Amplifier: a rögzített nettó kézi lezárása

## Cél és határ

A meglévő `wp sharity donations finance-close` HUF/EUR Amplifier támogatása készül. A zárás az eredeti `amplifier-net80-v1` snapshothoz tartozó, ténylegesen regisztrált nettó felosztás operátori jóváhagyása. Csak teljes, aktuális, azonos devizájú mozgáskészlet és pontos NGO/platform-net egyezés engedhető. Alap=floor(összes igazolt nettó/5), NGO=maradék; nincs ismételt díjlevonás. Előbb a ténylegesen szükséges fee-refund és ellenoldala legyen megfigyelve és regisztrálva; a close nem végez providerhívást.

A bankreceipt, fedezet, jövőbeni chargeback-kötelezettség, közösalap-kötelezettség, NGO-kifizetés és jutalomfeloldás külön fennmaradó folyamat. Az allocation/payment/settlement/activation false jelzők és az ismeretlen teljes kötelezettség megmaradnak. TimeWall nem része ennek a csomagnak. Nincs új séma, kapcsoló vagy nyilvántartás.

## Ellenőrzés és kiadás

Érintett forrás: `wp-content/mu-plugins/sharity-ngo-donations/finance-ledger.php`; meglévő teszt: `tests/sharity-donation-finance-target-mysql.test.php`. A Direct történeti receiptje és proposal-digest alakja változatlan. Az order→evidence→movements→target lockrend és az egyetlen meglévő mysqli-tranzakció tulajdonosa megmarad; nincs reconnect/automatikus újraírás.

A célzott tesztkör: pontos HUF/EUR split és kerekítés, hiányos/eltérő/pending/disputed/FX input elutasítása, dry-run írásmentesség, replay egyszeresség, új korrekció után reopened állapot, rollback/COMMIT-visszaolvasás és tényleges kétkapcsolatos konkurencia. A natív futás a meglévő ai-agent financial CI-ben, exact reviewed Impact Shop-commitra kötve történik; nem élő Stripe-/bankelfogadás.

Két egymást követő független tervaudit nem talált igazolt P1/P2 leletet. Induló, változatlan baseline: két PHP-lint és25Direct pure ellenőrzés sikeres. Új Amplifier-implementáció és natív teszt még nincs elfogadva. Commit/push/PR/merge/deploy/aktiválás külön ellenőrzendő; a terv nem ezek teljesítésének bizonyítéka.

Telepítés csak merge-elt főágból, a meglévő `bin/impactshop-guard-deploy.sh` exact-fájl útján: célpont és előállapot ellenőrzése, mentés, CAS, remote SHA és visszaolvasás. Nem indítunk close-ot vagy pénzügyi aktiválást szintetikus teszteredmény alapján. Új Amplifier-manual-close revision előtt kompatibilis régi artifact visszaállítható; utána a régi Direct-only reader inkompatibilis, ezért kapcsolódó írás leállítása és kompatibilis előrejavítás szükséges. Pénzügyi DB-visszatöltés nem rollback.
