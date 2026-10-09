<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\Group;
use App\Models\GroupMeeting;
use App\Support\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupMeetingController extends Controller
{
    public function update(Request $request, Group $group, FollowUp $followUp): RedirectResponse
    {
        if (! Scope::canManageGroupStrategy($request->user(), $group)) {
            abort(403);
        }

        $data = $request->validate([
            'held_on' => ['required', 'date'],
        ]);

        GroupMeeting::updateOrCreate(
            ['group_id' => $group->id, 'follow_up_id' => $followUp->id],
            ['held_on' => $data['held_on']]
        );

        return back();
    }
}
