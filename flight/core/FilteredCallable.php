<?php

declare(strict_types=1);

namespace flight\core;

use Closure;
use ReflectionFunction;
use Throwable;

/**
 * @template CallableWithoutFilters of Closure = Closure
 * @template Output = mixed
 */
final class FilteredCallable
{
    /**
     * @readonly
     * @var CallableWithoutFilters
     */
    private $closure;

    /** @var (callable(mixed[] &$input): (void|never|false))[] */
    private array $beforeFilters = [];

    /** @var (callable(mixed &$output): (void|never|false))[] */
    private array $afterFilters = [];

    /** @param CallableWithoutFilters|callable(): Output $callable */
    public function __construct(callable $callable)
    {
        $this->closure = Closure::fromCallable($callable);
    }

    /**
     * @param mixed ...$input
     * @return Output
     * @throws Throwable
     */
    public function __invoke(...$input)
    {
        foreach ($this->beforeFilters as $filter) {
            $filterReturnValue = $filter($input);

            if ($filterReturnValue === false) {
                break;
            }
        }

        $closure = $this->closure;

        $output = $closure(...$input);

        foreach ($this->afterFilters as $filter) {
            $filterReturnValue = $filter($output);

            if ($filterReturnValue === false) {
                break;
            }
        }

        return $output;
    }

    /** @param callable(mixed[] &$input): (void|never|false) $filter */
    public function pushBeforeFilter(callable $filter): void
    {
        if (!in_array($filter, $this->beforeFilters)) {
            $this->beforeFilters[] = $filter;
        }
    }

    /** @param callable(Output &$output): (void|never|false) $filter */
    public function pushAfterFilter(callable $filter): void
    {
        if (!in_array($filter, $this->afterFilters)) {
            $filterReflectionFunction = new ReflectionFunction($filter);

            if ($filterReflectionFunction->getNumberOfParameters() === 2) {
                $filter = static function (&$output) use ($filter) {
                    static $input = [];

                    return $filter($input, $output);
                };
            }

            $this->afterFilters[] = $filter;
        }
    }
}
