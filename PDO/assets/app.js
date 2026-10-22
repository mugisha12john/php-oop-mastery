const appTranslations = JSON.parse(document.getElementById('app-translations')?.textContent || '{}');
const message = (key, values) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(':' + name, value), appTranslations[key] || '');

document.querySelectorAll('[data-print]').forEach(button => {
    button.addEventListener('click', () => window.print());
});

const menu = document.querySelector('.workspace-menu');
const media = window.matchMedia('(max-width: 900px)');
if (menu) {
    const syncMenu = () => { menu.open = !media.matches; };
    syncMenu();
    media.addEventListener('change', syncMenu);
}

const productSelect = document.querySelector('[data-product-select]');
if (productSelect) {
    const amount = document.querySelector('[name="amount_requested_rwf"]');
    const term = document.querySelector('[name="term_months"]');
    const hint = document.querySelector('[data-product-hint]');
    const estimate = document.querySelector('[data-estimate]');
    // Match PHP formatting even in browsers whose locale data omits Kinyarwanda.
    const numberFormat = new Intl.NumberFormat('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const format = value => numberFormat.formatToParts(value).map(part => {
        if (part.type === 'group') return appTranslations.locale === 'fr' ? ' ' : appTranslations.locale === 'rw' ? '.' : ',';
        if (part.type === 'decimal') return appTranslations.locale === 'en' ? '.' : ',';
        return part.value;
    }).join('');
    const update = () => {
        const option = productSelect.selectedOptions[0];
        if (!option || !option.dataset.rate) return;
        amount.min = option.dataset.min;
        amount.max = option.dataset.max;
        term.max = option.dataset.term;
        hint.textContent = message('productHint', {min: format(Number(option.dataset.min)), max: format(Number(option.dataset.max)), term: option.dataset.term, rate: format(Number(option.dataset.rate))});
        const principal = Number(amount.value);
        const months = Number(term.value);
        const total = principal * (1 + Number(option.dataset.rate) / 100);
        estimate.textContent = principal > 0 && months > 0 ? message('estimate', {total: format(total), monthly: format(total / months)}) : appTranslations.estimateEmpty;
    };
    productSelect.addEventListener('change', update);
    amount.addEventListener('input', update);
    term.addEventListener('input', update);
    update();
}
