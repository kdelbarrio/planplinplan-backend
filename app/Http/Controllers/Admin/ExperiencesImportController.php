<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class ExperiencesImportController extends Controller
{
    public function run()
    {
        // Lanza el comando ETL
        Artisan::call('etl:import-experiences');
        $etlOutput = trim(Artisan::output());

        return response()->json([
            'status' => 'ok',
            'message' => 'Importación de Experiencias ejecutada',
            'output' => $etlOutput,
        ]);
    }
}
