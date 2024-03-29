<?php

namespace App\Http\Controllers\API;

use Http;
use App\Models\Menu;
use App\Models\Kurir;
use App\Mail\VerifMail;
use App\Models\Customer;
use App\Models\Transaksi;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\DetailTransaksi;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Http\Middleware\ApiKeyMiddleware;
use Illuminate\Support\Facades\Validator;

class ApiController extends Controller
{

    private VerifMail $verifMail;
    // Required apikey mobile
    public function __construct()
    {
        $this->verifMail = new VerifMail();
    }

    // Controller Login
    public function loginUser(Request $request)
    {
        $validadte = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'token_fcm' => 'required',
        ]);

        $dataEmail = $request->email;
        $dataTokenFcm = $request->token_fcm;

        if ($validadte->fails()) {
            return $this->sendMassage($validadte->errors()->first(), 400, false);
        } else {
            $customer = Customer::where('email', $dataEmail)->first();
            if ($customer) {
                if ($customer->email_verified == true) {
                    if (Hash::check($request->password, $customer->password)) {
                        $token = Str::random(200);
                        Customer::where('email', $dataEmail)->update([
                            'token' => $token,
                            // 'token_fcm' => $dataTokenFcm,
                        ]);
                        $dataCustomer = Customer::where('email', $dataEmail)->first();
                        return $this->sendMassage($dataCustomer, 200, true);
                    }
                    return $this->sendMassage('Password salah', 400, false);
                }
                return $this->sendMassage('Akun anda belum terverifikasi', 400, false);
            }
            return $this->sendMassage('Username tidak ditemukan', 400, false);
        }
    }

    // Controller Register
    public function registerUser(Request $request)
    {
        $validadte = Validator::make($request->all(), [
            'nama' => 'required',
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $RandomNumber = rand(10000, 99999);

        if ($validadte->fails()) {
            return $this->sendMassage($validadte->errors()->first(), 400, false);
        } else {
            $dataEmail = $request->input('email');
            $customer = Customer::where('email', $dataEmail)->first();
            if ($customer) {
                return $this->sendMassage('Akun yang anda gunakan telah terdapat pada list, lakukan aktifasi terlebih dahulu', 400, false);
            } else {

                $isRegister = Customer::create([
                    'id_customer' => "CUST" . $RandomNumber,
                    'nama' => $request->input('nama'),
                    'no_telepon' => $request->input('no_telepon'),
                    'alamat' => $request->input('alamat'),
                    'email' => $request->input('email'),
                    'password' => Hash::make($request->input('password')),
                ]);

                if (isset($isRegister)) {

                    $dataUser = [
                        'email' => $dataEmail,
                        'kode' => null
                    ];

                    $this->verifMail->dataUser = $dataUser;

                    Mail::to($request->input('email'))->send($this->verifMail);
                    return response()->json([
                        'data' => "Selamat anda berhasil registrasi",
                        'code' => 200,
                        'status' => true
                    ], 200);
                    ;
                }
            }
        }

    }

    public function verified($id)
    {

        $editCustomer = Customer::where("email", $id)->first()->update([
            'email_verified' => true
        ]);

        if ($editCustomer) {
            $hasil = Customer::where("email", $id)->first();
            return response()->json([
                'data' => $hasil->email_verified,
                'code' => 200,
                'status' => true
            ], 200);
        } else {
            return response()->json([
                'data' => null,
                'code' => 400,
                'status' => false
            ], 400);
        }
    }

    //Controller Kurir
    public function loginKurir(Request $request)
    {
        $validadte = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'token_fcm' => 'required',
        ]);

        $dataEmail = $request->email;
        $dataTokenFcm = $request->token_fcm;

        if ($validadte->fails()) {
            return $this->sendMassage($validadte->errors()->first(), 400, false);
        } else {
            $kurir = Kurir::where('email', $dataEmail)->first();
            if ($kurir) {
                if (Hash::check($request->password, $kurir->password)) {
                    $token = Str::random(200);
                    Kurir::where('email', $dataEmail)->update([
                        'token' => $token
                        // 'token_fcm' => $dataTokenFcm,
                    ]);
                    $dataKurir = Kurir::where('email', $dataEmail)->first();
                    return $this->sendMassage($dataKurir, 200, true);
                }
                return $this->sendMassage('Password salah', 400, false);
            }
            return $this->sendMassage('Username tidak ditemukan', 400, false);
        }
    }

    public function editProfile(Request $request)
    {
        $token = $request->bearerToken();

        $kurir = Kurir::where('token', $token)->first();

        $dataEmail = $kurir->email;

        if (isset($kurir)) {
            if ($kurir->status == true) {
                Kurir::where('email', $dataEmail)->update([
                    'status' => false
                ]);
                return $this->sendMassage($kurir->status, 200, true);
            } else {
                Kurir::where('email', $dataEmail)->update([
                    'status' => true
                ]);
                return $this->sendMassage($kurir->status, 200, true);
            }
        }
    }

    public function konfirmasiPesanan(Request $request)
    {

        $kode = $request->kode;
        $kantin = $request->kantin;
        $kurir = $request->kurir;
        $kodeTransaksi = $request->kode_tr;
        $buktiPengiriman = $request->bukti_pengiriman;

        $transaksi = Transaksi::with('detail_transaksi.Menu.Kantin')->where('kode_tr', $request->kode_tr)->first();
        $listKodeMenu = collect($transaksi['detail_transaksi'])->pluck('kode_menu')->toArray();

        // return $this->sendMassage($transaksi, 200, true);
        $listIdkantin = Menu::select('id_kantin')->whereIn('id_menu', $listKodeMenu)->get();

        // return $this->sendMassage($listIdkantin, 200, true);

        $idKurir = $transaksi->id_kurir;
        $kode_tr = $transaksi->kode_tr;
        $statusPesanan = $transaksi->status_pesanan;
        $statusKonfirm = $transaksi->status_konfirm;

        $valid = false;
        $valid2 = false;

        if ($kode == '1') {
            foreach ($listIdkantin as $key => $value) {
                if ($value->id_kantin == $kantin) {
                    $statusKonfirm = DetailTransaksi::select('detail_transaksi.status_konfirm', 'detail_transaksi.kode_menu')
                        ->join('menu', 'detail_transaksi.kode_menu', '=', 'menu.id_menu')
                        ->join('kantin', 'menu.id_kantin', '=', 'kantin.id_kantin')
                        ->where('detail_transaksi.kode_tr', $kodeTransaksi)
                        ->where('kantin.id_kantin', $kantin)
                        ->first();

                    $kodeMenu = $statusKonfirm->kode_menu;
                    $status = $statusKonfirm->status_konfirm;

                    if ($status == null) {
                        $konfirm_status = "memasak";
                        DetailTransaksi::where('kode_menu', $kodeMenu)->where('kode_tr', $kodeTransaksi)->update([
                            'status_konfirm' => $konfirm_status,
                        ]);
                        // return $this->sendMassage('Memasak', 200, true);
                    } else {
                        return $this->sendMassage('Anda sudah melakukan Konfirmasi Memasak', 200, true);
                    }
                }
            }

            $validatePesanan = DetailTransaksi::select('detail_transaksi.status_konfirm', 'detail_transaksi.kode_menu')->where('detail_transaksi.kode_tr', $kodeTransaksi)->get()->toArray();

            foreach ($validatePesanan as $key => $value) {
                if ($value['status_konfirm'] != null) {
                    if ($value['status_konfirm'] == 'memasak') {
                        $valid = true;
                    } else {
                        $valid = false;
                        break;
                    }
                } else {
                    $valid = false;
                    break;
                }
            }

            $temp = [
                'valid' => $valid
            ];

            array_push($validatePesanan, $temp);

            if ($valid == true) {
                Transaksi::where('kode_tr', $kodeTransaksi)->update([
                    'status_pesanan' => '2',
                ]);
            }

            return $validatePesanan;
            // return $this->sendMassage('status konfirm = 1, status pesanan = 2, status pengiriman = proses', 200, true);
        } elseif ($kode == '2') {
            foreach ($listIdkantin as $key => $value) {
                if ($value->id_kantin == $kantin) {
                    $statusKonfirm = DetailTransaksi::select('detail_transaksi.status_konfirm', 'detail_transaksi.kode_menu')
                        ->join('menu', 'detail_transaksi.kode_menu', '=', 'menu.id_menu')
                        ->join('kantin', 'menu.id_kantin', '=', 'kantin.id_kantin')
                        ->where('detail_transaksi.kode_tr', $kodeTransaksi)
                        ->where('kantin.id_kantin', $kantin)
                        ->first();

                    $kodeMenu = $statusKonfirm->kode_menu;
                    $status = $statusKonfirm->status_konfirm;
                    // return $kodeMenu;
                    if ($status == 'memasak') {
                        $konfirm_status = "selesai";
                        DetailTransaksi::where('kode_menu', $kodeMenu)->where('kode_tr', $kodeTransaksi)->update([
                            'status_konfirm' => $konfirm_status,
                        ]);
                    } else {
                        $konfirm_status = null;
                        DetailTransaksi::where('kode_menu', $kodeMenu)->where('kode_tr', $kodeTransaksi)->update([
                            'status_konfirm' => $konfirm_status,
                        ]);
                        return $this->sendMassage('Anda sudah menyelesaikan pesanan', 200, true);
                    }
                }
            }

            $validatePesanan = DetailTransaksi::select('detail_transaksi.status_konfirm', 'detail_transaksi.kode_menu')->where('detail_transaksi.kode_tr', $kodeTransaksi)->get()->toArray();

            foreach ($validatePesanan as $key => $value) {
                if ($value['status_konfirm'] != null) {
                    if ($value['status_konfirm'] == 'selesai') {
                        $valid2 = true;
                    } else {
                        $valid2 = false;
                        break;
                    }
                } else {
                    $valid2 = false;
                    break;
                }
            }

            $temp = [
                'valid' => $valid2
            ];

            array_push($validatePesanan, $temp);

            if ($valid2 == true) {
                Transaksi::where('kode_tr', $kodeTransaksi)->update([
                    'status_pesanan' => '3',
                ]);
            }

            return $validatePesanan;
            // return $this->sendMassage('status konfirm = 1, status pesanan = 3, status pengiriman = proses', 400, true);
        } elseif ($kode == '3') {
            if ($kode_tr == $kodeTransaksi) {
                if ($idKurir == $kurir && $statusKonfirm == '1' && $statusPesanan == '3') {
                    Transaksi::where('kode_tr', $kodeTransaksi)->update([
                        'status_konfirm' => '2',
                        'status_pengiriman' => 'kirim'
                    ]);
                    return $this->sendMassage('Pesanan dikirim', 200, true);
                }
                return $this->sendMassage('Kode transaksi tidak sesuai', 200, true);
            }
            // return $this->sendMassage('status konfirm = 2, status pesanan = 3, status pengiriman = kirim', 400, true);
        } elseif ($kode == '4') {
            if ($kode_tr == $kodeTransaksi) {
                if ($idKurir == $kurir && $statusKonfirm == '2' && $statusPesanan == '3') {
                    Transaksi::where('kode_tr', $kodeTransaksi)->update([
                        'status_konfirm' => '2',
                        'status_pengiriman' => 'terima',
                        'bukti_pengiriman' => $buktiPengiriman
                    ]);
                    return $this->sendMassage('Pesanan diterima', 200, true);
                }
                return $this->sendMassage('Kode transaksi tidak sesuai', 200, true);
            }
            // return $this->sendMassage('status konfirm = 2, status pesanan = 3, status pengiriman = terima', 400, true);
        } elseif ($kode == '5') {
            if ($kode_tr == $kodeTransaksi) {
                if ($kode_tr == $buktiPengiriman && $statusKonfirm == '2' && $statusPesanan == '3') {
                    Transaksi::where('kode_tr', $kodeTransaksi)->update([
                        'status_konfirm' => '3',
                    ]);
                    return $this->sendMassage('Pesanan selesai', 200, true);
                }
                return $this->sendMassage('Kode transaksi tidak sesuai', 200, true);
            }
            // return $this->sendMassage('status konfirm = 3, status pesanan = 3, status pengiriman = terima', 400, true);
        }
    }

    public function editCustomer(Request $request)
    {
        $token = $request->bearerToken();
        $user = Customer::where('token', $token)->first();

        if (!$token) {
            return $this->sendMassage('Tolong masukkan token', 400, false);
        }
            $user->nama = $request->input('nama');
            $user->email = $request->input('email');
            $user->no_telepon = $request->input('no_telepon');
            $user->alamat = $request->input('alamat');

            $user->save();

            return $this->sendMassage('Data terupdate', 200, true);

    }

    public function profileImage(Request $request){

        $token = $request->bearerToken();
        $user = Customer::where('token', $token)->first();

        if(!$token){
            return $this->sendMassage('Tolong masukkan token', 200, false);
        }

            if ($request->hasFile('foto')) {
                $myFile = 'customer/'.$user->foto;
                if(File::exists($myFile))
                {
                    File::delete($myFile);
                }

                $request->file('foto')->move('customer/', $request->file('foto')->getClientOriginalName());
                $user->foto=$request->file('foto')->getClientOriginalName();

                $user->save();

                return $this->sendMassage('Foto Profile terupdate', 200, false);
            }
    }

    public function tampilCustomer(Request $request){

        $token = $request->bearerToken();
        $customer = Customer::where('token', $token)->first();

        if(!$token){
            return $this->sendMassage('Tolong masukkan token', 200, false);
        }

        return $this->sendMassage($customer, 200, true);
    }

    public function sendMassage($text, $kode, $status)
    {
        return response()->json([
            'data' => $text,
            'code' => $kode,
            'status' => $status
        ], $kode);
    }
}
