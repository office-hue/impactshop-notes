## GitHub Copilot Chat

- Extension Version: 0.31.5 (prod)
- VS Code: vscode/1.104.2
- OS: Mac

## Network

User Settings:
```json
  "github.copilot.advanced.debug.useElectronFetcher": true,
  "github.copilot.advanced.debug.useNodeFetcher": false,
  "github.copilot.advanced.debug.useNodeFetchFetcher": true
```

Connecting to https://api.github.com:
- DNS ipv4 Lookup: 140.82.121.6 (1 ms)
- DNS ipv6 Lookup: ::ffff:140.82.121.6 (1 ms)
- Proxy URL: None (1 ms)
- Electron fetch (configured): HTTP 200 (115 ms)
- Node.js https: HTTP 200 (112 ms)
- Node.js fetch: HTTP 200 (139 ms)

Connecting to https://api.individual.githubcopilot.com/_ping:
- DNS ipv4 Lookup: 140.82.112.22 (33 ms)
- DNS ipv6 Lookup: ::ffff:140.82.112.22 (1 ms)
- Proxy URL: None (7 ms)
- Electron fetch (configured): HTTP 200 (396 ms)
- Node.js https: HTTP 200 (408 ms)
- Node.js fetch: HTTP 200 (411 ms)

## Documentation

In corporate networks: [Troubleshooting firewall settings for GitHub Copilot](https://docs.github.com/en/copilot/troubleshooting-github-copilot/troubleshooting-firewall-settings-for-github-copilot).