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

// Live search: debounce 250 ms; permintaan lama dibatalkan (AbortController) dan respons usang diabaikan (nomor urut)
if (searchOverlay?.dataset.searchLive) {
    const input = searchOverlay.querySelector('#search-input');
    const list = searchOverlay.querySelector('[data-search-results]');
    const status = searchOverlay.querySelector('[data-search-status]');
    const spinner = searchOverlay.querySelector('[data-search-spinner]');
    const hint = status.innerHTML;
    const type = searchOverlay.dataset.searchType;
    let timer;
    let controller;
    let seq = 0;
    let active = -1;

    const el = (tag, cls, text) => {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text) n.textContent = text;
        return n;
    };
    // Sorot kata kunci tanpa innerHTML (teks hasil tidak pernah dirender sebagai HTML)
    const highlight = (text, q) => {
        const frag = document.createDocumentFragment();
        const lower = text.toLowerCase();
        const needle = q.toLowerCase();
        let i = 0;
        let at;
        while (needle && (at = lower.indexOf(needle, i)) !== -1) {
            frag.append(text.slice(i, at), el('mark', 'bg-brand-100 text-inherit rounded-sm', text.slice(at, at + needle.length)));
            i = at + needle.length;
        }
        frag.append(text.slice(i));
        return frag;
    };
    const options = () => [...list.querySelectorAll('[role="option"]')];
    const setActive = (i) => {
        const opts = options();
        active = opts.length ? (i + opts.length) % opts.length : -1;
        opts.forEach((o, n) => o.setAttribute('aria-selected', String(n === active)));
        if (active >= 0) {
            input.setAttribute('aria-activedescendant', opts[active].id);
            opts[active].scrollIntoView({ block: 'nearest' });
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    };
    const reset = (message = hint) => {
        list.replaceChildren();
        status.innerHTML = message;
        input.setAttribute('aria-expanded', 'false');
        active = -1;
        input.removeAttribute('aria-activedescendant');
    };
    const group = (label, items, q, offset) => {
        const section = el('div', 'py-1');
        section.setAttribute('role', 'group');
        section.setAttribute('aria-label', label);
        if (!type) section.append(el('p', 'px-3 pb-1 pt-2 text-[11px] font-bold uppercase tracking-wider text-ink-400', label));
        items.forEach((item, n) => {
            const a = el('a', 'flex items-center gap-3 rounded-xl px-3 py-2 outline-none hover:bg-ink-50 aria-selected:bg-brand-50 focus-visible:ring-2 focus-visible:ring-brand-500');
            a.href = item.url;
            a.id = `search-opt-${offset + n}`;
            a.setAttribute('role', 'option');
            a.setAttribute('aria-selected', 'false');
            const thumb = el('span', 'grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-lg bg-ink-100 text-sm font-bold text-ink-400');
            if (item.image) {
                const img = el('img', 'h-full w-full object-cover');
                img.src = item.image;
                img.alt = '';
                img.width = 48;
                img.height = 48;
                img.loading = 'lazy';
                thumb.append(img);
            } else {
                thumb.textContent = item.title.charAt(0).toUpperCase();
            }
            const body = el('span', 'min-w-0 flex-1');
            const title = el('span', 'block truncate text-sm font-semibold text-ink-900');
            title.append(highlight(item.title, q));
            body.append(title);
            if (item.excerpt) body.append(el('span', 'block truncate text-xs text-ink-500', item.excerpt));
            if (item.meta) body.append(el('span', 'block truncate text-xs text-ink-400', item.meta));
            a.append(thumb, body);
            section.append(a);
        });
        return section;
    };

    const search = async (q) => {
        controller?.abort();
        controller = new AbortController();
        const mine = ++seq;
        spinner.classList.remove('hidden');
        try {
            const url = new URL(searchOverlay.dataset.searchLive, location.origin);
            url.searchParams.set('q', q);
            if (type) url.searchParams.set('type', type);
            const res = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!res.ok) throw new Error(res.status);
            const data = await res.json();
            if (mine !== seq) return; // sudah ada ketikan yang lebih baru

            list.replaceChildren();
            active = -1;
            if (data.products.length) list.append(group('Produk', data.products, q, 0));
            if (data.articles.length) list.append(group('Artikel', data.articles, q, data.products.length));
            const total = data.products.length + data.articles.length;
            input.setAttribute('aria-expanded', String(total > 0));
            const what = type === 'artikel' ? 'artikel' : type === 'produk' ? 'produk' : 'produk atau artikel';
            status.textContent = total
                ? `${total} hasil teratas. Tekan Enter untuk melihat semua hasil.`
                : `Tidak ada ${what} yang cocok dengan “${q}”. Coba kata kunci lain.`;
        } catch (err) {
            if (err.name === 'AbortError' || mine !== seq) return;
            reset('Pencarian langsung sedang tidak tersedia. Tekan Enter untuk mencari.');
        } finally {
            if (mine === seq) spinner.classList.add('hidden');
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim().replace(/\s+/g, ' ');
        if (q.length < 2) {
            controller?.abort();
            seq++;
            spinner.classList.add('hidden');
            reset(q.length ? 'Ketik minimal 2 huruf.' : hint);
            return;
        }
        timer = setTimeout(() => search(q), 250);
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            if (!options().length) return;
            e.preventDefault();
            setActive(active + (e.key === 'ArrowDown' ? 1 : -1));
        } else if (e.key === 'Enter' && active >= 0) {
            e.preventDefault();
            options()[active].click();
        }
    });
}

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
// Clipboard API hanya ada di koneksi aman (HTTPS valid); selain itu pakai textarea + execCommand
async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch {}
    }
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
    document.body.appendChild(ta);
    ta.select();
    let ok = false;
    try { ok = document.execCommand('copy'); } catch {}
    ta.remove();
    return ok;
}

document.querySelectorAll('[data-copy-link]').forEach((btn) => {
    const label = btn.querySelector('span');
    const original = label?.textContent;
    btn.addEventListener('click', async () => {
        const url = btn.dataset.copyLink || window.location.href;
        const ok = await copyText(url);
        if (!ok) {
            window.prompt('Salin tautan ini:', url);
            return;
        }
        const tooltip = btn.parentElement?.querySelector('[data-copy-tooltip]');
        tooltip?.classList.remove('hidden');
        if (label) label.textContent = 'Tersalin!';
        setTimeout(() => {
            tooltip?.classList.add('hidden');
            if (label) label.textContent = original;
        }, 2500);
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
    body.append('_token', slot.dataset.smToken);
    body.append('type', type);
    body.append('s', slot.dataset.smSlot);
    body.append('a', slot.dataset.smArticle);
    if (!navigator.sendBeacon?.(slot.dataset.smEvent, body)) {
        fetch(slot.dataset.smEvent, { method: 'POST', body, keepalive: true, credentials: 'same-origin' }).catch(() => {});
    }
};

const featureSlots = document.querySelectorAll('[data-sm-event]');
if (featureSlots.length && 'IntersectionObserver' in window) {
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
                        if (seen.has(slot.dataset.smId)) return;
                        seen.add(slot.dataset.smId);
                        sendAdEvent(slot, 'impression');
                    }, 1000));
                }
            } else if (timers.has(slot)) {
                clearTimeout(timers.get(slot));
                timers.delete(slot);
            }
        });
    }, { threshold: [0, 0.5] });
    featureSlots.forEach((slot) => observer.observe(slot));
}

// Klik iklan bertautan internal (tanpa /ad/) dicatat lewat beacon
document.addEventListener('click', (e) => {
    const link = e.target.closest('a[data-sm-internal]');
    const slot = link?.closest('[data-sm-event]');
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
