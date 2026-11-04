<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function index()
    {
        $empresas = DB::table('cor_companias')
            ->orderBy('codigo_compania')
            ->pluck('nombre_corto', 'codigo_compania');

        return view('auth.login', compact('empresas'));
    }

    public function store(LoginRequest $request)
    {
        $user = User::whereRaw('lower(email) = ?', [strtolower($request->email)])
            ->where('empresa', $request->empresa)
            ->where('flag_activo', 1)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return back()
                ->withErrors(['email' => 'Credenciales incorrectas o sin acceso a la compañía.'])
                ->onlyInput('email', 'empresa');
        }

        Auth::login($user);
        $request->session()->regenerate();

        $request->session()->put('empresa_nombre', DB::table('cor_companias')
            ->where('codigo_compania', $user->empresa)
            ->value('nombre_corto'));

        return redirect()->route('home');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
