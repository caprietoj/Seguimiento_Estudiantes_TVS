<?php

namespace App\Http\Controllers;

use App\Enums\StrategyResult;
use App\Enums\StrategyType;
use App\Models\Strategy;
use App\Models\StrategyReview;
use App\Support\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StrategyReviewController extends Controller
{
    public function store(Request $request, Strategy $strategy): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'result' => ['required', Rule::enum(StrategyResult::class)],
        ]);

        $group = $strategy->type === StrategyType::Group
            ? $strategy->group
            : $strategy->student?->currentGroup();

        abort_if($group === null, 404);

        if (! Scope::inScope($request->user(), $group)) {
            abort(403);
        }

        StrategyReview::create([
            'strategy_id' => $strategy->id,
            'reviewed_on' => now()->toDateString(),
            'body' => $data['body'],
            'result' => $data['result'],
            'author_id' => $request->user()->id,
        ]);

        return back();
    }
}
