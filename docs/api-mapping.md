# Skema Data dan API Mapping

Versi: 0.1 (draf, menunggu persetujuan) · Acuan: FSD v1.1, `docs/ai-contract.md`, `.ai/guidelines/`
Pemilik: Irfan (backend) dan Arya (frontend). Perubahan dokumen ini harus disetujui keduanya.

TandaRasa **tidak punya REST API terpisah**. Halaman Livewire (frontend) memanggil Action di
`app/Actions/` (backend). Dokumen ini adalah "menu" Action tersebut: apa yang dikirim frontend,
apa yang dikembalikan backend, dan pesan error yang mungkin muncul. Frontend boleh mulai membuat
halaman dengan data contoh yang bentuknya sama dengan dokumen ini sebelum backend selesai.

```
Halaman Livewire (Arya) ──► Action (Irfan) ──► Model / Job ──► database
        ▲                                                   │
        └──────────── data siap tampil (bagian 3) ◄─────────┘
```

---

## 1. Skema data

Semua tanggal "hari" memakai zona `Asia/Jakarta`. Nama tabel dan kolom berbahasa Inggris.

### 1.1 `entries` (entri harian, FR-06, FR-07)

Satu entri = satu pengguna pada satu tanggal kalender, berisi satu skor mood dan paling banyak satu
jurnal (FSD 2.9.3). Mengisi lagi di tanggal yang sama berarti mengedit entri itu.

| Kolom        | Tipe                          | Keterangan                                              |
| ------------ | ----------------------------- | ------------------------------------------------------- |
| `id`         | bigint, PK                    |                                                         |
| `user_id`    | FK `users`, cascade on delete | Pemilik entri (FR-21). Hapus akun ikut menghapus entri. |
| `entry_date` | date                          | Tanggal kalender Asia/Jakarta                           |
| `mood_score` | tinyint unsigned              | 1 sampai 5                                              |
| `journal`    | text, nullable, **encrypted** | Kosong berarti check-in tanpa jurnal                    |
| `created_at` | timestamp                     |                                                         |
| `updated_at` | timestamp                     | Dipakai job analisis untuk menolak hasil yang basi      |

Indeks: **unique `(user_id, entry_date)`** (satu entri per hari).

`journal` memakai cast `encrypted` Laravel, sehingga isi jurnal tidak terbaca langsung dari
database (NFR-06). Akibatnya isi jurnal tidak bisa dicari lewat SQL; fitur kita memang tidak
membutuhkannya.

> Catatan: `CLAUDE.md` di luar repo menyebut tabel `mood_entries` dan `journal_entries` terpisah.
> Draf ini mengikuti `.ai/guidelines/tandarasa-domain.md` dan FSD 2.9.3 (satu tabel `entries`),
> karena satu entri harian memang berisi mood dan jurnal sekaligus.

### 1.2 `analysis_results` (hasil analisis per entri, FR-09 sampai FR-12, FR-24)

Kolom mengikuti `docs/ai-contract.md` bagian 2, ditambah `tier` dan `entry_updated_at`.

| Kolom              | Tipe                                    | Keterangan                                                  |
| ------------------ | --------------------------------------- | ----------------------------------------------------------- |
| `id`               | bigint, PK                              |                                                             |
| `entry_id`         | FK `entries`, unique, cascade on delete | Satu hasil per entri; analisis ulang menimpa                |
| `status`           | string (`AnalysisStatus`)               | `pending`, `processing`, `done`, `failed`                   |
| `attempts`         | tinyint unsigned, default 0             | Jumlah percobaan panggilan AI                               |
| `risk_signal`      | string (`RiskSignal`), nullable         | Null selama belum `done`                                    |
| `tier`             | string (`Tier`), nullable               | Dihitung `TierCalculator` setelah `done`                    |
| `safety_flag`      | boolean, default false                  | FR-24, terpisah dari tier                                   |
| `emotions`         | json, nullable                          | Maks. 3 label                                               |
| `reason`           | string(200), nullable                   | Alasan singkat untuk pengguna                               |
| `summary`          | text, nullable                          | Ringkasan kondisi                                           |
| `source`           | string (`AnalysisSource`), nullable     | `ai` atau `mood_rule`; null selama belum `done`             |
| `prompt_version`   | string, nullable                        | Wajib bila `source = ai`                                    |
| `model`            | string, nullable                        | Nama model yang menjawab                                    |
| `entry_updated_at` | timestamp                               | `updated_at` entri yang sedang dianalisis (anti hasil basi) |
| `analyzed_at`      | timestamp, nullable                     |                                                             |
| `created_at`       | timestamp                               |                                                             |
| `updated_at`       | timestamp                               |                                                             |

Indeks tambahan: `(status, updated_at)` untuk perintah terjadwal yang mencoba ulang hasil `failed`.

Tidak ada `user_id` di tabel ini. Kepemilikan selalu dicek lewat `entry.user_id`, sehingga tidak ada
dua sumber kebenaran.

### 1.3 Tambahan di `users` (FR-03, FR-05, FR-22)

| Kolom              | Tipe                    | Keterangan                                       |
| ------------------ | ----------------------- | ------------------------------------------------ |
| `consent_version`  | string, nullable        | Versi teks persetujuan yang disetujui, mis. `v1` |
| `consented_at`     | timestamp, nullable     | Waktu persetujuan                                |
| `reminder_enabled` | boolean, default false  | Preferensi pengingat check-in                    |
| `reminder_time`    | time, nullable          | Jam pengingat (Asia/Jakarta)                     |

### 1.4 `support_contacts` (Pusat Dukungan, FR-18, FR-19, FR-24)

Kontak **tidak boleh dikarang**. Seeder awal hanya berisi placeholder yang jelas ditandai, sampai tim
mendapat data dari sumber resmi kampus.

| Kolom         | Tipe                | Keterangan                                       |
| ------------- | ------------------- | ------------------------------------------------ |
| `id`          | bigint, PK          |                                                  |
| `type`        | string              | `campus_counseling` atau `emergency`             |
| `name`        | string              | Nama layanan                                     |
| `phone`       | string, nullable    |                                                  |
| `hours`       | string, nullable    | Jam layanan, mis. "Senin–Jumat 08.00–15.00"      |
| `url`         | string, nullable    |                                                  |
| `source`      | string              | Sumber resmi (URL atau nama dokumen)             |
| `verified_at` | date, nullable      | Tanggal terakhir dicek; null = belum terverifikasi |
| `sort_order`  | smallint, default 0 |                                                  |

### 1.5 Enum

Sudah ada: `RiskSignal`, `Tier`. Ditambahkan: `AnalysisStatus` (`pending`, `processing`, `done`,
`failed`) dan `AnalysisSource` (`ai`, `mood_rule`).

`Tier` diberi method `label()` untuk teks UI: Hijau → "Stabil", Kuning → "Perlu perhatian",
Merah → "Butuh dukungan".

### 1.6 Relasi

```
users 1 ──< entries 1 ── 1 analysis_results
support_contacts (berdiri sendiri, tidak terkait pengguna)
```

---

## 2. Aturan umum untuk frontend

- **Pengguna selalu dari sesi login.** Frontend tidak pernah mengirim `user_id`. Setiap Action
  menerima `User $user` dari `auth()->user()`.
- **Akses data orang lain** (mis. membuka hasil entri milik akun lain) berakhir 404, bukan 403,
  supaya keberadaan entri orang lain tidak bocor (FR-21).
- **Error validasi** berupa `ValidationException` biasa, jadi `@error('mood_score')` di Blade langsung
  bekerja. Semua pesan berbahasa Indonesia yang suportif.
- **Status analisis** ditampilkan sebagai berikut (FR-11, NFR-03):

  | `status`                | Teks di UI                                                     |
  | ----------------------- | -------------------------------------------------------------- |
  | `pending`, `processing` | "Sedang membaca entrimu…" (polling, mis. `wire:poll.3s`)       |
  | sama, lewat ±10 detik   | "Analisis tertunda. Entrimu sudah tersimpan." (polling lanjut) |
  | `failed`                | "Analisis tertunda. Entrimu sudah tersimpan." (dicoba ulang otomatis) |
  | `done`                  | Tier, alasan, ringkasan, dan keterangan bukan diagnosis        |

- **Setiap hasil `done`** wajib menampilkan teks: "Hasil ini indikasi awal, bukan diagnosis."
- **Tier tidak boleh dibedakan hanya lewat warna:** selalu ikon dan label dari `Tier::label()`.

---

## 3. Daftar Action

Semua Action ada di `app/Actions/`, satu kelas satu aksi, dipanggil lewat container
(`app(SaveDailyEntry::class)->handle(...)` atau injeksi di method Livewire).

### 3.1 `SaveDailyEntry` (FR-06, FR-07, FR-08, FR-09)

Dipakai di: halaman check-in.

```php
handle(User $user, int $moodScore, ?string $journal): Entry
```

| Input        | Aturan                                                              |
| ------------ | ------------------------------------------------------------------- |
| `moodScore`  | wajib, bilangan bulat 1–5                                           |
| `journal`    | opsional, maks. 2000 karakter; spasi di ujung dibuang; kosong = null |

Proses:

1. Cari entri milik `$user` untuk **hari ini** (Asia/Jakarta). Ada → diubah. Tidak ada → dibuat.
2. Simpan entri lebih dulu (NFR-08).
3. Bila skor atau jurnal berubah: hasil analisis di-reset ke `pending`, lalu job `AnalyzeEntry`
   dikirim ke antrean setelah transaksi selesai. Bila tidak ada yang berubah, analisis lama dipakai.

Keluaran: `Entry` dengan relasi `analysis` sudah dimuat.

| Error validasi | Pesan                                        |
| -------------- | -------------------------------------------- |
| `mood_score`   | "Pilih skor mood dari 1 sampai 5, ya."       |
| `journal`      | "Jurnalnya maksimal 2000 karakter."          |

Entri tanggal sebelumnya tidak bisa diedit karena Action ini selalu bekerja pada hari ini.

### 3.2 `GetTodayEntry` (FR-06, FR-07)

Dipakai di: halaman check-in (isi awal form) dan dashboard ("sudah check-in hari ini?").

```php
handle(User $user): ?Entry   // null bila belum check-in hari ini
```

### 3.3 `GetEntryResult` (FR-12, FR-16, FR-17, FR-18, FR-24)

Dipakai di: halaman hasil setelah check-in dan detail riwayat.

```php
handle(User $user, int $entryId): EntryResult   // 404 bila entri bukan milik $user
```

`EntryResult` (objek data, hanya-baca):

| Field             | Tipe                     | Keterangan                                                    |
| ----------------- | ------------------------ | ------------------------------------------------------------- |
| `entryId`         | int                      |                                                               |
| `entryDate`       | `CarbonImmutable`        |                                                               |
| `moodScore`       | int                      |                                                               |
| `journal`         | ?string                  | Hanya untuk pemilik                                           |
| `status`          | `AnalysisStatus`         |                                                               |
| `tier`            | ?`Tier`                  | Null bila belum `done`                                        |
| `reason`          | ?string                  |                                                               |
| `summary`         | ?string                  |                                                               |
| `emotions`        | list<string>             | Maks. 3                                                       |
| `interventions`   | list<string>             | 1–3 saran, **hanya** bila tier Kuning (FR-17); selain itu kosong |
| `showSupport`     | bool                     | true bila tier Merah **atau** `safety_flag` (FR-18, FR-24)    |
| `safetyFlag`      | bool                     | true → tampilkan juga kontak darurat, apa pun tiernya         |
| `isEditable`      | bool                     | true bila `entryDate` = hari ini                              |

Contoh (tier Kuning):

```json
{
    "entryId": 42,
    "entryDate": "2026-11-27",
    "moodScore": 2,
    "journal": "Tugas numpuk, tidur cuma 4 jam.",
    "status": "done",
    "tier": "kuning",
    "reason": "Beberapa hari terakhir kamu menulis soal kurang tidur dan tugas yang menumpuk.",
    "summary": "Kamu sedang dalam tekanan, tapi masih berusaha mengatur waktu.",
    "emotions": ["lelah", "cemas"],
    "interventions": [
        "Tarik napas pelan 4 hitungan, tahan 4, buang 6. Ulangi 5 kali.",
        "Tulis 3 tugas paling penting besok, sisanya biarkan dulu."
    ],
    "showSupport": false,
    "safetyFlag": false,
    "isEditable": true
}
```

### 3.4 `GetDashboardSummary` (FR-14)

Dipakai di: dashboard (halaman utama setelah login).

```php
handle(User $user): DashboardSummary
```

| Field              | Tipe            | Keterangan                                       |
| ------------------ | --------------- | ------------------------------------------------ |
| `hasCheckedInToday`| bool            | Untuk tombol "Check-in hari ini" / "Ubah entri"  |
| `latest`           | ?`EntryResult`  | Entri terbaru (bisa bukan hari ini); null bila belum pernah check-in |
| `entriesThisWeek`  | int             | Jumlah entri dalam 7 hari terakhir               |

### 3.5 `GetMoodChart` (FR-15)

Dipakai di: dashboard / halaman tren, untuk Chart.js.

```php
handle(User $user): MoodChart   // selalu 7 hari: H-6 sampai hari ini
```

| Field     | Tipe                 | Keterangan                                              |
| --------- | -------------------- | ------------------------------------------------------- |
| `days`    | list<`ChartDay`>     | Tepat 7 item, urut dari H-6 ke hari ini                 |
| `average` | ?float               | Rata-rata skor mood (1 desimal); null bila tidak ada entri |
| `counts`  | array<string, int>   | Jumlah hari per tier, mis. `{"hijau": 3, "kuning": 1, "merah": 0}` |

`ChartDay`: `date` (Y-m-d), `label` (mis. "Kam"), `moodScore` (?int, null bila tidak check-in),
`tier` (?`Tier`, null bila tidak ada entri atau belum `done`).

Hari tanpa entri tetap muncul dengan nilai null, supaya grafik menampilkan celah, bukan nilai 0.

### 3.6 `GetEntryHistory` (FR-13)

Dipakai di: halaman riwayat.

```php
handle(User $user, int $perPage = 10): LengthAwarePaginator<HistoryItem>
```

`HistoryItem`: `entryId`, `entryDate`, `moodScore`, `hasJournal` (bool), `journalExcerpt`
(maks. 80 karakter, ?string), `status`, `tier`. Urut dari tanggal terbaru. Detail lengkap memakai
`GetEntryResult`.

### 3.7 `GetSupportContacts` (FR-18, FR-19, FR-24)

Dipakai di: halaman Pusat Dukungan dan kartu dukungan di halaman hasil.

```php
handle(): Collection<int, SupportContact>
```

Urut berdasarkan `type` (konseling kampus dulu) lalu `sort_order`. Bila `verified_at` null, frontend
menampilkan label "Data belum diverifikasi" dan **tidak** menampilkan nomor sebagai tautan telepon.

### 3.8 Registrasi dengan persetujuan (FR-01, FR-03)

Bukan Action baru: memperluas `app/Actions/Fortify/CreateNewUser.php`. Form registrasi menambah tiga
checkbox wajib:

| Field form              | Isi pernyataan                                                   |
| ----------------------- | ---------------------------------------------------------------- |
| `consent_data`          | Data mood dan jurnal dipakai untuk fitur aplikasi                |
| `consent_ai_processing` | Isi jurnal diproses penyedia layanan AI pihak ketiga untuk analisis |
| `consent_not_diagnosis` | Hasil analisis adalah indikasi awal, bukan diagnosis             |

Ketiganya `accepted`. Pesan error: "Kamu perlu menyetujui pernyataan ini untuk melanjutkan."
Backend menyimpan `consent_version` dan `consented_at`.

### 3.9 Hapus akun (FR-05, NFR-07)

Sudah ada di starter kit (`pages/settings/⚡delete-user-modal.blade.php`). Backend memastikan semua
entri, hasil analisis, dan persetujuan ikut terhapus lewat `cascade on delete`, dan menambah tes
untuk itu. Frontend tidak perlu Action baru; teks modal cukup diterjemahkan.

### 3.10 `UpdateReminderSettings` (FR-05, FR-22), menyusul

```php
handle(User $user, bool $enabled, ?string $time): void   // $time format "HH:MM", wajib bila $enabled
```

Pengingat dikirim lewat email oleh perintah terjadwal, dan tidak dikirim bila pengguna sudah
check-in hari itu.

---

## 4. Proses di belakang layar (tidak dipanggil frontend)

| Komponen                  | Fungsi                                                                 |
| ------------------------- | ---------------------------------------------------------------------- |
| Job `AnalyzeEntry`        | Sesuai `docs/ai-contract.md` bagian 3, lalu menghitung `tier`          |
| `TierCalculator`          | Sudah ada (PR #1)                                                      |
| Perintah retry terjadwal  | Mencoba ulang hasil `failed` berumur < 24 jam, tiap 15 menit           |
| Perintah pengingat        | Mengirim pengingat check-in sesuai `reminder_time`                     |
| `EntryPolicy`             | `view`/`update` hanya untuk pemilik (FR-21)                            |

Bila analisis entri lama baru selesai setelah entri yang lebih baru sudah punya tier (mis. hasil
retry), tier entri-entri sesudahnya dalam jendela 7 hari dihitung ulang.

---

## 5. Keputusan yang perlu disetujui

1. **Satu tabel `entries`** (bukan `mood_entries` + `journal_entries`).
2. **Jurnal dienkripsi** di database dengan cast `encrypted`.
3. **Batas jurnal 2000 karakter.** FSD hanya menyebut "singkat".
4. **Sumber micro-intervention:** usul awal memakai daftar saran yang dikurasi tim
   (`config/interventions.php`), dipilih 1–3 berdasarkan emosi entri. Lebih aman dari saran medis dan
   tidak butuh panggilan AI tambahan. Alternatifnya, saran dibuat AI dan divalidasi.
5. **Tiga checkbox persetujuan** saat registrasi (bukan satu).
6. **Akses data orang lain = 404.**
7. **Penyedia AI** untuk `AnalyzeEntry` (belum ditentukan, FSD Tabel 2.9).
