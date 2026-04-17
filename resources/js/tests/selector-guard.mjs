import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const rootDir = process.cwd();
const testsDir = join(rootDir, 'resources', 'js', 'tests');
const strictMode = process.argv.includes('--strict');

const patterns = [
    {
        key: 'button-find-chain',
        regex: /findAll\(\s*['"]button['"]\s*\)\.find\(/,
        message: "Keruld a findAll('button').find(...) lancot; hasznalj stabil data-* hookot vagy semantic selectort.",
    },
    {
        key: 'wrapper-text-hardcoded',
        regex: /wrapper\.text\(\)\.toContain\(\s*['"`]/,
        message: 'Keruld a hardcoded copy assertiont; preferald az i18n kulcsalapu ellenorzest.',
    },
];

const collectTestFiles = (dir) => {
    const entries = readdirSync(dir);
    const files = [];

    for (const entry of entries) {
        const fullPath = join(dir, entry);
        const stats = statSync(fullPath);

        if (stats.isDirectory()) {
            files.push(...collectTestFiles(fullPath));
            continue;
        }

        if (entry.endsWith('.test.js')) {
            files.push(fullPath);
        }
    }

    return files;
};

const warnings = [];

for (const filePath of collectTestFiles(testsDir)) {
    const source = readFileSync(filePath, 'utf8');
    const lines = source.split(/\r?\n/);

    lines.forEach((line, index) => {
        for (const pattern of patterns) {
            if (!pattern.regex.test(line)) {
                continue;
            }

            warnings.push({
                file: relative(rootDir, filePath),
                line: index + 1,
                key: pattern.key,
                message: pattern.message,
                snippet: line.trim(),
            });
        }
    });
}

if (warnings.length === 0) {
    console.log('[selector-guard] OK: nem talaltam anti-pattern mintakat.');
    process.exit(0);
}

console.log(`[selector-guard] ${warnings.length} figyelmeztetes talalhato.`);

for (const warning of warnings) {
    console.log(
        `- ${warning.file}:${warning.line} [${warning.key}] ${warning.message}\n  > ${warning.snippet}`,
    );
}

if (strictMode) {
    console.log('[selector-guard] strict mod aktiv: exit code 1');
    process.exit(1);
}

console.log('[selector-guard] warn-only mod: exit code 0');
