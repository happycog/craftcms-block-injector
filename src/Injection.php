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

    /** @var int */
    public $offset = 0;

    /** @var int */
    public $limit;

    /** @var bool */
    public $retry = true;

    /** @var float */
    public $ratio;

    /** @var callable */
    public $injectionCallback;

    /** @var int */
    private $injectionCount = 0;

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
        return $blocks->reduce(function ($blocks, $block, $index) {
            $targetIndex = $index + $this->injectionCount;

            if (($targetIndex + 1) % $this->interval === 0) {
                return $this->injectAtIndex($targetIndex, $blocks);
            }

            return $blocks;
        }, $blocks);
    }

    private function injectAtIndex(int $index, Collection $blocks): Collection
    {
        $targetIndex = $index + $this->offset;

        if ($targetIndex > $blocks->count() ||
            $this->limit !== null && ($this->injectionCount + 1) > $this->limit
        ) {
            Craft::info("Block(s) could NOT be injected at index `{$index}`.", __METHOD__);

            return $blocks;
        }

        // Convert to a splice-friendly index, for negatives
        $spliceIndex = $targetIndex < 0 ? $blocks->count() + $targetIndex + 1 : $targetIndex;
        $end = $blocks->splice($spliceIndex);
        $prev = $blocks->last();
        $next = $end->first();

        return $blocks->when($this->shouldInject($prev, $next), function ($blocks) use ($targetIndex, $end) {
            Craft::info("Injecting block(s) at index `{$targetIndex}`.", __METHOD__);
            $this->injectionCount++;

            return $blocks->concat($this->blocksToInject)->concat($end);
        }, function ($blocks) use ($targetIndex, $end) {
            return $blocks->concat($end)->when($this->retry, function ($blocks) use ($targetIndex) {
                $this->offset++;

                return $this->injectAtIndex($targetIndex, $blocks);
            });
        });
    }

    private function shouldInject(?MatrixBlock $prev, ?MatrixBlock $next): bool
    {
        return !$this->injectionCallback || ($this->injectionCallback)($prev, $next);
    }
}
