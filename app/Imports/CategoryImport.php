<?php

namespace App\Imports;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CategoryImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    private int $created = 0;

    private int $updated = 0;

    private int $skipped = 0;

    /**
     * @var array<int, array{row: int, message: string}>
     */
    private array $errors = [];

    public function __construct(private readonly int $businessId) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $status = $this->value($row, 'status');
            $data = [
                'name' => $this->value($row, 'name'),
                'description' => $this->value($row, 'description'),
                'status' => $status ? strtolower($status) : 'active',
            ];

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'status' => ['required', Rule::in(['active', 'inactive'])],
            ]);

            if ($validator->fails()) {
                $this->skipped++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => $validator->errors()->first(),
                ];

                continue;
            }

            $category = Category::query()->updateOrCreate(
                [
                    'business_id' => $this->businessId,
                    'name' => $data['name'],
                ],
                [
                    'description' => $data['description'],
                    'status' => $data['status'],
                ],
            );

            $category->wasRecentlyCreated ? $this->created++ : $this->updated++;
        }
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: array<int, array{row: int, message: string}>}
     */
    public function summary(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
        ];
    }

    private function value(Collection $row, string $key): ?string
    {
        $value = $row->get($key);

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
