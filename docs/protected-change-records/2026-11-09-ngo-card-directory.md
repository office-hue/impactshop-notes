# JYSK NGO Card — védett dokumentáció, 2026-11-09

Protected files touched: csak `docs/bastion-guard-status.md`, saját új állapotszakasz. A runtime additív új modulja a d5ebb56d commitban szerepel. Védett futtatókód, guardlogika, config és hashpolicy nem változik.

Feladatengedély: Arnold a nyolc Card katalógusba kötését és a pontos saját Mini-worktree forrás-/teszt-/dokumentációírását jóváhagyta. A meglévő natív protected-touch dokumentált override-ja csak erre a dokumentációs commitra érvényes; nem tesz lehetővé élő műveletet.

Smoke scope: dokumentációs diff-check és natív secret/protected-touch/commit-lane audit. Az érintett termékforráson korábban PHP-lint és 47 izolált fixture-állítás sikeres; független forrásreview nincs blokkoló P1/P2-vel. A dokumentációhoz nem tartozik runtime smoke-csoport; böngészős vagy telepítési PASS nincs.

Rollback: csak e saját append és a jelen change record visszavonása normál commitban, idegen bejegyzések megőrzésével. Deploy vagy éles adat-visszaállítás nem része ennek.
