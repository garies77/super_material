{{-- Riwayat login dari AuditLog (append-only). Maksimal 50 terakhir. --}}
@if($logs->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada login yang tercatat.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="py-2 pe-4 font-medium">Waktu (WIB)</th>
                    <th class="py-2 pe-4 font-medium">Alamat IP</th>
                    <th class="py-2 pe-4 font-medium">Cara</th>
                    <th class="py-2 font-medium">Browser / perangkat</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap py-2 pe-4 text-gray-950 dark:text-white">{{ $log->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
                        <td class="whitespace-nowrap py-2 pe-4 font-mono text-xs">{{ $log->changes['ip'] ?? '—' }}</td>
                        <td class="whitespace-nowrap py-2 pe-4">{{ ['email_link' => 'Tautan email', 'form' => 'Form login'][$log->changes['via'] ?? ''] ?? '—' }}</td>
                        <td class="py-2 text-xs text-gray-500 dark:text-gray-400" title="{{ $log->changes['user_agent'] ?? '' }}">{{ \Illuminate\Support\Str::limit($log->changes['user_agent'] ?? '—', 80) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
