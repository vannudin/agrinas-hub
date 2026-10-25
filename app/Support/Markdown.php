<?php

namespace App\Support;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Ubah jawaban asisten (Markdown) menjadi HTML yang aman.
 *
 * AKAR: model menjawab dengan `**tebal**`, `- daftar`, dan `| tabel |`, dan
 * halaman menampilkannya apa adanya lewat `textContent` — pengguna membaca
 * tanda bintangnya, bukan huruf tebalnya. Terukur di jawaban sungguhan:
 * satu jawaban berisi 12 penanda markdown yang tampil mentah.
 *
 * KEAMANAN: jawaban datang dari model LLM, jadi harus diperlakukan sebagai
 * masukan tak tepercaya (prompt injection bisa menyuruh model menulis HTML).
 * Karena itu `html_input => 'strip'` + `allow_unsafe_links => false`:
 * tag HTML mentah dibuang, tautan berbahaya (javascript:) ditolak. Tanpa ini
 * `{!! !!}` menjadi lubang XSS.
 *
 * Tabel diaktifkan karena jawaban asisten rutin memuat tabel ringkasan.
 */
class Markdown
{
    private static ?MarkdownConverter $konverter = null;

    private static function konverter(): MarkdownConverter
    {
        if (self::$konverter === null) {
            $env = new Environment([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 12,
            ]);
            $env->addExtension(new CommonMarkCoreExtension());
            $env->addExtension(new TableExtension());

            self::$konverter = new MarkdownConverter($env);
        }

        return self::$konverter;
    }

    public static function keHtml(?string $teks): string
    {
        return (string) self::konverter()->convert((string) $teks);
    }
}
