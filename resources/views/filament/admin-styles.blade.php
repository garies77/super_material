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
</style>
