import fs from 'fs';
import path from 'path';

const SHARED_DIR = 'shared/enums';
const PHP_DIR = 'apps/backend/src/Enum';
const TS_DIR = 'apps/frontend/src/app/enums';

function generatePhp(name, values) {
  const cases = Object.entries(values)
    .map(([key, val]) => `    case ${key} = '${val}';`)
    .join('\n');

  return `<?php

namespace App\\Enum;

enum ${name}: string
{
${cases}
}
`;
}

function generateTs(name, values) {
  const members = Object.entries(values)
    .map(([key, val]) => `  ${key} = '${val}',`)
    .join('\n');

  return `export enum ${name} {
${members}
}
`;
}

function sync() {
  const rootDir = process.cwd();
  const sharedPath = path.join(rootDir, SHARED_DIR);
  const phpPathBase = path.join(rootDir, PHP_DIR);
  const tsPathBase = path.join(rootDir, TS_DIR);

  if (!fs.existsSync(sharedPath)) {
    console.error(`Directory ${sharedPath} not found.`);
    return;
  }

  if (!fs.existsSync(phpPathBase)) fs.mkdirSync(phpPathBase, { recursive: true });
  if (!fs.existsSync(tsPathBase)) fs.mkdirSync(tsPathBase, { recursive: true });

  const files = fs.readdirSync(sharedPath).filter(f => f.endsWith('.json'));

  for (const file of files) {
    const filePath = path.join(sharedPath, file);
    const content = JSON.parse(fs.readFileSync(filePath, 'utf-8'));
    const { name, values } = content;

    // PHP
    const phpPath = path.join(phpPathBase, `${name}.php`);
    fs.writeFileSync(phpPath, generatePhp(name, values));
    console.log(`Generated PHP enum: ${phpPath}`);

    // TS
    const tsPath = path.join(tsPathBase, `${name}.ts`);
    fs.writeFileSync(tsPath, generateTs(name, values));
    console.log(`Generated TS enum: ${tsPath}`);
  }
}

sync();
