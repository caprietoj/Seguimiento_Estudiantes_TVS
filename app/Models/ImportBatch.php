<?php

namespace App\Models;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportTemplateType;
use Database\Factories\ImportBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'file_name',
        'file_hash',
        'template_type',
        'school_year_id',
        'status',
        'rows_read',
        'rows_imported',
        'rows_failed',
        'error_report',
        'user_id',
    ];

    protected $casts = [
        'template_type' => ImportTemplateType::class,
        'status' => ImportBatchStatus::class,
        'error_report' => 'array',
    ];

    /** @return BelongsTo<SchoolYear, $this> */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
