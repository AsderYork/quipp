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


        $existing_player = DB::table('games_users')
            ->where('user', $userid)
            ->where('game', $gameid)
            ->where('deleted_at', null)
            ->value('id');

        if($existing_player == null) {
            $gameid = DB::table('games_users')->insert(
                [
                    'game' => $gameid,
                    'user' => $userid
                ]);
        }
    }

    private function setup_quipp_round(&$game, $players) {

        $game->round++;
        $round = $game->round;
        $round++;

        $questions_ids = db::table('questions')->pluck('id')->toArray();

        $question = $questions_ids[array_rand($questions_ids)];

        $insert = [];
        foreach ($players as $player) {
            $insert[] = [
                'user' => $player['userid'],
                'game' => $game->id,
                'round' => $round,
                'question' => $question,
            ];
        }
        db::table('answers')->insert($insert);

        db::table('games')->where('id', $game->id)->update(['round' => $round]);





    }

    private function get_players_question($gameid, $round, $userid) {

        return db::table('answers')
            ->leftJoin('questions', 'questions.id', '=', 'answers.question')
            ->where('user', $userid)
            ->where('game', $gameid)
            ->where('round', $round)
            ->select(['questions.question', 'answers.answer'])
            ->first();

    }

    private function set_player_answer($gameid, $round, $userid, $answer) {

        db::table('answers')
            ->where('user', $userid)
            ->where('game', $gameid)
            ->where('round', $round)
            ->update(['answer' => $answer]);

    }



    private function set_player_ready($gameid, $userid, $status) {

        db::table('games_users')
            ->where('game', $gameid)
            ->where('user', $userid)
            ->where('deleted_at', null)
            ->update(['ready_status' => $status ? 'ready' : 'not ready']);

        $not_readies = db::table('games_users')
            ->where('game',  (int)$gameid)
            ->where('deleted_at', null)
            ->where('ready_status','=', 'not ready')
            ->count();

        if($not_readies == 0) {
            db::table('games')
                ->where('id', $gameid)
                ->where('deleted_at', null)
                ->update(['started' => db::raw('NOW()')]);
            return true;
        }

        return false;


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

    public function join(Request $request) {

        $key = $request->input('key');

        $user = DB::table('users')
            ->where('key', $key)
            ->first();

        if($user == null) {
            $result = ['err' => 'unauthrized'];
            return json_encode($result);
        }

        $game = db::table('games')
            ->where('name', $request->input('game'))
            ->where('deleted_at', null)
            ->first();

        if($game == null) {
            $result = ['err' => 'no such game'];
            return json_encode($result);
        }


        $this->add_new_player($game->id, $user->id);

        return json_encode(['name' => $game->name, 'id' => $game->id]);

    }


    public function get_game_players($game) {

        $game_data = db::table('games_users')
            ->leftJoin('users', 'users.id', '=', 'games_users.user')
            ->leftJoin('games', 'games.id', '=', 'games_users.game')
            ->leftJoin('answers', function ($join) {
                $join->on('answers.game', '=', 'games_users.game')
                    ->on('answers.user', '=', 'users.id')
                    ->on('answers.round', '=', 'games.round');
            })
            ->where('games_users.game', $game)
            ->where('games_users.deleted_at', null)
            ->select([
                'games_users.ready_status',
                'games_users.user',
                'users.name',
                'answers.answer'
            ])
            ->get();

        $result = [];
        foreach ($game_data as $game_datum) {
            $result[] = [
                'ready' => $game_datum->ready_status,
                'userid' => $game_datum->user,
                'username' => $game_datum->name,
                'answer_ready' => $game_datum->answer != null,
            ];

        }

        return $result;
    }


    public function lobby_status(Request $request) {

        $authorization = $this->authorise_user($request);


        if(empty($authorization['user'])) {
            return ['err' => 'unauthorized'];
        } elseif(!array_key_exists('in_game', $authorization) || $authorization['in_game'] == 0) {
            return ['err' => 'not part of the game'];
        }

        $gameid = $request->input('game');

        $gamedata = $this->get_game_players($gameid);

        $game = db::table('games')
            ->where('id', $gameid)
            ->where('deleted_at', null)
            ->first();

        $result = ['players' => $gamedata, 'game' => $game];

        if($game->started != null) {
            $result['question'] = $this->get_players_question($gameid, $game->round, $authorization['user']->id);
        }


        return json_encode($result);

    }

    private function get_game($gameid) {

        return db::table('games')
            ->where('id', $gameid)
            ->first();

    }

    public function ready(Request $request) {

        $authorization = $this->authorise_user($request);

        if(empty($authorization['user'])) {
            return ['err' => 'unauthorized'];
        } elseif($authorization['in_game'] == 0) {
            return ['err' => 'not part of the game'];
        }


        $gameid = $request->input('game');
        $userid = $authorization['user']->id;
        $status = (bool)$request->input('ready');

        if($this->get_game($gameid)->started != null) {
            return json_encode(['err' => 'game allready started']);
        }


        $started = $this->set_player_ready($gameid, $userid, $status);

        $gamedata = $this->get_game_players($gameid);

        $game = db::table('games')
            ->where('id', $gameid)
            ->where('deleted_at', null)
            ->first();

        if($started) {
            $this->setup_quipp_round($game, $gamedata);
        }

        return json_encode(['players' => $gamedata, 'game' => $game]);

    }

    public function answer(Request $request) {

        $authorization = $this->authorise_user($request);

        if(empty($authorization['user'])) {
            return ['err' => 'unauthorized'];
        } elseif($authorization['in_game'] == 0) {
            return ['err' => 'not part of the game'];
        }

        $gameid = $request->input('game');
        $userid = $authorization['user']->id;

        $game = $this->get_game($gameid);

        $this->set_player_answer($gameid, $game->round, $userid, $request->input('answer'));



    }




}
