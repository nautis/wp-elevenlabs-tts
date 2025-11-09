const puppeteer = require('puppeteer');

async function checkNbsp() {
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.goto('https://tellingtime.com/blog/actor/dave-bautista/', {
        waitUntil: 'networkidle2',
        timeout: 30000
    });

    await page.waitForTimeout(2000);

    const bioFull = await page.$('.bio-full');
    if (bioFull) {
        const html = await page.evaluate(el => el.innerHTML.substring(0, 500), bioFull);
        const text = await page.evaluate(el => el.textContent.substring(0, 500), bioFull);
        
        console.log('HTML source (first 500 chars):');
        console.log(html);
        console.log('\n---\n');
        console.log('Rendered text (first 500 chars):');
        console.log(text);
        console.log('\n---\n');
        console.log('Contains &nbsp; in HTML?', html.includes('&nbsp;'));
        console.log('Contains literal "&nbsp;" in text?', text.includes('&nbsp;'));
    }

    await browser.close();
}

checkNbsp().catch(console.error);
