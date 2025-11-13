<?php

namespace App\Http\Controllers\Api\V1\Price;

use App\Http\Controllers\Controller;
use App\Models\Price;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PriceController extends Controller
{

    protected $merchant;

    /**
     * Initialize the merchant app
     */
    public function __construct()
    {
        $this->merchant = merchantApp(request());
    }

    /**
     * Display a listing of prices.
     */
    public function index()
    {
        $prices = Price::with('merchantApp')->get();
        return response()->json($prices);
    }

    /**
     * Store a newly created price.
     */
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(),[
            'amount' => 'required|numeric|min:0',
            'cycle' => 'required|in:once,daily,weekly,monthly,quarterly,yearly',
            'currency' => 'required|in:USD,BDT,INR,YEN,CAD,PKR,DNG,EUR,GBP,AUD,SAR,QAR'
        ]);

        // check validation
        if ($validated->fails()) {
            return response()->json([
                'code'    => "INVALID_DATA",
                'message' => 'Invalid price data, Please have a look our API docs.',
                'errors'  => $validated->errors(),
            ], 422);
        }
        $form = $validated->validated();
        $form['price_id'] = uniqueToken(Price::class,'price_id','price_');
        $form['merchant_app_id'] = $this->merchant->id;

        $price = Price::create($form);

        return response()->json([
            'code' => 'PRICE_CREATED',
            'message' => 'Price created successfully',
            'data' => $price
        ], 201);
    }

    /**
     * Display a specific price.
     */
    public function show($id=null)
    {
        if($id){
            $price = Price::where('merchant_app_id', $this->merchant->id)->where('price_id',$id)->first();
        }else{
            $price = Price::where('merchant_app_id', $this->merchant->id)->limit(100)->get();
        }

        if(!$price){
            return response()->json([
                'code' => 'PRICE_NOT_FOUND',
                'message' => 'Price ID or authentication is invalid!'
            ],422);
        }

        return response()->json([
            'code' => 'PRICE_RETRIEVED',
            'message' => 'Price successfully retrieved',
            'price' => $price
        ],200);
    }

    /**
     * Update a specific price.
     */
    public function update(Request $request, $id)
    {
        $price = Price::where('merchant_app_id', $this->merchant->id)->where('price_id',$id)->first();

        if (!$price) {
            return response()->json([
                'code' => 'PRICE_NOT_FOUND',
                'message' => 'Requested price ID not found!'
            ], 404);
        }

        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:0',
            'cycle' => 'sometimes|in:once,daily,weekly,monthly,quarterly,yearly',
            'currency' => 'sometimes|in:USD,BDT,INR,YEN,CAD,PKR,DNG,EUR,GBP,AUD,SAR,QAR',
        ]);

        $price->update($validated);

        return response()->json([
            'code' => 'PRICE_UPDATED',
            'message' => 'Price updated successfully',
            'data' => $price
        ]);
    }

    /**
     * Remove a specific price.
     */
    public function destroy($id)
    {
        $price = Price::where('merchant_app_id', $this->merchant->id)->where('price_id',$id)->first();

        if (!$price) {
            return response()->json([
                'code' => 'PRICE_NOT_FOUND',
                'message' => 'Requested price ID not found!'
            ], 404);
        }

        $price->delete();

        return response()->json([
            'code' => 'PRICE_DELETED',
            'message' => 'Price deleted successfully'
        ],200);
    }
}
