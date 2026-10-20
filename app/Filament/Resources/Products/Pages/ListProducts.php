<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductLink;
use App\Models\SalesChannel;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
                    $query = $this->getFilteredTableQuery()->with(['category', 'brand', 'links.channel']);

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
                        ->label('Unggah File CSV Produk')
                        ->disk('local')
                        ->directory('imports')
                        ->required()
                        ->helperText('Format kolom header: nama, kategori, merek, harga_min, harga_max, satuan, badge, shopee_url, tokopedia_url, lazada_url, tiktok_url, whatsapp_message'),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['csv_file']);
                    if (! file_exists($path)) {
                        Notification::make()->title('File tidak ditemukan')->danger()->send();
                        return;
                    }

                    $file = fopen($path, 'r');
                    $header = fgetcsv($file);
                    if (! $header) {
                        Notification::make()->title('File CSV kosong')->warning()->send();
                        fclose($file);
                        return;
                    }

                    $headerMap = array_flip(array_map('trim', array_map('strtolower', $header)));
                    $channels = SalesChannel::all()->keyBy('slug');
                    $count = 0;

                    while (($row = fgetcsv($file)) !== false) {
                        if (empty(array_filter($row))) {
                            continue;
                        }

                        $name = trim($row[$headerMap['nama'] ?? -1] ?? '');
                        if (empty($name)) {
                            continue;
                        }

                        $categoryName = trim($row[$headerMap['kategori'] ?? -1] ?? '');
                        $brandName = trim($row[$headerMap['merek'] ?? -1] ?? '');
                        $priceMin = (int) ($row[$headerMap['harga_min'] ?? -1] ?? 0);
                        $priceMax = (int) ($row[$headerMap['harga_max'] ?? -1] ?? 0);
                        $unit = trim($row[$headerMap['satuan'] ?? -1] ?? 'pcs');
                        $badge = trim($row[$headerMap['badge'] ?? -1] ?? '');
                        $slug = Str::slug($name);

                        $category = ! empty($categoryName) ? Category::firstOrCreate(['slug' => Str::slug($categoryName)], ['name' => $categoryName, 'is_active' => true]) : null;
                        $brand = ! empty($brandName) ? Brand::firstOrCreate(['slug' => Str::slug($brandName)], ['name' => $brandName]) : null;

                        $product = Product::updateOrCreate(
                            ['slug' => $slug],
                            [
                                'name' => $name,
                                'category_id' => $category?->id,
                                'brand_id' => $brand?->id,
                                'price_min' => $priceMin ?: null,
                                'price_max' => $priceMax ?: null,
                                'unit' => $unit ?: 'pcs',
                                'badge' => $badge ?: null,
                                'is_published' => true,
                                'show_quantity' => true,
                                'price_display' => 'show',
                            ]
                        );

                        // Sync tautan kanal pembelian dari CSV
                        $channelMappings = [
                            'shopee' => $row[$headerMap['shopee_url'] ?? -1] ?? null,
                            'tokopedia' => $row[$headerMap['tokopedia_url'] ?? -1] ?? null,
                            'lazada' => $row[$headerMap['lazada_url'] ?? -1] ?? null,
                            'tiktok-shop' => $row[$headerMap['tiktok_url'] ?? -1] ?? null,
                        ];

                        foreach ($channelMappings as $chSlug => $chUrl) {
                            $chUrl = trim((string) $chUrl);
                            if (! empty($chUrl) && isset($channels[$chSlug])) {
                                ProductLink::updateOrCreate(
                                    ['product_id' => $product->id, 'sales_channel_id' => $channels[$chSlug]->id],
                                    ['url' => $chUrl, 'is_active' => true]
                                );
                            }
                        }

                        // WhatsApp Message
                        $waMsg = trim((string) ($row[$headerMap['whatsapp_message'] ?? -1] ?? ''));
                        if (! empty($waMsg) && isset($channels['whatsapp'])) {
                            ProductLink::updateOrCreate(
                                ['product_id' => $product->id, 'sales_channel_id' => $channels['whatsapp']->id],
                                ['message' => $waMsg, 'is_active' => true]
                            );
                        }

                        $count++;
                    }

                    fclose($file);
                    @unlink($path);

                    Notification::make()
                        ->title("Berhasil mengimpor {$count} produk beserta tautan kanal")
                        ->success()
                        ->send();
                }),
        ];
    }
}
