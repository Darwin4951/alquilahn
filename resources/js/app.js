// Livewire 3 loads Alpine; do not start a second Alpine instance.
// Keep metadata in sync with applied filters and pagination, without a reload.
window.addEventListener('seo-updated', (event) => {
    const seo = event.detail?.seo;
    if (!seo || typeof seo.title !== 'string') return;
    document.title = seo.title;
    const values = {
        'meta[name="description"]': seo.description,
        'meta[name="robots"]': seo.robots,
        'meta[property="og:title"]': seo.title,
        'meta[property="og:description"]': seo.description,
        'meta[property="og:image"]': seo.image,
        'meta[property="og:url"]': seo.canonical,
        'meta[name="twitter:title"]': seo.title,
        'meta[name="twitter:description"]': seo.description,
        'meta[name="twitter:image"]': seo.image,
    };
    Object.entries(values).forEach(([selector, content]) => {
        const element = document.head.querySelector(selector);
        if (element) element.setAttribute('content', content);
    });
    document.head.querySelector('link[rel="canonical"]')?.setAttribute('href', seo.canonical);
    const schema = document.getElementById('seo-schema');
    if (schema) schema.textContent = JSON.stringify(seo.schema);
});
