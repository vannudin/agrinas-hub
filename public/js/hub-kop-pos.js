/* Kasir POS koperasi — keranjang di sisi klien, harga & stok di sisi server.
 *
 * Yang dikerjakan di sini HANYA menyusun keranjang dan menampilkannya. Total
 * yang ditampilkan adalah bantuan baca, bukan angka yang dipercaya: `jual()`
 * di KoperasiController menghitung ulang total dari harga di basis data dan
 * menolak baris yang stoknya kurang. Mempercayai total dari peramban berarti
 * harga nota bisa diubah dari DevTools.
 */
(function () {
  var form = document.getElementById('pos-form');
  if (!form) return;

  var isi = form.querySelector('[data-pos-item]');          // input hidden JSON
  var badan = form.querySelector('[data-pos-keranjang]');
  var totalHidup = form.querySelector('[data-pos-total-hidup]');
  var inputBayar = form.querySelector('[data-pos-dibayar]');
  var caraBayar = form.querySelector('[data-pos-bayar]');
  var barisKembali = form.querySelector('[data-pos-kembali]');
  var brs = [];                                             // [{id,nama,satuan,harga,jumlah,stok}]

  var rupiah = function (n) { return (n || 0).toLocaleString('id-ID'); };

  function simpan() {
    isi.value = JSON.stringify(brs.map(function (b) {
      return { id: b.id, jumlah: b.jumlah };
    }));
  }

  /* Cermin dari KoperasiBarang::diskonUntuk() di server. Dua baris ini harus
   * tetap SAMA dengan sana; kalau berbeda, total di layar kasir tidak cocok
   * dengan struk yang tercetak (dan uang kembalian jadi salah). */
  function potongan(b) {
    if (b.promoJenis !== 'DISKON' || b.jumlah < Math.max(1, b.promoMin)) return 0;
    return Math.round(b.harga * b.jumlah * Math.min(100, Math.max(0, b.promoNilai)) / 100);
  }
  function dapatBonus(b) {
    return b.promoJenis === 'GRATIS' && b.promoBonus !== ''
      && b.promoNilai > 0 && b.jumlah >= Math.max(1, b.promoMin);
  }
  function totalKeranjang() {
    return brs.reduce(function (s, b) { return s + b.harga * b.jumlah - potongan(b); }, 0);
  }

  function gambar() {
    var total = totalKeranjang();

    if (brs.length === 0) {
      badan.innerHTML = '<tr><td colspan="5" class="kosong" data-pos-kosong>'
        + 'Keranjang masih kosong — ketuk “+ Keranjang” pada barang di atas.</td></tr>';
    } else {
      badan.innerHTML = brs.map(function (b, i) {
        var potong = potongan(b);
        var baris = '<tr>'
          + '<td data-l="Barang">' + b.nama + (potong > 0
              ? ' <span class="lencana s-DISETUJUI">promo −' + rupiah(potong) + '</span>' : '')
          + '</td>'
          + '<td data-l="Harga" class="angka-kanan">' + rupiah(b.harga) + '</td>'
          + '<td data-l="Jumlah" class="angka-kanan">'
          + '<input type="number" min="1" max="' + b.stok + '" step="1" value="' + b.jumlah
          + '" data-pos-jumlah="' + i + '" aria-label="Jumlah ' + b.nama + '"></td>'
          + '<td data-l="Subtotal" class="angka-kanan">' + rupiah(b.harga * b.jumlah - potong) + '</td>'
          + '<td class="kolom-aksi"><button class="tombol mini samar" type="button" '
          + 'data-pos-hapus="' + i + '">Hapus</button></td>'
          + '</tr>';
        // Bonus gratis ditampilkan sebagai baris sendiri supaya kasir tahu
        // barang apa yang harus diserahkan — dan tidak menagihnya.
        if (dapatBonus(b)) {
          baris += '<tr class="baris-gratis">'
            + '<td data-l="Barang">' + b.promoBonus
            + ' <span class="lencana s-DISETUJUI">GRATIS</span></td>'
            + '<td data-l="Harga" class="angka-kanan">0</td>'
            + '<td data-l="Jumlah" class="angka-kanan">' + b.promoNilai + '</td>'
            + '<td data-l="Subtotal" class="angka-kanan">0</td>'
            + '<td class="kolom-aksi"></td></tr>';
        }
        return baris;
      }).join('');
    }

    totalHidup.value = rupiah(total);
    simpan();
    kembali();
  }

  /* Kembalian hanya ditampilkan bila cara bayarnya bukan hutang: pada hutang,
     "kembalian negatif" tidak berarti apa-apa dan cuma membingungkan kasir. */
  function kembali() {
    if (!barisKembali) return;
    var total = totalKeranjang();
    var hutang = caraBayar && caraBayar.value === 'HUTANG';
    if (hutang) { barisKembali.hidden = true; return; }
    var bayar = parseInt((inputBayar && inputBayar.value) || '0', 10) || 0;
    barisKembali.hidden = false;
    barisKembali.querySelector('b').textContent = rupiah(Math.max(0, bayar - total));
  }

  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-pos-jumlah],[data-pos-hapus]');
    var b = ev.target.closest('.tambah[data-id]');

    if (b) {
      var id = parseInt(b.dataset.id, 10);
      var ada = null;
      for (var i = 0; i < brs.length; i++) if (brs[i].id === id) ada = brs[i];
      if (ada) {
        // Batasnya stok, bukan angka karangan: menambah melebihi stok ditahan
        // di sini supaya kasir tidak menemui galat server saat menyimpan.
        if (ada.jumlah >= ada.stok) {
          if (window.hubToast) window.hubToast('Stok ' + ada.nama + ' hanya ' + ada.stok + '.', true);
          return;
        }
        ada.jumlah++;
      } else {
        brs.push({
          id: id, nama: b.dataset.nama, satuan: b.dataset.satuan,
          harga: parseInt(b.dataset.harga, 10) || 0, jumlah: 1,
          stok: parseInt(b.dataset.stok, 10) || 0,
          // Promo disalin dari tombol; angka yang DICATAT tetap dihitung server
          // (KoperasiBarang::diskonUntuk) — ini hanya untuk bacaan kasir.
          promoJenis: b.dataset.promoJenis || '',
          promoNilai: parseInt(b.dataset.promoNilai, 10) || 0,
          promoMin: parseInt(b.dataset.promoMin, 10) || 0,
          promoBonus: b.dataset.promoBonus || ''
        });
      }
      gambar();
      return;
    }

    if (t && t.hasAttribute('data-pos-hapus')) {
      brs.splice(parseInt(t.getAttribute('data-pos-hapus'), 10), 1);
      gambar();
    }
  });

  document.addEventListener('change', function (ev) {
    if (ev.target.hasAttribute('data-pos-jumlah')) {
      var i = parseInt(ev.target.getAttribute('data-pos-jumlah'), 10);
      var n = parseInt(ev.target.value, 10) || 1;
      brs[i].jumlah = Math.min(Math.max(1, n), brs[i].stok);
      gambar();
      return;
    }
    if (ev.target === caraBayar || ev.target === inputBayar) kembali();
  });

  document.addEventListener('input', function (ev) {
    if (ev.target === inputBayar) kembali();
  });

  var kosongkan = form.querySelector('[data-pos-kosongkan]');
  if (kosongkan) {
    kosongkan.addEventListener('click', function () {
      brs = [];
      gambar();
    });
  }

  form.addEventListener('submit', function (ev) {
    if (brs.length === 0) {
      ev.preventDefault();
      if (window.hubToast) window.hubToast('Keranjang masih kosong — tambahkan barang dulu.', true);
      return;
    }
    simpan();
  });

  gambar();
})();
