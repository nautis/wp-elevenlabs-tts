const puppeteer = require('puppeteer');

async function checkArchiveWidth() {
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 2400, height: 1200 });
    await page.setCacheEnabled(false);

    // Check a category/archive page
    console.log('Loading category page...\n');
    await page.goto('https://tellingtime.com/blog/category/watches/', {
        waitUntil: 'networkidle2',
        timeout: 30000
    });

    const article = await page.$('article');
    if (article) {
        const articleWidth = await article.evaluate(el => el.offsetWidth);
        console.log('ARCHIVE PAGE - ARTICLE WIDTH:');
        console.log(`  ${articleWidth}px\n`);
    }

    const main = await page.$('main');
    if (main) {
        const mainWidth = await main.evaluate(el => el.offsetWidth);
        console.log('ARCHIVE PAGE - MAIN WIDTH:');
        console.log(`  ${mainWidth}px\n`);
    }

    const contentArea = await page.$('.content-area');
    if (contentArea) {
        const contentWidth = await contentArea.evaluate(el => el.offsetWidth);
        console.log('ARCHIVE PAGE - CONTENT-AREA WIDTH:');
        console.log(`  ${contentWidth}px\n`);
    }

    await browser.close();
}

checkArchiveWidth();
