const puppeteer = require('puppeteer');

async function checkDimensions() {
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 2400, height: 1200 }); // Wide viewport to match user's screen
    await page.setCacheEnabled(false);

    console.log('Loading War, Inc. page with 2400px viewport...\n');
    await page.goto('https://tellingtime.com/blog/movie/war-inc/', {
        waitUntil: 'networkidle2',
        timeout: 30000
    });

    // Check poster dimensions
    const poster = await page.$('.fww-movie-poster');
    if (poster) {
        const posterWidth = await poster.evaluate(img => img.offsetWidth);
        const posterHeight = await poster.evaluate(img => img.offsetHeight);
        console.log('POSTER DIMENSIONS:');
        console.log(`  Width: ${posterWidth}px (should be 320px)`);
        console.log(`  Height: ${posterHeight}px (should be 480px)`);
        console.log(`  ${posterWidth === 320 ? '✓ CORRECT' : '✗ WRONG - needs fixing!'}\n`);
    }

    // Check article/content width
    const article = await page.$('article');
    if (article) {
        const articleWidth = await article.evaluate(el => el.offsetWidth);
        console.log('ARTICLE WIDTH:');
        console.log(`  Width: ${articleWidth}px\n`);
    }

    // Check "Watches worn in this film" section width
    const watchesSection = await page.$('.movie-watches');
    if (watchesSection) {
        const sectionWidth = await watchesSection.evaluate(el => el.offsetWidth);
        console.log('WATCHES SECTION WIDTH:');
        console.log(`  Width: ${sectionWidth}px (user says should match archive pages, not 1470px)\n`);
    }

    // Check entry-content width
    const entryContent = await page.$('.entry-content');
    if (entryContent) {
        const contentWidth = await entryContent.evaluate(el => el.offsetWidth);
        console.log('ENTRY-CONTENT WIDTH:');
        console.log(`  Width: ${contentWidth}px\n`);
    }

    await browser.close();
}

checkDimensions();
