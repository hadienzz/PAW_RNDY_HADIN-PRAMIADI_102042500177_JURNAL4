<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        if (!Auth::user()->isMahasiswa()) {
            abort(403, 'Akses ditolak. Pengumpulan tugas hanya dapat dilakukan oleh Mahasiswa.');
        }

        $user = Auth::user();

        $enrollment = $assignment->course->students()->where('users.id', $user->id)->first();
        if (!$enrollment || $enrollment->pivot->status !== 'approved') {
            abort(403, 'Akses ditolak. Anda belum disetujui oleh Dosen untuk mengikuti mata kuliah tugas ini.');
        }

        if ($assignment->isOverdue()) {
            return back()->with('error', 'Gagal mengumpulkan: Batas waktu pengumpulan (deadline) tugas telah berakhir.');
        }

        $validated = $request->validate([
            'jawaban' => ['required', 'string', 'min:10', 'max:20000'],
        ], [
            'jawaban.required' => 'Teks jawaban tugas wajib diisi.',
            'jawaban.min' => 'Jawaban terlalu pendek. Tuliskan jawaban minimal 10 karakter.',
            'jawaban.max' => 'Jawaban melebihi batas maksimal 20.000 karakter.',
        ]);

        Submission::updateOrCreate(
            [
                'assignment_id' => $assignment->id,
                'user_id' => $user->id,
            ],
            [
                'jawaban' => $validated['jawaban'],
                'submitted_at' => now(),
            ]
        );

        return back()->with('success', 'Jawaban tugas Anda berhasil disimpan ke sistem.');
    }

    public function grade(Request $request, Submission $submission): RedirectResponse
    {
        // TODO 5a (grade): Cek apakah dosen adalah pengampu mata kuliah, validasi nilai (0-100) & catatan_dosen, lalu update submission.
        // 1. Pastikan $submission->assignment->course->dosen_id === Auth::id(), jika tidak abort(403).
        // 2. Validasi input 'nilai' (required|integer|min:0|max:100) dan 'catatan_dosen' (nullable|string|max:1000).
        // 3. Simpan perubahan menggunakan $submission->update(...) dan redirect back() dengan pesan sukses.

        abort_unless($submission->assignment->course->dosen_id === Auth::id(), 403);

        $validated = $request->validateWithBag('grade_'.$submission->id, [
            'nilai' => ['required', 'integer', 'min:0', 'max:100'],
            'catatan_dosen' => ['nullable', 'string', 'max:1000'],
        ], [
            'nilai.required' => 'Nilai wajib diisi.',
            'nilai.integer' => 'Nilai harus berupa bilangan bulat.',
            'nilai.min' => 'Nilai minimal 0.',
            'nilai.max' => 'Nilai maksimal 100.',
            'catatan_dosen.string' => 'Catatan dosen harus berupa teks.',
            'catatan_dosen.max' => 'Catatan dosen maksimal 1000 karakter.',
        ]);

        $submission->update($validated);

        return back()->with('success', 'Nilai tugas berhasil disimpan.');
    }

    public function exportCsv(Assignment $assignment): StreamedResponse
    {
        // TODO 5b (exportCsv): Cek kepemilikan dosen, lalu gunakan response()->streamDownload() dan fputcsv() untuk mengunduh data nilai mahasiswa.
        // 1. Pastikan $assignment->course->dosen_id === Auth::id(), jika tidak abort(403).
        // 2. Muat relasi course.approvedStudents dan submissions dari $assignment.
        // 3. Kembalikan response()->streamDownload(...) yang menulis header & baris CSV menggunakan fopen('php://output', 'w') dan fputcsv().

        abort_unless($assignment->course->dosen_id === Auth::id(), 403);

        $assignment->load(['course.approvedStudents', 'submissions']);
        $submissions = $assignment->submissions->keyBy('user_id');

        return response()->streamDownload(function () use ($assignment, $submissions) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['NIM', 'Nama Mahasiswa', 'Status Pengumpulan', 'Waktu Pengumpulan', 'Nilai', 'Status Nilai', 'Catatan Dosen'], escape: '');

            foreach ($assignment->course->approvedStudents as $student) {
                $submission = $submissions->get($student->id);
                $row = [
                    $student->nomor_induk,
                    $student->name,
                    $submission ? 'Sudah Mengumpulkan' : 'Belum Mengumpulkan',
                    $submission?->submitted_at?->format('Y-m-d H:i:s'),
                    $submission?->nilai,
                    $submission?->status_nilai,
                    $submission?->catatan_dosen,
                ];

                // Cegah spreadsheet membaca teks sebagai formula.
                $row = array_map(
                    fn ($value) => is_string($value) && preg_match('/^\s*[=+\-@]|\A[\t\r\n]/u', $value)
                        ? "'".$value
                        : $value,
                    $row,
                );

                fputcsv($output, $row, escape: '');
            }

            fclose($output);
        }, "rekap-nilai-tugas-{$assignment->id}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
