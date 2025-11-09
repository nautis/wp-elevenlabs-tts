const puppeteer = require('puppeteer');

async function checkPosterActual() {
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1920, height: 1080 }); // Standard desktop
    await page.setCacheEnabled(false);

    console.log('Loading War, Inc. page...\n');
    await page.goto('https://tellingtime.com/blog/movie/war-inc/', {
        waitUntil: 'networkidle2',
        timeout: 30000
    });

    // Check poster image
    const posterInfo = await page.evaluate(() => {
        const img = document.querySelector('.fww-movie-poster');
        if (!img) return null;

        const computed = window.getComputedStyle(img);
        return {
            src: img.src,
            naturalWidth: img.naturalWidth,
            naturalHeight: img.naturalHeight,
            offsetWidth: img.offsetWidth,
            offsetHeight: img.offsetHeight,
            computedWidth: computed.width,
            computedHeight: computed.height,
            computedMinWidth: computed.minWidth,
            computedMaxWidth: computed.maxWidth,
            htmlWidth: img.getAttribute('width'),
            htmlHeight: img.getAttribute('height')
        };
    });

    if (posterInfo) {
        console.log('POSTER IMAGE DETAILS:');
        console.log('======================');
        console.log(`Source URL: ${posterInfo.src}`);
        console.log(`\nNatural (actual image) size:`);
        console.log(`  ${posterInfo.naturalWidth}px × ${posterInfo.naturalHeight}px`);
        console.log(`\nRendered size (what you see):`);
        console.log(`  ${posterInfo.offsetWidth}px × ${posterInfo.offsetHeight}px`);
        console.log(`\nComputed CSS:`);
        console.log(`  width: ${posterInfo.computedWidth}`);
        console.log(`  height: ${posterInfo.computedHeight}`);
        console.log(`  min-width: ${posterInfo.computedMinWidth}`);
        console.log(`  max-width: ${posterInfo.computedMaxWidth}`);
        console.log(`\nHTML attributes:`);
        console.log(`  width="${posterInfo.htmlWidth}"`);
        console.log(`  height="${posterInfo.htmlHeight}"`);

        if (posterInfo.offsetWidth !== 320) {
            console.log(`\n⚠️  ISSUE: Poster rendering at ${posterInfo.offsetWidth}px instead of 320px!`);
        } else {
            console.log('\n✓ Poster correctly rendering at 320px');
        }
    } else {
        console.log('Poster image not found!');
    }

    await browser.close();
}

checkPosterActual();
