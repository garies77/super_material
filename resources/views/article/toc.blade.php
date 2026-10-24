                {{-- 6. Daftar Isi Otomatis (Dapat Dilipat / Accordion) --}}
                @if(count($toc) >= 3)
                    <details open class="group my-6 rounded-2xl border border-ink-200 bg-white p-5 shadow-xs">
                        <summary class="flex items-center justify-between cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-100 text-brand-700">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                                </span>
                                <h3 class="font-display text-base font-bold text-ink-900 m-0">Daftar isi</h3>
                            </div>
                            <div class="text-xs font-semibold text-brand-700 flex items-center gap-1">
                                <span class="group-open:hidden">Tampilkan</span>
                                <span class="hidden group-open:inline">Sembunyikan</span>
                                <svg viewBox="0 0 24 24" class="h-4 w-4 transition-transform duration-200 group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            </div>
                        </summary>
                        <ul class="mt-4 space-y-2.5 border-t border-ink-100 pt-3 text-sm">
                            @foreach($toc as $item)
                                <li class="{{ $item['level'] === 3 ? 'pl-5 text-xs text-ink-600' : 'font-medium text-ink-800' }}">
                                    <a href="#{{ $item['id'] }}" class="inline-flex items-center gap-1.5 transition hover:text-brand-600 hover:translate-x-0.5">
                                        <span class="text-brand-500 font-bold">›</span>
                                        <span>{{ $item['text'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
