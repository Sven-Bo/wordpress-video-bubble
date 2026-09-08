const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'video-bubble.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/css/video-bubble.css'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/video-bubble.js'), 'utf8');
assert.match(php, /id="vb-container" style="display:none!important;/);
let markup = php.slice(php.indexOf('<div id="vb-container"'), php.indexOf('    <script>', php.indexOf('<div id="vb-container"')));
markup = markup.replace(/<\?php[\s\S]*?\?>/g, '').replace(/<iframe[\s\S]*?<\/iframe>/g, '');
markup = markup.replace(/<div id="vb-container"[^\n]*>/, '<div id="vb-container" style="display:none!important;--vb-accent:#3772a3" class="vb-scroll-hidden" data-position="bottom-right" data-video-type="direct">');
(async () => {
 const browser = await chromium.launch({headless:true, channel:process.env.BROWSER_CHANNEL || 'msedge'});
 try {
 const page = await browser.newPage({viewport:{width:1200,height:800}});
 page.on('pageerror', error=>console.error('PAGE ERROR',error));
 await page.route('**/*', route => route.abort()); // never send a real message
 await page.setContent('<!doctype html><main style="height:4000px">Hero</main>' + markup);
 assert.equal(await page.locator('#vb-container').isVisible(), false, 'hidden even without styles');
 await page.evaluate(() => { window.vbConfig={webhookUrl:'https://api.pythonandvba.com/contact/web',scrollThreshold:1};window.sends=[];window.fetch=async (url, opts)=>{window.sends.push(JSON.parse(opts.body));return {status:422,json:async()=>({status:'error',message:"'email' must be a string. &#x20;"})}}; });
 await page.addScriptTag({content:js});
 await page.evaluate(() => window.scrollTo(0, 600));
 await page.waitForTimeout(100);
 assert.equal(await page.locator('#vb-container').isVisible(), false, 'scroll before CSS stays hidden');
 await page.addStyleTag({content:css});
 await page.evaluate(() => document.dispatchEvent(new Event('load')));
 assert.equal(await page.locator('#vb-container').isVisible(), true, 'reveal after CSS and threshold');
 await page.locator('#vb-bubble').click();
 await page.locator('#vb-cta-btn').click();
 await page.locator('#vb-field-name').fill('Jane');
 await page.locator('#vb-field-email').fill('jane@example.com');
 await page.locator('#vb-field-email').focus();
 await page.waitForTimeout(250);
 assert.equal(await page.locator('#vb-field-email').evaluate(el=>getComputedStyle(el).borderTopColor), 'rgb(55, 114, 163)', 'validated email focus follows accent');
 assert.match(await page.locator('#vb-field-email').evaluate(el=>getComputedStyle(el).boxShadow), /55|0\.215/, 'focus glow follows accent');
 await page.locator('#vb-field-message').fill('Hi');
 await page.locator('#vb-submit-btn').click();
 assert.equal(await page.locator('#vb-form-feedback').textContent(), 'Could you tell me a little more about what you need help with?');
 assert.equal(await page.evaluate(()=>window.sends.length),0);
 assert.equal(await page.evaluate(()=>document.activeElement.id),'vb-field-message');
 await page.locator('#vb-field-message').fill('Could you help me choose a tool?');
 await page.locator('#vb-submit-btn').click();
 await page.waitForTimeout(100);
 assert.equal(await page.locator('#vb-form-feedback').textContent(), 'Please check your email address, or try another one so I can reply to you.');
 assert.equal(await page.evaluate(()=>window.sends.length),1);
 // A fresh visit with styles ready still stays hidden at the hero.
 await page.setContent('<!doctype html><main style="height:4000px">Hero</main>' + markup);
 await page.evaluate(()=>window.scrollTo(0,0));
 await page.addStyleTag({content:css});
 await page.addScriptTag({content:js});
 assert.equal(await page.locator('#vb-container').isVisible(),false,'styles ready at hero must not show widget');
 await page.evaluate(()=>window.scrollTo(0,600));
 await page.waitForTimeout(100);
 assert.equal(await page.locator('#vb-container').isVisible(),true);
 console.log('PASS: delayed CSS, scroll gate, hero reload, branded focus, helpful validation, and safe API errors');
 } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
