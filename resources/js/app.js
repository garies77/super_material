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
    body.append('_token', slot.dataset.promoToken);
    body.append('type', type);
    body.append('s', slot.dataset.promoSlot);
    body.append('a', slot.dataset.promoArticle);
    if (!navigator.sendBeacon?.(slot.dataset.promoEvent, body)) {
        fetch(slot.dataset.promoEvent, { method: 'POST', body, keepalive: true, credentials: 'same-origin' }).catch(() => {});
    }
};

const promoSlots = document.querySelectorAll('[data-promo-event]');
if (promoSlots.length && 'IntersectionObserver' in window) {
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
                        if (seen.has(slot.dataset.promoId)) return;
                        seen.add(slot.dataset.promoId);
                        sendAdEvent(slot, 'impression');
                    }, 1000));
                }
            } else if (timers.has(slot)) {
                clearTimeout(timers.get(slot));
                timers.delete(slot);
            }
        });
    }, { threshold: [0, 0.5] });
    promoSlots.forEach((slot) => observer.observe(slot));
}

// Klik iklan bertautan internal (tanpa /ad/) dicatat lewat beacon
document.addEventListener('click', (e) => {
    const link = e.target.closest('a[data-promo-internal]');
    const slot = link?.closest('[data-promo-event]');
    if (slot) sendAdEvent(slot, 'click');
});

// Banner kampanye modal (PRD 8.9). Status tampil/tutup disimpan di browser per ID kampanye,
// tanpa data pribadi. Satu modal per halaman; kandidat sudah diurutkan prioritas oleh server.
const campaignRoot = document.querySelector('[data-sm-campaign-root]');
if (campaignRoot) {
    const STORE = 'sm-campaign';
    const DAY = 86400000;
    const preview = campaignRoot.hasAttribute('data-sm-campaign-preview');
    const read = (storage) => {
        try { return JSON.parse(storage.getItem(STORE)) || {}; } catch { return {}; }
    };
    const write = (storage, data) => {
        try { storage.setItem(STORE, JSON.stringify(data)); } catch { /* penyimpanan diblokir */ }
    };
    const remember = (id, patch) => {
        const local = read(localStorage);
        local[id] = { ...local[id], ...patch };
        write(localStorage, local);
    };

    const isMobile = () => window.matchMedia('(max-width: 767px)').matches;
    const suppressed = (cfg) => {
        if (cfg.device === 'desktop' && isMobile()) return true;
        if (cfg.device === 'mobile' && !isMobile()) return true;
        const rec = read(localStorage)[cfg.id];
        if (rec?.never) return true;
        if (cfg.frequency === 'once') return Boolean(rec);
        if (cfg.frequency === 'session') return Boolean(read(sessionStorage)[cfg.id]);
        return Boolean(rec?.t) && Date.now() - rec.t < cfg.days * DAY;
    };

    const templates = [...campaignRoot.querySelectorAll('template[data-sm-campaign]')];
    const pick = templates
        .map((tpl) => ({ tpl, cfg: JSON.parse(tpl.dataset.smCampaign) }))
        .find(({ cfg }) => preview || !suppressed(cfg));

    const send = (tpl, type) => {
        if (preview) return;
        const body = new FormData();
        body.append('_token', campaignRoot.dataset.smCampaignToken);
        body.append('type', type);
        body.append('d', isMobile() ? 'mobile' : 'desktop');
        body.append('p', location.pathname);
        if (!navigator.sendBeacon?.(tpl.dataset.smCampaignEvent, body)) {
            fetch(tpl.dataset.smCampaignEvent, { method: 'POST', body, keepalive: true, credentials: 'same-origin' }).catch(() => {});
        }
    };

    const open = ({ tpl, cfg }) => {
        // Tunggu bila overlay lain (pencarian, filter) sedang terbuka
        if (document.body.classList.contains('overflow-hidden')) {
            setTimeout(() => open({ tpl, cfg }), 2000);
            return;
        }

        const modal = tpl.content.firstElementChild.cloneNode(true);
        const dialog = modal.querySelector('[role="dialog"]');
        const returnTo = document.activeElement;
        document.body.appendChild(modal);
        lockScroll(true);

        if (!preview) {
            remember(cfg.id, { t: Date.now() });
            const session = read(sessionStorage);
            session[cfg.id] = 1;
            write(sessionStorage, session);
        }
        send(tpl, 'impression');
        if (typeof window.gtag === 'function' && !preview) {
            window.gtag('event', 'campaign_view', { campaign_name: tpl.dataset.smCampaignUtm });
        }

        const focusables = () => [...dialog.querySelectorAll('a[href], button, [tabindex]:not([tabindex="-1"])')]
            .filter((el) => el.offsetParent !== null);

        const close = (type) => {
            modal.remove();
            lockScroll(false);
            document.removeEventListener('keydown', onKey);
            if (type) {
                if (!preview) remember(cfg.id, { t: Date.now() });
                send(tpl, type);
            }
            returnTo?.focus?.();
        };

        const onKey = (e) => {
            if (e.key === 'Escape') {
                e.stopPropagation();
                close('close');
            } else if (e.key === 'Tab') {
                // Fokus terkunci di dalam modal
                const items = focusables();
                if (!items.length) return;
                const first = items[0];
                const last = items[items.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        };
        document.addEventListener('keydown', onKey);

        modal.querySelectorAll('[data-sm-campaign-dismiss]').forEach((el) => el.addEventListener('click', () => close('close')));
        modal.querySelector('[data-sm-campaign-never]')?.addEventListener('click', () => {
            if (!preview) remember(cfg.id, { never: true });
            close('close');
        });
        modal.querySelectorAll('[data-sm-campaign-cta]').forEach((link) => link.addEventListener('click', () => {
            send(tpl, 'click');
            if (typeof window.gtag === 'function' && !preview) {
                window.gtag('event', 'campaign_click', { campaign_name: tpl.dataset.smCampaignUtm, link_url: link.href });
            }
            close(null);
        }));

        (modal.querySelector('button[data-sm-campaign-dismiss]') || dialog).focus();
    };

    const arm = (choice) => {
        const { cfg } = choice;
        if (preview) {
            open(choice);
            return;
        }

        const pageScrollable = () => document.documentElement.scrollHeight - window.innerHeight > 200;
        if (cfg.trigger === 'scroll' && pageScrollable()) {
            const onScroll = () => {
                const max = document.documentElement.scrollHeight - window.innerHeight;
                if (max > 0 && window.scrollY / max >= cfg.scroll / 100) {
                    window.removeEventListener('scroll', onScroll);
                    open(choice);
                }
            };
            window.addEventListener('scroll', onScroll, { passive: true });
        } else {
            // Jeda dihitung setelah halaman selesai dimuat; halaman pendek tanpa gulir memakai jeda bawaan
            setTimeout(() => open(choice), (cfg.trigger === 'delay' ? cfg.delay : 5) * 1000);
        }
    };

    if (pick) {
        if (document.readyState === 'complete') arm(pick);
        else window.addEventListener('load', () => arm(pick), { once: true });
    }
}
