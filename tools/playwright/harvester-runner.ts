#!/usr/bin/env tsx
import { promises as fs } from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { chromium, Browser } from '@playwright/test';

interface PageConfig {
  slug: string;
  url: string;
  waitForSelector?: string;
  waitAfterLoadMs?: number;
  saveAs?: string;
  screenshotSelector?: string;
  screenshotName?: string;
}

interface RunnerConfig {
  outDir: string;
  summaryPath?: string;
  timeoutMs?: number;
  waitAfterLoadMs?: number;
  screenshotDir?: string;
  screenshotFullPage?: boolean;
  pages: PageConfig[];
}

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

async function readConfig(configPath: string): Promise<RunnerConfig> {
  const abs = path.isAbsolute(configPath) ? configPath : path.resolve(configPath);
  const raw = await fs.readFile(abs, 'utf8');
  const cfg = JSON.parse(raw);
  if (!Array.isArray(cfg.pages) || cfg.pages.length === 0) {
    throw new Error(`A konfiguráció nem tartalmaz Playwright oldalt: ${abs}`);
  }
  if (!cfg.outDir) {
    throw new Error('A konfigurációból hiányzik az "outDir" mező.');
  }
  return cfg;
}

async function ensureDir(targetPath: string): Promise<void> {
  await fs.mkdir(path.dirname(targetPath), { recursive: true });
}

async function saveHtml(target: string, html: string): Promise<void> {
  await ensureDir(target);
  await fs.writeFile(target, html, 'utf8');
}

async function writeSummary(summaryPath: string | undefined, entries: Record<string, string>[]): Promise<void> {
  if (!summaryPath) {
    return;
  }
  const payload = {
    generated_at: new Date().toISOString(),
    entries,
  };
  await ensureDir(summaryPath);
  await fs.writeFile(summaryPath, JSON.stringify(payload, null, 2), 'utf8');
}

async function run(configPath: string): Promise<void> {
  const cfg = await readConfig(configPath);
  const outDir = path.isAbsolute(cfg.outDir) ? cfg.outDir : path.resolve(cfg.outDir);
  const timeout = cfg.timeoutMs ?? 30000;
  const defaultWait = cfg.waitAfterLoadMs ?? 1000;
  const screenshotDir = cfg.screenshotDir
    ? (path.isAbsolute(cfg.screenshotDir) ? cfg.screenshotDir : path.resolve(cfg.screenshotDir))
    : undefined;
  const screenshotFullPage = cfg.screenshotFullPage ?? true;

  const browser: Browser = await chromium.launch({ headless: true });
  const summary: Record<string, string>[] = [];

  try {
    for (const pageCfg of cfg.pages) {
      const page = await browser.newPage();
      try {
        console.log(`🌐  Betöltés: ${pageCfg.slug} → ${pageCfg.url}`);
        await page.goto(pageCfg.url, { timeout, waitUntil: 'domcontentloaded' });
        const selector = pageCfg.waitForSelector;
        if (selector) {
          try {
            await page.waitForSelector(selector, { timeout });
          } catch (err) {
            console.warn(`⚠️  ${pageCfg.slug}: ${selector} nem jelent meg a megadott időn belül (${timeout} ms).`);
          }
        }
        const waitMs = pageCfg.waitAfterLoadMs ?? defaultWait;
        if (waitMs > 0) {
          await page.waitForTimeout(waitMs);
        }
        const html = await page.content();
        const fileName = pageCfg.saveAs || `${pageCfg.slug}.html`;
        const targetPath = path.join(outDir, fileName);
        await saveHtml(targetPath, html);
        const summaryEntry: Record<string, string> = {
          slug: pageCfg.slug,
          url: pageCfg.url,
          html_path: targetPath,
        };
        if (screenshotDir) {
          const screenshotName = pageCfg.screenshotName || `${pageCfg.slug}.png`;
          const screenshotPath = path.join(screenshotDir, screenshotName);
          await ensureDir(screenshotPath);
          if (pageCfg.screenshotSelector) {
            const locator = page.locator(pageCfg.screenshotSelector);
            await locator.screenshot({ path: screenshotPath });
          } else {
            await page.screenshot({ path: screenshotPath, fullPage: screenshotFullPage });
          }
          summaryEntry.screenshot_path = screenshotPath;
          console.log(`🖼️  Screenshot mentve: ${screenshotPath}`);
        }
        summary.push(summaryEntry);
        console.log(`✅  Mentve: ${targetPath}`);
      } catch (error) {
        console.error(`❌  Playwright hiba (${pageCfg.slug}):`, error);
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }

  await writeSummary(cfg.summaryPath ? path.resolve(cfg.summaryPath) : undefined, summary);
  if (summary.length === 0) {
    process.exitCode = 1;
    console.error('❌  Nem készült egyetlen HTML snapshot sem. Ellenőrizd a konfigurációt.');
  }
}

async function main(): Promise<void> {
  const configArgIndex = process.argv.findIndex(arg => arg === '--config');
  const configPath = configArgIndex >= 0 && process.argv[configArgIndex + 1]
    ? process.argv[configArgIndex + 1]
    : path.join(__dirname, 'harvester-config.json');
  await run(configPath);
}

main().catch(err => {
  console.error('Playwright harvester hiba:', err);
  process.exit(1);
});
