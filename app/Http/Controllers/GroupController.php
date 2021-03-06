<?php

namespace App\Http\Controllers;

use App\Group;
use Auth;
use Carbon\Carbon;
use File;
use Gate;
use Illuminate\Http\Request;
use Image;
use Storage;

class GroupController extends Controller
{
    public function __construct()
    {
        $this->middleware('verified', ['only' => ['create', 'store', 'edit', 'update', 'destroy']]);
        $this->middleware('groupadmin', ['only' => ['edit', 'update', 'destroy']]);
    }

    /**
    * Display the specified resource.
    *
    * @param int $id
    *
    * @return Response
    */
    public function show(Group $group)
    {
        $discussions = false;
        $actions = false;
        $files = false;
        $activities = false;

        // User is logged
        if (Auth::check())
        {
            if (Gate::allows('viewDiscussions', $group)) {
                $discussions = $group->discussions()
                ->has('user')
                ->with('user', 'group', 'userReadDiscussion')
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get();
            }

            if (Gate::allows('viewFiles', $group)) {
                $files = $group->files()->with('user')->orderBy('updated_at', 'desc')->limit(5)->get();
            }

            if (Gate::allows('viewActions', $group)) {
                $actions = $group->actions()->where('start', '>=', Carbon::now())->orderBy('start', 'asc')->limit(10)->get();
            }

            if (Gate::allows('viewActivities', $group)) {
                $activities = $group->activities()->limit(10)->get();
            }
        }
        else // anonymous user
        {
            if ($group->isPublic())
            {
                $discussions = $group->discussions()
                ->has('user')
                ->with('user', 'group')
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get();

                $files = $group->files()->with('user')->orderBy('updated_at', 'desc')->limit(5)->get();
                $actions = $group->actions()->where('start', '>=', Carbon::now())->orderBy('start', 'asc')->limit(10)->get();
            }
        }

        return view('groups.show')
        ->with('group', $group)
        ->with('discussions', $discussions)
        ->with('actions', $actions)
        ->with('files', $files)
        ->with('activities', $activities)
        ->with('admins', $group->admins()->get())
        ->with('tab', 'home');
    }

    /**
    * Show the form for creating a new resource.
    *
    * @return Response
    */
    public function create()
    {
        Gate::authorize('create', \App\Group::class);

        return view('groups.create')
        ->with('all_tags', \App\Group::allTags());
    }

    /**
    * Store a newly created resource in storage.
    *
    * @return Response
    */
    public function store(Request $request)
    {
        Gate::authorize('create', \App\Group::class);

        $group = new group();

        $group->name = $request->input('name');
        $group->body = $request->input('body');
        $group->group_type = $request->input('group_type');

        if ($request->get('address')) {
            $group->address = $request->input('address');
            if (!$group->geocode()) {
                flash(trans('messages.address_cannot_be_geocoded'))->warning();
            } else {
                flash(trans('messages.ressource_geocoded_successfully'))->success();
            }
        }

        if ($group->isInvalid()) {
            // Oops.
            return redirect()->route('groups.create')
            ->withErrors($group->getErrors())
            ->withInput();
        }
        $group->save();

        $group->user()->associate(Auth::user());

        if ($request->get('tags'))
        {
            $group->tag($request->get('tags'));
        }

        // handle cover
        if ($request->hasFile('cover')) {
            Storage::disk('local')->makeDirectory('groups/'.$group->id);
            Image::make($request->file('cover'))->widen(800)->save(storage_path().'/app/groups/'.$group->id.'/cover.jpg');
            Image::make($request->file('cover'))->fit(300, 200)->save(storage_path().'/app/groups/'.$group->id.'/thumbnail.jpg');
        }

        // make the current user an admin of the group
        $membership = \App\Membership::firstOrNew(['user_id' => Auth::user()->id, 'group_id' => $group->id]);
        $membership->notification_interval = 60 * 24; // default to daily interval
        $membership->membership = \App\Membership::ADMIN;
        $membership->save();

        // notify admins (if they want it)
        if (\App\Setting::get('notify_admins_on_group_create'))
        {
            foreach (\App\User::admins()->get() as $admin)
            {
                $admin->notify(new \App\Notifications\GroupCreated($group));
            }
        }

        flash(trans('messages.ressource_created_successfully'))->success();

        return redirect()->action('MembershipController@update', [$group->id]);
    }

    /**
    * Show the form for editing the specified resource.
    *
    * @param int $id
    *
    * @return Response
    */
    public function edit(Request $request, Group $group)
    {
        return view('groups.edit')
        ->with('group', $group)
        ->with('all_tags', \App\Group::allTags())
        ->with('model_tags', $group->tags)
        ->with('tab', 'admin');
    }

    /**
    * Update the specified resource in storage.
    *
    * @param int $id
    *
    * @return Response
    */
    public function update(Request $request, Group $group)
    {
        $group->name = $request->input('name');
        $group->body = $request->input('body');

        if (Gate::allows('changeGroupType', $group)) {
            $group->group_type = $request->input('group_type');
        }

        if ($group->address != $request->input('address')) {
            // we need to update user address and geocode it
            $group->address = $request->input('address');
            if (!$group->geocode()) {
                flash(trans('messages.address_cannot_be_geocoded'))->warning();
            } else {
                flash(trans('messages.ressource_geocoded_successfully'))->success();
            }
        }

        $group->user()->associate(Auth::user());

        if ($request->get('tags'))
        {
            $group->retag($request->get('tags'));
        }

        // validation
        if ($group->isInvalid()) {
            // Oops.
            return redirect()->route('groups.edit', $group->id)
            ->withErrors($group->getErrors())
            ->withInput();
        }

        // handle cover
        if ($request->hasFile('cover')) {
            Storage::disk('local')->makeDirectory('groups/'.$group->id);
            Image::make($request->file('cover'))->widen(800)->save(storage_path().'/app/groups/'.$group->id.'/cover.jpg');
            Image::make($request->file('cover'))->fit(300, 200)->save(storage_path().'/app/groups/'.$group->id.'/thumbnail.jpg');
        }

        $group->save();

        flash(trans('messages.ressource_updated_successfully'))->success();

        return redirect()->route('groups.show', [$group->id]);
    }

    public function destroyConfirm(Request $request, Group $group)
    {
        if (Gate::allows('delete', $group)) {
            return view('groups.delete')
            ->with('group', $group)
            ->with('tab', 'home');
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
    public function destroy(Request $request, Group $group)
    {
        if (Gate::allows('delete', $group)) {
            $group->delete();
            flash(trans('messages.ressource_deleted_successfully'))->success();

            return redirect()->action('DashboardController@index');
        } else {
            abort(403);
        }
    }

    /**
    * Show the revision history of the group.
    */
    public function history(Group $group)
    {
        return view('groups.history')
        ->with('group', $group)
        ->with('tab', 'home');
    }
}
