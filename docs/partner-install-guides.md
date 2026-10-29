# Partner integrációs minták – Shopify / WooCommerce / UNAS

**A hidak fejlesztési minták, nem igazolt éles pontozási integrációk. A közvetlen partnerlane OFF.** A `partner-prod` technikai tesztazonosító, nem bekötött kereskedő.

- Shopify: az Order Created esemény önmagában nem bizonyít fizetést, végleges adományt vagy SAT1-hozzárendelést.
- WooCommerce: a `tools/partner-bridge-samples/woocommerce-bridge.php` minta bejövő hitelesítése és HMAC-üzenetformája nem tekinthető ellenőrzött production szerződésnek. Ne telepítsd változtatás nélkül.
- UNAS: az export/webhook minta nem igazolja a végleges adományt és korrekciókövetést.

Későbbi valódi integrációnál a [mezőszerződés](partner-data-mapping.md) és a [teljes payloadminta](partner-api-examples.md#5-pontozási-contractminta-szintetikus-lane-off) alapján kell igazolni a partner bejövő eseményeinek hitelességét, adományszabályát, SAT1-kötését, véglegességét, ismétléseit és korrekcióit. A HMAC nyers bodyt ír alá; az `accepted` válasz önmagában nem pontozási elfogadás.

Ebben a munkacsomagban a hidakat nem telepítjük, nem aktiválunk valódi partnert, és nem végzünk éles tesztvásárlást.
