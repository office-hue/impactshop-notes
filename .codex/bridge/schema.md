# Bridge JSON séma

- **current-task.json** (ChatGPT/Architect tölti ki)

```json
{
  "goal": "szöveges leírás",
  "host": "cp40.ezit.hu",
  "user": "sharityh",
  "workdir": "/home/sharityh",                 // opcionális
  "actions": [
    "wp --allow-root --path=/home/sharityh/app-staging option update home https://app.sharity.hu/impactshop-staging",
    "wp --allow-root --path=/home/sharityh/app-staging option update siteurl https://app.sharity.hu/impactshop-staging",
    "wp --allow-root --path=/home/sharityh/app-staging rewrite flush --hard",
    "curl -ILs https://app.sharity.hu/impactshop-staging/ | sed -n '1,10p'",
    "curl -ILs https://app.sharity.hu/impactshop-staging/wp-json/impact/v1/total | sed -n '1,10p'"
  ],
  "expected": "All REST endpoints 200",
  "timeout_sec": 600
}
```

- **last-run.json** (Codex/Executor írja vissza)

```json
{
  "goal": "...",
  "started_at": "ISO_DATETIME",
  "finished_at": "ISO_DATETIME",
  "status": "OK|ERROR",
  "results": [
    {"cmd": "…", "exit": 0, "stdout": "…", "stderr": ""}
  ]
}
```

