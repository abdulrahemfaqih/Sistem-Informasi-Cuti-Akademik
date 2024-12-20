<?php

namespace App\Models;

use App\Models\User;
use App\Models\PengajuanBss;
use App\Models\ProgramStudi;
use App\Models\HistoriMahasiswa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Mahasiswa extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'mahasiswa';
    protected $guarded = [];


    public function prodi()
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function historiMahasiswa()
    {
        return $this->hasMany(HistoriMahasiswa::class);
    }

    public function pengajuanBss()
    {
        return $this->hasMany(PengajuanBss::class);
    }

    public function tanggunganLab()
    {
        return $this->hasMany(TanggunganLab::class);
    }

    public function tanggunganFakultas()
    {
        return $this->hasMany(TanggunganFakultas::class);
    }

    public function tanggunganPerpustakaan()
    {
        return $this->hasMany(TanggunganPerpustakaan::class);
    }

    public function suratKeteranganCuti()
    {
        return $this->hasMany(SuratKeteranganCuti::class);
    }
}

