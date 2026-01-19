#!/usr/bin/env tsx
import fs from 'fs/promises';
import path from 'path';

type Coupon = {
  source?: string;
  shop_slug?: string;
  shop_name?: string;
  coupon_code?: string;
  title?: string;
  description?: string;
  cta_url?: string;
  starts_at?: string;
  expires_at?: string;
  type?: string;
  discount_label?: string;
  old_price?: string | number;
  new_price?: string | number;
  slug?: string;
  url?: string;
  headline?: string;
  scrapedAt?: string;
};

async function readJson(filePath: string): Promise<Coupon[]> {
  try {
    const raw = await fs.readFile(filePath, 'utf8');
    return JSON.parse(raw) as Coupon[];
  } catch {
    return [];
  }
}

function escapeCsv(value: unknown): string {
  if (value === undefined || value === null) {
    return '';
  }
  const text = String(value);
  if (/[",\n]/.test(text)) {
    return `"${text.replace(/"/g, '""')}"`;
  }
  return text;
}

function extractPercent(value: string): string | undefined {
  const match = value.match(/(-?\d+)\s*%/);
  if (!match) {
    return undefined;
  }
  const pct = match[1].replace(/^\+/, '');
  return pct.startsWith('-') ? `${pct}%` : `-${pct}%`;
}

function extractPriceFt(value: string): string | undefined {
  const match = value.match(/([0-9][0-9\s.]*)\s*Ft/i);
  if (!match) {
    return undefined;
  }
  return match[1].replace(/\s+/g, '');
}

async function main() {
  const root = process.cwd();
  const ingestDir = path.join(root, 'tmp', 'ingest');
  const gmailPath = path.join(ingestDir, 'gmail.json');
  const arukeresoPath = path.join(ingestDir, 'arukereso.json');
  const outputPath = path.join(ingestDir, 'export-coupons.csv');

  const [gmail, arukereso] = await Promise.all([
    readJson(gmailPath),
    readJson(arukeresoPath),
  ]);
  const normalizedArukereso = (arukereso || []).map(item => {
    const headline = item.headline || '';
    const headlineText = String(headline);
    const pct = typeof item.discountPercent === 'number'
      ? `-${item.discountPercent}%`
      : extractPercent(headlineText);
    const newPrice = item.newPrice || extractPriceFt(headlineText);
    const oldPrice = item.oldPrice || '';
    return {
      source: 'arukereso',
      shop_slug: 'arukereso',
      shop_name: 'Arukereso',
      type: 'sale_event',
      coupon_code: '',
      title: item.title || item.slug || '',
      description: headline || '',
      cta_url: item.url || '',
      starts_at: item.scrapedAt || '',
      expires_at: '',
      discount_label: pct || headline || '',
      old_price: oldPrice || '',
      new_price: newPrice || '',
    } as Coupon;
  });

  const rows = [...gmail, ...normalizedArukereso];

  const headers = [
    'source',
    'shop_slug',
    'shop_name',
    'type',
    'coupon_code',
    'title',
    'description',
    'cta_url',
    'starts_at',
    'expires_at',
    'discount_label',
    'old_price',
    'new_price',
  ];

  const csv = [
    headers.join(','),
    ...rows.map(row => headers.map(h => escapeCsv((row as Record<string, unknown>)[h])).join(',')),
  ].join('\n');

  await fs.writeFile(outputPath, csv, 'utf8');
  console.log(`Exportált ${rows.length} kupon → ${outputPath}`);
}

main().catch(err => {
  console.error('Export hiba:', err);
  process.exit(1);
});
