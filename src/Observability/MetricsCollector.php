<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Observability;

use Closure;

use function array_filter;
use function array_reduce;
use function count;

/**
 * Collects and reports request metrics.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MetricsCollector
{
    /** @var array<int, RequestMetrics> */
    private array $metrics = [];

    /** @var array<Closure(RequestMetrics): void> */
    private array $reporters = [];

    /**
     * Record a metric.
     */
    public function record(RequestMetrics $metric): void
    {
        $this->metrics[] = $metric;

        foreach ($this->reporters as $reporter) {
            $reporter($metric);
        }
    }

    /**
     * Register a reporter callback.
     *
     * @param Closure(RequestMetrics): void $callback
     */
    public function addReporter(Closure $callback): self
    {
        $this->reporters[] = $callback;

        return $this;
    }

    /**
     * Get all collected metrics.
     *
     * @return array<int, RequestMetrics>
     */
    public function all(): array
    {
        return $this->metrics;
    }

    /**
     * Get metrics filtered by criteria.
     *
     * @param  Closure(RequestMetrics): bool $filter
     * @return array<int, RequestMetrics>
     */
    public function filter(Closure $filter): array
    {
        return array_filter($this->metrics, $filter);
    }

    /**
     * Get failed request metrics.
     *
     * @return array<int, RequestMetrics>
     */
    public function failed(): array
    {
        return $this->filter(fn (RequestMetrics $m): bool => $m->errorType !== null);
    }

    /**
     * Get cached request metrics.
     *
     * @return array<int, RequestMetrics>
     */
    public function cached(): array
    {
        return $this->filter(fn (RequestMetrics $m): bool => $m->cached);
    }

    /**
     * Get average duration in milliseconds.
     */
    public function averageDuration(): float
    {
        if ($this->metrics === []) {
            return 0.0;
        }

        $total = array_reduce(
            $this->metrics,
            fn (float $sum, RequestMetrics $m): float => $sum + $m->duration,
            0.0,
        );

        return $total / count($this->metrics);
    }

    /**
     * Get total request count.
     */
    public function count(): int
    {
        return count($this->metrics);
    }

    /**
     * Get count by status code.
     *
     * @return array<int, int>
     */
    public function countByStatus(): array
    {
        $counts = [];

        foreach ($this->metrics as $metric) {
            $counts[$metric->status] = ($counts[$metric->status] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Get count by endpoint.
     *
     * @return array<string, int>
     */
    public function countByEndpoint(): array
    {
        $counts = [];

        foreach ($this->metrics as $metric) {
            $counts[$metric->endpoint] = ($counts[$metric->endpoint] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Clear all collected metrics.
     */
    public function clear(): void
    {
        $this->metrics = [];
    }
}
