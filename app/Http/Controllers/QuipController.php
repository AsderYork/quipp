<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuipController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function auth(Request $request)
    {
        $name = $request->input('name');

        $reconnect_users = DB::table('users')
            ->where('name', $name)
            ->where(DB::raw('DATEDIFF(created_at, NOW()) < 1'))
            ->count();

        $key = null;

        $result = [];

        if($reconnect_users == 0) {
            $result['key'] = base64_encode($name . time());
            $result['id'] = DB::table('users')->insertGetId(['name' => $name, 'key' => $result['key']]);
            $result['new'] = true;
        } else {
            $user = DB::table('users')->where('name', $name)->first();
            $result['key'] = $user->key;
            $result['id'] = $user->id;
            $result['new'] = false;
        }

        echo json_encode($result);

    }

    private function generate_random_name($len = 4) {

        $alphabet = str_split('ABCDEIFGHIJKLMNOPQRSTUWVXYQ234567890');

        return implode('', array_intersect_key($alphabet, array_flip(array_rand($alphabet, $len))));

    }

    public function new_game(Request $request) {


        echo $this->generate_random_name();
        exit;

        $key = $request->input('key');

        $user = DB::table('users')
            ->where('key', $key)
            ->first();

        $result = [];

        if($user == null) {
            $result = ['err' => 'unauthrized'];
            return json_encode($result);
        }







    }



}
