<?php

namespace App\Http\Controllers;

use App\Action;
use App\Group;
use Auth;
use Carbon\Carbon;
use Gate;
use Illuminate\Http\Request;

class GroupActionController extends Controller
{
  public function __construct()
  {
    $this->middleware('member', ['only' => ['edit', 'update', 'destroy']]);
    $this->middleware('cache', ['only' => ['index', 'show']]);
    $this->middleware('verified', ['only' => ['create', 'store', 'edit', 'update', 'destroy']]);
    $this->middleware('public', ['only' => ['index', 'indexJson', 'show']]);
  }

  /**
  * Display a listing of the resource.
  *
  * @return Response
  */
  public function index(Request $request, Group $group)
  {
    $this->authorize('view-actions', $group);

    $view = 'grid';

    if (Auth::check()) {
      if (Auth::user()->getPreference('calendar')) {
        if (Auth::user()->getPreference('calendar') == 'list') {
          $view = 'list';
        } else {
          $view = 'grid';
        }
      }

      if ($request->get('type') == 'list') {
        Auth::user()->setPreference('calendar', 'list');
        $view = 'list';
      }

      if ($request->get('type') == 'grid') {
        Auth::user()->setPreference('calendar', 'grid');
        $view = 'grid';
      }
    } else {
      if ($request->get('type') == 'list') {
        $view = 'list';
      }

      if ($request->get('type') == 'grid') {
        $view = 'grid';
      }
    }

    if ($view == 'list') {
      $actions = $group->actions()
      ->orderBy('start', 'asc')
      ->where('stop', '>=', Carbon::now()->subDays(1))
      ->paginate(10);

      return view('actions.index-list')
      ->with('title', $group->name.' - '.trans('messages.agenda'))
      ->with('actions', $actions)
      ->with('group', $group)
      ->with('tab', 'action');
    }

    return view('actions.index')
    ->with('group', $group)
    ->with('tab', 'action');
  }

  public function indexJson(Request $request, Group $group)
  {
    $this->authorize('view-actions', $group);

    // load of actions between start and stop provided by calendar js
    if ($request->has('start') && $request->has('end')) {
      $actions = $group->actions()
      ->where('start', '>', Carbon::parse($request->get('start')))
      ->where('stop', '<', Carbon::parse($request->get('end')))
      ->orderBy('start', 'asc')->get();
    } else {
      $actions = $group->actions()->orderBy('start', 'asc')->get();
    }

    $event = [];
    $events = [];

    foreach ($actions as $action) {
      $event['id'] = $action->id;
      $event['title'] = $action->name.' ('.$group->name.')';
      $event['description'] = $action->body.' <br/> '.$action->location;
      $event['body'] = $action->body;
      $event['summary'] = summary($action->body);
      $event['location'] = $action->location;
      $event['start'] = $action->start->toIso8601String();
      $event['end'] = $action->stop->toIso8601String();
      $event['url'] = route('groups.actions.show', [$group->id, $action->id]);
      $event['color'] = $group->color();

      $events[] = $event;
    }

    return $events;
  }

  /**
  * Show the form for creating a new resource.
  *
  * @return Response
  */
  public function create(Request $request, Group $group)
  {
    $this->authorize('create-action', $group);

    $action = new Action();

    if ($request->get('start')) {
      $action->start = Carbon::createFromFormat('Y-m-d H:i', $request->get('start'));
    } else {
      $action->start = Carbon::now();
    }

    if ($request->get('stop')) {
      $action->stop = Carbon::createFromFormat('Y-m-d H:i', $request->get('stop'));
    }

    if ($request->get('title')) {
      $action->name = $request->get('title');
    }


    return view('actions.create')
    ->with('action', $action)
    ->with('group', $group)
    ->with('all_tags', $group->tagsUsed())
    ->with('tab', 'action');
  }

  /**
  * Store a newly created resource in storage.
  *
  * @return Response
  */
  public function store(Request $request, Group $group)
  {
    // if no group is in the route, it means user choose the group using the dropdown
    if (!$group->exists) {
      $group = \App\Group::findOrFail($request->get('group'));
    }

    $this->authorize('createa-ction', $group);

    $action = new Action();

    $action->name = $request->input('name');
    $action->body = $request->input('body');

    try {
      $action->start = Carbon::createFromFormat('Y-m-d H:i', $request->input('start_date').' '.$request->input('start_time'));
    } catch (\Exception $e) {
      return redirect()->route('groups.actions.create', $group)
      ->withErrors($e->getMessage().'. Incorrect format in the start date, use yyyy-mm-dd hh:mm')
      ->withInput();
    }

    if ($request->get('stop_time')) {
      $stop_time = $request->get('stop_time');
    } else {
      $stop_time = $request->input('start_time');
    }

    try {
      if ($request->get('stop_date')) {
        if ($request->get('stop_time')) {
          $action->stop = Carbon::createFromFormat('Y-m-d H:i', $request->input('stop_date').' '.$request->input('stop_time'));
        } else { // asssume action will have a one hour duration
          $action->stop = Carbon::createFromFormat('Y-m-d H:i', $request->input('stop_date').' '.$request->input('start_time'))->addHour();
        }
      } else { // assume it will be same day
        if ($request->get('stop_time')) {
          $action->stop = Carbon::createFromFormat('Y-m-d H:i', $request->input('start_date').' '.$request->input('stop_time'));
        } else { // asssume action will have a one hour duration
          $action->stop = Carbon::createFromFormat('Y-m-d H:i', $request->input('start_date').' '.$request->input('start_time'))->addHour();
        }
      }
    } catch (\Exception $e) {
      return redirect()->route('groups.actions.create', $group)
      ->withErrors($e->getMessage().'. Incorrect format in the stop date, use yyyy-mm-dd hh:mm')
      ->withInput();
    }

    if ($request->get('location')) {
      $action->location = $request->input('location');
      if (!$action->geocode()) {
        flash(trans('messages.address_cannot_be_geocoded'));
      } else {
        flash(trans('messages.ressource_geocoded_successfully'));
      }
    }

    // handle tags
    if ($request->get('tags')) {
      $action->tag($request->get('tags'));
    }

    $action->user()->associate($request->user());

    $action->group()->associate($group);

    // update activity timestamp on parent items
    $group->touch();
    \Auth::user()->touch();

    if ($action->isInvalid()) {
      // Oops.
      return redirect()->route('groups.actions.create', $group)
      ->withErrors($action->getErrors())
      ->withInput();
    } else {
      $action->save();
      flash(trans('messages.ressource_created_successfully'));

      return redirect()->route('groups.actions.index', $group);
    }
  }

  /**
  * Display the specified resource.
  *
  * @param int $id
  *
  * @return Response
  */
  public function show(Group $group, Action $action)
  {
    $this->authorize('view', $action);

    return view('actions.show')
    ->with('title', $group->name.' - '.$action->name)
    ->with('action', $action)
    ->with('group', $group)
    ->with('tab', 'action');
  }

  /**
  * Show the form for editing the specified resource.
  *
  * @param int $id
  *
  * @return Response
  */
  public function edit(Request $request, Group $group, Action $action)
  {
    $this->authorize('update', $action);

    return view('actions.edit')
    ->with('action', $action)
    ->with('group', $group)
    ->with('all_tags', $group->tagsUsed())
    ->with('model_tags', $action->tags)
    ->with('tab', 'action');
  }

  /**
  * Update the specified resource in storage.
  *
  * @param int $id
  *
  * @return Response
  */
  public function update(Request $request, Group $group, Action $action)
  {
    $this->authorize('update', $action);

    $action->name = $request->input('name');
    $action->body = $request->input('body');

    $action->start = Carbon::createFromFormat('Y-m-d H:i', $request->input('start_date').' '.$request->input('start_time'));

    if ($request->has('stop_date') && $request->get('stop_date') != '') {
      $action->stop = Carbon::createFromFormat('Y-m-d H:i', $request->input('stop_date').' '.$request->input('stop_time'));
    } else {
      $action->stop = Carbon::createFromFormat('Y-m-d H:i', $request->input('start_date').' '.$request->input('stop_time'));
    }

    if ($action->location != $request->input('location')) {
      // we need to update user address and geocode it
      $action->location = $request->input('location');
      if (!$action->geocode()) {
        flash(trans('messages.address_cannot_be_geocoded'));
      } else {
        flash(trans('messages.ressource_geocoded_successfully'));
      }
    }

    // handle tags
    if ($request->get('tags')) {
      $action->tag($request->get('tags'));
    }
    else {
      $action->detag();
    }


    if ($action->isInvalid()) {
      // Oops.
      return redirect()->route('groups.actions.create', $group_id)
      ->withErrors($action->getErrors())
      ->withInput();
    }

    $action->save();

    return redirect()->route('groups.actions.show', [$action->group, $action]);
  }

  /**
  * Remove the specified resource from storage.
  *
  * @param int $id
  *
  * @return \Illuminate\Http\Response
  */
  public function destroyConfirm(Request $request, Group $group, Action $action)
  {
    $this->authorize('delete', $action);

    if (Gate::allows('delete', $action)) {
      return view('actions.delete')
      ->with('action', $action)
      ->with('group', $group)
      ->with('tab', 'discussion');
    } else {
      abort(403);
    }
  }

  /**
  * Remove the specified resource from storage.
  *
  * @param int $id
  *
  * @return \Illuminate\Http\Response
  */
  public function destroy(Request $request, Group $group, Action $action)
  {
    $this->authorize('delete', $action);
    $action->delete();
    flash(trans('messages.ressource_deleted_successfully'));

    return redirect()->route('groups.actions.index', [$group]);
  }

  /**
  * Show the revision history of the discussion.
  */
  public function history(Group $group, Action $action)
  {
    $this->authorize('history', $action);

    return view('actions.history')
    ->with('group', $group)
    ->with('action', $action)
    ->with('tab', 'action');
  }
}
