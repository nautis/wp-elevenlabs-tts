const puppeteer = require('puppeteer');

async function checkCSSVariables() {
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

    // Check CSS variable values
    const variables = await page.evaluate(() => {
        const root = document.documentElement;
        const body = document.body;
        const article = document.querySelector('article');

        const getVars = (el, name) => {
            const computed = window.getComputedStyle(el);
            return {
                element: name,
                siteWidth: computed.getPropertyValue('--site-width').trim(),
                contentWidth: computed.getPropertyValue('--content-width').trim(),
                contentMaxWidth: computed.getPropertyValue('--content-max-width').trim()
            };
        };

        return {
            root: getVars(root, ':root'),
            body: getVars(body, 'body'),
            article: getVars(article, 'article')
        };
    });

    console.log('CSS VARIABLES:\n');
    for (const [context, vars] of Object.entries(variables)) {
        console.log(`${context}:`);
        console.log(`  --site-width: ${vars.siteWidth || '(not set)'}`);
        console.log(`  --content-width: ${vars.contentWidth || '(not set)'}`);
        console.log(`  --content-max-width: ${vars.contentMaxWidth || '(not set)'}\n`);
    }

    await browser.close();
}

checkCSSVariables();
