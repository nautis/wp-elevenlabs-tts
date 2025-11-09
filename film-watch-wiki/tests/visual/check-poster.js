const puppeteer = require('puppeteer');

async function checkPoster() {
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.setCacheEnabled(false);

    console.log('Loading War, Inc. page...');
    await page.goto('https://tellingtime.com/blog/movie/war-inc/', {
        waitUntil: 'networkidle2',
        timeout: 30000
    });

    // Check for poster image
    const poster = await page.$('.fww-movie-poster');

    console.log('\nPoster Image:');
    console.log('=============');
    console.log(`Present: ${poster ? 'YES' : 'NO'}`);

    if (poster) {
        const posterSrc = await poster.evaluate(img => img.src);
        const naturalWidth = await poster.evaluate(img => img.naturalWidth);
        const naturalHeight = await poster.evaluate(img => img.naturalHeight);

        console.log(`URL: ${posterSrc}`);
        console.log(`Dimensions: ${naturalWidth}x${naturalHeight}px`);
        console.log(`From TMDB: ${posterSrc.includes('tmdb.org') ? 'YES' : 'NO'}`);
    }

    await browser.close();
}

checkPoster();
