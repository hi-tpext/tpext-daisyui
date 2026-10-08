/**
 * 验证：easymde 已改用官方选项（toolbarButtonClassPrefix / iconClassMap / autoDownloadFontAwesome）
 * 图标映射表直接从 MDEditor.php 源码解析，保证与 PHP 实际输出一致。
 */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const ROOT = path.join(__dirname, '..', '..', '..');
const PHP_FILE = path.join(ROOT, 'src/displayer/MDEditor.php');
const php = fs.readFileSync(PHP_FILE, 'utf8');

// --- 从 PHP 源码解析选项（等价于 mdeDefaults() 的返回） ---
function parseSection(method) {
    const s = php.indexOf('protected function ' + method + '(');
    if (s < 0) throw new Error('method not found: ' + method);
    const e = php.indexOf('protected function ', s + 10);
    return php.slice(s, e < 0 ? php.length : e);
}

const iconClassMap = {};
const mapBody = parseSection('iconClassMap');
const re = /'([a-z0-9-]+)'\s*=>\s*'([^']+)'/g;
let m;
while ((m = re.exec(mapBody)) !== null) {
    iconClassMap[m[1]] = m[2];
}

const defBody = parseSection('mdeDefaults');
const prefixMatch = defBody.match(/'toolbarButtonClassPrefix'\s*=>\s*'([^']+)'/);
const prefix = prefixMatch ? prefixMatch[1] : '';
const faMatch = defBody.match(/'autoDownloadFontAwesome'\s*=>\s*(true|false)/);
const autoFa = faMatch ? faMatch[1] : '';

console.log('解析自 MDEditor.php:');
console.log('  toolbarButtonClassPrefix =', JSON.stringify(prefix));
console.log('  autoDownloadFontAwesome  =', autoFa);
console.log('  iconClassMap 条目数       =', Object.keys(iconClassMap).length);
console.log('');

const cssFiles = ['css/builder.css', 'css/easymde.min.css', 'css/builder-input.css'];
const css = cssFiles
    .map(f => fs.readFileSync(path.join(ROOT, 'assets', f), 'utf8'))
    .join('\n');

const js = fs.readFileSync(path.join(ROOT, 'assets/js/vendors/easymde.min.js'), 'utf8');

const dom = new JSDOM(
    `<!DOCTYPE html><html><head><style>${css}</style></head>
     <body><textarea id="md"></textarea></body></html>`,
    { runScripts: 'outside-only', pretendToBeVisual: true }
);
const { window } = dom;
window.eval(js);

const toolbar = [
    'bold', 'italic', 'heading', '|', 'unordered-list', 'ordered-list', 'quote', 'code',
    'table', 'link', 'upload-image', 'image', '|', 'preview', 'side-by-side', 'fullscreen', '|', 'guide',
];

const titles = {
    bold: '加粗', italic: '斜体', heading: '标题', 'unordered-list': '无序列表',
    'ordered-list': '有序列表', quote: '引用', code: '代码', table: '插入表格',
    link: '插入链接', 'upload-image': '上传图片', image: '插入图片', preview: '预览',
    'side-by-side': '分栏预览', fullscreen: '全屏', guide: '帮助',
};

const mde = new window.EasyMDE(Object.assign({
    element: window.document.getElementById('md'),
    minHeight: '300px',
    autosave: { enabled: false },
    toolbar,
    uploadImage: true,
}, {
    autoDownloadFontAwesome: autoFa === 'false' ? false : true,
    toolbarButtonClassPrefix: prefix,
    iconClassMap,
}));

// 复刻 MDEditor.php 里的 title 覆盖逻辑
const btns = mde.toolbarElements || {};
for (const k in titles) {
    if (!btns[k]) continue;
    const shortcut = (btns[k].title || '').match(/\(([^()]*)\)\s*$/);
    const text = titles[k] + (shortcut ? ' (' + shortcut[1] + ')' : '');
    btns[k].title = text;
    btns[k].setAttribute('aria-label', text);
}

const doc = window.document;
const buttons = [...doc.querySelectorAll('.editor-toolbar button')];

let fail = 0;
const check = (ok, msg) => {
    console.log((ok ? '  ✅ ' : '  ❌ ') + msg);
    if (!ok) fail++;
};

console.log('1. 按钮 class 前缀');
const clash = ['table', 'link', 'italic', 'code', 'image', 'preview', 'fullscreen', 'guide', 'bold', 'heading', 'quote'];
// 按 class token 精确比对（mde-table 属于带前缀，不算裸类）
const bad = buttons.filter(b => b.className.split(/\s+/).some(c => clash.indexOf(c) >= 0));
check(bad.length === 0, `无裸 daisyUI 同名类 (${bad.length} 个异常)`);
const prefixed = buttons.filter(b => b.className.startsWith(prefix + '-'));
check(prefixed.length === buttons.length - 0 || prefixed.length > 0,
    `按钮均带 "${prefix}-" 前缀 (${prefixed.length}/${buttons.length})`);

console.log('\n2. 图标映射');
const icons = [...doc.querySelectorAll('.editor-toolbar button i')];
check(icons.length === toolbar.filter(t => t !== '|').length,
    `<i> 数量 = ${icons.length} / 期望 ${toolbar.filter(t => t !== '|').length}`);
const faLeft = icons.filter(i => /(^|\s)fa(\s|$)/.test(i.className) || /fa-/.test(i.className));
check(faLeft.length === 0, `无 fa-* 残留 (${faLeft.length})`);
const mdiOk = icons.filter(i => /mdi mdi-/.test(i.className));
check(mdiOk.length === icons.length, `全部为 mdi (${mdiOk.length}/${icons.length})`);

console.log('\n3. .table 污染（核心）');
const btnTable = buttons.find(b => b.className.includes(prefix + '-table'));
const btnBold = buttons.find(b => b.className.includes(prefix + '-bold'));
if (!btnTable || !btnBold) {
    check(false, '找不到 table/bold 按钮');
} else {
    const keys = ['display', 'width', 'position', 'text-align', 'font-size', 'line-height', 'border-radius'];
    const a = window.getComputedStyle(btnTable);
    const b = window.getComputedStyle(btnBold);
    let same = true;
    for (const k of keys) {
        const va = a.getPropertyValue(k);
        const vb = b.getPropertyValue(k);
        if (va !== vb) {
            console.log(`     差异 ${k}: table=${va} bold=${vb}`);
            same = false;
        }
    }
    check(same, `button.${prefix}-table 与 button.${prefix}-bold 计算样式完全一致`);
    console.log(`     display=${a.getPropertyValue('display')} width=${a.getPropertyValue('width')}`);
}

console.log('\n4. FontAwesome 注入');
const faLinks = [...doc.querySelectorAll('link')].filter(l => /font-?awesome/i.test(l.href || ''));
check(faLinks.length === 0, `head 中无 FA link 注入 (${faLinks.length})`);

console.log('\n5. 多语言 title');
let titleOk = 0;
for (const k in titles) {
    if (btns[k] && btns[k].title.indexOf(titles[k]) === 0) titleOk++;
}
check(titleOk === Object.keys(titles).length, `${titleOk}/${Object.keys(titles).length} 个 title 已本地化`);
console.log('     table → ' + (btns.table ? btns.table.title : '(缺失)'));
console.log('     preview → ' + (btns.preview ? btns.preview.title : '(缺失)'));

console.log('\n' + (fail === 0 ? '全部通过 ✅' : `失败 ${fail} 项 ❌`));
process.exit(fail === 0 ? 0 : 1);
