<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class NidController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'front' => 'required|image|max:10240',
            'back' => 'required|image|max:10240',
            'client_barcode' => 'nullable|string'
        ]);

        // save files (public disk or s3)
        $front = $request->file('front');
        $back = $request->file('back');

        $frontName = 'nid/front_' . Str::random(12) . '.' . $front->getClientOriginalExtension();
        $backName = 'nid/back_' . Str::random(12) . '.' . $back->getClientOriginalExtension();

        // store on 'public' disk; change to 's3' if you want S3
        $frontPath = $front->storeAs('uploads', $frontName, 'public');
        $backPath  = $back->storeAs('uploads', $backName, 'public');

        $response = [
            'success' => true,
            'front_url' => Storage::disk('public')->url($frontPath),
            'back_url'  => Storage::disk('public')->url($backPath),
            'barcode_data' => null,
        ];

        // If client provided barcode, return it
        if ($request->filled('client_barcode')) {
            $response['barcode_data'] = ['raw' => $request->input('client_barcode'), 'source' => 'client'];
            return response()->json($response);
        }

        // Server-side scan fallback (Google Vision recommended)
        try {
            $barcodeData = $this->scanBarcodeWithGoogleVision(storage_path('app/public/' . $backPath));
            if ($barcodeData) {
                $response['barcode_data'] = ['raw' => $barcodeData, 'source' => 'google_vision'];
            } else {
                // optionally: try other server libs or return null
                $response['barcode_data'] = null;
            }
        } catch (\Exception $e) {
            // handle exceptions gracefully
            $response['barcode_scan_error'] = $e->getMessage();
        }

        return response()->json($response);
    }

    protected function scanBarcodeWithGoogleVision($localFilePath)
    {
        // Using Google Cloud Vision: barcode detection returns annotation with rawValue
        // Make sure GOOGLE_APPLICATION_CREDENTIALS is set and google/cloud-vision installed
        $vision = new ImageAnnotatorClient();
        $image = file_get_contents($localFilePath);
        $response = $vision->barcodeDetection($image);
        $annotations = $response->getBarcodeAnnotations();

        if ($annotations && count($annotations) > 0) {
            // return first raw value
            return $annotations[0]->getRawValue();
        }

        return null;
    }
}
