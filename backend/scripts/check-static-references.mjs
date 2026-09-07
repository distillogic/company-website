import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..', '..');

function walk(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    if (entry.name === 'node_modules' || entry.name === '.git') return [];
    const absolute = path.join(directory, entry.name);
    return entry.isDirectory() ? walk(absolute) : [absolute];
  });
}

const files = walk(root);
const available = new Set(
  files.map((file) => path.relative(root, file).split(path.sep).join('/')),
);
const failures = [];

for (const file of files.filter((candidate) => /\.(?:html|css)$/i.test(candidate))) {
  const contents = fs.readFileSync(file, 'utf8');
  const markupWithoutInlineScripts = contents.replace(
    /<script\b[^>]*>[\s\S]*?<\/script>/gi,
    '',
  );
  const references = file.endsWith('.html')
    ? [...markupWithoutInlineScripts.matchAll(/(?:href|src)=["']([^"']+)["']/gi)].map(
        (match) => match[1],
      )
    : [...contents.matchAll(/url\(["']?([^\)"']+)/gi)].map((match) => match[1]);

  for (const originalReference of references) {
    if (
      !originalReference ||
      /^(?:https?:|data:|mailto:|tel:|#|javascript:)/i.test(originalReference)
    ) {
      continue;
    }
    const reference = decodeURIComponent(originalReference.split(/[?#]/)[0]);
    if (reference.startsWith('/api/')) continue;
    const target = reference.startsWith('/')
      ? path.join(root, reference)
      : path.resolve(path.dirname(file), reference);
    const relativeTarget = path.relative(root, target).split(path.sep).join('/');
    if (relativeTarget.startsWith('..') || !available.has(relativeTarget)) {
      failures.push(
        `${path.relative(root, file)}: ${originalReference} -> ${relativeTarget}`,
      );
    }
  }
}

if (failures.length) {
  console.error(failures.join('\n'));
  process.exitCode = 1;
} else {
  const pageCount = files.filter((file) => file.endsWith('.html')).length;
  console.log(`Static reference check passed for ${pageCount} HTML pages.`);
}
