// saveCookies.js

const puppeteer = require('puppeteer');
const fs = require('fs');
const readline = require('readline');

(async () => {
    const browser = await puppeteer.launch({
        headless: false,
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();

    // Go to Google sign-in page first
    await page.goto('https://accounts.google.com/signin', { waitUntil: 'networkidle2' });

    console.log('Please log in manually including password and 2FA.');
    console.log('After you have successfully logged in and navigated to your Google Business Reviews page, come back here and press Enter to save cookies.');

    // Wait for user to press Enter in terminal before proceeding
    await new Promise(resolve => {
        const rl = readline.createInterface({
            input: process.stdin,
            output: process.stdout
        });
        rl.question('Press Enter after you have completed login and navigated to your reviews page...', () => {
            rl.close();
            resolve();
        });
    });

    const cookies = await page.cookies();
    fs.writeFileSync('cookies.json', JSON.stringify(cookies, null, 2));
    console.log('✅ Cookies saved to cookies.json');

    await browser.close();
})();
