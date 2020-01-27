<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function foo\func;

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

    public function index($name = null) {

        $data = ['name' => $name];

        return view('quipp', ['name' => 'James']);

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

    private function add_new_player($gameid, $userid, $type = 'player') {


        $existing_player = DB::table('games_users')
            ->where('user', $userid)
            ->where('game', $gameid)
            ->where('deleted_at', null)
            ->value('id');

        if($existing_player == null) {
            $gameid = DB::table('games_users')->insert(
                [
                    'game' => $gameid,
                    'user' => $userid,
                    'type' => $type,
                ]);
        }
    }

    private function setup_quipp_round(&$game, $players) {

        $players_count = count($players);
        $subrounds_count = (($players_count * $players_count) - $players_count) / 2;


        $game->subround++;
        if($game->subround < $subrounds_count && $game->status === 'showing_results') {
            db::table('games')->where('id', $game->id)->update(['round' => $game->round, 'status' => 'voting', 'subround' => $game->subround]);
            return;
        }
        $game->subround = 0;

        $game->round++;


        $questions_ids = db::table('questions')->pluck('id')->toArray();


        $subrounds = [];
        for($i = 0; $i < count($players); $i++) {
            for($j = $i + 1; $j < count($players); $j++) {
                $subrounds[] = [$players[$i]['userid'], $players[$j]['userid']];
            }
        }

        $rand_array = array_rand($questions_ids, count($subrounds));
        if(!is_array($rand_array)) {
            $rand_array = [$rand_array];
        }

        $questions = array_map(function ($x) use($questions_ids) {return $questions_ids[$x];},
            $rand_array
        );


        $inserts = [];
        for($i = 0; $i < count($subrounds); $i++) {
            foreach ([0,1] as $index) {
                $inserts[] = [
                    'user' => $subrounds[$i][$index],
                    'game' => $game->id,
                    'round' => $game->round,
                    'subround' => $i,
                    'question' => $questions[$i],
                ];
            }
        }

        db::table('answers')->insert($inserts);

        db::table('games')->where('id', $game->id)->update(['round' => $game->round, 'status' => 'answers', 'subround' => 0]);



        echo 'succ'; exit;


    }

    private function get_players_question($game, $userid) {

        if($game->status == 'answers') {
            return db::table('answers')
                ->leftJoin('questions', 'questions.id', '=', 'answers.question')
                ->where('user', $userid)
                ->where('game', $game->id)
                ->where('round', $game->round)
                ->where('answer', null)
                ->orderBy('subround', 'asc')
                ->select(['questions.question', 'answers.answer'])
                ->first();
        } else {

            return db::table('answers')
                ->leftJoin('questions', 'questions.id', '=', 'answers.question')
                ->where('game', $game->id)
                ->where('round', $game->round)
                ->where('subround', $game->subround)
                ->select(['questions.question'])
                ->first();



        }


    }

    private function start_voting($gameid) {

        db::table('games')
            ->where(['id' => $gameid])
            ->update(['status' => 'voting']);

    }

    private function set_player_answer($gameid, $round, $subround, $userid, $answer) {

        $unanswered_players_questions = db::table('answers')
            ->where('user', $userid)
            ->where('game', $gameid)
            ->where('round', $round)
            ->where('answer', null)
            ->orderBy('subround', 'asc')
            ->pluck('id')->toArray();

        if(count($unanswered_players_questions) > 0) {
            db::table('answers')
                ->where('id', $unanswered_players_questions[0])
                ->update(['answer' => $answer]);
        }

        $no_answers = db::table('answers')
            ->where('game', $gameid)
            ->where('round', $round)
            ->where('answer', null)
            ->count();

        if ($no_answers === 0) {
            $this->start_voting($gameid);
        }



    }



    private function set_player_ready($gameid, $userid, $status) {

        db::table('games_users')
            ->where('game', $gameid)
            ->where('user', $userid)
            ->where('type', 'player')
            ->where('deleted_at', null)
            ->update(['ready_status' => $status ? 'ready' : 'not ready']);

        $not_readies = db::table('games_users')
            ->where('game',  (int)$gameid)
            ->where('type', 'player')
            ->where('deleted_at', null)
            ->where('ready_status','=', 'not ready')
            ->count();

        if($not_readies == 0) {
            db::table('games')
                ->where('id', $gameid)
                ->where('deleted_at', null)
                ->update(['started' => db::raw('NOW()'), 'status' => 'answers']);
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

    /**
     * Returns all the answers for a question in a current round
     * @param $game object A game object
     * @return array
     */
    private function get_answers($game) {

        return db::table('answers')
            ->where('game', $game->id)
            ->where('round', $game->round)
            ->where('subround', $game->subround)
            ->pluck('answer', 'id')->toArray();

    }

    private function get_results($game) {

        $data = db::table('answers')
            ->leftJoin('votes', 'votes.answer', 'answers.id')
            ->leftJoin('users AS answers_authors', 'answers_authors.id', '=', 'answers.user')
            ->leftJoin('users AS votes_authors', 'votes_authors.id', '=', 'votes.user')
            ->where('answers.game', $game->id)
            ->select([
                'answers.id',
                'answers.answer',
                'answers.round',
                'answers.subround',
                'answers_authors.name AS player_name',
                'answers_authors.id AS player_id',
                'votes.type',
                'votes.value',
                'votes_authors.id AS voter_id',
                'votes_authors.name AS voter_name'

            ])
            ->get();

        $results = ['full' => [], 'current' => []];
        foreach ($data as $datum) {
            $type = $datum->type == null ? 'default' : $datum->type;

            if(!array_key_exists($datum->player_id, $results['full'])) {
                $results['full'][$datum->player_id] = ['name' => $datum->player_name, 'id' => $datum->player_id, 'results' =>[$type => ['sum' => 0]]];
            }

            if(!array_key_exists($type, $results['full'][$datum->player_id]['results'])) {
                $results['full'][$datum->player_id]['results'][$type] = ['sum' => 0];
            }
            $results['full'][$datum->player_id]['results'][$type]['sum'] += $datum->value;

            if(($datum->round === $game->round) && ($datum->subround == $game->subround)) {

                if(!array_key_exists($datum->id, $results['current'])) {
                    $results['current'][$datum->id] = ['id' => $datum->id, 'player_id' => $datum->player_id, 'player_name' => $datum->player_name, 'answer' => $datum->answer, 'results' => []];
                }

                if(!array_key_exists($type, $results['current'][$datum->id]['results'])) {
                    $results['current'][$datum->id]['results'][$type] = ['sum' => 0, 'voters' => []];
                }

                $results['current'][$datum->id]['results'][$type]['sum'] += $datum->value;
                if($datum->voter_id != null) {
                    $results['current'][$datum->id]['results'][$type]['voters'][] = ['id' => $datum->voter_id, 'name' => $datum->voter_name, 'value' => $datum->value];
                }

            }


        }

        uasort($results['full'], function ($a, $b) use($type) {return $b['results'][$type]['sum'] - $a['results'][$type]['sum'];});
        $results['full'] = array_values($results['full']);

        uasort($results['current'], function ($a, $b) use($type) {return $b['results'][$type]['sum'] - $a['results'][$type]['sum'];});
        $results['current'] = array_values($results['current']);

        return $results;

    }


    private function add_votes($game, $userid, $answers, $value = 1, $type = null) {

        db::table('votes')
            ->where('game', $game->id)
            ->where('user', $userid)
            ->where('round', $game->round)
            ->where('subround', $game->subround)
            ->where('type', $type)
            ->delete();


        if(!is_array($answers)) {
            $answers = [$answers];
        }

        $insert = [];
        foreach ($answers as $answer) {
            $insert[] = [
                'game' => $game->id,
                'user' => $userid,
                'round' => $game->round,
                'subround' => $game->subround,
                'answer' => $answer,
                'type' => $type,
                'value' => $value
            ];
        }



        db::table('votes')
            ->insert($insert);

        $missing_votes = db::table('games_users')
            ->leftJoin('votes', function($join) use ($game) {
                $join->on('votes.user', '=', 'games_users.user')
                    ->on('votes.game', '=', 'games_users.game')
                    ->where('votes.round', $game->round)
                    ->where('votes.subround', $game->subround);
                })
            ->where('games_users.type', 'player')
            ->where('games_users.game', $game->id)
            ->where('votes.id', null)
            ->count();

        if($missing_votes === 0) {
            db::table('games')
                ->where('id', $game->id)
                ->update(['status' => 'showing_results', 'updated_at' => db::raw('NOW()')]);
        }



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
        $type = $request->input('type');

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

        if($game->started != null) {
            return json_encode(['err' => 'game allready started']);
        }


        $this->add_new_player($game->id, $user->id, $type);

        return json_encode(['name' => $game->name, 'id' => $game->id]);

    }


    public function get_game_players($game) {

        $game_data = db::table('games_users')
            ->leftJoin('users', 'users.id', '=', 'games_users.user')
            ->leftJoin('games', 'games.id', '=', 'games_users.game')
            ->leftJoin('answers', function ($join) {
                $join->on('answers.game', '=', 'games_users.game')
                    ->on('answers.user', '=', 'users.id')
                    ->on('answers.round', '=', 'games.round')
                    ->on('answers.subround', '=', 'games.subround');
            })
            ->where('games_users.game', $game)
            ->where('games_users.type', 'player')
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


        $game = $this->get_game($gameid);


        $result = ['players' => $gamedata, 'game' => $game];


        if($game->started != null) {
            $result['question'] = $this->get_players_question($game, $authorization['user']->id);

        }

        switch ($game->status) {
            case 'voting':
                $result['voting'] = $this->get_answers($game);
                break;
            case 'showing_results':
                $result['results'] = $this->get_results($game);


                $show_results_time = date_create('now')->diff(date_create($game->updated_at))->s;
                $result['results_time'] = $show_results_time;

                /*if($show_results_time > 10) {
                    $this->setup_quipp_round($game, $gamedata);
                }*/


                break;
        }

        return json_encode($result);

    }

    private function get_game($gameid) {

        return db::table('games')
            ->where('id', $gameid)
            ->where('deleted_at', null)
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

        if($game->status != 'answers') {
            return json_encode(['err' => 'wrong gamestate']);
        }

        $this->set_player_answer($gameid, $game->round, $game->subround, $userid, $request->input('answer'));

    }

    public function vote(Request $request) {

        $authorization = $this->authorise_user($request);

        if(empty($authorization['user'])) {
            return ['err' => 'unauthorized'];
        } elseif($authorization['in_game'] == 0) {
            return ['err' => 'not part of the game'];
        }

        $gameid = $request->input('game');
        $userid = $authorization['user']->id;

        $game = $this->get_game($gameid);

        if($game->status != 'voting') {
            return json_encode(['err' => 'wrong gamestate']);
        }

        $vote = $request->input('vote');
        if($vote === null) {
            return json_encode(['err' => 'no vote has been sent']);
        }

        $this->add_votes($game, $userid, $request->input('vote'));

        return json_encode(['ok' => 'ok']);

    }




}
