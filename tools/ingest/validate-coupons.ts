#!/usr/bin/env tsx
import fs from 'fs/promises';
import path from 'path';

type Row = Record<string, string>;

const STOPWORDS = new Set([
  'AKCI', 'AKCIO', 'AKCIOK', 'KUPON', 'KUPONKOD', 'KOD', 'CODE', 'PROMO',
  'MINDEN', 'KATEG', 'KATEGORIA', 'JANU', 'FEBR', 'MARC', 'APR', 'MAJ',
  'JUN', 'JUL', 'AUG', 'SEP', 'OKT', 'NOV', 'DEC', 'FENY', 'SHOP',
  'CANNOT', 'AND', 'THE', 'WITH', 'FROM', 'SAVE', 'DEAL',
]);

function parseCsv(text: string): Row[] {
  const lines = text.split(/\r?\n/).filter(Boolean);
  if (lines.length === 0) {
    return [];
  }
  const headers = lines[0].split(',').map(h => h.trim());
  return lines.slice(1).map(line => {
    const cols: string[] = [];
    let current = '';
    let inQuotes = false;
    for (let i = 0; i < line.length; i += 1) {
      const ch = line[i];
      if (ch === '"' && line[i + 1] === '"') {
        current += '"';
        i += 1;
        continue;
      }
      if (ch === '"') {
        inQuotes = !inQuotes;
        continue;
      }
      if (ch === ',' && !inQuotes) {
        cols.push(current);
        current = '';
        continue;
      }
      current += ch;
    }
    cols.push(current);
    const row: Row = {};
    headers.forEach((h, idx) => {
      row[h] = cols[idx] ?? '';
    });
    return row;
  });
}

function normalizeSimple(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\u00a0/g, ' ')
    .toLowerCase();
}

function isSequentialDigits(code: string): boolean {
  return /(?:0123|1234|2345|3456|4567|5678|6789|7890)/.test(code);
}

function hasBenefitSignal(text: string): boolean {
  if (!text) {
    return false;
  }
  const normalized = text
    .replace(/\u00a0/g, ' ')
    .replace(/[‐‑‒–—−]/g, '-')
    .toLowerCase();
  if (/\d{1,3}\s*%/.test(normalized)) {
    return true;
  }
  if (/\b\d{3,5}\s?(ft|huf)\b/.test(normalized)) {
    return true;
  }
  if (normalized.includes('ingyenes szallitas') || normalized.includes('ingyenes szállítás')) {
    return true;
  }
  if (normalized.includes('ajándék') || normalized.includes('ajandek')) {
    return true;
  }
  if (/(akci[oó]|le[aá]raz|kedvezm[eé]ny|[áa]rengedm[eé]ny|kupon|promo|prom[oó]ci)/.test(normalized)) {
    return true;
  }
  return false;
}

function isSuspicious(
  codeRaw: string,
  title: string,
  description: string,
  discountLabel: string,
  type: string,
  source: string,
): { ok: boolean; reason: string } {
  const code = codeRaw.trim().toUpperCase();
  if (!code) {
    return { ok: true, reason: '' };
  }
  if (type && type !== 'coupon_code') {
    return { ok: true, reason: '' };
  }
  if (STOPWORDS.has(code)) {
    return { ok: false, reason: 'stopword' };
  }
  if (code.length < 5 || code.length > 12) {
    return { ok: false, reason: 'length' };
  }
  // nincs kötelező betű+szám feltétel, csak hossz + stopword + benefit jel
  if (isSequentialDigits(code)) {
    return { ok: false, reason: 'sequential_digits' };
  }
  if (/^20\d{2}$/.test(code)) {
    return { ok: false, reason: 'year_like' };
  }
  const benefitText = `${title} ${description} ${discountLabel}`.trim();
  if (!hasBenefitSignal(benefitText)) {
    const sourceNorm = (source || '').toLowerCase();
    const titleNorm = normalizeSimple(title || '');
    if (sourceNorm === 'gmail_structured' && /(ajanlat|akcio|kupon|kuponkod|k[oó]d)/.test(titleNorm)) {
      return { ok: true, reason: '' };
    }
    return { ok: false, reason: 'missing_discount_signal' };
  }
  return { ok: true, reason: '' };
}

function escapeCsv(value: string): string {
  if (/[",\n]/.test(value)) {
    return `"${value.replace(/"/g, '""')}"`;
  }
  return value;
}

async function main() {
  const root = process.cwd();
  const ingestDir = path.join(root, 'tmp', 'ingest');
  const inputPath = path.join(ingestDir, 'export-coupons.csv');
  const outputPath = path.join(ingestDir, 'export-coupons-validation.csv');
  const raw = await fs.readFile(inputPath, 'utf8');
  const rows = parseCsv(raw);

  const flagged = rows
    .filter(row => (row.coupon_code || '').trim() !== '')
    .map(row => {
      const { ok, reason } = isSuspicious(
        row.coupon_code || '',
        row.title || '',
        row.description || '',
        row.discount_label || '',
        row.type || '',
        row.source || '',
      );
      return { ...row, ok: ok ? 'ok' : 'suspect', reason };
    })
    .filter(row => row.ok === 'suspect');

  const headers = [
    'shop_slug',
    'coupon_code',
    'title',
    'source',
    'cta_url',
    'ok',
    'reason',
  ];

  const csv = [
    headers.join(','),
    ...flagged.map(row => headers.map(h => escapeCsv(row[h] || '')).join(',')),
  ].join('\n');

  await fs.writeFile(outputPath, csv, 'utf8');
  console.log(`Validation: ${flagged.length} gyanus kupon → ${outputPath}`);
}

main().catch(err => {
  console.error('Validation hiba:', err);
  process.exit(1);
});
