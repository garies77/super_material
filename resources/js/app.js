// Pelacakan klik tombol beli (lapis 2: event browser ke GA4 / Meta Pixel)
document.addEventListener('click', (e) => {
    const link = e.target.closest('[data-buy]');
    if (!link) return;

    const params = {
        product: link.dataset.product,
        channel: link.dataset.channel,
        page: location.pathname,
    };

    if (typeof window.gtag === 'function') {
        window.gtag('event', 'buy_click', params);
    }
    if (typeof window.fbq === 'function') {
        window.fbq('trackCustom', 'BuyClick', params);
    }

    // Sertakan referrer asli agar tercatat di server
    if (document.referrer) {
        const url = new URL(link.href, location.origin);
        url.searchParams.set('ref', document.referrer);
        link.href = url.toString();
    }
});

const desktop = window.matchMedia('(min-width: 1024px)');
const lockScroll = (lock) => document.body.classList.toggle('overflow-hidden', lock);

// Overlay pencarian
const searchOverlay = document.querySelector('[data-search-overlay]');
let searchTrigger = null;
const setSearch = (open) => {
    if (!searchOverlay) return;
    searchOverlay.classList.toggle('hidden', !open);
    lockScroll(open);
    document.querySelectorAll('[data-search-open]').forEach((b) => b.setAttribute('aria-expanded', String(open)));
    if (open) {
        const input = searchOverlay.querySelector('input');
        input?.focus();
        input?.select();
    } else {
        searchTrigger?.focus();
    }
};
document.querySelectorAll('[data-search-open]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
        e.preventDefault(); // tanpa JS, tautan membuka halaman /cari
        searchTrigger = btn;
        setSearch(true);
    });
});
document.querySelectorAll('[data-search-close]').forEach((el) => el.addEventListener('click', () => setSearch(false)));

// Panel filter katalog (drawer di mobile)
const filterPanel = document.querySelector('[data-filter-panel]');
const setFilter = (open) => {
    if (!filterPanel) return;
    filterPanel.classList.toggle('hidden', !open);
    lockScroll(open && !desktop.matches);
    document.querySelectorAll('[data-filter-open]').forEach((b) => b.setAttribute('aria-expanded', String(open)));
};
document.querySelectorAll('[data-filter-open]').forEach((b) => b.addEventListener('click', () => setFilter(true)));
document.querySelectorAll('[data-filter-close]').forEach((b) => b.addEventListener('click', () => setFilter(false)));
desktop.addEventListener('change', () => setFilter(false));

// Di desktop, urutan & merek langsung diterapkan; di mobile menunggu tombol "Terapkan filter"
document.querySelectorAll('[data-autosubmit]').forEach((el) => {
    el.addEventListener('change', () => {
        if (desktop.matches) el.form?.requestSubmit();
    });
});

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (searchOverlay && !searchOverlay.classList.contains('hidden')) setSearch(false);
    if (filterPanel && !filterPanel.classList.contains('hidden') && !desktop.matches) setFilter(false);
});

// Galeri produk
document.querySelectorAll('[data-thumb]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const main = document.querySelector('[data-main-image]');
        if (main) main.src = btn.dataset.thumb;
        document.querySelectorAll('[data-thumb]').forEach((b) => b.classList.remove('ring-2', 'ring-brand-500'));
        btn.classList.add('ring-2', 'ring-brand-500');
    });
});

// Tombol Salin Tautan Berita / Artikel
document.querySelectorAll('[data-copy-link]').forEach((btn) => {
    btn.addEventListener('click', async () => {
        const url = btn.dataset.copyLink || window.location.href;
        try {
            await navigator.clipboard.writeText(url);
            const parent = btn.parentElement;
            const tooltip = parent?.querySelector('[data-copy-tooltip]');
            if (tooltip) {
                tooltip.classList.remove('hidden');
                setTimeout(() => tooltip.classList.add('hidden'), 2500);
            }
        } catch (err) {
            console.error('Gagal menyalin URL', err);
        }
    });
});

// Penanganan navigasi daftar isi (TOC) & in-page anchor
document.addEventListener('click', (e) => {
    const link = e.target.closest('a[href^="#"]');
    if (!link) return;

    const href = link.getAttribute('href');
    if (!href || href === '#' || href.length < 2) return;

    const id = decodeURIComponent(href.slice(1));
    const target = document.getElementById(id);
    if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.pushState(null, '', href);
    }
});


// Iklan internal (PRD 8.8): impresi dicatat saat ≥50% slot terlihat selama 1 detik
const sendAdEvent = (slot, type) => {
    const body = new FormData();
    body.append('_token', slot.dataset.adToken);
    body.append('type', type);
    body.append('s', slot.dataset.adSlot);
    body.append('a', slot.dataset.adArticle);
    if (!navigator.sendBeacon?.(slot.dataset.adEvent, body)) {
        fetch(slot.dataset.adEvent, { method: 'POST', body, keepalive: true, credentials: 'same-origin' }).catch(() => {});
    }
};

const adSlots = document.querySelectorAll('[data-ad-event]');
if (adSlots.length && 'IntersectionObserver' in window) {
    const timers = new WeakMap();
    // Slot sidebar punya salinan mobile; satu iklan dihitung sekali per tampilan halaman
    const seen = new Set();
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            const slot = entry.target;
            if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
                if (!timers.has(slot)) {
                    timers.set(slot, setTimeout(() => {
                        observer.unobserve(slot);
                        if (seen.has(slot.dataset.adId)) return;
                        seen.add(slot.dataset.adId);
                        sendAdEvent(slot, 'impression');
                    }, 1000));
                }
            } else if (timers.has(slot)) {
                clearTimeout(timers.get(slot));
                timers.delete(slot);
            }
        });
    }, { threshold: [0, 0.5] });
    adSlots.forEach((slot) => observer.observe(slot));
}

// Klik iklan bertautan internal (tanpa /ad/) dicatat lewat beacon
document.addEventListener('click', (e) => {
    const link = e.target.closest('a[data-ad-internal]');
    const slot = link?.closest('[data-ad-event]');
    if (slot) sendAdEvent(slot, 'click');
});
