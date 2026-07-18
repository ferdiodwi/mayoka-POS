<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QzTrayController extends Controller
{
    /**
     * Endpoint for QZ Tray to fetch the public digital certificate
     */
    public function getCertificate()
    {
        $certPath = storage_path('app/qz-tray/digital-certificate.txt');
        
        if (!file_exists($certPath)) {
            return response('Certificate not found', 404);
        }

        return response()->file($certPath, [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * Endpoint for QZ Tray to sign requests using the private key
     */
    public function signRequest(Request $request)
    {
        $message = $request->input('request');

        if (!$message) {
            return response('No request provided', 400);
        }

        $privateKeyPath = storage_path('app/qz-tray/private-key.pem');

        if (!file_exists($privateKeyPath)) {
            return response('Private key not found', 404);
        }

        $privateKey = file_get_contents($privateKeyPath);
        
        // Compute SHA-512 signature
        $signature = '';
        openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA512);

        // Base64 encode the signature
        $base64Signature = base64_encode($signature);

        return response($base64Signature, 200, [
            'Content-Type' => 'text/plain'
        ]);
    }
}
