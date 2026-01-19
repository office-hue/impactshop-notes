# 216. Beszélgetés összefoglaló: Ruby frissítés + Bundler 2.7.2

## Áttekintés
A cél a Rails tesztekhez szükséges Bundler 2.7.2 telepítése volt úgy, hogy a rendszeres `usr/bin/ruby` érintetlen maradjon. Ehhez fel kellett venni a Homebrew Ruby 3.4.7-et a munkakörnyezet PATH-jába és biztosítani az automatikus betöltést a guard/worker shellekben.

## Megoldás
- Létrehoztam a `~/.impact-secrets/env.d/ruby.env` fájlt, amely a Homebrew Ruby binárisát és a felhasználói gem bin könyvtárat (3.4.0) teszi a PATH elejére, illetve beállítja a szükséges `LDFLAGS/CPPFLAGS` változókat; az `~/.impact-secrets/init.sh` forrásolásakor ez automatikusan betöltődik.
- `source ~/.impact-secrets/init.sh && ruby -v` most `ruby 3.4.7` kimenetet ad, így a guard/worker shell már az új Ruby verziót használja, miközben a rendszer Ruby változatlan.
- A frissített környezetben lefuttattam a `gem install bundler -v 2.7.2` parancsot, ami sikeresen települt, így a Rails projektekhez szükséges bundler verzió elérhető.

## Következő lépések
1. Ha más gépen vagy CI-ben is futnak guardok, másold át a `ruby.env` beállítást (vagy állítsd be ott is a PATH-ot), majd futtasd `source ~/.impact-secrets/init.sh`-t.
2. Igény szerint futtass egy `bundle exec` alapú Rails tesztet, hogy validáld az új Ruby+Bundler páros működését.
