<?php

namespace App\Models;

use App\Models\PengajuanBss;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SuratKeteranganCuti extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'surat_keterangan_cuti';
    protected $guarded = [];

    public function pengajuanBss()
    {
        return $this->belongsTo(PengajuanBss::class);
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function historiMahasiswa()
    {
        return $this->belongsTo(HistoriMahasiswa::class);
    }

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_masuk_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_masuk_id');
    }
}
