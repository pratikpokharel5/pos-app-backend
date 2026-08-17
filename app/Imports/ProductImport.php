<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
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
                'category_id' => $this->categoryId($row),
                'name' => $this->value($row, 'name'),
                'sku' => $this->value($row, 'sku'),
                'price' => $this->price($row),
                'description' => $this->value($row, 'description'),
                'status' => $status ? strtolower($status) : 'active',
            ];

            $validator = Validator::make($data, [
                'category_id' => [
                    'required',
                    Rule::exists('categories', 'id')
                        ->where('business_id', $this->businessId)
                        ->where('status', 'active'),
                ],
                'name' => ['required', 'string', 'max:255'],
                'sku' => ['nullable', 'string', 'max:255'],
                'price' => ['required', 'numeric', 'min:0'],
                'description' => ['nullable', 'string'],
                'status' => ['required', Rule::in(['active', 'inactive'])],
            ]);

            if ($validator->fails()) {
                $this->skip($rowNumber, $validator->errors()->first());

                continue;
            }

            $product = $data['sku']
                ? Product::query()->updateOrCreate(
                    [
                        'business_id' => $this->businessId,
                        'sku' => $data['sku'],
                    ],
                    [
                        'category_id' => $data['category_id'],
                        'name' => $data['name'],
                        'price' => $data['price'],
                        'description' => $data['description'],
                        'status' => $data['status'],
                    ],
                )
                : Product::query()->create([
                    ...$data,
                    'business_id' => $this->businessId,
                ]);

            $product->wasRecentlyCreated ? $this->created++ : $this->updated++;
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

    private function categoryId(Collection $row): ?int
    {
        $categoryId = $this->value($row, 'category_id');

        if ($categoryId !== null) {
            return (int) $categoryId;
        }

        $categoryName = $this->value($row, 'category') ?? $this->value($row, 'category_name');

        if (! $categoryName) {
            return null;
        }

        return Category::query()
            ->where('business_id', $this->businessId)
            ->where('status', 'active')
            ->where('name', $categoryName)
            ->value('id') ?? -1;
    }

    private function price(Collection $row): ?string
    {
        $price = $this->value($row, 'price');

        return $price === null ? null : str_replace(',', '', $price);
    }

    private function skip(int $row, string $message): void
    {
        $this->skipped++;
        $this->errors[] = [
            'row' => $row,
            'message' => $message,
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
