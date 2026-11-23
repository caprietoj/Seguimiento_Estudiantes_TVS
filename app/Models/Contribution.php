<?php

namespace App\Models;

use App\Enums\ContributionField;
use App\Enums\SupportFlag;
use Database\Factories\ContributionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property bool $in_follow_up
 * @property bool $family_attended
 * @property bool $piar_meeting
 * @property bool $crisis_attention
 * @property string|null $agreements
 * @property string|null $strategy_follow_up
 */
class Contribution extends Model
{
    /** @use HasFactory<ContributionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'follow_up_id',
        'field',
        'body',
        'agreements',
        'strategy_follow_up',
        'in_follow_up',
        'family_attended',
        'piar_meeting',
        'crisis_attention',
        'author_id',
    ];

    protected $casts = [
        'field' => ContributionField::class,
        'in_follow_up' => 'boolean',
        'family_attended' => 'boolean',
        'piar_meeting' => 'boolean',
        'crisis_attention' => 'boolean',
    ];

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<FollowUp, $this> */
    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUp::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<ImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /**
     * Partes y marcas de una nota del Dpto. de Apoyo, para la reunión, la ficha y el PDF.
     * En los demás campos solo hay `body`.
     *
     * @return array{agreements: string|null, strategy_follow_up: string|null, flags: list<array{key: string, label: string}>}
     */
    public function supportDetails(): array
    {
        if (! $this->field->isClinical()) {
            return ['agreements' => null, 'strategy_follow_up' => null, 'flags' => []];
        }

        $flags = [];
        foreach (SupportFlag::cases() as $flag) {
            if ($this->{$flag->value}) {
                $flags[] = ['key' => $flag->value, 'label' => $flag->shortLabel()];
            }
        }

        return ['agreements' => $this->agreements, 'strategy_follow_up' => $this->strategy_follow_up, 'flags' => $flags];
    }

    /** Los aportes importados se muestran como "Administrador", no con el nombre de quien importó. */
    public function authorLabel(): string
    {
        return $this->import_batch_id !== null ? 'Administrador' : $this->author->name;
    }
}
