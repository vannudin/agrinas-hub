@extends('layouts.utama')

@section('judul', 'Manager Memilih Sistem')
@section('deskripsi', 'Manajer koperasi memilih sistem yang dipakai ke depan: satu sistem terpusat Agrinas Hub atau tetap tiga sistem berjalan.')

@section('isi')
<header class="kepala">
  <div>
    <h1>Manager Memilih Sistem</h1>
    <p class="tenang">Suara Anda menentukan sistem yang dipakai ke depan.</p>
  </div>
</header>

<section class="blok">
  <h2>Yang manajer butuhkan</h2>
  <p>
    Satu sistem terpusat yang <b>lengkap</b>, <b>mudah digunakan</b>, dan <b>stabil saat diakses</b> —
    dari persiapan, penempatan, operasional, pelaporan, sampai kepegawaian.
  </p>
  <p>
    Agrinas Hub menyatukan kelima urusan itu dalam satu akun dan satu tempat, sehingga manajer
    tidak perlu berpindah aplikasi atau menyalin data manual.
  </p>

  <div class="petak">
    @foreach (['Persiapan', 'Penempatan', 'Operasional', 'Pelaporan', 'Kepegawaian'] as $bidang)
      <div class="kartu"><b>{{ $bidang }}</b><small class="pudar">Satu alur, satu data</small></div>
    @endforeach
  </div>
</section>

<section class="blok">
  <h2>{{ $suara ? 'Ubah pilihan Anda' : 'Pilih sistem yang Anda gunakan' }}</h2>
  <p class="bantuan">
    Boleh diubah kapan saja; suara terakhir yang dihitung. Satu manajer satu suara.
  </p>

  <form method="post" action="{{ route('vote.simpan') }}" class="petak-form">
    @csrf
    @foreach (\App\Models\VoteSuara::PILIHAN as $nilai => $label)
      <label class="krj-opsi">
        <input type="radio" name="pilihan" value="{{ $nilai }}" required
               @checked(old('pilihan', $suara->pilihan ?? null) == $nilai)>
        <span><b>{{ $label }}</b></span>
      </label>
    @endforeach

    <label class="lebar">
      <span>Catatan <small class="pudar">(opsional)</small></span>
      <textarea name="catatan" rows="3" maxlength="500"
                placeholder="Alasan atau harapan Anda…">{{ old('catatan', $suara->catatan ?? '') }}</textarea>
    </label>

    <div class="tombol-baris">
      <button class="tombol utama" type="submit">{{ $suara ? 'Simpan Perubahan' : 'Kirim Suara' }}</button>
      @if ($suara)
        <a class="tombol samar" href="{{ route('kerja') }}">Kembali ke Dashboard</a>
      @endif
    </div>
  </form>
</section>

<section class="blok">
  <h2>Hasil Suara <small class="pudar">{{ number_format($total, 0, ',', '.') }} suara</small></h2>
  @foreach (\App\Models\VoteSuara::PILIHAN as $nilai => $label)
    @php($n = (int) ($hitung[$nilai] ?? 0))
    <p style="margin:0 0 .2rem">{{ $label }} — <b>{{ number_format($n, 0, ',', '.') }}</b>
      <small class="pudar">({{ $total > 0 ? round($n / $total * 100) : 0 }}%)</small></p>
    <progress value="{{ $n }}" max="{{ max($total, 1) }}" style="width:100%"></progress>
  @endforeach
  <p class="bantuan">
    Catatan: bila suara yang masuk kurang dari
    <b>{{ number_format(\App\Models\VoteSuara::MIN_SUARA, 0, ',', '.') }}</b>
    atau manajer menolak, sistem ini akan kami hapus dan tidak digunakan.
  </p>
</section>
@endsection
