/**
 * Check if War, Inc. now has TMDB data
 */

const puppeteer = require('puppeteer');

async function checkWarInc() {
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

    // Check for TMDB data fields
    const tagline = await page.$('.movie-tagline');
    const certification = await page.$('.movie-certification');
    const genres = await page.$('.movie-genres');
    const runtime = await page.$('.movie-runtime');
    const overview = await page.$('.movie-overview');

    console.log('\nTMDB Data Present:');
    console.log('==================');
    console.log(`Tagline:        ${tagline ? 'YES' : 'NO'}`);
    console.log(`Certification:  ${certification ? 'YES' : 'NO'}`);
    console.log(`Genres:         ${genres ? 'YES' : 'NO'}`);
    console.log(`Runtime:        ${runtime ? 'YES' : 'NO'}`);
    console.log(`Overview:       ${overview ? 'YES' : 'NO'}`);

    if (tagline) {
        const taglineText = await tagline.evaluate(el => el.textContent);
        console.log(`\nTagline text: "${taglineText}"`);
    }

    if (genres) {
        const genresText = await genres.evaluate(el => el.textContent);
        console.log(`Genres: ${genresText}`);
    }

    await browser.close();
}

checkWarInc();
