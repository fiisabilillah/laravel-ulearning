<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Create User
     * @param Request $request
     * @return User
     */
    public function createUser(Request $request)
    {
        try {
            // Validasi request
            $validateUser = Validator::make($request->all(), [
                'avatar' => 'required',
                'type' => 'required',
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'open_id' => 'required|string|unique:users,open_id',
                'password' => 'required|string|min:6'
            ]);

            if ($validateUser->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation error',
                    'errors' => $validateUser->errors()
                ], 401);
            }

            // Ambil data yang sudah divalidasi
            $validated = $validateUser->validated();

            // Cek apakah user sudah ada berdasarkan type & open_id
            $user = User::where([
                'type' => $validated['type'],
                'open_id' => $validated['open_id']
            ])->first();

            if (!$user) {
                // Buat user baru
                $validated['token'] = md5(uniqid() . rand(10000, 99999));
                $validated['created_at'] = Carbon::now();
                $validated['password'] = Hash::make($validated['password']); // FIX: Enkripsi password dengan benar

                $userID = User::insertGetId($validated);
                $userInfo = User::find($userID);
                $accessToken = $userInfo->createToken(uniqid())->plainTextToken;
                $userInfo->access_token = $accessToken;

                // Simpan token ke database
                $userInfo->update(['access_token' => $accessToken]);

                return response()->json([
                    'status' => true,
                    'message' => 'User Created Successfully',
                    'data' => $userInfo
                ], 200);
            }

            // Jika user sudah ada, update access token dan login ulang
            $accessToken = $user->createToken(uniqid())->plainTextToken;
            $user->update(['access_token' => $accessToken]);

            return response()->json([
                'status' => true,
                'message' => 'User logged in Successfully',
                'data' => $user
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }


    /**
     * Login The User
     * @param Request $request
     * @return User
     */
    public function loginUser(Request $request)
    {
        try {
            $validateUser = Validator::make(
                $request->all(),
                [
                    'email' => 'required|email',
                    'password' => 'required'
                ]
            );

            if ($validateUser->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ], 401);
            }

            if (!Auth::attempt($request->only(['email', 'password']))) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email & Password does not match with our record.',
                ], 401);
            }

            $user = User::where('email', $request->email)->first();

            return response()->json([
                'status' => true,
                'message' => 'User Logged In Successfully',
                'token' => $user->createToken("API TOKEN")->plainTextToken
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
