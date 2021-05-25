<?php
namespace happycog\blockinjector;

use Craft;
use craft\elements\MatrixBlock;
use happycog\blockinjector\Condition;
use happycog\blockinjector\Injection;
use Illuminate\Support\Collection;
use yii\base\Exception;

class Rule extends \craft\base\Component
{
    /** @var Collection */
    private $conditions;

    /** @var Collection */
    private $blocks;

    /** @var Collection */
    private $injections;

    /** @var Injection */
    private $currentInjection;

    /** @var bool */
    private $enabled = true;

    public static function create(array $config = []): self
    {
        return new self($config);
    }

    public function init()
    {
        $this->conditions = new Collection();
        $this->injections = new Collection();
        $this->currentInjection = new Injection();
    }

    public function enable(bool $enabled = true): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function disable()
    {
        return $this->enable(false);
    }

    public function enabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getBlocks()
    {
        return $this->blocks;
    }

    public function apply(Collection $blocks): Collection
    {
        // Ensure any added behaviors
        $this->ensureBehaviors();

        $this->blocks = $blocks->pipe(function ($blocks) {
            return $this->applyConditions($blocks);
        })
        ->when($this->enabled, function ($blocks) {
            return $blocks->pipe(function ($blocks) {
                return $this->applyInjections($blocks);
            });
        });

        return $this->blocks;
    }

    // TODO: move injectionCallback to inject
    public function at(int $position, $injectionCallback = null): self
    {
        if (!abs($position)) {
            throw new Exception('Position parameter must be a positive or negative integer.');
        }

        $index = $position > 0 ? $position - 1 : $position;

        return $this->atIndex($index, $injectionCallback);
    }

    // TODO: move injectionCallback to inject
    public function atIndex(int $index, $injectionCallback = null): self
    {
        $this->injections->push(
            Craft::configure(clone $this->currentInjection, [
                'index' => $index,
                'injectionCallback' => $injectionCallback,
            ])
        );

        return $this;
    }

    // TODO: move injectionCallback to inject
    public function atRatio(float $ratio, $injectionCallback = null): self
    {
        $this->injections->push(
            Craft::configure(clone $this->currentInjection, [
                'ratio' => $ratio,
                'injectionCallback' => $injectionCallback,
            ])
        );

        return $this;
    }

    // TODO: move injectionCallback to inject, bring back intervalCallback
    public function atInterval(int $interval, $injectionCallback = null): self
    {
        $this->injections->push(
            Craft::configure(clone $this->currentInjection, [
                'interval' => $interval,
                'injectionCallback' => $injectionCallback,
            ])
        );

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->currentInjection->offset = $offset;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->currentInjection->limit = $limit;

        return $this;
    }

    public function inject(MatrixBlock $block, bool $retry = true): self
    {
        return $this->injectMany([$block], $retry);
    }


    public function injectMany(iterable $blocks, bool $retry = true): self
    {
        Craft::configure($this->currentInjection, [
            'retry' => $retry,
            'blocksToInject' => (new Collection($blocks))->filter(),
        ]);

        return $this;
    }

    public function when($value, callable $callback = null, callable $default = null): self
    {
        $this->conditions->push(new Condition([
            'condition' => $value,
            'callback' => $callback,
            'default' => $default,
        ]));

        return $this;
    }

    public function unless($value, callable $callback = null, callable $default = null): self
    {
        $this->conditions->push(new Condition([
            'condition' => $value,
            'callback' => $callback,
            'default' => $default,
            'inverse' => true,
        ]));

        return $this;
    }

    private function applyConditions(Collection $blocks): Collection
    {
        return $this->conditions->reduce(function (Collection $blocks, Condition $condition) {
            $this->blocks = $blocks;

            return $condition->apply($this)->getBlocks();
        }, $blocks);
    }

    private function applyInjections(Collection $blocks): Collection
    {
        return $this->injections->reduce(function (Collection $blocks, Injection $injection) {
            return $injection->apply($blocks);
        }, $blocks);
    }
}
