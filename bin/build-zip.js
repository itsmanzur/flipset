const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const rootDir = path.resolve(__dirname, '..');
const distDir = path.join(rootDir, 'dist');
const stageDir = path.join(distDir, 'flipset');
const zipPathRoot = path.join(rootDir, 'flipset.zip');
const zipPathDist = path.join(distDir, 'flipset.zip');

console.log('🚀 Starting Flipset WordPress Plugin Build...');

// 1. Clean dist directory
if (fs.existsSync(distDir)) {
	fs.rmSync(distDir, { recursive: true, force: true });
}
fs.mkdirSync(stageDir, { recursive: true });

// 2. Exact ignores (relative from root)
const rootIgnores = new Set([
	'.git',
	'.github',
	'.gitignore',
	'.distignore',
	'.DS_Store',
	'Thumbs.db',
	'.idea',
	'.vscode',
	'.cursor',
	'.editorconfig',
	'.gitattributes',
	'.wp-env.json',
	'.wp-env.override.json',
	'node_modules',
	'package.json',
	'package-lock.json',
	'composer.json',
	'composer.lock',
	'phpcs.xml',
	'phpcs.xml.dist',
	'.phpcs.xml.dist',
	'phpunit.xml',
	'phpunit.xml.dist',
	'.phpunit.result.cache',
	'vendor',
	'docs',
	'tests',
	'bin',
	'dist',
	'wp-cli.phar',
	'composer.phar',
	'playwright.config.js',
	'playwright-report',
	'test-results',
	'PRODUCT_ROADMAP.md',
	'translations-src',
	'.wordpress-org'
]);

function shouldIgnore(relativePath) {
	const normalized = relativePath.replace(/\\/g, '/');
	const topLevel = normalized.split('/')[0];

	// Ignore root-level dev files and folders
	if (rootIgnores.has(topLevel)) {
		return true;
	}

	// Extension-based ignores
	if (normalized.endsWith('.zip') || normalized.endsWith('.log') || normalized.endsWith('.tmp')) {
		return true;
	}

	if (normalized.includes('.DS_Store') || normalized.includes('Thumbs.db')) {
		return true;
	}

	return false;
}

// 3. Copy files recursively
let fileCount = 0;
function copyFolder(src, dest, relBase = '') {
	const items = fs.readdirSync(src);
	for (const item of items) {
		const srcPath = path.join(src, item);
		const relPath = relBase ? `${relBase}/${item}` : item;

		if (shouldIgnore(relPath)) {
			continue;
		}

		const destPath = path.join(dest, item);
		const stat = fs.statSync(srcPath);

		if (stat.isDirectory()) {
			fs.mkdirSync(destPath, { recursive: true });
			copyFolder(srcPath, destPath, relPath);
		} else if (stat.isFile()) {
			fs.copyFileSync(srcPath, destPath);
			fileCount++;
		}
	}
}

copyFolder(rootDir, stageDir);
console.log(`✅ Staged ${fileCount} files into ${stageDir}`);

// 4. Create ZIP using PowerShell Compress-Archive
try {
	if (fs.existsSync(zipPathRoot)) {
		fs.unlinkSync(zipPathRoot);
	}
	console.log('📦 Compressing into flipset.zip...');
	// Compress-Archive on Windows PowerShell 5.1 writes backslash entry names,
	// which extract as literal "flipset\file.php" on Linux hosts. Build the
	// archive with System.IO.Compression and force forward slashes instead.
	const psScript = [
		'Add-Type -AssemblyName System.IO.Compression',
		'Add-Type -AssemblyName System.IO.Compression.FileSystem',
		`$stage = '${stageDir}'`,
		`$zipPath = '${zipPathRoot}'`,
		'$parent = Split-Path $stage -Parent',
		'$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)',
		'try {',
		'  Get-ChildItem -Path $stage -Recurse -File | ForEach-Object {',
		'    $rel = $_.FullName.Substring($parent.Length).TrimStart([char]92) -replace [regex]::Escape([string][char]92), "/"',
		'    [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $rel, [System.IO.Compression.CompressionLevel]::Optimal)',
		'  }',
		'} finally { $zip.Dispose() }',
	].join('\n');
	const psFile = path.join(distDir, 'zip.ps1');
	fs.writeFileSync(psFile, psScript);
	execSync(`powershell -NoProfile -ExecutionPolicy Bypass -File "${psFile}"`, { stdio: 'inherit' });
	fs.unlinkSync(psFile);

	// Copy to dist/flipset.zip as well
	fs.copyFileSync(zipPathRoot, zipPathDist);

	const stats = fs.statSync(zipPathRoot);
	const sizeMB = (stats.size / (1024 * 1024)).toFixed(2);
	const sizeKB = (stats.size / 1024).toFixed(1);

	console.log(`\n🎉 Build complete!`);
	console.log(`📁 Zip File: ${zipPathRoot}`);
	console.log(`📁 Dist File: ${zipPathDist}`);
	console.log(`📊 Size: ${sizeMB} MB (${sizeKB} KB)`);
	console.log(`📦 Total packaged files: ${fileCount}`);
} catch (err) {
	console.error('❌ Failed to create zip archive:', err);
	process.exit(1);
}
