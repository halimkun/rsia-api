<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RsiaSuratInternalController extends Controller
{
    public function index(Request $request)
    {
        $rsia_surat_internal = \App\Models\RsiaSuratInternal::select("*")->with(['pj_detail' => function ($q) {
            $q->select('nip', 'nama');
        }, 'penerima']);

        $data = $rsia_surat_internal->orderBy('created_at', 'desc')
            ->orderBy('no_surat', 'desc')
            ->whereDoesntHave('memo');

        if ($request->keyword) {
            $data = $data->where(function ($q) use ($request) {
                $q->where('no_surat', 'like', '%' . $request->keyword . '%')
                    ->orWhere('perihal', 'like', '%' . $request->keyword . '%')
                    ->orWhere('tempat', 'like', '%' . $request->keyword . '%')
                    ->orWhere('pj', 'like', '%' . $request->keyword . '%')
                    ->orWhereHas('pj_detail', function ($q) use ($request) {
                        $q->where('nama', 'like', '%' . $request->keyword . '%');
                    });
            });
        }

        if ($request->tgl_terbit) {
            $data = $data->where('tgl_terbit', $request->tgl_terbit);
        }

        // status
        if ($request->status) {
            $data = $data->where('status', $request->status);
        }

        if ($request->datatables) {
            if ($request->datatables == 1 || $request->datatables == true || $request->datatables == 'true') {
                $data = $data->get();
                return \Yajra\DataTables\DataTables::of($data)->make(true);
            } else {
                $data = $data->paginate(env('PER_PAGE', 10));
            }
        } else {
            $data = $data->paginate(env('PER_PAGE', 10));
        }

        return isSuccess($data, "Data berhasil ditemukan");
    }

    public function getCalendar(Request $request)
    {
        // get this month and  return [title is perihal, date is tanggal]
        $rsia_surat_internal = \App\Models\RsiaSuratInternal::select('no_surat', 'tempat', 'pj', 'perihal as title', 'tanggal as date', 'tanggal', 'status')
            ->whereHas('penerima')
            ->with('pj_detail');

        if ($request->start && $request->end) {
            $start               = date('Y-m-d', strtotime($request->start . ' +1 day'));
            $msg                 = "Data berhasil ditemukan dari tanggal " . $start . " sampai " . $request->end;
            $rsia_surat_internal = $rsia_surat_internal->whereBetween('tanggal', [$start, $request->end]);
        } else {
            $msg                 = "Data berhasil ditemukan dari tanggal " . date('Y-m-d') . " sampai " . date('Y-m-d');
            $rsia_surat_internal = $rsia_surat_internal->whereMonth('tanggal', date('m'))->whereYear('tanggal', date('Y'));
        }

        $rsia_surat_internal = $rsia_surat_internal->get();

        return isSuccess($rsia_surat_internal, $msg);
    }

    public function get_by(Request $request)
    {
        $rsia_surat_internal = \App\Models\RsiaSuratInternal::with(['pj_detail' => function ($q) {
            $q->select('nip', 'nama');
        }]);
        $data = $this->selSuratInternal($rsia_surat_internal, $request);
        $data = $this->colSuratInternal($rsia_surat_internal, $request);

        if ($request->group) {
            if (in_array($request->group, ['no_surat', 'penerima', 'pj', 'status'])) {
                $data = $data->orderBy('no_surat', 'desc')->get()->groupBy($request->group);
            } else {
                $data = $data->orderBy('no_surat', 'desc')->get();
            }
        } else {
            $data = $data->orderBy('no_surat', 'desc')->get();
        }

        if ($request->datatables) {
            if ($request->datatables == 1 || $request->datatables == true || $request->datatables == 'true') {
                return \Yajra\DataTables\DataTables::of($data)->make(true);
            }
        }

        return isSuccess($data, "Data berhasil ditemukan");
    }

    public function detail(Request $request)
    {
        if (!$request->nomor) {
            return isFail("No surat tidak boleh kosong");
        }

        $surat = \App\Models\RsiaSuratInternal::where('no_surat', $request->nomor)->with(['pj_detail' => function ($q) {
            $q->select('nip', 'nama');
        }])->first();

        if (!$surat) {
            return isFail("Data tidak ditemukan");
        }

        $surat->penerima = \App\Models\RsiaPenerimaUndangan::where('no_surat', $request->nomor)->with(['pegawai' => function ($q) {
            $q->select('nik', 'nama', 'jbtn', 'bidang');
        }])->get();

        return isSuccess($surat, "Data berhasil ditemukan");
    }

    public function detailSimple(Request $request)
    {
        if (!$request->nomor) {
            return isFail("No surat tidak boleh kosong");
        }

        $surat = \App\Models\RsiaSuratInternal::where('no_surat', $request->nomor)->with(['pj_detail' => function ($q) {
            $q->select('nip', 'nama');
        }])->first();

        if (!$surat) {
            return isFail("Data tidak ditemukan");
        }
        
        return isSuccess($surat, "Data berhasil ditemukan");
    }

    public function create(Request $request)
    {
        // get last surat by nomor surat
        $data = \App\Models\RsiaSuratInternal::select('no_surat')
            ->orderBy('no_surat', 'desc')
            ->whereYear('created_at', date('Y'))
            ->first();

        if ($data) {
            $data = explode('/', $data->no_surat);
        } else {
            $data = [0];
        }

        if (!$request->tgl_terbit) {
            return isFail("Tanggal terbit tidak boleh kosong");
        }

        // last number
        $date_now    = $request->tgl_terbit ? date('dmy', strtotime($request->tgl_terbit)) : date('dmy');
        $last_number = $data[0];
        $last_number = str_pad($last_number + 1, 3, '0', STR_PAD_LEFT);
        $nomor_surat = $last_number . '/A/S-RSIA/' . $date_now;

        // check request
        if (!$request->perihal) {
            return isFail("Perihal tidak boleh kosong");
        }

        if (!$request->pj) {
            return isFail("PJ tidak boleh kosong");
        }

        if (!$request->tanggal) {
            return isFail("Tanggal tidak boleh kosong");
        }

        if (!$request->tempat) {
            return isFail("Tempat tidak boleh kosong");
        }

        // if (!$request->karyawan) {
        //     return isFail("Penerima tidak boleh kosong");
        // }

        try {
            // Start a database transaction
            \Illuminate\Support\Facades\DB::beginTransaction();

            $rsia_surat_internal = \App\Models\RsiaSuratInternal::create([
                'no_surat'   => $nomor_surat,
                'perihal'    => $request->perihal,
                'tempat'     => $request->tempat,
                'pj'         => $request->pj,
                'tanggal'    => $request->tanggal,
                'tgl_terbit' => $request->tgl_terbit,
                'catatan'    => $request->catatan ?? '-',
                'status'     => 'pengajuan',
            ]);

            $penerima = $request->karyawan ? $request->karyawan : [];
            foreach ($penerima as $key => $value) {
                $rsia_penerima_undangan           = new \App\Models\RsiaPenerimaUndangan;
                $rsia_penerima_undangan->no_surat = $nomor_surat;
                $rsia_penerima_undangan->penerima = $value;
                $rsia_penerima_undangan->ref      = \App\Models\RsiaSuratInternal::class;

                $nm_pegawai = \App\Models\Pegawai::where('nik', $value)->first();
                $nm         = $nm_pegawai ? $nm_pegawai->nama : '';

                $body = "👋 Halo $nm, anda mendapatkan undangan perihal: \n\n";
                $body .= "$request->perihal \n\n";
                $body .= "Tempat \t: " . $request->tempat . "\n";
                $body .= "Tanggal \t: " . \Carbon\Carbon::parse($request->tanggal)->isoFormat('dddd, D MMMM Y') . "\n";
                $body .= "Jam \t\t\t\t: " . \Carbon\Carbon::parse($request->tanggal)->isoFormat('HH:mm') . "\n";

                // if request->catatan && not empty or null or -
                if ($request->catatan && $request->catatan != '-' && $request->catatan != '') {
                    $body .= "\n";
                    $body .= "Catatan \t: " . $request->catatan . "\n";
                }

                \App\Http\Controllers\PushNotificationPegawai::sendTo(
                    "Undangan baru untuk anda 📨",
                    $body,
                    [
                        'route'      => 'undangan',
                        'kategori'   => 'surat_internal',
                        'no_surat'   => $request->old_nomor,
                        'perihal'    => $request->perihal,
                        'tempat'     => $request->tempat,
                        'tgl_terbit' => $request->tgl_terbit,
                        'tanggal'    => $request->tanggal,
                    ],
                    $value,
                );

                $rsia_penerima_undangan->save();
            }

            // Commit the transaction if everything is successful
            \Illuminate\Support\Facades\DB::commit();

            return isSuccess([
                'no_surat' => $nomor_surat,
                'surat'    => $rsia_surat_internal->toArray(),
            ], "Surat berhasil dibuat");
        } catch (\Exception $e) {
            // An error occurred, rollback the transaction
            \Illuminate\Support\Facades\DB::rollback();

            return isFail("Error: " . $e->getMessage());
        }

        return isSuccess([
            'no_surat' => $nomor_surat,
            'surat'    => $rsia_surat_internal->toArray(),
        ], "Surat berhasil dibuat");
    }

    public function update(Request $request)
    {
        if (!$request->old_nomor) {
            return isFail("No surat tidak boleh kosong");
        }

        $dataSurat = \App\Models\RsiaSuratInternal::where('no_surat', $request->old_nomor)->first();
        if (!$dataSurat) {
            return isFail("Data tidak ditemukan");
        }

        // check request
        if (!$request->perihal) {
            return isFail("Perihal tidak boleh kosong");
        }

        if (!$request->pj) {
            return isFail("PJ tidak boleh kosong");
        }

        if (!$request->tgl_terbit) {
            return isFail("Tanggal terbit tidak boleh kosong");
        }

        if (!$request->tanggal) {
            return isFail("Tanggal tidak boleh kosong");
        }

        if (!$request->tempat) {
            return isFail("Tempat tidak boleh kosong");
        }

        $update_data = [
            'pj'         => $request->pj,
            'no_surat'   => $request->no_surat,
            'perihal'    => $request->perihal,
            'tempat'     => $request->tempat,
            'tanggal'    => $request->tanggal,
            'tgl_terbit' => $request->tgl_terbit,
            'catatan'    => $request->catatan ?? '-',
        ];

        // Update the main record
        // $rsia_surat_internal = \App\Models\RsiaSuratInternal::where('no_surat', $request->nomor);
        // $data = $rsia_surat_internal->update($update_data);

        $data = $dataSurat->update($update_data);

        // Update the PJ record
        // $rsia_surat_internal = \App\Models\RsiaSuratInternal::where('no_surat', $request->nomor);
        // $data = $rsia_surat_internal->update($update_pj);

        // Delete all penerima
        $rsia_penerima_undangan = \App\Models\RsiaPenerimaUndangan::where('no_surat', $request->old_nomor);
        $rsia_penerima_undangan->delete();

        // Insert new penerima
        $penerima = $request->penerima ? $request->penerima : [];
        foreach ($penerima as $key => $value) {
            $rsia_penerima_undangan           = new \App\Models\RsiaPenerimaUndangan;
            $rsia_penerima_undangan->no_surat = $request->old_nomor;
            $rsia_penerima_undangan->penerima = $value;
            $rsia_penerima_undangan->ref      = \App\Models\RsiaSuratInternal::class;

            $nm_pegawai = \App\Models\Pegawai::where('nik', $value)->first();
            $nm         = $nm_pegawai ? $nm_pegawai->nama : '';

            $body = "👋 Halo $nm, anda mendapatkan undangan perihal: \n\n";
            $body .= "$request->perihal \n\n";
            $body .= "Tempat \t: " . $request->tempat . "\n";
            $body .= "Tanggal \t: " . \Carbon\Carbon::parse($request->tanggal)->isoFormat('dddd, D MMMM Y') . "\n";
            $body .= "Jam \t\t\t\t: " . \Carbon\Carbon::parse($request->tanggal)->isoFormat('HH:mm') . "\n";

            // if request->catatan && not empty or null or -
            if ($request->catatan && $request->catatan != '-' && $request->catatan != '') {
                $body .= "\n";
                $body .= "Catatan \t: " . $request->catatan . "\n";
            }

            \App\Http\Controllers\PushNotificationPegawai::sendTo(
                "Undangan baru untuk anda 📨",
                $body,
                [
                    'route'      => 'undangan',
                    'kategori'   => 'surat_internal',
                    'no_surat'   => $request->old_nomor,
                    'perihal'    => $request->perihal,
                    'tempat'     => $request->tempat,
                    'tanggal'    => $request->tanggal,
                    'tgl_terbit' => $request->tgl_terbit,
                ],
                $value,
            );

            $rsia_penerima_undangan->save();
        }

        return isSuccess($data, "Data berhasil diupdate");
    }

    public function update_status(Request $request)
    {
        if (!$request->nomor) {
            return isFail("No surat tidak boleh kosong");
        }

        if (!$request->status) {
            return isFail("Status tidak boleh kosong");
        }

        $rsia_surat_internal = \App\Models\RsiaSuratInternal::where('no_surat', $request->nomor);
        $data                = $rsia_surat_internal->update([
            'status' => $request->status,
        ]);

        return isSuccess($data, "Data berhasil diupdate");
    }

    public function destroy(Request $request)
    {
        if (!$request->no_surat) {
            return isFail("No surat tidak boleh kosong");
        }

        $rsia_surat_internal = \App\Models\RsiaSuratInternal::where('no_surat', $request->no_surat);
        $data                = $rsia_surat_internal->delete();

        return isSuccess($data, "Data berhasil dihapus");
    }

    // metrics
    public function metrics(Request $request)
    {
        // get count all data group by status
        $rsia_surat_internal = \App\Models\RsiaSuratInternal::select('status', \Illuminate\Support\Facades\DB::raw('count(*) as total'));
        $data                = $rsia_surat_internal->groupBy('status')->get();

        return isSuccess($data, "Data berhasil ditemukan");
    }

    // cetakUndangan
    public function cetakUndangan($nomor)
    {
        $nomor = str_replace('--', '/', $nomor);
        $penerima = \App\Models\RsiaPenerimaUndangan::where('no_surat', $nomor)->with(['pegawai' => function ($q) {
            $q->select('nik', 'nama', 'jbtn', 'bidang');
        }])->get();

        if ($penerima->count() == 0) {
            return isFail("Data penerima tidak ditemukan");
        }

        // penerima order by pegawai nama ascending
        $penerima = $penerima->sortBy('pegawai.nama', SORT_NATURAL | SORT_FLAG_CASE);
        
        // reset key $penerima
        $penerima = $penerima->values();

        $surat = \App\Models\RsiaSuratInternal::where('no_surat', $nomor)->with(['pegawai_detail' => function ($q) {
            $q->with('jenjang_jabatan')->select('nik', 'nama', 'bidang', 'jbtn', 'jnj_jabatan');
        }])->first();

        if (!$surat) {
            return isFail("Data surat tidak ditemukan");
        }

        $html = view('print.undangan_internal', [
            'nomor' => $nomor,
            'penerima' => $penerima,
            'undangan' => $surat,
        ]);

        // PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setWarnings(false)->setOptions([
            'isPhpEnabled'            => true,
            'isRemoteEnabled'         => true,
            'isHtml5ParserEnabled'    => true,
            'dpi'                     => 300,
            'defaultFont'             => 'sans-serif',
            'isFontSubsettingEnabled' => true,
            'isJavascriptEnabled'     => true,
        ]);

        $pdf->setOption('margin-top', 0);
        $pdf->setOption('margin-right', 0);
        $pdf->setOption('margin-bottom', 0);
        $pdf->setOption('margin-left', 0);

        return $pdf->stream('undangan_internal.pdf');
    }

    private function colSuratInternal($model, $request)
    {
        $col = ['no_surat', 'penerima', 'pj', 'status', 'month(tgl_terbit)', 'year(tgl_terbit)', 'date(tgl_terbit)'];

        $new_model = $model->where(function ($q) use ($col, $request) {
            foreach ($col as $key => $value) {
                if ($request->has($value)) {
                    if ($value == 'month(tgl_terbit)' || $value == 'year(tgl_terbit)' || $value == 'date(tgl_terbit)') {
                        $q->whereRaw($value . ' = ?', [$request->input($value)]);
                    } else {
                        $q->where($value, $request->input($value));
                    }
                }
            }
        });

        return $new_model;
    }

    private function selSuratInternal($modal, $request)
    {
        if ($request->select) {
            $select = explode(',', $request->select);
            $modal  = $modal->select($select);
        } else {
            $modal = $modal->select('*');
        }

        return $modal;
    }
}
