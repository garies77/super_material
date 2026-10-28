<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Support\Catalog\ProductImporter;
use App\Support\Catalog\ProductPhotos;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            // Action: Ekspor CSV Produk
            Action::make('export_csv')
                ->label('Ekspor CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $query = $this->getFilteredTableQuery()->with(['category', 'brand', 'links.channel', 'media']);

                    return response()->streamDownload(function () use ($query) {
                        $out = fopen('php://output', 'w');
                        fputcsv($out, [
                            'nama',
                            'slug',
                            'kategori',
                            'merek',
                            'harga_min',
                            'harga_max',
                            'satuan',
                            'badge',
                            'shopee_url',
                            'tokopedia_url',
                            'lazada_url',
                            'tiktok_url',
                            'terbit',
                            'gambar',
                        ]);

                        $query->reorder('id')->chunk(500, function ($products) use ($out) {
                            foreach ($products as $p) {
                                $links = $p->links->keyBy(fn ($l) => $l->channel?->slug);
                                fputcsv($out, [
                                    $p->name,
                                    $p->slug,
                                    $p->category?->name,
                                    $p->brand?->name,
                                    $p->price_min,
                                    $p->price_max,
                                    $p->unit,
                                    $p->badge,
                                    $links['shopee']->url ?? '',
                                    $links['tokopedia']->url ?? '',
                                    $links['lazada']->url ?? '',
                                    $links['tiktok-shop']->url ?? '',
                                    $p->is_published ? '1' : '0',
                                    // Path foto pustaka: berkas ekspor bisa diimpor ulang tanpa kehilangan foto
                                    implode(', ', ProductPhotos::paths($p)),
                                ]);
                            }
                        });

                        fclose($out);
                    }, 'katalog-produk-'.now()->format('Ymd-His').'.csv');
                }),

            // Action: Impor CSV Produk beserta Tautan Kanal
            Action::make('import_csv')
                ->label('Impor CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    FileUpload::make('csv_file')
                        ->label('Unggah file CSV atau Excel')->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->disk('local')
                        ->directory('imports')
                        ->required()
                        ->helperText('CSV atau Excel (.xlsx). Kolom: nama, slug, kategori, merek, harga_min, harga_max, satuan, badge, shopee_url, tokopedia_url, lazada_url, tiktok_url, whatsapp_message, terbit, gambar. Kolom gambar berisi nama berkas dari Pustaka Media (pisah koma; foto pertama = utama; kosong = foto tidak diubah). Baris tidak valid dilewati.'),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['csv_file']);
                    if (! file_exists($path)) {
                        Notification::make()->title('File tidak ditemukan')->danger()->send();

                        return;
                    }

                    // Logika impor sama dengan API (POST /api/v1/admin/imports)
                    $result = ProductImporter::import($path);
                    @unlink($path);

                    $notification = Notification::make()
                        ->title("Impor selesai: {$result['created']} produk baru, {$result['updated']} diperbarui");

                    if ($result['failed']) {
                        $notification->warning()->persistent()->body(
                            count($result['failed']).' baris dilewati: '.collect($result['failed'])->take(5)
                                ->map(fn ($f) => 'baris '.$f['row'].' ('.collect($f['errors'])->flatten()->first().')')->implode('; ')
                        );
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),
        ];
    }
}
