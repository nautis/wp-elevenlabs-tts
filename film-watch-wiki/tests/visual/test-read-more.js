const puppeteer = require('puppeteer');

async function testReadMore() {
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.setCacheEnabled(false);

    console.log('Loading Jon Favreau page...');
    await page.goto('https://tellingtime.com/blog/actor/jon-favreau/', {
        waitUntil: 'networkidle2',
        timeout: 30000
    });

    await page.waitForTimeout(2000);

    console.log('\n=== INITIAL STATE ===');
    const bioContainer = await page.$('.bio-container');
    console.log('Bio container exists:', !!bioContainer);

    const readMoreBtn = await page.$('.bio-toggle-btn');
    const isBtnVisible = readMoreBtn ? await page.evaluate(el => {
        const style = window.getComputedStyle(el);
        return style.display !== 'none' && !el.classList.contains('hidden');
    }, readMoreBtn) : false;
    console.log('Read More button visible:', isBtnVisible);

    if (readMoreBtn) {
        const btnText = await page.evaluate(el => el.textContent.trim(), readMoreBtn);
        console.log('Button text:', `"${btnText}"`);
    }

    const bioPreview = await page.$('.bio-preview');
    console.log('Bio preview exists:', !!bioPreview);

    if (bioPreview) {
        const previewText = await page.evaluate(el => el.textContent, bioPreview);
        const words = previewText.trim().replace(/\s+/g, ' ').split(' ');
        console.log('Preview word count:', words.length);
        console.log('Preview ends:', words.slice(-7).join(' '));
    }

    const bioFull = await page.$('.bio-full');
    console.log('Bio full exists:', !!bioFull);
    
    if (bioFull) {
        const isHidden = await page.evaluate(el => el.classList.contains('hidden'), bioFull);
        console.log('Bio full hidden:', isHidden);
    }

    if (readMoreBtn && isBtnVisible) {
        console.log('\n=== CLICKING "READ MORE" ===');
        await readMoreBtn.click();
        await page.waitForTimeout(500);

        const btnText = await page.evaluate(el => el.textContent.trim(), readMoreBtn);
        const fullVisible = await page.evaluate(el => !el.classList.contains('hidden'), bioFull);
        const previewHidden = await page.evaluate(el => el.classList.contains('hidden'), bioPreview);

        console.log('Button text:', `"${btnText}"`);
        console.log('Full visible:', fullVisible);
        console.log('Preview hidden:', previewHidden);
        
        console.log('\n✅ WORKING!');
    } else {
        console.log('\n❌ BUTTON NOT VISIBLE');
    }

    await browser.close();
}

testReadMore().catch(console.error);
