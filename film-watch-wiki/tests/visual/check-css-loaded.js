const puppeteer = require('puppeteer');

async function checkCSSLoaded() {
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.setCacheEnabled(false);

    console.log('Loading page with cache disabled...\n');
    await page.goto('https://tellingtime.com/blog/movie/war-inc/', {
        waitUntil: 'networkidle2',
        timeout: 30000
    });

    // Get all stylesheet URLs
    const stylesheets = await page.evaluate(() => {
        const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
        return links.map(link => link.href);
    });

    console.log('ALL STYLESHEETS LOADED:');
    stylesheets.forEach((url, index) => {
        if (url.includes('film-watch') || url.includes('fww') || url.includes('frontend')) {
            console.log(`  ${index + 1}. ${url} *** PLUGIN CSS ***`);
        } else {
            const shortUrl = url.replace('https://tellingtime.com/', '');
            console.log(`  ${index + 1}. ${shortUrl.substring(0, 80)}`);
        }
    });

    console.log(`\nTotal: ${stylesheets.length} stylesheets\n`);

    // Check if our CSS is there
    const hasFrontendCSS = stylesheets.some(url => url.includes('frontend.css'));
    console.log(`Frontend.css loaded: ${hasFrontendCSS ? 'YES ✓' : 'NO ✗'}\n`);

    await browser.close();
}

checkCSSLoaded();
