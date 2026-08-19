#!/usr/bin/env node

const major = Number(String(process.versions.node || '').split('.')[0] || 0);

if (major < 18) {
	console.error(
		[
			`Node.js ${process.versions.node} detected.`,
			'Playwright requires Node.js 18+ (recommended: Node 20).',
			'Fix:',
			'  - If you use nvm: `nvm install 20 && nvm use 20`',
			'  - Or ensure your PATH points to a Node 20 binary (e.g. /usr/local/bin/node).',
		].join('\n')
	);
	process.exit(1);
}

