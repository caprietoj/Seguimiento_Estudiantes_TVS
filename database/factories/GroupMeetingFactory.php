<?php

namespace Database\Factories;

use App\Models\FollowUp;
use App\Models\Group;
use App\Models\GroupMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GroupMeeting> */
class GroupMeetingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'follow_up_id' => FollowUp::factory(),
            'held_on' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
        ];
    }
}
