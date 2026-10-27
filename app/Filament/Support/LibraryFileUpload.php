<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;

/**
 * Unggahan milik Pustaka Media itu sendiri (menu Media dan modal "Pilih dari Media").
 * Tetap memakai penyimpan global (deteksi duplikat), tetapi tidak diberi tombol
 * "Pilih dari Media" oleh konfigurasi global FileUpload.
 */
class LibraryFileUpload extends FileUpload {}
