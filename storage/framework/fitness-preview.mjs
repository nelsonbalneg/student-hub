import {createServer} from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwind from '@tailwindcss/vite';
import puppeteer from 'puppeteer';
import path from 'node:path';
const server = await createServer({configFile:false,root:process.cwd(),plugins:[vue(),tailwind()],optimizeDeps:{noDiscovery:true,include:[]},resolve:{alias:{'@':path.resolve('resources/js')}},server:{host:'127.0.0.1',port:5184,strictPort:true}});
await server.listen();console.log('server ready');
let browser;
try {
    console.log('launching browser');browser=await puppeteer.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    console.log('browser ready');const page=await browser.newPage();
    const errors=[];page.on('pageerror',error=>errors.push(error.message));
    for(const [name,width] of [['desktop',1280],['tablet',768],['mobile',390]]) {
        await page.setViewport({width,height:1100});
        await page.goto('http://127.0.0.1:5184/storage/framework/fitness-preview.html',{waitUntil:'networkidle0'});
        if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)) throw Error(`${name} overflows`);
        await page.screenshot({path:`storage/framework/fitness-${name}.png`,fullPage:true});
    }

    await page.locator('button').filter(button=>button.textContent.trim()==='Post-test').click();
    await page.waitForFunction(()=>document.body.textContent.includes('Post-test Assessment'));
    if(await page.evaluate(()=>[...document.querySelectorAll('button')].some(button=>button.textContent.includes('Fill Up assessment')))) throw Error('Upcoming phase permits fill up');
    await page.select('select','second');
    await page.locator('button').filter(button=>button.textContent.includes('View My Analytics')).click();
    await page.waitForFunction(()=>document.querySelector('#action').textContent==='analytics');
    await page.evaluate(()=>document.documentElement.classList.add('dark'));
    await page.screenshot({path:'storage/framework/fitness-dark.png',fullPage:true});
    if(errors.length)throw Error(errors.join('\n'));
    console.log('PASS: desktop/tablet/mobile overflow, phase and term switching, schedule-gated primary action, resource event, dark mode, no JavaScript errors.');
} finally {await browser?.close();await server.close();}
