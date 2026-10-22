<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem Sedang Dalam Pemeliharaan | Supermaterial</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #090d16;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: #111827;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px 32px;
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .icon {
            width: 64px;
            height: 64px;
            background: rgba(234, 88, 12, 0.15);
            color: #ea580c;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }
        h1 {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 12px;
            line-height: 1.3;
        }
        p {
            font-size: 14px;
            line-height: 1.6;
            color: #94a3b8;
            margin-bottom: 24px;
        }
        .badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            color: #cbd5e1;
        }
        .footer {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <h1>Sedang Dalam Pemeliharaan Rutin</h1>
        <p>
            Kami sedang melakukan peningkatan performa katalog dan sinkronisasi data material konstruksi. Layanan akan kembali online dalam beberapa saat.
        </p>
        <div class="badge">
            HTTP 503 · Service Temporarily Unavailable
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Supermaterial. Terima kasih atas kesabaran Anda.
        </div>
    </div>
</body>
</html>
