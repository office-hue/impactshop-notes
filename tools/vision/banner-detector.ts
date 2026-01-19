#!/usr/bin/env tsx
import { promises as fs } from 'fs';
import path from 'path';
import { ImageAnnotatorClient, protos } from '@google-cloud/vision';

interface CliOptions {
  image: string;
  provider: 'google' | 'azure';
  maxLabels: number;
  keywordLimit: number;
  languageHint?: string;
  pretty: boolean;
}

interface DetectionResult {
  provider: string;
  sourceImage: string;
  detectedText: string;
  locale?: string;
  labels: Array<{ description: string; score: number }>;
  logos: Array<{ description: string; score: number }>;
  keywords: string[];
}

const DEFAULT_STOPWORDS = new Set([
  'the', 'and', 'for', 'with', 'egy', 'az', 'aki', 'mert', 'vagy', 'hogy', 'mint', 'mely', 'illetve',
  'akár', 'kedvezmény', 'akcio', 'akció', 'sale', 'black', 'friday', 'kupon', 'kedvenc', 'shop',
]);

function parseArgs(argv: string[]): CliOptions {
  const options: Partial<CliOptions> = {
    provider: 'google',
    maxLabels: 10,
    keywordLimit: 15,
    pretty: true,
  };

  for (let i = 0; i < argv.length; i++) {
    const arg = argv[i];
    if (arg === '--image' && argv[i + 1]) {
      options.image = argv[++i];
    } else if (arg === '--provider' && argv[i + 1]) {
      const provider = argv[++i].toLowerCase();
      if (provider === 'google' || provider === 'azure') {
        options.provider = provider;
      } else {
        throw new Error(`Ismeretlen provider: ${provider}. Támogatott értékek: google | azure`);
      }
    } else if (arg === '--max-labels' && argv[i + 1]) {
      options.maxLabels = Number(argv[++i]);
    } else if (arg === '--keyword-limit' && argv[i + 1]) {
      options.keywordLimit = Number(argv[++i]);
    } else if (arg === '--language-hint' && argv[i + 1]) {
      options.languageHint = argv[++i];
    } else if (arg === '--json') {
      options.pretty = false;
    }
  }

  if (!options.image) {
    throw new Error('Hiányzik a --image paraméter (helyi fájl vagy HTTPS URL).');
  }

  return options as CliOptions;
}

function isRemoteImage(image: string): boolean {
  try {
    const parsed = new URL(image);
    return parsed.protocol === 'http:' || parsed.protocol === 'https:';
  } catch {
    return false;
  }
}

async function buildImageRequest(imagePath: string): Promise<protos.google.cloud.vision.v1.IImage> {
  if (isRemoteImage(imagePath)) {
    return { source: { imageUri: imagePath } };
  }
  const absPath = path.isAbsolute(imagePath) ? imagePath : path.resolve(imagePath);
  const content = await fs.readFile(absPath);
  return { content };
}

function collectLabels(
  annotations: protos.google.cloud.vision.v1.IEntityAnnotation[] | null | undefined,
): Array<{ description: string; score: number }> {
  if (!annotations) {
    return [];
  }
  return annotations
    .filter(a => !!a?.description)
    .map(a => ({ description: a?.description ?? '', score: a?.score ?? 0 }))
    .sort((a, b) => b.score - a.score);
}

function sanitizeText(text: string): string {
  return text.replace(/\s+/g, ' ').trim();
}

function buildKeywordList(text: string, labels: Array<{ description: string }>, limit: number): string[] {
  const keywords = new Set<string>();
  const addKeyword = (word: string) => {
    const normalized = word.toLowerCase();
    if (normalized.length < 3 || DEFAULT_STOPWORDS.has(normalized)) {
      return;
    }
    keywords.add(normalized);
  };

  for (const label of labels) {
    if (label.description) {
      addKeyword(label.description);
    }
  }

  const tokens = text
    .replace(/[.,;:!\?\-_/\\\(\)"'`]/g, ' ')
    .split(/\s+/);
  for (const token of tokens) {
    if (!token) {
      continue;
    }
    addKeyword(token);
    if (keywords.size >= limit) {
      break;
    }
  }
  return Array.from(keywords).slice(0, limit);
}

async function detectWithGoogle(options: CliOptions): Promise<DetectionResult> {
  const client = new ImageAnnotatorClient();
  const image = await buildImageRequest(options.image);
  const [result] = await client.annotateImage({
    image,
    features: [
      { type: 'TEXT_DETECTION', maxResults: 5 },
      { type: 'LABEL_DETECTION', maxResults: options.maxLabels },
      { type: 'LOGO_DETECTION', maxResults: 5 },
    ],
    imageContext: options.languageHint ? { languageHints: [options.languageHint] } : undefined,
  });

  const detectedText = sanitizeText(result.fullTextAnnotation?.text ?? '');
  const labels = collectLabels(result.labelAnnotations);
  const logos = collectLabels(result.logoAnnotations);
  const keywords = buildKeywordList(detectedText, labels, options.keywordLimit);

  return {
    provider: 'google',
    sourceImage: options.image,
    detectedText,
    locale: result.textAnnotations?.[0]?.locale,
    labels,
    logos,
    keywords,
  };
}

async function detectWithAzure(): Promise<DetectionResult> {
  throw new Error('Azure Computer Vision integráció még nincs implementálva ebben a PoC-ban.');
}

async function main(): Promise<void> {
  const options = parseArgs(process.argv.slice(2));
  let result: DetectionResult;
  if (options.provider === 'google') {
    result = await detectWithGoogle(options);
  } else {
    result = await detectWithAzure();
  }
  const output = options.pretty ? JSON.stringify(result, null, 2) : JSON.stringify(result);
  console.log(output);
}

main().catch(err => {
  console.error('Banner detektor hiba:', err.message ?? err);
  process.exit(1);
});
