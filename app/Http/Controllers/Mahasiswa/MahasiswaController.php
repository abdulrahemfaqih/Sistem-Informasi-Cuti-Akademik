<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\PengajuanBss;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\DokumenPendukung;
use App\Models\HistoriMahasiswa;
use App\Http\Controllers\Controller;
use App\Mail\NotifikasiPengajuanBssBaru;
use App\Mail\PengajuanBssMahasiswa;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class MahasiswaController extends Controller
{
  public function indexBss()
  {
    $pengajuanBss = PengajuanBss::where('mahasiswa_id', auth()->user()->mahasiswa->id)->latest()->get();
    $semesterAktif = Semester::where('status', 'aktif')->first() ?? null;

    $statusList = ['belum lengkap', 'diajukan'];

    $sudahMengajukan = false;
    $sudahDisetujui = false;

    if ($semesterAktif) {
      $sudahMengajukan = PengajuanBss::where('mahasiswa_id', auth()->user()->mahasiswa->id)
        ->where('tahun_ajaran_id', $semesterAktif->tahunAjaran->id)
        ->where('semester_id', $semesterAktif->id)
        ->whereIn('status', $statusList)
        ->exists();

      $sudahDisetujui = PengajuanBss::where('mahasiswa_id', auth()->user()->mahasiswa->id)
        ->where('tahun_ajaran_id', $semesterAktif->tahunAjaran->id)
        ->where('semester_id', $semesterAktif->id)
        ->where('status', 'disetujui')
        ->exists();
    }

    return view('mahasiswa.pengajuan_bss', compact('pengajuanBss', 'semesterAktif', 'sudahMengajukan', 'sudahDisetujui'));
  }

  public function showBss($IdPengajuanBss)
  {
    $pengajuanBss = PengajuanBss::findOrFail($IdPengajuanBss);

    if ($pengajuanBss->status === 'diajukan') {
      return view('mahasiswa.detail_pengajuan_bss', compact('pengajuanBss'));
    } else {
      return view('mahasiswa.detail_cuti_mahasiswa', compact('pengajuanBss'));
    }
  }

  public function storeBss(Request $request)
  {
    $rules = [
      'semester_id' => 'required',
      'tahun_ajaran_id' => 'required',
      'alasan' => 'required|in:1,2,3',
    ];

    $messages = [
      'semester_id.required' => 'Semester harus diisi!',
      'tahun_ajaran_id.required' => 'Tahun ajaran harus diisi!',
      'alasan.required' => 'Alasan harus diisi!',
      'alasan.in' => 'Alasan tidak valid!',
    ];

    $request->validate($rules, $messages);

    if (Mahasiswa::where('id', auth()->user()->mahasiswa->id)->where('status', 'aktif')->doesntExist()) {
      return redirect()->route('mahasiswa.bss.index')->with('error', 'Maaf, Anda tidak dapat mengajukan cuti karena status mahasiswa tidak aktif.');
    }

    $statusList = ['belum lengkap', 'diajukan', 'disetujui'];

    foreach ($statusList as $status) {
      if (PengajuanBss::where('mahasiswa_id', auth()->user()->mahasiswa->id)
        ->where('tahun_ajaran_id', $request->tahun_ajaran_id)
        ->where('semester_id', $request->semester_id)
        ->where('status', $status)
        ->exists()
      ) {

        return redirect()->route('mahasiswa.bss.index')
          ->with('error', "Maaf, Anda sudah mengajukan cuti sebelumnya dengan status $status.");
      }
    }

    if (HistoriMahasiswa::where('mahasiswa_id', auth()->user()->mahasiswa->id)->count() > 2) {
      return redirect()->route('mahasiswa.bss.index')->with('error', 'Maaf, batas pengajuan cuti hanya 2 kali.');
    }

    $pengajuanBss = PengajuanBss::create([
      'mahasiswa_id' => auth()->user()->mahasiswa->id,
      'semester_id' => $request->semester_id,
      'tahun_ajaran_id' => $request->tahun_ajaran_id,
      'alasan' => $request->alasan,
    ]);

    // $pdf = Pdf::loadView('mahasiswa.surat_permohonan_bss', [
    //   'pengajuanBss' => $pengajuanBss,
    // ]);

    // $nim = auth()->user()->mahasiswa->nim;

    // $pathFile = 'surat_bss/' . time() . '_surat_permohonan_bss_' . $nim . '.pdf';
    // $namaFile = $nim .  '_surat_permohonan_bss.pdf';
    // Storage::disk('public')->put($pathFile, $pdf->output());

    // $pengajuanBss->update([
    //   'path_file' => $pathFile,
    //   'name_file' => $namaFile,
    // ]);

    return redirect()->route('mahasiswa.bss.edit', ['IdPengajuanBss' => $pengajuanBss->id])->with('success', 'Anda memenuhi syarat untuk mengajukan cuti. Silahkan lengkapi data dokumen pendukung untuk pengajuan cuti.');
  }

  public function editBss($IdPengajuanBss)
  {
    $pengajuanBss = PengajuanBss::findOrFail($IdPengajuanBss);

    return view('mahasiswa.edit_pengajuan_bss', compact('pengajuanBss'));
  }

  public function updateBss(Request $request, $IdPengajuanBss)
  {
    $rules = [
      'surat_permohonan_bss' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
      'kartu_mahasiswa' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
      'surat_bebas_tanggungan_fakultas' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
      'surat_bebas_tanggungan_perpustakaan' => 'required|file|mimes:jgp,jpeg,png,pdf|max:2048',
      'surat_bebas_tanggungan_lab' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
    ];

    $messages = [
      'surat_permohonan_bss.required' => 'Surat permohonan BSS harus diisi!',
      'surat_permohonan_bss.file' => 'Surat permohonan BSS harus berupa file!',
      'surat_permohonan_bss.mimes' => 'Surat permohonan BSS harus berupa file berformat jpg, jpeg, png, atau pdf!',
      'surat_permohonan_bss.max' => 'Ukuran file surat permohonan BSS maksimal 2MB!',
      'kartu_mahasiswa.required' => 'Kartu mahasiswa harus diisi!',
      'kartu_mahasiswa.file' => 'Kartu mahasiswa harus berupa file!',
      'kartu_mahasiswa.mimes' => 'Kartu mahasiswa harus berupa file berformat jpg, jpeg, png, atau pdf!',
      'kartu_mahasiswa.max' => 'Ukuran file kartu mahasiswa maksimal 2MB!',
      'surat_bebas_tanggungan_fakultas.required' => 'Surat bebas tanggungan fakultas harus diisi!',
      'surat_bebas_tanggungan_fakultas.file' => 'Surat bebas tanggungan fakultas harus berupa file!',
      'surat_bebas_tanggungan_fakultas.mimes' => 'Surat bebas tanggungan fakultas harus berupa file berformat jpg, jpeg, png, atau pdf!',
      'surat_bebas_tanggungan_fakultas.max' => 'Ukuran file surat bebas tanggungan fakultas maksimal 2MB!',
      'surat_bebas_tanggungan_perpustakaan.required' => 'Surat bebas tanggungan perpustakaan harus diisi!',
      'surat_bebas_tanggungan_perpustakaan.file' => 'Surat bebas tanggungan perpustakaan harus berupa file!',
      'surat_bebas_tanggungan_perpustakaan.mimes' => 'Surat bebas tanggungan perpustakaan harus berupa file berformat jpg, jpeg, png, atau pdf!',
      'surat_bebas_tanggungan_perpustakaan.max' => 'Ukuran file surat bebas tanggungan perpustakaan maksimal 2MB!',
      'surat_bebas_tanggungan_lab.file' => 'Surat bebas tanggungan laboratorium harus berupa file!',
      'surat_bebas_tanggungan_lab.mimes' => 'Surat bebas tanggungan laboratorium harus berupa file berformat jpg, jpeg, png, atau pdf!',
      'surat_bebas_tanggungan_lab.max' => 'Ukuran file surat bebas tanggungan laboratorium maksimal 2MB!',
    ];

    $request->validate($rules, $messages);

    $pengajuanBss = PengajuanBss::findOrFail($IdPengajuanBss);
    $nim = auth()->user()->mahasiswa->nim;

    $suratPermohonanBss = $request->file('surat_permohonan_bss');
    $kartuMahasiswa = $request->file('kartu_mahasiswa');
    $suratBebasTanggunganFakultas = $request->file('surat_bebas_tanggungan_fakultas');
    $suratBebasTanggunganPerpustakaan = $request->file('surat_bebas_tanggungan_perpustakaan');

    $suratBebasTanggunganLaboratorium = null;
    if ($request->hasFile('surat_bebas_tanggungan_lab')) {
      $suratBebasTanggunganLaboratorium = $request->file('surat_bebas_tanggungan_lab');
    }

    $jenisDokumen = [
      'kartu_mahasiswa' => $kartuMahasiswa,
      'surat_bebas_tanggungan_fakultas' => $suratBebasTanggunganFakultas,
      'surat_bebas_tanggungan_perpustakaan' => $suratBebasTanggunganPerpustakaan,
      'surat_permohonan_bss' => $suratPermohonanBss,
    ];

    if ($suratBebasTanggunganLaboratorium) {
      $jenisDokumen['surat_bebas_tanggungan_lab'] = $suratBebasTanggunganLaboratorium;
    }

    foreach ($jenisDokumen as $jenis => $path) {
      $fileName = time() . '_' . $nim . '_' . $jenis . '.' . $path->getClientOriginalExtension();
      $pathFile = $path->storeAs($jenis, $fileName, 'public');

      DokumenPendukung::create([
        'pengajuan_bss_id' => $pengajuanBss->id,
        'path_file' => $pathFile,
        'nama_file' => $nim . '_' . $jenis . '.' . $path->getClientOriginalExtension(),
        'jenis_dokumen' => $jenis,
      ]);
    }

    $pengajuanBss->update(['status' => 'diajukan', 'diajukan_pada' => now()]);
    $bakEmail = 'faqih3935@gmail.com';

    Mail::to($bakEmail)->queue(new PengajuanBssMahasiswa($pengajuanBss));

    return redirect()->route('mahasiswa.bss.index')->with('success', 'Pengajuan cuti berhasil diajukan! Silahkan tunggu konfirmasi persetujuan dari pihak BAK.');
  }

  public function cetakBss($IdPengajuanBss)
  {
    $pengajuanBss = PengajuanBss::findOrFail($IdPengajuanBss);

    return view('mahasiswa.surat_permohonan_bss', compact('pengajuanBss'));
  }
}
