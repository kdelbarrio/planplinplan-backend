<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();

        // solo admins deberían ver esta vista; si no lo son, abortamos
        //abort_unless($me->is_admin, 403);

        // Usamos last_login_at si existe; si no, fallback a updated_at
        $lastLogin = $me->last_login_at ?? $me->updated_at;

        // listado usuarios no-admin (is_admin = 0)
        //$usersToReview = User::where('is_admin', 0)->orderBy('created_at', 'asc')->get();
        
        $usersToReview = $me->is_admin ? User::where('is_admin', 0)->orderBy('created_at')->get() : collect();
        // últimas 10 importaciones (etl_runs)
        //$etlRuns = DB::table('etl_runs')
        $etlRuns = $me->is_admin ? DB::table('etl_runs')
            ->select('finished_at', 'source', 'inserted', 'updated', 'errors')
            ->orderBy('finished_at', 'desc')
            ->limit(10)
            ->get(): collect();

        return view('dashboard', [
            'me' => $me,
            'lastLogin' => $lastLogin ? Carbon::parse($lastLogin) : null,
            'usersToReview' => $usersToReview,
            'etlRuns' => $etlRuns,
        ]);
    }

    public function promote(Request $request, User $user)
    {
        $me = $request->user();
        abort_unless($me->is_admin, 403);

        $user->is_admin = 1;
        $user->save();

        return back()->with('success', 'Usuario promovido a administrador.');
    }
}