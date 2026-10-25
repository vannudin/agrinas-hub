{{-- Satu simpul pohon wiki. Rekursif lewat `anak`; kedalaman dibatasi di controller
     (Wiki::boleh_induk) jadi tidak ada risiko tak berujung. --}}
<li>
  <a href="{{ route('workspace.wiki.lihat', $simpul['hal']->id) }}">{{ $simpul['hal']->judul }}</a>
  @if (! empty($simpul['anak']))
    <ul>
      @foreach ($simpul['anak'] as $anak)
        @include('workspace._wiki-simpul', ['simpul' => $anak])
      @endforeach
    </ul>
  @endif
</li>
