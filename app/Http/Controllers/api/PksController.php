<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PksController extends Controller
{
    public function index(Request $request)
    {
        $pks = \App\Models\RsiaPks::with('pj_detail')->orderBy('tgl_terbit', 'DESC')->where('status', '1');

        if ($request->jenis) {
            $jenis = '/' . $request->jenis . '/';
            $pks = $pks->where('no_pks_internal', 'like', '%' . $jenis . '%');
        }

        if ($request->keyword) {
            $keywords = $request->keyword ?? $request->keywords;
            $pks = $pks->where(function($query) use ($keywords) {
                $query->where('no_pks_eksternal', 'like', '%' . $keywords . '%')
                    ->orWhere('judul', 'like', '%' . $keywords . '%')
                    ->orWhereHas('pj_detail', function ($query) use ($keywords) {
                        $query->where('nama', 'like', '%' . $keywords . '%');
                    });
            });
        }

        if ($request->month) {
            // request month 2023-08
            [$year, $month] = explode('-', $request->month);
            $pks = $pks->whereYear('tanggal_akhir', $year)->whereMonth('tanggal_akhir', $month);
        }

        if ($request->perpage) {
            $pks = $pks->paginate(env('PER_PAGE', $request->perpage));
        } else {
            $pks = $pks->paginate(env('PER_PAGE', 10));
        }

        return isSuccess($pks, 'Data PKS ditemukan');
    }

    public function getLastNomor(Request $request)
    {
        if ($request->tanggal_awal) {
            $year = date('Y', strtotime($request->tanggal_awal));
        } else {
            $year = date('Y');
        }

        $q = \App\Models\RsiaPks::select('no_pks_internal')->whereYear('tgl_terbit', $year)->orderBy('no_pks_internal', 'DESC');
        $internal = (clone $q)->where('no_pks_internal', 'like', '%/A/%')->first();
        $eksternal = (clone $q)->where('no_pks_internal', 'like', '%/B/%')->first();

        $pks = [
            'internal' => $internal ? $internal->no_pks_internal : null,
            'eksternal' => $eksternal ? $eksternal->no_pks_internal : null,
        ];

        return isSuccess($pks, 'Data PKS ditemukan');
    }

    // create
    public function store(Request $request)
    {
        // rules for data
        $rules = [
            'no_pks_internal' => 'required',
            'judul' => 'required',
            'pj' => 'required',
            'tgl_terbit' => 'required',
            'tanggal_awal' => 'required',
            // 'status' => 'required',
            'file' => 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,image,jpeg,jpg,png|max:102400',
        ];

        // validate
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        // validator fails
        if ($validator->fails()) {
            return isFail($validator->errors(), 422);
        }

        
        $file = $request->file('file');

        if ($file) {
            $file_name = strtotime(now()) . '-' . str_replace([' ', '_'], '-', $file->getClientOriginalName());
            
            $st = new \Illuminate\Support\Facades\Storage();
            // if directory not exists create it
            if (!$st::disk('sftp')->exists(env('DOCUMENT_PKS_SAVE_LOCATION'))) {
                $st::disk('sftp')->makeDirectory(env('DOCUMENT_PKS_SAVE_LOCATION'));
            }
        }

        // final data
        $final_data = [
            'no_pks_internal' => $request->no_pks_internal,
            'no_pks_eksternal' => $request->no_pks_eksternal ?? "",
            'judul' => $request->judul,
            'tgl_terbit' => $request->tgl_terbit,
            'tanggal_awal' => $request->tanggal_awal,
            'tanggal_akhir' => $request->tanggal_akhir ?? "0000-00-00",
            'berkas' => $file_name ?? "",
            'pj' => $request->pj,
        ];

        $pks = \App\Models\RsiaPks::create($final_data);
        // if pks saved
        if ($pks) {
            // if file exists
            if ($file) {
                $st::disk('sftp')->put(env('DOCUMENT_PKS_SAVE_LOCATION') . $file_name, file_get_contents($file));
            }
        }

        return isSuccess($pks, 'Data PKS berhasil ditambahkan');
    }

    public function update(Request $request, $id)
    {
        $pks = \App\Models\RsiaPks::find($id);
        
        if (!$pks) {
            return isFail('Data PKS tidak ditemukan', 404);
        }

        $rules = [
            'no_pks_internal' => 'required',
            'judul' => 'required',
            'pj' => 'required',
            'tgl_terbit' => 'required',
            'tanggal_awal' => 'required',
            'file' => 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,image,jpeg,jpg,png|max:102400',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return isFail($validator->errors(), 422);
        }

        // if file existss
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $file_name = strtotime(now()) . '-' . str_replace([' ', '_'], '-', $file->getClientOriginalName());
            
            // move 
            $st = new \Illuminate\Support\Facades\Storage();
            // if directory not exists create it
            if (!$st::disk('sftp')->exists(env('DOCUMENT_PKS_SAVE_LOCATION'))) {
                $st::disk('sftp')->makeDirectory(env('DOCUMENT_PKS_SAVE_LOCATION'));
            }
            // move file
            $st::disk('sftp')->put(env('DOCUMENT_PKS_SAVE_LOCATION') . $file_name, file_get_contents($file));
            // final data
            $final_data = [
                'no_pks_internal' => $request->no_pks_internal,
                'no_pks_eksternal' => $request->no_pks_eksternal ?? "",
                'judul' => $request->judul,
                'tgl_terbit' => $request->tgl_terbit,
                'tanggal_awal' => $request->tanggal_awal,
                'tanggal_akhir' => $request->tanggal_akhir ?? "0000-00-00",
                'berkas' => $file_name,
                'pj' => $request->pj,
            ];
        } else {
            $final_data = [
                'no_pks_internal' => $request->no_pks_internal,
                'no_pks_eksternal' => $request->no_pks_eksternal ?? "",
                'judul' => $request->judul,
                'tgl_terbit' => $request->tgl_terbit,
                'tanggal_awal' => $request->tanggal_awal,
                'tanggal_akhir' => $request->tanggal_akhir ?? "0000-00-00",
                'pj' => $request->pj,
            ];
        }
        
        // old berkas
        $old_berkas = $pks->berkas;

        //  try update date using database transaction
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            // get old berkas and if new data has berkas and finished update delete old berkas
            $pks->update($final_data);
            \Illuminate\Support\Facades\DB::commit();
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\DB::rollback();
            return isFail($th->getMessage(), 500);
        }
        
        // delete old berkas
        if ($request->hasFile('file')) {
            $st = new \Illuminate\Support\Facades\Storage();
            if ($old_berkas && $st::disk('sftp')->exists(env('DOCUMENT_PKS_SAVE_LOCATION') . $old_berkas)) {
                $st::disk('sftp')->delete(env('DOCUMENT_PKS_SAVE_LOCATION') . $old_berkas);
            }
        }

        return isSuccess($pks, 'Data PKS berhasil diupdate');
    }

    public function delete($id)
    {
        if (!$id) {
            return isFail('Data PKS tidak ditemukan', 404);
        }

        $pks = \App\Models\RsiaPks::find($id);

        if (!$pks) {
            return isFail('Data PKS tidak ditemukan', 404);
        }

        $pks->update(['status' => '0']);
        return isSuccess($pks, 'Data PKS berhasil dihapus');
    }

    public function destroy($id)
    {
        $pks = \App\Models\RsiaPks::find($id);

        if (!$pks) {
            return isFail('Data PKS tidak ditemukan', 404);
        }

        $st = new \Illuminate\Support\Facades\Storage();

        if ($pks->berkas && $st::disk('sftp')->exists(env('DOCUMENT_PKS_SAVE_LOCATION') . $pks->berkas)) {
            $st::disk('sftp')->delete(env('DOCUMENT_PKS_SAVE_LOCATION') . $pks->berkas);
        }

        $pks->delete();
        return isSuccess($pks, 'Data PKS berhasil dihapus');
    }
}
