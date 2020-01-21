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

    public function index() {

        $data = [];
        return view('quipp', $data);

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


    /**
     * Creates new game
     * @param $author int ID of the author
     * @return array pair [name, id] of the game
     */
    private function create_new_game($author) {
        $gamename = $this->generate_random_name();

        $gameid = DB::table('games')->insertGetId(
            [
                'name' => $gamename,
                'creator' => $author
            ]);

        return ['name' => $gamename, 'id' => $gameid];

    }

    private function add_new_player($gameid, $userid) {
        $gameid = DB::table('games_users')->insert(
            [
                'game' => $gameid,
                'user' => $userid
            ]);
    }

    private function set_player_ready($gameid, $userid, $status) {

        db::table('games_users')
            ->where('game', $gameid)
            ->where('user', $userid)
            ->update(['ready_status' => $status ? 'ready' : 'not ready']);

    }


    /**
     * Makes shure that this user exsts, and is realy a part of the game it claims to be
     * @param Request $request Request of the method
     * @param null $gameid optional ID of the game to check
     * @return array An array with user data, and if $gameid whas provided, in_game flag
     */
    private function authorise_user(Request $request) {

        $key = $request->input('key');
        $gameid = $request->input('game');

        $user = DB::table('users')
            ->where('key', $key)
            ->first();

        if($user && $gameid) {

            $in_game = db::table('games_users')
                ->where('user', $user->id)
                ->where('game', $gameid)
                ->count();

            return ['user' => $user, 'in_game' => $in_game];

        }

        return ['user' => $user];


    }


    public function new_game(Request $request) {

        $key = $request->input('key');

        $user = DB::table('users')
            ->where('key', $key)
            ->first();

        if($user == null) {
            $result = ['err' => 'unauthrized'];
            return json_encode($result);
        }

        $game = $this->create_new_game($user->id);

        $this->add_new_player($game['id'], $user->id);

        return json_encode($game);

    }

    public function get_game_players($game) {

        //@TODO Доделать
        $game_data = db::table('games_users')
            ->where('game', $game)
            ->where('games_users.deleated_at', null)
            ->get();

        $result = [];




    }


    public function lobby_status(Request $request) {

        $authorization = $this->authorise_user($request);

        if(empty($authorization['user'])) {
            return ['err' => 'unauthorized'];
        } elseif($authorization['in_game'] == 0) {
            return ['err' => 'not part of the game'];
        }







    }




}
