<?php

namespace App\Http\Controllers;

use App\Discussion;
use App\Events\MessageSent;
use App\Events\UserJoinDiscussion;
use App\Group;
use Auth;
use Carbon\Carbon;
use Gate;
use Illuminate\Http\Request;

class GroupDiscussionController extends Controller
{
    public function __construct()
    {
        $this->middleware('member', ['only' => ['edit', 'update', 'destroy']]);
        $this->middleware('verified', ['only' => ['create', 'store', 'edit', 'update', 'destroy']]);
        $this->middleware('public', ['only' => ['index', 'show', 'history']]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request, Group $group)
    {
        // we don't authorize at this stage since we might not have a group
        $tags = $group->tagsUsed();

        return view('discussions.create')
            ->with('group', $group)
            ->with('all_tags', $tags)
            ->with('tab', 'discussion');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Group $group, Discussion $discussion)
    {
        $this->authorize('view', $discussion);
        $discussion->delete();
        flash(trans('messages.ressource_deleted_successfully'));

        return redirect()->route('groups.discussions.index', [$group]);
    }

    public function destroyConfirm(Request $request, Group $group, Discussion $discussion)
    {
        $this->authorize('delete', $discussion);

        if (Gate::allows('delete', $discussion)) {
            return view('discussions.delete')
                ->with('group', $group)
                ->with('discussion', $discussion)
                ->with('tab', 'discussion');
        } else {
            abort(403);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return Response
     */
    public function edit(Request $request, Group $group, Discussion $discussion)
    {
        $this->authorize('update', $discussion);

        $tags = $group->tagsUsed();

        return view('discussions.edit')
            ->with('discussion', $discussion)
            ->with('group', $group)
            ->with('all_tags', $tags)
            ->with('model_tags', $discussion->tags)
            ->with('tab', 'discussion');
    }

    /**
     * Show the revision history of the discussion.
     */
    public function history(Group $group, Discussion $discussion)
    {
        $this->authorize('history', $discussion);

        return view('discussions.history')
            ->with('group', $group)
            ->with('discussion', $discussion)
            ->with('tab', 'discussion');
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index(Request $request, Group $group)
    {
        $this->authorize('view-discussions', $group);

        $tags = $group->tagsInDiscussions();

        $tag = $request->get('tag');

        if (\Auth::check()) {
            $discussions =
                $group->discussions()
                    ->has('user')
                    ->with('userReadDiscussion', 'user')
                    ->withCount('comments')
                    ->orderBy('updated_at', 'desc')
                    ->when($tag, function ($query) use ($tag) {
                        return $query->withAnyTags($tag);
                    })
                    ->paginate(50);
        } else { // don't load the unread relation, since we don't know who to look for.
            $discussions = $group->discussions()->has('user')->with('user')->withCount('comments')->orderBy('updated_at', 'desc')->paginate(50);
        }

        return view('discussions.index')
            ->with('title', $group->name . ' - ' . trans('messages.discussions'))
            ->with('discussions', $discussions)
            ->with('tags', $tags)
            ->with('group', $group)
            ->with('tab', 'discussion');
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show(Group $group, Discussion $discussion)
    {
        $this->authorize('view', $discussion);

        broadcast(new MessageSent(auth()->user(), "TEST"))->toOthers();

        // if user is logged in, we update the read count for this discussion.
        // just before that, we save the number of already read comments in $read_comments to be used in the view to scroll to the first unread comments
        if (Auth::check()) {

            event(new UserJoinDiscussion(auth()->user()));

            $UserReadDiscussion = \App\UserReadDiscussion::firstOrNew(['discussion_id' => $discussion->id, 'user_id' => Auth::user()->id]);

            $read_comments = $UserReadDiscussion->read_comments;
            $UserReadDiscussion->read_comments = $discussion->total_comments;
            $UserReadDiscussion->read_at = Carbon::now();
            $UserReadDiscussion->save();
        } else {
            $read_comments = 0;
        }

        return view('discussions.show')
            ->with('title', $group->name . ' - ' . $discussion->name)
            ->with('discussion', $discussion)
            ->with('read_comments', $read_comments)
            ->with('group', $group)
            ->with('tab', 'discussion');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(Request $request, Group $group)
    {

        // if no group is in the route, it means user chose the group using the dropdown
        if (!$group->exists) {
            $group = \App\Group::find($request->get('group'));
            //if group is null, redirect to the discussion create page with error messages, saying
            //that you must select a group
            if (is_null($group)) {
                return redirect()->route('discussions.create')->withErrors(['You must select a Group']);
            }
        }

        $this->authorize('create-discussion', $group);

        $discussion = new Discussion();
        $discussion->name = $request->input('name');
        $discussion->body = $request->input('body');
        $discussion->total_comments = 1; // the discussion itself is already a comment
        $discussion->user()->associate(Auth::user());

        if (!$group->discussions()->save($discussion)) {
            // Oops.
            return redirect()->route('groups.discussions.create', $group)
                ->withErrors($discussion->getErrors())
                ->withInput();
        }

        // update activity timestamp on parent items
        $group->touch();
        \Auth::user()->touch();

        if ($request->get('tags')) {
            $discussion->tag($request->get('tags'));
        }

        flash(trans('messages.ressource_created_successfully'));

        return redirect()->route('groups.discussions.show', [$group, $discussion]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     *
     * @return Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(Request $request, Group $group, Discussion $discussion)
    {
        $this->authorize('update', $discussion);

        $discussion->name = $request->input('name');
        $discussion->body = $request->input('body');
        //$discussion->user()->associate(Auth::user()); // we use revisionable to manage who changed what, so we keep the original author
        $discussion->save();

        if ($request->get('tags')) {
            $discussion->retag($request->get('tags'));
        } else {
            $discussion->detag();
        }

        flash(trans('messages.ressource_updated_successfully'));

        return redirect()->route('groups.discussions.show', [$discussion->group, $discussion]);
    }
}
