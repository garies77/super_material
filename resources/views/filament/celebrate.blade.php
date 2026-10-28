{{--
    Perayaan confetti panel admin (render hook BODY_END). Tanpa pustaka tambahan: potongan kertas berupa <span>
    dengan animasi CSS (.sm-confetti di theme.css). window.smConfetti() dipanggil panel misi saat skor naik ke 100%;
    sesi "sm_celebrate" (diisi saat konten diajukan dengan skor tinggi) memutarnya sekali setelah halaman dimuat.
--}}
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    window.smConfetti = function () {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

        const colors = ['#f97316', '#22c55e', '#3b82f6', '#eab308', '#ec4899', '#a855f7']
        const layer = document.createElement('div')
        layer.className = 'sm-confetti'
        layer.setAttribute('aria-hidden', 'true')

        for (let i = 0; i < 90; i++) {
            const piece = document.createElement('span')
            piece.style.left = Math.random() * 100 + 'vw'
            piece.style.background = colors[i % colors.length]
            piece.style.animationDelay = Math.random() * 0.4 + 's'
            piece.style.animationDuration = 1.6 + Math.random() * 1.4 + 's'
            piece.style.setProperty('--sm-drift', (Math.random() * 30 - 15) + 'vw')
            piece.style.setProperty('--sm-spin', (Math.random() * 720 - 360) + 'deg')
            layer.appendChild(piece)
        }

        document.body.appendChild(layer)
        setTimeout(() => layer.remove(), 3500)
    }

    @if (session()->pull('sm_celebrate'))
        setTimeout(() => window.smConfetti(), 300)
    @endif
</script>
