<?php
use \Model\Quests;
use \Model\Missions;
use \Model\Task;
use \Model\Rewards;

class Controller_Quests extends Controller {
  public function __construct() { Hacker::check(Uri::base()); }

	public function action_index() {
        if (Task::get_one(Hacker::id(), 1)) Response::redirect(Uri::create('quests/interface'));

        $tVars = array();
        $groups = Quests::groups();
        $tVars['groups'] = $groups;
        return Response::forge(View::forge('quests/quests', $tVars))->set_header('Access-Control-Allow-Origin', '*');
    }

    public function action_group($group) {
        if (Task::get_one(Hacker::id(), 1)) Response::redirect(Uri::create('quests/interface'));
        $tVars = array();

        $quests = Quests::of($group);
        foreach($quests as &$q) {
          $q['available'] = $q['type'] == 1 && !$q['times'] || $q['type'] == 3 || $q['type'] == 2 && $q['last_done'] <= time() - 24 * 60 * 60;
          if (!$q['available'] && $q['type'] == 2) {
            $q['available_in'] = $q['last_done'] - time() + 24 * 60 * 60;
          }
        }
        $tVars['quests'] = $quests;
        return Response::forge(View::forge('quests/quests_group', $tVars))->set_header('Access-Control-Allow-Origin', '*');
    }


    public function action_play($mission) {
        if (Task::get_one(Hacker::id(), 1)) Response::redirect(Uri::create('quests/interface'));
        $data = array('mission' => Missions::prepare_data($mission));
        Task::create(Hacker::id(), 1, $data['mission']['duration'] * 60, $data, $mission);
        Response::redirect(Uri::create('quests/interface'));
    }

    public function action_interface() {

        $task = Task::get_one(Hacker::id(), 1);
        if (!$task) Response::redirect(Uri::create('quests'));

        if (isset($task['data']['mission']['completed'])) {
            Quests::record_completion($task['user_id'], $task['data_id'], $task['task_duration'] - $task['remaining']);

            $task['complete'] = time();
            $task['complete_status'] = 1;
            $quest = DB::select()->from('quest')->where('quest_id', $task['data_id'])->limit(1)->execute()->as_array()[0];

            $reward = array(
              'experience' => $quest['experience'],
              'money' => $quest['money'],
              'skill_points' => $quest['skill_points']
            );
            Rewards::give(Hacker::id(), $reward, 'Mission');

            Messages::modal('Mission complete', '<p class="text-center">You have succesfully finished the mission!</p>');
            Task::save($task);
            Response::redirect(Uri::create('quests'));
        }
        return Missions::do_interface($task);
    }
}
