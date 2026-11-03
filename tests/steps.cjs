// SM-B2: the step bar follows the width the Server Maker gets, not the window (core audit tablet768, admin page in a card
// next to the admin menu). In a real browser, without a panel: on a 768px window a 430px wide Server Maker shows the
// phone step button, a 1200px wide one shows every step inside the bar. Needs Chrome or Chromium.
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const candidates = [process.env.CHROME, '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/opt/pw-browsers/chromium'].filter(Boolean);
const chrome = candidates.map((file) => (fs.existsSync(file) && fs.statSync(file).isDirectory() ? findChrome(file) : file)).find((file) => file && fs.existsSync(file));
function findChrome(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isFile() && entry.name === 'chrome') return full;
    if (entry.isDirectory()) { const found = findChrome(full); if (found) return found; }
  }
  return null;
}
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/steps.cjs (set CHROME)'); process.exit(1); }

const blade = fs.readFileSync(path.join(__dirname, '..', 'resources', 'views', 'wizard', 'styles.blade.php'), 'utf8');
const style = (blade.match(/<style>[\s\S]*?<\/style>/) || [''])[0];
const labels = ['Game', 'Minecraft', 'Server', 'Resources', 'Review'];
const maker = (width) => `<div style="width:${width}px"><div class="smk"><div class="smk-navbar"><nav class="smk-steps">
${labels.map((label, i) => `<button type="button" class="smk-step${i === 0 ? ' is-current' : ''}"><span>${i + 1}</span><b>${label}</b></button>`).join('')}
<button type="button" class="smk-steps-phone"><strong>Game</strong><small>Step 1 of 5</small></button></nav></div></div></div>`;
const page = `<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width">${style}<body style="margin:0">
${maker(430)}${maker(1200)}<pre id="out"></pre>
<script>
  const results = [];
  const ok = (name, pass, detail) => results.push((pass ? 'PASS ' : 'FAIL ') + name + (pass ? '' : ': ' + JSON.stringify(detail)));
  const shown = (el) => getComputedStyle(el).display !== 'none';
  const [narrow, wide] = document.querySelectorAll('.smk');
  const steps = (smk) => [...smk.querySelectorAll('.smk-step')];
  ok('a 430px wide Server Maker on a 768px window shows the phone step button', shown(narrow.querySelector('.smk-steps-phone')) && steps(narrow).every((el) => !shown(el)), { window: innerWidth, phone: shown(narrow.querySelector('.smk-steps-phone')), steps: steps(narrow).map(shown) });
  const bar = wide.querySelector('.smk-steps').getBoundingClientRect();
  const outside = steps(wide).filter((el) => { const r = el.getBoundingClientRect(); return !shown(el) || r.left < bar.left - 1 || r.right > bar.right + 1; }).map((el) => el.textContent);
  ok('a 1200px wide Server Maker shows every step inside the step bar', !shown(wide.querySelector('.smk-steps-phone')) && outside.length === 0, { outside, bar: [bar.left, bar.right] });
  document.getElementById('out').textContent = results.join('\\n') + '\\nDONE';
</script>`;

const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'smk-steps-'));
const file = path.join(dir, 'page.html');
fs.writeFileSync(file, page);
let dom = '';
try {
  dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--window-size=768,1000', '--virtual-time-budget=5000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
} finally {
  fs.rmSync(dir, { recursive: true, force: true });
}
const out = (dom.match(/<pre id="out">([\s\S]*?)<\/pre>/) || [])[1] || '';
const lines = out.replace(/&quot;/g, '"').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').split('\n').filter(Boolean);
if (!lines.includes('DONE')) { console.error('FAIL the step bar test did not finish in the browser'); process.exit(1); }
const tests = lines.filter((line) => line !== 'DONE');
tests.forEach((line) => console.log(line));
if (tests.some((line) => line.startsWith('FAIL'))) process.exit(1);
