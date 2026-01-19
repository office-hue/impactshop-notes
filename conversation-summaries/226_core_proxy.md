# Core AI proxy setup summary

- A Core Console lokális eléréséhez beállítottam a `core-ai.sharity.hu` hostot: hosts bejegyzés (`127.0.0.1 core-ai.sharity.hu`) és a Homebrew nginx szerverblokk (`/opt/homebrew/etc/nginx/servers/core-ai.conf`) a 4000-es port felé proxiz.
- Az `nginx.conf`-ban `pid /opt/homebrew/var/run/nginx.pid;` szerepel, reload: `brew services restart nginx` vagy `sudo nginx -s reload`.
- Lefoglalva script: `~/Documents/GitHub/setup-core-ai-proxy.sh` automatizálja a config + hosts + reload lépéseket.
- Ha újra kell építeni: `setup-core-ai-proxy.sh` csak a server blokkokat írja ki (PID marad a globális nginx.conf-ban); futtatás után `brew services restart nginx`. Ez a fallback lépés szerepel a notes.md-ben.
