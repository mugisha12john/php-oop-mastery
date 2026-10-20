// Optional local Chrome QA. Invoked by the integration suite with temporary sessions on stdin.
import {spawn} from 'node:child_process';
import {mkdtemp, readFile, writeFile, mkdir, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import path from 'node:path';

let input = '';
for await (const chunk of process.stdin) input += chunk;
const config = JSON.parse(input);
const profile = await mkdtemp(path.join(tmpdir(), 'mlms-chrome-'));
const output = '/tmp/mlms-ui-preview';
await mkdir(output, {recursive: true});
const chrome = spawn(process.env.CHROME_BIN || '/usr/bin/google-chrome', [
    '--headless=new', '--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--no-first-run',
    '--no-default-browser-check', '--disable-background-networking', '--disable-extensions',
    '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank',
], {stdio: 'ignore'});
let ws;
try {
    let port;
    for (let n = 0; n < 100; n++) {
        try { port = (await readFile(path.join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]; break; }
        catch { await new Promise(resolve => setTimeout(resolve, 100)); }
    }
    if (!port) throw new Error('Chrome did not start.');
    const target = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, {method:'PUT'})).json();
    ws = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { ws.onopen=resolve; ws.onerror=reject; });
    let sequence = 0;
    const pending = new Map();
    const exceptions = [];
    ws.onmessage = event => {
        const data = JSON.parse(event.data);
        if (data.id) {
            const handlers = pending.get(data.id);
            pending.delete(data.id);
            if (data.error) handlers?.reject(new Error(JSON.stringify(data.error)));
            else handlers?.resolve(data.result);
        }
        if (data.method === 'Runtime.exceptionThrown') exceptions.push(data.params.exceptionDetails.text);
    };
    const call = (method, params={}) => new Promise((resolve,reject) => {
        const id = ++sequence;
        pending.set(id,{resolve,reject});
        ws.send(JSON.stringify({id,method,params}));
    });
    const evaluate = async expression => (await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true})).result.value;
    await call('Page.enable');
    await call('Runtime.enable');
    await call('Network.enable');
    for (const page of config.pages) {
        await call('Network.setCookie',{name:'PHPSESSID',value:page.session,url:config.base,httpOnly:true,sameSite:'Lax'});
        for (const width of [1440,390]) {
            await call('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<600});
            await call('Page.navigate',{url:config.base+page.path});
            for (let n=0;n<100;n++) {
                if (await evaluate('document.readyState === "complete" && document.URL === '+JSON.stringify(config.base+page.path)+' && !!document.querySelector("#main-content")')) break;
                await new Promise(resolve=>setTimeout(resolve,50));
            }
            if (page.language) {
                const current = await evaluate('document.documentElement.lang');
                if (current !== page.language) {
                    await evaluate('(()=>{const form=document.querySelector(".language-switcher");form.querySelector("select").value='+JSON.stringify(page.language)+';form.requestSubmit();})()');
                    for (let n=0;n<100;n++) {
                        if (await evaluate('document.readyState === "complete" && document.documentElement.lang === '+JSON.stringify(page.language))) break;
                        await new Promise(resolve=>setTimeout(resolve,50));
                    }
                }
                const languageCheck = await evaluate('document.documentElement.lang === '+JSON.stringify(page.language)+' && document.querySelector(".language-switcher select").value === '+JSON.stringify(page.language));
                if (!languageCheck) throw new Error('Language switcher failed: '+page.path+' '+page.language);
            }
            await new Promise(resolve=>setTimeout(resolve,150));
            const data = await evaluate('({title:document.title,overflow:document.documentElement.scrollWidth>innerWidth,body:document.body.innerText,css:!!document.querySelector(".shell")&&getComputedStyle(document.querySelector(".shell")).paddingLeft})');
            if (!data || !data.title || data.body.includes('Fatal error') || data.body.includes('Warning:')) throw new Error('Page failed: '+page.path);
            if (data.overflow) throw new Error('Horizontal page overflow at '+width+': '+page.path);
            const assetCheck = await evaluate('(()=>{const sidebar=document.querySelector(".sidebar");const sheet=document.querySelector("link[rel=stylesheet]");const script=document.querySelector("script[src]");return !!sidebar && getComputedStyle(sidebar).backgroundColor==="rgb(32, 61, 67)" && getComputedStyle(sidebar).position===(innerWidth>900 ? "fixed" : "relative") && /[?]v=[a-f0-9]{12}$/.test(sheet.href) && /[?]v=[a-f0-9]{12}$/.test(script.src);})()');
            if (!assetCheck) throw new Error('Current versioned styles or sidebar layout did not load: '+page.path);
            const signoutVisible = await evaluate('(()=>{const b=document.querySelector(".signout");return !!b && b.getBoundingClientRect().height>0 && getComputedStyle(b).visibility!=="hidden";})()');
            if (!signoutVisible) throw new Error('Sign out is not accessible at '+width+': '+page.path);
            if (page.language && page.path === 'requestLoan.php') {
                const estimate = await evaluate('(()=>{const select=document.querySelector("[data-product-select]");select.selectedIndex=1;document.querySelector("[name=amount_requested_rwf]").value="10000.50";document.querySelector("[name=term_months]").value="3";select.dispatchEvent(new Event("change"));return {hint:document.querySelector("[data-product-hint]").textContent,total:document.querySelector("[data-estimate]").textContent};})()');
                const range = page.language === 'rw' ? '1.000.000,00' : '1 000 000,00';
                const label = page.language === 'rw' ? 'Inyungu' : 'intérêt';
                if (!estimate.hint.includes(range) || !estimate.hint.includes(label) || estimate.total.includes('Estimated total:') || /NaN|Infinity/.test(estimate.hint+estimate.total)) throw new Error('Localized loan estimate failed: '+JSON.stringify(estimate));
            }
            const screenshot = await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
            await writeFile(path.join(output,page.name+'-'+width+'.png'),Buffer.from(screenshot.data,'base64'));
            if (width===390) {
                const menuCheck = await evaluate('(()=>{const m=document.querySelector(".workspace-menu"); if(!m)return true; const before=m.open; m.querySelector("summary").click(); return !before && m.open;})()');
                if (!menuCheck) throw new Error('Mobile navigation failed.');
            }
        }
    }
    if (exceptions.length) throw new Error('Browser exceptions: '+exceptions.join(', '));
    console.log('PASS: Chrome desktop/mobile rendering, no horizontal page overflow, mobile navigation, and no JavaScript exceptions. Screenshots: '+output);
} finally {
    ws?.close();
    chrome.kill('SIGTERM');
    if (chrome.exitCode === null && chrome.signalCode === null) await new Promise(resolve => chrome.once('exit',resolve));
    await rm(profile,{recursive:true,force:true,maxRetries:10,retryDelay:200});
}
