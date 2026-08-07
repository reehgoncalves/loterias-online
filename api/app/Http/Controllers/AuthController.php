<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request) {
        $data = $request->validate(['email'=>'required|email','password'=>'required|string','portal'=>'required|in:cliente,admin']);
        $user = User::query()->where('email', strtolower($data['email']))->where('portal', $data['portal'])->where('active', true)->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) return response()->json(['message'=>'Credenciais inválidas.'], 422);
        $token = $user->createToken('loterias-online-'.$data['portal'])->plainTextToken;
        return response()->json(['data'=>['access_token'=>$token,'profile'=>$this->profile($user)]]);
    }
    public function register(Request $request) {
        $data = $request->validate(['name'=>'required|string|min:2|max:120','email'=>'required|email|max:160|unique:users,email','password'=>'required|string|min:8|confirmed']);
        $user = User::create(['name'=>$data['name'],'email'=>strtolower($data['email']),'password'=>$data['password'],'portal'=>'cliente','is_admin'=>false,'active'=>true,'marketing_opt_in'=>false]);
        $token = $user->createToken('loterias-online-cliente')->plainTextToken;
        return response()->json(['data'=>['access_token'=>$token,'profile'=>$this->profile($user)]], 201);
    }
    public function me(Request $request) { return response()->json(['data'=>$this->profile($request->user())]); }
    public function logout(Request $request) { $request->user()->currentAccessToken()?->delete(); return response()->json(['message'=>'Sessão encerrada.']); }
    private function profile(User $user): array { return ['id'=>$user->id,'name'=>$user->name,'email'=>$user->email,'portal'=>$user->portal,'is_admin'=>$user->is_admin]; }
}
