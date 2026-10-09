<?php

namespace App\Models;

use App\Enums\StrategyResult;
use Database\Factories\StrategyReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyReview extends Model
{
    /** @use HasFactory<StrategyReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'strategy_id',
        'reviewed_on',
        'body',
        'result',
        'author_id',
    ];

    protected $casts = [
        'reviewed_on' => 'date',
        'result' => StrategyResult::class,
    ];

    /** @return BelongsTo<Strategy, $this> */
    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
