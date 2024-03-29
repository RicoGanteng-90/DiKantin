<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailTransaksi extends Model
{
    use HasFactory;
    protected $table = 'detail_transaksi';
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = array('kode_tr', 'QTY', 'subtotal_bayar', 'kode_menu', 'status_konfirm', 'created_at', 'updated_at');

    public function Transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'kode_tr', 'kode_tr');
    }

    public function Menu()
    {
        return $this->hasOne(Menu::class, 'id_menu', 'kode_menu');
    }
}
