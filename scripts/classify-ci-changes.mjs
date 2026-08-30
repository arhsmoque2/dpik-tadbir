#!/usr/bin/env node

import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const ZERO_SHA = /^0+$/;

function git(args) {
  return execFileSync('git', args, { cwd: ROOT, encoding: 'utf8' }).trim();
}

function changedFiles() {
  const base = process.env.QUALITY_BASE_SHA?.trim();
  const head = process.env.QUALITY_HEAD_SHA?.trim() || 'HEAD';
  try {
    if (base && !ZERO_SHA.test(base)) {
      return git(['diff', '--name-only', `${base}...${head}`])
        .split(/\r?\n/)
        .filter(Boolean);
    }
    return git(['diff-tree', '--no-commit-id', '--name-only', '-r', head])
      .split(/\r?\n/)
      .filter(Boolean);
  } catch (error) {
    console.error(`Change classification failed closed: ${error.message}`);
    return ['.github/workflows/ci.yml'];
  }
}

const files = changedFiles().map((file) => file.replaceAll('\\', '/'));
const matches = (patterns) => files.some((file) => patterns.some((pattern) => pattern.test(file)));
const all = matches([
  /^\.github\/workflows\/ci\.yml$/,
  /^scripts\/classify-ci-changes\.mjs$/,
  /^composer\.lock$/,
  /^pnpm-lock\.yaml$/,
]);

const flags = {
  php:
    all ||
    matches([
      /^(app|bootstrap|config|database|routes|tests)\//,
      /^composer\.(json|lock)$/,
      /^phpunit\.xml$/,
      /^phpstan/,
    ]),
  ui:
    all ||
    matches([
      /^app\/Filament\//,
      /^resources\/(views|css|js)\//,
      /^public\//,
      /^tests\/Browser\//,
      /^playwright\.config\./,
      /^package\.json$/,
      /^pnpm-lock\.yaml$/,
    ]),
  docs:
    matches([
      /\.md$/,
      /^docs\//,
      /^\.cspell\.json$/,
      /^\.markdownlint/,
    ]),
};

const output = Object.entries(flags)
  .map(([name, enabled]) => `${name}=${enabled}`)
  .join('\n');
console.log(`Changed files (${files.length}):\n${files.map((file) => `- ${file}`).join('\n')}`);
console.log(`Selected lanes:\n${output}`);
if (process.env.GITHUB_OUTPUT) fs.appendFileSync(process.env.GITHUB_OUTPUT, `${output}\n`);
