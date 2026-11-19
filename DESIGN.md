# DESIGN SYSTEM & UI/UX SPECIFICATION: PINJAMKARYA

> **Instruksi untuk AI Coding Agent (OpenCode):**
> Dokumen ini adalah spesifikasi antarmuka (*UI/UX Layer*) resmi untuk **PinjamKarya**. Terapkan seluruh token warna branding sosial, komponen responsif *mobile-first*, tata letak *Hero* pengunjung baru, serta aturan aksesibilitas di bawah ini **tanpa mengubah struktur fitur, skema database, nama kolom SQL, URL modul (`modul1/`–`modul9/`), maupun 9 aturan pengaman logika (*guardrails*)** yang telah dikunci pada `PINJAMKARYA_SPEC.md`.

## 1. Filosofi Desain & Aksesibilitas Lintas Generasi

**PinjamKarya** adalah platform sosial berbasis gotong royong (*community sharing & mutual aid*) yang melayani warga dari berbagai latar belakang dan usia—mulai dari mahasiswa, ibu rumah tangga, pelaku UMKM, hingga teknisi lokal senior. Oleh karena itu, antarmuka memadukan **pendekatan visual hangat ala aplikasi modern *mobile-first* (referensi: *Talkie UI/UX Case Study*)** dengan **kejelasan fungsi yang sangat ramah pengguna awam**:

1. **Mobile-First & Ramah Jempol (*Thumb-Friendly*):**
   * Tata letak dirancang mulai dari layar ponsel (`360px`–`430px`) terlebih dahulu, lalu meluas secara adaptif ke tablet dan desktop (`>= 768px`).
   * Seluruh tombol utama, filter kategori, kolom formulir, dan menu navigasi wajib memiliki tinggi area sentuh minimal **`48px` (`min-height: 48px`)** dengan jarak antar-tombol minimal **`12px`** agar mudah ditekan oleh pengguna segala usia.
2. **Tipografi Jelas & Tidak Melelahkan Mata (*High Legibility*):**
   * Menggunakan font geometris humanis **Plus Jakarta Sans** dengan ukuran teks utama minimal **`16px` (`1rem`)** untuk deskripsi perkakas, panduan keselamatan, dan hasil diagnosis teknisi.
   * Rasio kontras warna teks terhadap latar memenuhi standar aksesibilitas **WCAG AA/AAA** (`#13262E` di atas permukaan `#FFFFFF` / `#F3F8F9`).
3. **Kartu Terstruktur & Alur Visual Sederhana (*Chunked Cards*):**
   * Menghindari tampilan tabel yang sempit atau dinding teks panjang di layar ponsel. Informasi dipecah menjadi kartu-kartu membulat lembut (`border-radius: 20px`), kontainer ikon berlatar pastel lembut, *badge* status yang lugas, serta indikator tahapan (*stepper*) visual.
4. **Bahasa Sosial yang Hangat & Mudah Dipahami:**
   * Label antarmuka menggunakan Bahasa Indonesia yang sopan, membumi, dan langsung dimengerti warga umum (contoh: *"Skor Kepercayaan Anda"*, *"Jadwal Sudah Dipesan"*, *"Setujui Biaya Perbaikan"*).

## 2. Sistem Warna Branding Sosial & Saling Membantu

Identitas visual **PinjamKarya** mengusung perpaduan **Biru Kepercayaan Komunitas (*Social Trust Blue*)** dan **Hijau-Teal Kepedulian & Keberlanjutan (*Caring Teal-Green*)**, dipadukan dengan aksen **Koral-Hangat Senja (*Community Warmth*)** yang menyatu secara alami dengan atmosfer foto *Hero*.

Simpan seluruh variabel dan kelas komponen UI berikut ke dalam berkas terpusat **`assets/css/pinjamkarya-ui.css`**, dan muat di `includes/header.php` tepat setelah CDN Bootstrap 5.3.3.

### 2.1 Palet Token Warna (`:root`)

```css
:root {
  /* Warna Utama Branding Sosial (Trust Blue & Caring Teal-Green) */
  --pk-primary: #0e5a6d;          /* Deep Social Teal-Blue — kepercayaan, tombol utama, navigasi */
  --pk-primary-hover: #094251;    /* Hover tombol utama */
  --pk-primary-soft: #e3f2f6;     /* Latar kartu sorotan, kontainer ikon aktif */

  --pk-secondary: #168072;        /* Caring Community Green-Teal — gotong royong, status tersedia */
  --pk-secondary-soft: #e4f5f1;   /* Latar lembut badge tersedia & dampak positif */

  /* Warna Aksen Hangat & Pastel Pendukung (Talkie-Inspired Soft Containers) */
  --pk-warm: #e07a4b;             /* Warm Sunset Coral — kehangatan manusia, poin kepercayaan */
  --pk-warm-soft: #fff2eb;        /* Latar lembut kartu skor & pemberitahuan penting */
  --pk-sky-soft: #eaf4fc;         /* Latar lembut info teknisi & jadwal kalender */
  --pk-mint-soft: #ebf7f3;        /* Latar lembut metrik SDGs & penghematan */

  /* Permukaan & Kanvas (Bersih, Tenang, Anti-Silau) */
  --pk-bg-canvas: #f3f8f9;        /* Latar halaman utama bernuansa biru-hijau sangat lembut */
  --pk-surface: #ffffff;          /* Latar kartu & formulir */
  --pk-surface-muted: #ebf1f3;    /* Latar input & area sekunder */
  --pk-border: #d8e4e8;           /* Garis tepi halus */

  /* Tipografi & Kontras Tinggi */
  --pk-text-main: #13262e;        /* Teks utama gelap kebiruan (kontras tinggi) */
  --pk-text-muted: #4b636d;       /* Teks keterangan / sekunder */
  --pk-text-light: #ffffff;

  /* Warna Status Fungsional */
  --pk-success: #15805d;
  --pk-warning: #d97706;
  --pk-danger: #dc3545;

  /* Radius & Bayangan Lembut */
  --pk-radius-sm: 12px;
  --pk-radius-md: 16px;
  --pk-radius-lg: 22px;
  --pk-radius-pill: 999px;
  --pk-shadow-card: 0 6px 24px rgba(14, 90, 109, 0.06);
  --pk-shadow-float: 0 14px 34px rgba(9, 44, 54, 0.16);
}
```

### 2.2 Tipografi (`Plus Jakarta Sans`)

Muat Google Fonts `Plus Jakarta Sans` (`400, 500, 600, 700, 800`) di `includes/header.php`:
* **Display Hero Headline (`PinjamKarya`):** `clamp(2.6rem, 6.5vw, 5.5rem)`, `font-weight: 800`, `line-height: 1.03`, `letter-spacing: -0.035em`.
* **Judul Halaman (`H1` / `H2`):** `clamp(1.4rem, 2.4vw, 1.9rem)`, `font-weight: 700`, `line-height: 1.25`.
* **Judul Kartu (`H3` / `H4`):** `1.125rem` (`18px`), `font-weight: 700`, `line-height: 1.35`.
* **Teks Utama (*Body*):** `1rem` (`16px`), `font-weight: 400`, `line-height: 1.6`.
* **Label Tombol & Badge:** `0.9rem` (`14.5px`), `font-weight: 600`.

## 3. Spesifikasi Landing Hero (Khusus Pengunjung Belum Login)

### 3.1 Perilaku Halaman Awal (`index.php`)
* **Jika Pengunjung Belum Login (`!isset($_SESSION['user'])`):**
  `index.php` menampilkan **Full-Viewport Split-Horizon Hero (`min-height: 100svh`)** di bagian paling atas, diikuti langsung di bawahnya (`#layanan-kami`) oleh ringkasan layanan sosial, pratinjau perkakas komunitas, dan statistik dampak live dari database.
* **Jika Pengguna Sudah Login (`isset($_SESSION['user'])`):**
  `index.php` langsung mengarahkan pengguna ke ruang kerja aktifnya (`modul4/katalog.php` untuk Warga/Mitra atau `modul9/dashboard.php` untuk Admin) agar operasional harian berjalan cepat tanpa langkah tambahan.

### 3.2 Komposisi Visual Hero (Mengacu pada Gambar Referensi)
Tata letak Hero mengadaptasi komposisi foto *split-horizon* pada gambar referensi (langit senja hangat di atas cakrawala air, kedalaman laut biru-teal tenang di bawahnya, dengan kotak perkakas & mesin bor listrik melayang tepat di garis batas air) yang dipadukan dengan tata letak tipografi bersih di keempat sudut:

1. **Latar Belakang *Split-Horizon* (Otomatis Mendukung Gambar Aset + Fallback CSS):**
   * Sistem memeriksa berkas gambar di folder **`assets/img/`** dengan nama dasar **`hero-banner`** (mendukung ekstensi `.jpg`, `.jpeg`, `.png`, atau `.webp`) melalui fungsi helper `get_hero_banner()`.
   * **Jika berkas `assets/img/hero-banner.*` sudah diisi oleh Anda:** Gambar ditampilkan penuh (`background-size: cover; background-position: center center;`) dengan lapisan gradasi lembut (*scrim*) hanya di tepi atas dan bawah agar seluruh teks putih terbaca tajam tanpa menutupi objek bor & kotak perkakas di tengah layar.
   * **Jika berkas `assets/img/hero-banner.*` belum diisi (kosong):** Sistem otomatis merender latar CSS *split-horizon* (gradasi senja hangat `#587683` $\rightarrow$ `#e4a77b` di bagian atas dan kedalaman biru-teal sosial `#115c68` $\rightarrow$ `#062931` di bagian bawah) beserta ilustrasi vektor garis tipis perkakas & kotak alat di garis cakrawala—sehingga **tidak pernah muncul ikon gambar pecah (*broken image*)**.
2. **Sudut Kiri Atas — Judul Utama Merek:**
   * Teks besar putih bersih rata kiri: **`PinjamKarya`**
3. **Sudut Kanan Atas — Tombol Pil Menu Minimalis:**
   * Tombol kapsul kaca transparan (`border-radius: 999px`, `border: 1px solid rgba(255,255,255,0.5)`, `background: rgba(14, 90, 109, 0.28)`, `backdrop-filter: blur(8px)`, `min-height: 46px`, `padding: 8px 24px`) berlabel **`Menu`**.
   * Saat diklik, membuka *Offcanvas Drawer* bersih berisi navigasi publik (*Katalog Perkakas*, *Panduan Keselamatan*, *Dashboard Dampak Sosial*, *Masuk Akun*, *Daftar Warga / Mitra*).
4. **Area Tengah Layar — Ruang Fokus Objek Layanan:**
   * Dibiarkan terbuka dan bersih di tengah (`pointer-events: none` pada area kosong) agar visual kotak perkakas, mesin bor, kunci pas, dan roda gigi yang membelah garis cakrawala air tampil utuh sebagai pusat perhatian emosional.
5. **Sudut Kiri Bawah — Pernyataan Inti Layanan & Tombol Aksi Utama:**
   * Teks putih tebal 2 baris yang langsung dipahami oleh seluruh lapisan masyarakat:
     ```text
     Alat kerja tak harus beli sendiri.
     Barang rusak tak harus dibuang.
     ```
   * Tepat di bawah pernyataan 2 baris tersebut, sediakan **2 tombol pil ramah jempol (`min-height: 48px`)**:
     * **Tombol Utama (Pil Putih Solid, teks `#0e5a6d`):** **`Jelajahi Katalog Alat`** $\rightarrow$ `modul4/katalog.php`
     * **Tombol Sekunder (Pil Transparan Garis Putih):** **`Masuk / Daftar`** $\rightarrow$ `modul1/login.php`
6. **Tengah Bawah — Petunjuk Gulir Halus:**
   * Tautan kecil tenang di bagian tengah bawah: **`Gulir ke bawah untuk melihat cara kerja`** (`href="#layanan-kami"`).
7. **Sudut Kanan Bawah — Keterangan Singkat Layanan Sosial:**
   * Teks kecil rata kanan (`0.875rem`, `rgba(255,255,255,0.88)`):
     ```text
     Platform gotong royong pinjam alat
     & perbaikan barang warga lokal.
     ```
8. **Aturan Ketat Kebersihan Visual:**
   * **DILARANG** menambahkan lencana penghargaan palsu (seperti pita *"W. Honors"* pada contoh referensi lama), popup promosi yang mengganggu, atau istilah asing yang membingungkan warga umum.

### 3.3 Bagian Tepat di Bawah Hero (`#layanan-kami` pada `index.php`)
Saat pengunjung menggulir ke bawah dari Hero, tampilkan bagian edukatif singkat dengan latar `--pk-bg-canvas`:
1. **Dua Kartu Layanan Gotong Royong (Grid 2 Kolom di Desktop, 1 Kolom di Ponsel):**
   * **Kartu 1 — Pinjam Perkakas & Alat Kesehatan (*Library of Things*):** Menjelaskan bahwa warga dan pelaku UMKM dapat meminjam bor, tangga, alat produksi UMKM, hingga kursi roda milik komunitas secara gratis atau berbiaya perawatan ringan. Tombol: **`Lihat Alat Tersedia`** (`modul4/katalog.php`).
   * **Kartu 2 — Perbaiki Barang Rusak (*Right-to-Repair Hub*):** Menjelaskan bahwa barang elektronik atau perkakas rusak dapat diperiksa oleh teknisi lokal terverifikasi dengan persetujuan biaya transparan di awal. Tombol: **`Ajukan Perbaikan`** (`modul1/login.php` / `modul7/buat_tiket.php`).
2. **Strip Dampak Sosial & Lingkungan Komunitas (Data Live dari SQL):**
   * Menampilkan 3 kartu angka nyata (Jumlah Alat Siap Pinjam, Unit Barang Berhasil Diperbaiki, dan Total Estimasi Penghematan Warga dalam Rupiah) dengan tombol **`Lihat Laporan Dampak Lengkap`** $\rightarrow$ `modul9/dashboard.php`.

## 4. Komponen UI Bersama (*Talkie-Inspired Mobile-First System*)

Terapkan gaya komponen berikut di seluruh halaman `modul1/` hingga `modul9/`:

1. **Header & Navigasi Responsif (`includes/header.php` & `includes/sidebar.php`):**
   * **Navbar Atas:** Latar gradien biru-teal sosial (`linear-gradient(135deg, #0e5a6d 0%, #168072 100%)`), tinggi minimal `64px`. Di layar ponsel (`< 768px`), tampilkan tombol *hamburger* besar (`48x48px`) yang membuka **Bootstrap Offcanvas Sidebar** sehingga menu tidak memakan layar ponsel.
   * **Badge Skor Kepercayaan (Khusus Warga):** Ditampilkan di navbar dengan warna hangat `--pk-warm-soft` dan teks `--pk-warm` (`<i class="bi bi-heart-fill"></i> Skor: 100`).
2. **Kartu Katalog Alat (`modul4/katalog.php`):**
   * Bentuk kartu `border-radius: 20px`, latar putih `#ffffff`, bayangan `--pk-shadow-card`.
   * Bagian atas gambar memiliki *badge* pil kategori (`--pk-primary-soft`) dan *badge* status (`Tersedia` dalam hijau-teal `--pk-secondary-soft`).
   * Informasi kunci disusun ringkas dengan ikon jelas: **Maksimal Durasi Pinjam**, **Syarat Skor Minimal**, dan **Biaya Perawatan** (`Gratis` dicetak tebal hijau-teal jika `0`).
   * Tombol **`Cek Jadwal & Pinjam`** selebar kartu penuh (`w-100`, `min-height: 48px`, `border-radius: 999px`).
3. **Kartu Skor & Profil Pengguna (`modul1/profil.php`):**
   * Menampilkan kartu sorotan skor kredibilitas dengan bilah progres visual (*progress bar*) dan penjelasan sederhana cara menambah poin (*"+10 poin setiap pengembalian tepat waktu & kondisi baik"*).
4. **Pelacakan Servis 6 Tahap (`modul8/tiket_saya.php`):**
   * Menampilkan *stepper* horizontal/vertikal bernomor (`1. Lapor` $\rightarrow$ `2. Diagnosis` $\rightarrow$ `3. Setujui Biaya` $\rightarrow$ `4. Dikerjakan` $\rightarrow$ `5. Siap Diambil` $\rightarrow$ `6. Selesai`) agar warga awam langsung melihat posisi pengerjaan barangnya.
   * Saat status `Menunggu Persetujuan`, kotak penawaran biaya dari teknisi disorot dengan latar `--pk-warm-soft` beserta dua tombol besar berdampingan: **`Setujui Biaya`** (hijau-teal) dan **`Tolak Estimasi`** (abu-abu lembut).
5. **Formulir & Tabel Ramah Semua Usia:**
   * Semua `<input>`, `<select>`, dan `<textarea>` memiliki `min-height: 48px`, `border-radius: 14px`, label jelas di atas input (`font-weight: 600`), dan pesan bantuan singkat di bawah input.
   * Setiap `<table>` wajib dibungkus `<div class="table-responsive">` dengan sel ber-padding lega (`py-3 px-3`).

## 5. Panduan Pengisian Template Aset Gambar (`assets/img/`)

> **Catatan untuk Pemilik Proyek:**
> Direktori `assets/img/` sengaja dikosongkan (hanya berisi `.gitkeep`) agar Anda dapat memasukkan gambar Hero secara mandiri tanpa mengganggu struktur Git.

### 5.1 Cara Memasang Gambar Hero Anda
1. Siapkan gambar bertema perkakas & kotak alat di garis cakrawala air (seperti gambar referensi Anda).
2. Simpan file tersebut ke dalam folder proyek pada lokasi:
   `pinjamkarya/assets/img/hero-banner.jpeg` *(atau `.jpg` / `.png` / `.webp`)*.
3. **Spesifikasi Teknis yang Disarankan:**
   * **Rasio Aspek:** Lanskap `16:9` (minimal resolusi `1600 x 900 px` agar tajam di layar laptop maupun ponsel).
   * **Ukuran Berkas:** Maksimal `1.5 MB` (kompres ringan agar halaman awal terbuka cepat di jaringan seluler warga).
   * **Komposisi Ruang:** Pastikan subjek utama berada di bagian tengah layar sehingga ruang sudut kiri atas, kanan atas, kiri bawah, dan kanan bawah memiliki ruang lapang untuk teks judul dan tombol aksi.

### 5.2 Helper PHP Wajib di `includes/functions.php`
Implementasikan fungsi berikut agar `index.php` otomatis mendeteksi gambar Anda begitu file dimasukkan ke `assets/img/`:

```php
function get_hero_banner() {
    $extensions = ['jpg', 'jpeg', 'png', 'webp'];
    foreach ($extensions as $ext) {
        $relative = '/assets/img/hero-banner.' . $ext;
        if (file_exists(ROOT_PATH . $relative)) {
            return BASE_URL . $relative;
        }
    }
    return null; // Mengaktifkan tampilan fallback CSS split-horizon secara otomatis
}
```

## 6. Checklist Implementasi UI untuk OpenCode

Sebelum menyelesaikan pembangunan kode, pastikan seluruh poin berikut terpenuhi:
* [ ] Berkas `assets/css/pinjamkarya-ui.css` terpasang dan dimuat di `includes/header.php` bersama Google Font `Plus Jakarta Sans`.
* [ ] Folder `assets/img/` dibuat dengan file `.gitkeep` di dalamnya (tanpa file gambar *dummy* biner yang dipaksakan), dan `get_hero_banner()` berfungsi mulus baik saat folder kosong maupun saat `hero-banner.jpeg` ditambahkan.
* [ ] Pengunjung yang belum login melihat **Split-Horizon Hero** saat membuka `index.php`, sedangkan pengguna yang sudah login langsung diarahkan ke ruang kerja modulnya.
* [ ] Palet warna biru-teal sosial (`#0e5a6d` & `#168072`) diterapkan konsisten di navbar, sidebar/offcanvas, tombol, kartu katalog, dan dashboard dampak.
* [ ] Seluruh tombol interaktif dan input formulir memiliki tinggi sentuh minimal `48px` dan bebas dari teks mikro (`< 14px`) pada konten utama.