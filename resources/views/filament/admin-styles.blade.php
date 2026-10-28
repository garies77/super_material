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
