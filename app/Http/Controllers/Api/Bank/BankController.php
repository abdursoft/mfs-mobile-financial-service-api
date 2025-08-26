<?php

namespace App\Http\Controllers\Api\Bank;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankDistrict;
use App\Models\DistrictBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BankController extends Controller
{
    public function bank()
    {
        $path = public_path('bank.json');
        $bank = file_get_contents($path);
        $banks = json_decode($bank, true);
        return response()->json($banks);
        
        try {
            DB::beginTransaction();
            foreach ($banks as $b) {
                $ba = Bank::create([
                    'bank_name' => $b['name'],
                    'bank_slug' => $b['slug'],
                    'bank_code' => $b['bank_code'],
                ]);

                $ds = $b['districts'];
                foreach ($ds as $d) {
                    $sl = Str::slug(strtoupper($d['district_name']), '_');

                    $da = BankDistrict::create([
                        'district_name' => $d['district_name'],
                        'district_slug' => $sl,
                        'bank_id'       => $ba->id
                    ]);

                    foreach ($d['branches'] as $bb) {
                        DistrictBranch::create([
                            'branch_name' => $bb['branch_name'],
                            'branch_slug' => $bb['branch_slug'],
                            'branch_code' => $bb['branch_code'] ?? '',
                            'routing_number' => $bb['routing_number'] ?? '',
                            'swift_code' => $bb['swift_code'] ?? '',
                            'address' => $bb['address'] ?? '',
                            'telephone' => $bb['telephone'] ?? '',
                            'fax' => $bb['fax'] ?? '',
                            'bank_id' => $ba->id,
                            'bank_district_id' => $da->id
                        ]);
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            dd($th->getMessage());
        }
    }
}
