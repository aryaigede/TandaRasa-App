<?php

namespace App\Actions;

use App\Enums\AnalysisStatus;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Menyimpan check-in hari ini: skor mood dan jurnal opsional (FR-06, FR-07, FR-08).
 *
 * Satu entri per pengguna per tanggal Asia/Jakarta. Menyimpan lagi di hari yang sama berarti
 * mengedit entri itu. Entri tanggal sebelumnya tidak pernah tersentuh karena Action ini selalu
 * bekerja pada hari ini.
 */
class SaveDailyEntry
{
    public const int JOURNAL_MAX_LENGTH = 2000;

    /**
     * @throws ValidationException
     */
    public function handle(User $user, int $moodScore, ?string $journal): Entry
    {
        $journal = $this->normalizeJournal($journal);

        Validator::make(
            ['mood_score' => $moodScore, 'journal' => $journal],
            [
                'mood_score' => ['required', 'integer', 'between:1,5'],
                'journal' => ['nullable', 'string', 'max:'.self::JOURNAL_MAX_LENGTH],
            ],
            [
                'mood_score.required' => 'Pilih skor mood dari 1 sampai 5, ya.',
                'mood_score.integer' => 'Pilih skor mood dari 1 sampai 5, ya.',
                'mood_score.between' => 'Pilih skor mood dari 1 sampai 5, ya.',
                'journal.string' => 'Jurnalnya maksimal 2000 karakter.',
                'journal.max' => 'Jurnalnya maksimal 2000 karakter.',
            ],
        )->validate();

        return DB::transaction(function () use ($user, $moodScore, $journal): Entry {
            $today = now()->toDateString();

            $entry = $user->entries()->onDate($today)->first()
                ?? $user->entries()->make(['entry_date' => $today]);

            $entry->fill(['mood_score' => $moodScore, 'journal' => $journal]);

            $contentChanged = ! $entry->exists || $entry->isDirty(['mood_score', 'journal']);

            // Entri disimpan lebih dulu; analisis tidak boleh menggagalkan penyimpanan (NFR-08).
            $entry->save();

            if ($contentChanged || $entry->analysis()->doesntExist()) {
                $this->markAnalysisPending($entry);
            }

            return $entry->load('analysis');
        });
    }

    /**
     * Spasi di ujung dibuang; jurnal yang kosong setelah itu berarti check-in tanpa jurnal.
     */
    private function normalizeJournal(?string $journal): ?string
    {
        $journal = trim((string) $journal);

        return $journal === '' ? null : $journal;
    }

    /**
     * Hasil analisis lama tidak lagi sesuai isi entri, jadi dikosongkan dan ditandai tertunda
     * (FR-09, FR-11). `entry_updated_at` dipakai job analisis untuk menolak hasil yang basi.
     *
     * Job AnalyzeEntry dikirim ke antrean dari sini setelah job itu dibuat (task service AI).
     */
    private function markAnalysisPending(Entry $entry): void
    {
        $entry->analysis()->updateOrCreate([], [
            'status' => AnalysisStatus::Pending,
            'attempts' => 0,
            'risk_signal' => null,
            'tier' => null,
            'safety_flag' => false,
            'emotions' => null,
            'reason' => null,
            'summary' => null,
            'suggestions' => null,
            'source' => null,
            'prompt_version' => null,
            'model' => null,
            'analyzed_at' => null,
            'entry_updated_at' => $entry->updated_at,
        ]);
    }
}
