<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Validator;


class UserController extends Controller
{
    public function registration (Request $request) {
        $data = $request->all();

        $validate = Validator::make($data, [
            'email'=>'required|email|unique:users,email',
            'password'=>'required|min:3|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/',
            'first_name'=>'required',
            'last_name'=>'required',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'success'=>false,
                'message'=>$validate->errors()
            ], 422);
        }

        User::create($data);

        return response()->json([
            'success'=>true,
            'message'=>'Успешная регистрация'
        ], 200);
    }

    public function authorization (Request $request) {
        $data = $request->all();

        $validate = Validator::make($data, [
            'email'=>'required',
            'password'=>'required',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'success'=>false,
                'message'=>$validate->errors()
            ], 422);
        }


        $check = User::where('email', '=', $data['email'])
                ->where('password', '=', $data['password'])->first();

        if ($check) {
            return response()->json([
                'success'=>true,
                'message'=>'Успешная авторизация',
                'token'=>$check->genToken()
            ], 200);
        }

        return response()->json([
            'success'=>false,
            'message'=>'Не верная почта или пароль'
        ], 401);
    }

    public function logout (Request $request) {
        $token = $request->bearerToken();
        $check = User::where('api_token', '=', $token)->first();
        $check->out();

        return response()->json([
            'success'=>true,
            'message'=>'Успешный сброс авторизации'
        ], 200);
    }
}
