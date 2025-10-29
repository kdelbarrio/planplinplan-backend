<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApiDocsController extends Controller
{
    public function show(Request $request)
    {
        // Descubre la base URL de la API en función del host actual
        $baseApiUrl = url('/api');

        // Última actualización (si quieres, cámbialo a una constante o config)
        $lastUpdated = now('Europe/Madrid');

        return view('api-docs', compact('baseApiUrl', 'lastUpdated'));
    }
}
