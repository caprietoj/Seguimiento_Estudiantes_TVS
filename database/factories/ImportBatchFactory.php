<?php

namespace Database\Factories;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportTemplateType;
use App\Models\ImportBatch;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ImportBatch> */
class ImportBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'file_name' => 'Seguimiento 10A-2.xlsx',
            'file_hash' => fake()->sha256(),
            'template_type' => ImportTemplateType::CurrentFormat->value,
            'school_year_id' => SchoolYear::factory(),
            'status' => ImportBatchStatus::Imported->value,
            'rows_read' => 1,
            'rows_imported' => 1,
            'rows_failed' => 0,
            'error_report' => null,
            'user_id' => User::factory(),
        ];
    }
}
