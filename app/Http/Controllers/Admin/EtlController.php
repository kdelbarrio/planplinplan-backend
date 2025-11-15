<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Throwable;

class EtlController extends Controller
{
    public function index(): View
    {
        $etls = config('etl.jobs', []);
        return view('admin.etl.index', compact('etls'));
    }

    public function run(Request $request): JsonResponse
    {
        $request->validate([
            'etl' => 'required|string',
        ]);

        $key  = $request->string('etl')->toString();
        $etls = config('etl.jobs', []);

        if (! isset($etls[$key])) {
            return response()->json([
                'output' => '(error)',
                'error'  => 'ETL no registrada: '.$key,
            ], 422);
        }

        $entry  = $etls[$key];
        $cmd    = $entry['command'] ?? null;
        $params = $entry['params']  ?? [];

        if (! $cmd) {
            return response()->json([
                'output' => '(error)',
                'error'  => 'Comando no definido para la ETL: '.$key,
            ], 500);
        }

        try {
            // Ejecuta y captura salida
            //Artisan::call($cmd, $params);
            //$output = trim(Artisan::output());
            $buffer = new \Symfony\Component\Console\Output\BufferedOutput();
            Artisan::call($cmd, $params, $buffer);
            $output = trim($buffer->fetch());


            return response()->json([
                'output' => $output === '' ? '(sin salida)' : $output,
            ]);

        } catch (Throwable $e) {
            return response()->json([
                'output' => '(error)',
                'error'  => $e->getMessage(),
            ], 500);
        }
    }
}
