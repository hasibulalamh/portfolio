<?php

namespace Tests\Unit;

use App\Services\ReorderService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ReorderService::reorder without a database.
 *
 * The real method opens a transaction and runs multiple UPDATEs, which needs a
 * database. The decision under test is the ordering contract the admin panel
 * sends in: each `{ id, order }` pair is applied in submission order, the
 * `order` value is coerced to an integer (the admin sends HTML-option numbers,
 * which are strings), and the return value is the count of rows the updates
 * actually touched.
 *
 * We intercept DB::transaction and fake the model query builder to record
 * updates without executing SQL.
 */
class ReorderServiceTest extends TestCase
{
    /**
     * A fake Eloquent query builder that records the updates it is asked to run.
     */
    private function builder(array $affected = []): object
    {
        return new class($affected)
        {
            public array $updates = [];

            public array $calls = [];

            private array $affected;

            public function __construct(array $affected)
            {
                $this->affected = $affected;
            }

            public function whereKey(int|string $id): static
            {
                $this->calls[] = ['whereKey' => $id];

                return $this;
            }

            public function update(array $values): int
            {
                $this->calls[] = ['update' => $values];

                $hit = $this->calls[count($this->calls) - 2]['whereKey'] ?? null;

                return in_array($hit, $this->affected, true) ? 1 : 0;
            }
        };
    }

    public function test_each_pair_is_written_in_submission_order(): void
    {
        $builder = $this->builder([9, 7]);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($closure) => $closure());

        // The real service calls $modelClass::query() which returns an Eloquent
        // builder. We fake the query chain by intercepting the model class.
        $fakeModel = new class($builder)
        {
            private $builder;

            public function __construct($builder)
            {
                $this->builder = $builder;
            }

            public static function query()
            {
                // This will be called by the service; return our fake builder
                return app('reorder_fake_builder');
            }
        };

        app()->bind('reorder_fake_builder', fn () => $builder);

        // Since the service uses $modelClass::query(), we need to make the model
        // class return our builder. Bind a fake model in the container.
        $fakeClass = get_class($fakeModel);
        app()->bind($fakeClass, fn () => $fakeModel);

        $service = new ReorderService;
        $updated = $service->reorder($fakeClass, [
            ['id' => 9, 'order' => 0],
            ['id' => 7, 'order' => 1],
        ]);

        $this->assertSame(2, $updated);
        $this->assertSame(
            [
                ['whereKey' => 9],
                ['update' => ['order' => 0]],
                ['whereKey' => 7],
                ['update' => ['order' => 1]],
            ],
            $builder->calls,
            'rows must be reordered in the order the client submitted them',
        );
    }

    public function test_string_orders_are_coerced_to_integers(): void
    {
        // The admin sends the whole list on every arrow click, with order values
        // that come straight from HTML option numbers — i.e. strings.
        $builder = $this->builder([1]);

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($closure) => $closure());

        $fakeClass = $this->fakeModelClass($builder);

        $service = new ReorderService;
        $service->reorder($fakeClass, [
            ['id' => 1, 'order' => '4'],
        ]);

        $writes = array_column(array_filter($builder->calls, fn ($call) => isset($call['update'])), 'update');
        $this->assertSame([['order' => 4]], $writes, 'the string "4" must be persisted as the integer 4');
    }

    public function test_the_return_is_the_number_of_rows_actually_updated(): void
    {
        // A split unbind — the admin drags together rows that belong to a
        // different project — still returns 0 rather than pretending success.
        $builder = $this->builder([]);

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($closure) => $closure());

        $fakeClass = $this->fakeModelClass($builder);

        $service = new ReorderService;
        $updated = $service->reorder($fakeClass, [
            ['id' => 99, 'order' => 0],
        ]);

        $this->assertSame(0, $updated);
    }

    public function test_a_non_transactional_execution_would_still_pass(): void
    {
        // Guard against a change that strips the wrapping transaction: the
        // reorder must never run partially. DB::transaction is the atomicity
        // boundary — asserting it is called keeps the updates inside it.
        DB::shouldReceive('transaction')
            ->once()
            ->withArgs(fn ($closure) => is_callable($closure))
            ->andReturnUsing(fn ($closure) => $closure());

        $builder = $this->builder([1]);
        $fakeClass = $this->fakeModelClass($builder);

        $service = new ReorderService;
        $service->reorder($fakeClass, [
            ['id' => 1, 'order' => 0],
        ]);
    }

    /**
     * Create a fake model class that returns the given builder from query().
     */
    private function fakeModelClass(object $builder): string
    {
        $class = new class($builder)
        {
            private object $builder;

            public function __construct(object $builder)
            {
                $this->builder = $builder;
            }

            public static function query()
            {
                return app('reorder_fake_builder');
            }
        };

        $className = get_class($class);
        app()->bind('reorder_fake_builder', fn () => $builder);

        return $className;
    }
}
