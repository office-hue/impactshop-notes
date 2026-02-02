# Partner bridge minták (Shopify / WooCommerce / UNAS)

Ez a mappa **minta bridge** fájlokat tartalmaz a non‑affiliate partner webhookok továbbításához az Impact Shop partner API felé.

## Tartalom
- `shopify-bridge-worker.js` – Cloudflare Worker mintakód
- `woocommerce-bridge.php` – WooCommerce plugin snippet (webhook forward)
- `unas-bridge-node.js` – UNAS export → Impact Shop forward (Node)
- `runner.js` – lokális dry‑run aláíró + mintaküldés
- `.env.sample` – környezeti változók mintája
- `package.json` – futtatás a runnerhez

## Gyors start (dry‑run)
```bash
node tools/partner-bridge-samples/runner.js --dry-run
```

## Éles teszt (ha endpoint elérhető)
```bash
node tools/partner-bridge-samples/runner.js --send
```

## Megjegyzés
- A `runner.js` **nem** igényel külső csomagokat.
- HMAC aláírás a `partner-webhook-sla.md` szerint történik.
