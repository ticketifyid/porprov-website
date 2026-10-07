@php
    // width/height = ukuran asli berkas, supaya browser menyisihkan ruang sebelum gambar
    // dimuat dan tata letak tidak bergeser. Tinggi tampil diatur CSS (.logo-bar__img--*),
    // lebarnya mengikuti rasio.
    $logos = [
        'porprov' => ['file' => 'logo-porprov', 'alt' => 'Logo Porprov XVII Jawa Tengah', 'w' => 529, 'h' => 192, 'size' => 'main'],
        'jateng' => ['file' => 'logo-jateng', 'alt' => 'Lambang Provinsi Jawa Tengah', 'w' => 173, 'h' => 192, 'size' => 'small'],
        'koni' => ['file' => 'logo-koni-jateng', 'alt' => 'Logo KONI Jawa Tengah', 'w' => 158, 'h' => 192, 'size' => 'small'],
        'ngopeni' => ['file' => 'logo-ngopeni-nglakoni', 'alt' => 'Ngopeni Nglakoni Jateng', 'w' => 375, 'h' => 192, 'size' => 'small'],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'logo-bar']) }}>
    <div class="logo-bar__inner">
        {{-- Kiri: Porprov. Tengah: Jawa Tengah + KONI. Kanan: Ngopeni Nglakoni. --}}
        @foreach ([['porprov'], ['jateng', 'koni'], ['ngopeni']] as $group)
            <div class="logo-bar__group">
                @foreach ($group as $key)
                    @php $logo = $logos[$key]; @endphp
                    <picture>
                        <source srcset="@versionedAsset('img/'.$logo['file'].'.webp')" type="image/webp">
                        <img src="@versionedAsset('img/'.$logo['file'].'.png')" alt="{{ $logo['alt'] }}"
                             width="{{ $logo['w'] }}" height="{{ $logo['h'] }}"
                             class="logo-bar__img logo-bar__img--{{ $logo['size'] }}">
                    </picture>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
