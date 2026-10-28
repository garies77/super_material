{{-- Gaya tambahan panel admin (render hook STYLES_AFTER). Scrollbar sidebar ramping 6px, track transparan, tanpa tombol panah. --}}
<style>
    .fi-sidebar-nav {
        --sm-thumb: rgb(0 0 0 / 0.16);
        --sm-thumb-hover: rgb(0 0 0 / 0.32);
    }

    .dark .fi-sidebar-nav {
        --sm-thumb: rgb(255 255 255 / 0.16);
        --sm-thumb-hover: rgb(255 255 255 / 0.32);
    }

    /* Chrome, Edge, Safari: kontrol penuh lewat pseudo-element. Jangan set scrollbar-width/color di sini,
       karena Chromium 121+ lalu mengabaikan ::-webkit-scrollbar dan kembali ke scrollbar "thin" bawaan OS. */
    .fi-sidebar-nav::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }

    .fi-sidebar-nav::-webkit-scrollbar-track,
    .fi-sidebar-nav::-webkit-scrollbar-corner {
        background: transparent;
    }

    .fi-sidebar-nav::-webkit-scrollbar-button {
        display: none;
        width: 0;
        height: 0;
    }

    .fi-sidebar-nav::-webkit-scrollbar-thumb {
        background-color: var(--sm-thumb);
        border-radius: 9999px;
    }

    .fi-sidebar-nav:hover::-webkit-scrollbar-thumb {
        background-color: var(--sm-thumb-hover);
    }

    /* Firefox: tidak mendukung ::-webkit-scrollbar, pakai properti standar */
    @supports not selector(::-webkit-scrollbar) {
        .fi-sidebar-nav {
            scrollbar-width: thin;
            scrollbar-color: var(--sm-thumb) transparent;
        }

        .fi-sidebar-nav:hover {
            scrollbar-color: var(--sm-thumb-hover) transparent;
        }
    }

    /* Kotak verifikasi sudo di form Pengguna: footer callout berupa baris flex, buat field password selebar kotak */
    .sm-sudo-callout .fi-callout-footer > .fi-sc,
    .sm-sudo-callout .fi-callout-footer > .fi-sc > * {
        flex: 1 1 100%;
        max-width: 28rem;
    }

    /* Pustaka Media: grid pilih gambar di modal "Pilih dari Media" */
    .sm-media-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(8.5rem, 1fr));
        gap: 0.75rem;
        max-height: 26rem;
        overflow-y: auto;
        padding: 0.25rem;
    }

    .sm-media-item {
        position: relative;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border-radius: 0.5rem;
        background: rgb(249 250 251);
        box-shadow: 0 0 0 1px rgb(0 0 0 / 0.1);
        text-align: left;
        transition: box-shadow 0.15s;
    }

    .dark .sm-media-item {
        background: rgb(255 255 255 / 0.05);
        box-shadow: 0 0 0 1px rgb(255 255 255 / 0.1);
    }

    .sm-media-item:hover {
        box-shadow: 0 0 0 2px var(--primary-300, #93c5fd);
    }

    .sm-media-item.is-selected {
        box-shadow: 0 0 0 3px var(--primary-500, #3b82f6);
    }

    .sm-media-item img {
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: contain;
        background: repeating-conic-gradient(rgb(0 0 0 / 0.04) 0 25%, transparent 0 50%) 0 0 / 16px 16px;
    }

    .sm-media-badge {
        position: absolute;
        top: 0.375rem;
        right: 0.375rem;
        min-width: 1.5rem;
        height: 1.5rem;
        padding: 0 0.375rem;
        border-radius: 9999px;
        background: var(--primary-500, #3b82f6);
        color: #fff;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1.5rem;
        text-align: center;
    }

    .sm-media-fit {
        position: absolute;
        top: 0.375rem;
        left: 0.375rem;
        padding: 0 0.375rem;
        border-radius: 0.25rem;
        background: rgb(0 0 0 / 0.55);
        color: #fff;
        font-size: 0.6875rem;
        line-height: 1.25rem;
    }

    .sm-media-fit.is-fit {
        background: rgb(22 163 74 / 0.9);
    }

    /* Pustaka Media: mode pilih untuk hapus (tanpa checkbox). Kartu terpilih berbingkai & berlabel merah */
    .sm-hidden {
        display: none !important;
    }

    .sm-select-bar {
        position: fixed;
        bottom: 1.25rem;
        left: 50%;
        z-index: 30;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 0.75rem 0.5rem 1rem;
        border-radius: 0.75rem;
        background: rgb(17 24 39);
        color: #fff;
        font-size: 0.875rem;
        box-shadow: 0 10px 25px rgb(0 0 0 / 0.35);
        transform: translateX(-50%);
    }

    .sm-select-bar {
        flex-wrap: wrap;
        justify-content: center;
        max-width: calc(100vw - 2rem);
    }

    .sm-select-hint {
        font-size: 0.75rem;
        color: rgb(156 163 175);
    }

    /* Pustaka Media: tombol aksi ⋮ melayang di pojok kanan atas kartu agar kartu ringkas */
    .fi-ta-record.sm-media-card {
        position: relative;
    }

    .fi-ta-record.sm-media-card .fi-ta-actions {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        z-index: 1;
        margin: 0;
        padding: 0;
    }

    .fi-ta-record.sm-media-card .fi-ta-actions .fi-icon-btn {
        border-radius: 9999px;
        background: rgb(255 255 255 / 0.85);
        box-shadow: 0 1px 3px rgb(0 0 0 / 0.25);
    }

    .dark .fi-ta-record.sm-media-card .fi-ta-actions .fi-icon-btn {
        background: rgb(17 24 39 / 0.8);
    }

    .fi-ta-record.sm-media-card-selectable {
        position: relative;
        cursor: pointer;
        user-select: none;
        transition: box-shadow 0.15s, background-color 0.15s;
    }

    .fi-ta-record.sm-media-card-selectable:hover {
        box-shadow: 0 0 0 2px rgb(239 68 68 / 0.45);
    }

    .fi-ta-record.sm-media-card-selected {
        box-shadow: 0 0 0 3px rgb(239 68 68);
        background-color: rgb(239 68 68 / 0.12);
    }

    .fi-ta-record.sm-media-card-selected::after {
        content: 'Dipilih';
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        padding: 0 0.5rem;
        border-radius: 9999px;
        background: rgb(239 68 68);
        color: #fff;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1.5rem;
        pointer-events: none;
    }

    /* Panel skor SEO langsung di form produk & artikel */
    .sm-seo-head {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
        font-size: 0.875rem;
    }

    .sm-seo-score {
        flex: none;
        min-width: 3.5rem;
        padding: 0.375rem 0.5rem;
        border-radius: 0.5rem;
        color: #fff;
        font-size: 1.125rem;
        font-weight: 700;
        text-align: center;
    }

    .sm-seo-score.is-good {
        background: rgb(22 163 74);
    }

    .sm-seo-score.is-fair {
        background: rgb(217 119 6);
    }

    .sm-seo-score.is-poor {
        background: rgb(220 38 38);
    }

    .sm-seo-sub {
        font-size: 0.75rem;
        color: rgb(107 114 128);
    }

    .sm-seo-list {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        font-size: 0.8125rem;
    }

    .sm-seo-list li {
        display: flex;
        gap: 0.5rem;
        color: rgb(220 38 38);
    }

    .sm-seo-list li.is-passed {
        color: rgb(107 114 128);
    }

    .sm-seo-list li.is-passed .sm-seo-mark {
        color: rgb(22 163 74);
    }

    .sm-seo-list li.is-passed > span:last-child {
        text-decoration: line-through;
    }

    .sm-seo-goto {
        display: flex;
        gap: 0.5rem;
        padding: 0;
        color: inherit;
        text-align: left;
        cursor: pointer;
    }

    .sm-seo-goto:hover span:last-child {
        text-decoration: underline;
    }

    .sm-seo-progress {
        height: 0.5rem;
        margin-bottom: 0.75rem;
        overflow: hidden;
        border-radius: 9999px;
        background: rgb(0 0 0 / 0.08);
    }

    .dark .sm-seo-progress {
        background: rgb(255 255 255 / 0.1);
    }

    .sm-seo-progress span {
        display: block;
        height: 100%;
        border-radius: inherit;
        transition: width 0.5s ease;
    }

    .sm-seo-progress .is-good {
        background: rgb(22 163 74);
    }

    .sm-seo-progress .is-fair {
        background: rgb(217 119 6);
    }

    .sm-seo-progress .is-poor {
        background: rgb(220 38 38);
    }

    /* Papan misi dashboard (filament/widgets/mission-board) */
    .sm-mission-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(20rem, 1fr));
        gap: 0.75rem;
    }

    .sm-mission,
    .sm-mission-empty {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        border-radius: 0.75rem;
        box-shadow: 0 0 0 1px rgb(0 0 0 / 0.08);
    }

    .dark .sm-mission,
    .dark .sm-mission-empty {
        box-shadow: 0 0 0 1px rgb(255 255 255 / 0.1);
    }

    .sm-mission-empty {
        flex-wrap: wrap;
    }

    .sm-mission-icon {
        flex: none;
        font-size: 1.75rem;
        line-height: 1;
    }

    .sm-mission-body {
        display: flex;
        flex: 1;
        flex-direction: column;
        min-width: 0;
        font-size: 0.875rem;
    }

    .sm-mission-count {
        flex: none;
        min-width: 2rem;
        padding: 0.125rem 0.5rem;
        border-radius: 9999px;
        background: var(--primary-500, #f97316);
        color: #fff;
        font-size: 0.875rem;
        font-weight: 700;
        text-align: center;
    }

    /* Confetti perayaan (filament/celebrate.blade.php) */
    .sm-confetti {
        position: fixed;
        inset: 0;
        z-index: 60;
        overflow: hidden;
        pointer-events: none;
    }

    .sm-confetti span {
        position: absolute;
        top: -1rem;
        width: 0.5rem;
        height: 0.9rem;
        border-radius: 2px;
        animation-name: sm-confetti-fall;
        animation-timing-function: cubic-bezier(0.25, 0.6, 0.4, 1);
        animation-fill-mode: forwards;
    }

    @keyframes sm-confetti-fall {
        to {
            transform: translate(var(--sm-drift), 105vh) rotate(var(--sm-spin));
            opacity: 0.6;
        }
    }

    /* Pustaka Media: pemilih titik fokus */
    .sm-focal {
        position: relative;
        width: fit-content;
        max-width: 100%;
        margin: 0 auto;
        cursor: crosshair;
        user-select: none;
    }

    .sm-focal img {
        display: block;
        max-height: 24rem;
        max-width: 100%;
    }

    .sm-focal-marker {
        position: absolute;
        width: 1.75rem;
        height: 1.75rem;
        border: 3px solid #fff;
        border-radius: 9999px;
        background: rgb(239 68 68 / 0.45);
        box-shadow: 0 0 0 2px rgb(0 0 0 / 0.5);
        transform: translate(-50%, -50%);
        pointer-events: none;
    }

    .sm-media-caption {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
        padding: 0.375rem 0.5rem;
        min-width: 0;
    }

    .sm-media-name,
    .sm-media-meta {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sm-media-name {
        font-size: 0.75rem;
        font-weight: 500;
    }

    .sm-media-more {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-top: 0.75rem;
    }

    .sm-media-more .sm-media-empty {
        margin-top: 0;
    }

    .sm-media-warning {
        font-size: 0.6875rem;
        font-weight: 600;
        color: rgb(217 119 6);
    }

    .sm-media-meta,
    .sm-media-empty {
        font-size: 0.75rem;
        color: rgb(107 114 128);
    }

    .sm-media-empty {
        margin-top: 0.5rem;
    }

    /* Pustaka Media: thumbnail grid menu Media dan modal detail */
    .sm-media-thumb {
        width: 100% !important;
        object-fit: contain;
    }

    .sm-media-detail {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .sm-media-detail-image {
        max-height: 20rem;
        margin: 0 auto;
        object-fit: contain;
        border-radius: 0.5rem;
    }

    .sm-media-detail-info {
        display: grid;
        grid-template-columns: 8rem 1fr;
        gap: 0.375rem 1rem;
        font-size: 0.875rem;
    }

    .sm-media-detail-info dt {
        color: rgb(107 114 128);
    }

    .sm-media-detail-info dd {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .sm-media-detail-heading {
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .sm-media-detail-usage {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.375rem;
        font-size: 0.875rem;
    }
</style>
