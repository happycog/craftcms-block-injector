<?php
namespace happycog\blockinjector;

use Craft;
use craft\elements\MatrixBlock;
use Illuminate\Support\Collection;

class Injection extends \craft\base\Component
{
    /** @var Collection */
    public $blocksToInject;

    /** @var int */
    public $index;

    /** @var int */
    public $interval;

    /** @var bool */
    public $retry = true;

    /** @var float */
    public $ratio;

    /** @var callable */
    public $intervalCallback;

    /** @var callable */
    public $injectionCallback;

    /** @var int */
    private $intervalCounter = 0;

    /** @var int */
    private $retryOffset = 0;

    public function apply(Collection $blocks): Collection
    {
        return $blocks
            ->when($this->index !== null, function (Collection $blocks) {
                return $this->applyIndex($blocks);
            })
            ->when($this->ratio, function (Collection $blocks) {
                return $this->applyRatio($blocks);
            })
            ->when($this->interval, function (Collection $blocks) {
                return $this->applyInterval($blocks);
            });
    }

    public function applyRatio(Collection $blocks): Collection
    {
        $index = floor($blocks->count() * $this->ratio);

        return $this->injectAtIndex($index, $blocks);
    }

    public function applyIndex(Collection $blocks): Collection
    {
        return $this->injectAtIndex($this->index, $blocks);
    }

    public function applyInterval(Collection $blocks): Collection
    {
        $indices = $blocks->map(function ($block, $index) use ($blocks) {
            $next = $blocks->get($index + 1);

            if (!$this->intervalCallback || ($this->intervalCallback)($block, $next)) {
                $this->intervalCounter++;

                if ($this->intervalCounter % $this->interval === 0) {
                    return $index + 1;
                }
            }

            return null;
        })->filter(function ($index) {
            return $index !== null;
        })
        ->values();

        return $indices->reduce(function ($blocks, $index) {
            return $this->injectAtIndex($index + $this->retryOffset, $blocks);
        }, $blocks);
    }

    private function injectAtIndex(int $index, Collection $blocks): Collection
    {
        if ($index > $blocks->count()) {
            Craft::info("Block(s) could NOT be injected at index `{$index}`.", __METHOD__);

            return $blocks;
        }

        $end = $blocks->splice($index - 1);
        $prev = $blocks->last();
        $next = $end->first();

        return $blocks->when($this->shouldInject($prev, $next), function ($blocks) use ($index, $end) {
            Craft::info("Injecting block(s) at index `{$index}`.", __METHOD__);

            return $blocks->concat($this->blocksToInject)->concat($end);
        }, function($blocks) use ($index, $end) {
            return $blocks->concat($end)->when($this->retry, function($blocks) use ($index) {
                Craft::info("Retrying injection at index `{$index}`.", __METHOD__);
                $this->retryOffset ++;

                return $this->injectAtIndex($index + 1, $blocks);
            });
        });
    }

    private function shouldInject(?MatrixBlock $prev, ?MatrixBlock $next): bool
    {
        return !$this->injectionCallback || ($this->injectionCallback)($prev, $next);
    }
}
