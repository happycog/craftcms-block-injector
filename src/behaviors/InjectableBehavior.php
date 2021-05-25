<?php

namespace happycog\blockinjector\behaviors;

use craft\elements\MatrixBlock;
use craft\helpers\StringHelper;
use Illuminate\Support\Collection;
use happycog\blockinjector\BlockInjector;
use happycog\blockinjector\Rule;
use Stringy\Stringy;
use yii\base\Behavior;

class InjectableBehavior extends Behavior
{
    public function withInjections(): array
    {
        $blocks = (new Collection($this->owner->all()))
            ->pipe(function ($blocks) {
                return $this->splitCopyBlocks($blocks);
            })
            ->pipe(function ($blocks) {
                return $this->applyRules($blocks);
            });

        if (BlockInjector::getInstance()->getSettings()->debug) {
            return \Craft::dd($blocks->pluck('type.handle'));
        }

        return $blocks->all();
    }

    private function applyRules(Collection $blocks): Collection
    {
        return (new Collection(BlockInjector::getInstance()->getSettings()->rules))
            ->reduce(function (Collection $blocks, Rule $rule): Collection {
                return $rule->apply($blocks);
            }, $blocks);
    }

    private function splitCopyBlocks(Collection $blocks): Collection
    {
        return $blocks->flatMap(function ($block) {
            return $this->splitCopyBlock($block);
        });
    }

    private function splitCopyBlock(MatrixBlock $block): Collection
    {
        if ($block->type->handle !== 'copy' || !$block->text) {
            return new Collection([$block]);
        }

        $splitOn = "\n\n";
        $paragraphs = new Collection(Stringy::create($block->text)->split($splitOn));
        $tryAppend = false;

        return $paragraphs->reduce(function ($blocks, Stringy $paragraph) use (&$tryAppend, $splitOn) {
            if ($paragraph->length() < 200) {
                if ($tryAppend) {
                    $lastBlock = $blocks->pop();

                    if ($lastBlock) {
                        return $blocks->push($lastBlock->append($splitOn)->append($paragraph));
                    }
                }

                $tryAppend = true;
            } else {
                $tryAppend = false;
            }

            return $blocks->push($paragraph);
        }, new Collection())
        ->map(function ($text) use ($block): MatrixBlock {
            $newBlock = clone $block;
            $newBlock->setFieldValue('text', (string) $text);

            return $newBlock;
        });
    }
}
