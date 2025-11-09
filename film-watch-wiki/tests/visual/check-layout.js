const puppeteer = require('puppeteer');

async function checkLayout() {
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 2400, height: 1200 });
    await page.setCacheEnabled(false);

    console.log('Loading page...\n');
    await page.goto('https://tellingtime.com/blog/movie/war-inc/', {
        waitUntil: 'networkidle2',
        timeout: 30000
    });

    // Check all relevant widths
    const widths = await page.evaluate(() => {
        const getWidth = (selector) => {
            const el = document.querySelector(selector);
            if (!el) return null;
            return {
                offset: el.offsetWidth,
                computed: window.getComputedStyle(el).width,
                maxWidth: window.getComputedStyle(el).maxWidth
            };
        };

        return {
            body: getWidth('body'),
            main: getWidth('#main'),
            article: getWidth('article'),
            movieHeaderWrapper: getWidth('.movie-header-wrapper'),
            movieWatches: getWidth('.movie-watches'),
            watchList: getWidth('.watch-list'),
            watchItem: getWidth('.watch-item')
        };
    });

    console.log('ELEMENT WIDTHS:\n');
    for (const [name, data] of Object.entries(widths)) {
        if (data) {
            console.log(`${name}:`);
            console.log(`  offsetWidth: ${data.offset}px`);
            console.log(`  computed width: ${data.computed}`);
            console.log(`  computed max-width: ${data.maxWidth}\n`);
        } else {
            console.log(`${name}: NOT FOUND\n`);
        }
    }

    await browser.close();
}

checkLayout();
