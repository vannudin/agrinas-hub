/* Editor ONLYOFFICE: menyulap wadah kosong jadi editor penuh.
 *
 * Config dibaca dari <script type="application/json"> supaya tidak ada JSON yang
 * ditempel mentah ke dalam JavaScript (tanda kutip & unicode jadi aman).
 *
 * DUA wadah, dua halaman:
 *   #editor-dokumen  -> halaman biasa (di dalam layout Hub, 70vh)
 *   #editor-penuh    -> halaman penuh di tab tersendiri (seluruh layar)
 *
 * AKAR BUG 2026-10-21: versi pertama skrip ini HANYA mencari #editor-dokumen.
 * Halaman penuh memakai id lain, jadi baris `if (!wadah) return;` langsung
 * keluar tanpa pesan — hasilnya halaman BLANK tanpa satu pun galat di konsol,
 * karena tidak ada yang error: skripnya memang berhenti dengan sengaja.
 * Sejak itu wadah dicari lewat `[data-editor-onlyoffice]`, dan id apa pun boleh
 * dipakai asal atribut itu ada. Kalau atribut tidak ada, jatuh ke kedua id lama
 * supaya halaman yang sudah ada tidak perlu diubah.
 */
(function () {
  'use strict';

  var wadah = document.querySelector('[data-editor-onlyoffice]')
    || document.getElementById('editor-dokumen')
    || document.getElementById('editor-penuh');

  var berkas = document.getElementById('config-dokumen');

  // Kegagalan TIDAK boleh senyap: halaman kosong tanpa sebab adalah keluhan
  // yang mustahil ditelusuri. Setiap cabang di bawah menulis pesan terlihat.
  if (!wadah) { return; }   // halaman ini memang tidak punya editor (mis. PDF)

  if (!berkas) {
    wadah.innerHTML = '<div class="pesan rusak" role="alert">Config editor tidak ditemukan '
      + '(script#config-dokumen hilang).</div>';
    return;
  }

  if (typeof DocsAPI === 'undefined') {
    wadah.innerHTML = '<div class="pesan rusak" role="alert">Editor ONLYOFFICE gagal dimuat '
      + '(api.js tidak tersedia). Periksa koneksi ke OnlyOffice.</div>';
    return;
  }

  var config;
  try {
    config = JSON.parse(berkas.textContent);
  } catch (e) {
    wadah.innerHTML = '<div class="pesan rusak" role="alert">Config editor rusak: '
      + e.message + '</div>';
    return;
  }

  // Id elemen diteruskan apa adanya ke DocEditor: pustaka itu mencarinya sendiri
  // lewat document.getElementById, jadi id-nya WAJIB ada di DOM.
  if (!wadah.id) { wadah.id = 'editor-onlyoffice'; }

  // Tinggi editor diatur CSS (.editor-bingkai = 70vh; halaman penuh = flex).
  // Jangan diatur di sini — dua sumber kebenaran akan saling menimpa.
  try {
    new DocsAPI.DocEditor(wadah.id, config);
  } catch (e) {
    wadah.innerHTML = '<div class="pesan rusak" role="alert">Editor gagal dinyalakan: '
      + e.message + '</div>';
  }
})();
