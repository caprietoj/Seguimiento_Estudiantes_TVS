<?php

namespace App\Models;

use Database\Factories\GroupMeetingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMeeting extends Model
{
    /** @use HasFactory<GroupMeetingFactory> */
    use HasFactory;

    protected $fillable = [
        'group_id',
        'follow_up_id',
        'held_on',
    ];

    protected $casts = ['held_on' => 'date'];

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<FollowUp, $this> */
    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUp::class);
    }
}
