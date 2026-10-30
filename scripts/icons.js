/**
 * Gera os ícones do site em assets/icons/ a partir do desenho definido aqui (presente branco
 * sobre o roxo da marca, com um brilho rosa). Renderiza com o Chrome instalado (puppeteer-core).
 *
 * Uso: npm run icons
 * Chrome em outro caminho? CHROME_PATH="C:/caminho/chrome.exe" npm run icons
 */
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');

const OUT = path.resolve(__dirname, '..', 'assets', 'icons');

const BRAND = {
	grape: '#6a00f4',
	grapeLight: '#8a2bff',
	grapeDark: '#5200c2',
	berry: '#ff4f79',
	ink: '#1b1135',
	snow: '#f7f5fc',
};

// Ícone "gift" do Heroicons (viewBox 24) — o mesmo usado no cabeçalho do site.
const GIFT =
	'M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z';

// Estrela de 4 pontas, raio 1, centrada na origem.
const SPARKLE = 'M0 -1 Q0.16 -0.16 1 0 Q0.16 0.16 0 1 Q-0.16 0.16 -1 0 Q-0.16 -0.16 0 -1Z';

/**
 * SVG do ícone (viewBox 512).
 * @param {object} o
 * @param {boolean} o.rounded  cantos arredondados com fundo transparente (false = quadrado cheio)
 * @param {boolean} o.sparkle  brilho rosa acima do laço
 * @param {number}  o.stroke   espessura do traço, em unidades do ícone de 24px
 * @param {number}  o.scale    tamanho do presente em relação ao ícone (0–1)
 */
function iconSvg({ rounded = true, sparkle = true, stroke = 2, scale = 0.56 } = {}) {
	const s = (512 * scale) / 24;
	const offset = 256 - 12 * s;
	return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
	<defs>
		<linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
			<stop offset="0" stop-color="${BRAND.grapeLight}"/>
			<stop offset="1" stop-color="${BRAND.grapeDark}"/>
		</linearGradient>
	</defs>
	<rect width="512" height="512" rx="${rounded ? 112 : 0}" fill="url(#bg)"/>
	<g transform="translate(${offset} ${offset}) scale(${s})">
		<path d="${GIFT}" fill="none" stroke="#fff" stroke-width="${stroke}" stroke-linecap="round" stroke-linejoin="round"/>
		${sparkle ? `<path d="${SPARKLE}" fill="${BRAND.berry}" transform="translate(19.9 2.8) scale(2.4)"/>` : ''}
	</g>
</svg>`;
}

const variants = {
	// Favicon: sem brilho e traço mais grosso, para continuar legível em 16–32 px.
	favicon: iconSvg({ sparkle: false, stroke: 2.6, scale: 0.64 }),
	// Ícone padrão (cantos arredondados, fundo transparente).
	app: iconSvg(),
	// Quadrado cheio: iOS, Facebook e Google aplicam a própria máscara/arredondamento.
	full: iconSvg({ rounded: false }),
	// Maskable (Android): presente dentro da zona segura central de 80%.
	maskable: iconSvg({ rounded: false, scale: 0.46 }),
};

const files = [
	{ name: 'favicon-32.png', svg: variants.favicon, size: 32 },
	{ name: 'icon-192.png', svg: variants.app, size: 192 },
	{ name: 'icon-512.png', svg: variants.app, size: 512 },
	{ name: 'icon-maskable-512.png', svg: variants.maskable, size: 512 },
	{ name: 'apple-touch-icon.png', svg: variants.full, size: 180 },
	{ name: 'facebook-app-icon-1024.png', svg: variants.full, size: 1024 },
	{ name: 'google-logo-120.png', svg: variants.full, size: 120 },
];

// Imagem de compartilhamento (Open Graph), 1200×630.
function ogHtml() {
	const icon = `data:image/svg+xml;base64,${Buffer.from(variants.full).toString('base64')}`;
	return `<!doctype html><html><head><meta charset="utf-8">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;700;800&display=block" rel="stylesheet">
<style>
	* { margin: 0; box-sizing: border-box; }
	body { width: 1200px; height: 630px; overflow: hidden; font-family: Outfit, sans-serif; background: ${BRAND.snow}; color: ${BRAND.ink}; position: relative; }
	.blob { position: absolute; border-radius: 50%; filter: blur(80px); }
	.b1 { width: 620px; height: 620px; right: -160px; top: -260px; background: ${BRAND.grape}; opacity: .28; }
	.b2 { width: 420px; height: 420px; right: 120px; bottom: -300px; background: ${BRAND.berry}; opacity: .22; }
	.wrap { position: relative; display: flex; align-items: center; justify-content: space-between; height: 100%; padding: 0 90px; }
	.text { max-width: 640px; }
	.eyebrow { font-size: 24px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: ${BRAND.grape}; }
	h1 { margin-top: 22px; font-size: 76px; line-height: 1.02; font-weight: 800; letter-spacing: -.02em; }
	h1 span { color: ${BRAND.grape}; }
	p { margin-top: 26px; font-size: 28px; font-weight: 500; color: rgba(27,17,53,.6); }
	.icon { width: 330px; height: 330px; border-radius: 76px; transform: rotate(-6deg); box-shadow: 0 40px 80px -20px rgba(106,0,244,.55); }
</style></head><body>
	<div class="blob b1"></div><div class="blob b2"></div>
	<div class="wrap">
		<div class="text">
			<div class="eyebrow">Amigo Secreto</div>
			<h1>Sorteie, monte sua lista e <span>acerte no presente.</span></h1>
			<p>Grupos, sorteio e listas de presentes com links.</p>
		</div>
		<img class="icon" src="${icon}" alt="">
	</div>
</body></html>`;
}

function findChrome() {
	const candidates = [
		process.env.CHROME_PATH,
		'C:/Program Files/Google/Chrome/Application/chrome.exe',
		'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
		'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
		'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
		'/usr/bin/google-chrome',
		'/usr/bin/chromium',
	].filter(Boolean);
	const found = candidates.find((p) => fs.existsSync(p));
	if (!found) throw new Error('Chrome/Edge não encontrado. Defina CHROME_PATH.');
	return found;
}

(async () => {
	fs.mkdirSync(OUT, { recursive: true });
	fs.writeFileSync(path.join(OUT, 'favicon.svg'), variants.favicon);
	console.log('favicon.svg');

	const browser = await puppeteer.launch({ executablePath: findChrome(), headless: true });
	const page = await browser.newPage();

	for (const { name, svg, size } of files) {
		await page.setViewport({ width: size, height: size, deviceScaleFactor: 1 });
		await page.setContent(
			`<html><body style="margin:0;background:transparent">${svg.replace('<svg ', `<svg width="${size}" height="${size}" style="display:block" `)}</body></html>`
		);
		await page.screenshot({ path: path.join(OUT, name), omitBackground: true });
		console.log(`${name} (${size}×${size})`);
	}

	await page.setViewport({ width: 1200, height: 630, deviceScaleFactor: 1 });
	await page.setContent(ogHtml(), { waitUntil: 'networkidle0' });
	await page.evaluate(() => document.fonts.ready);
	const fontOk = await page.evaluate(() => document.fonts.check('800 76px Outfit'));
	if (!fontOk) console.warn('Aviso: fonte Outfit não carregou (sem internet?) — og-image.png usou fonte alternativa.');
	await page.screenshot({ path: path.join(OUT, 'og-image.png') });
	console.log('og-image.png (1200×630)');

	await browser.close();
})().catch((err) => {
	console.error(err);
	process.exit(1);
});
