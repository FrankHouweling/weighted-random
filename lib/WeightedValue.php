<?php
declare(strict_types=1);

namespace FrankHouweling\WeightedRandom;

/**
 * Class WeightedValue
 */
final class WeightedValue
{
    /** @var mixed */
    private $value;

    /** @var int */
    private $weight;

    /**
     * WeightedValue constructor.
     * @param $value
     * @param int $weight
     */
    public function __construct($value, int $weight)
    {
        $this->value = $value;
        $this->weight = $weight;
    }

    /**
     * @return array
     */
    public function getArrayCopy()
    {
        return [
            'value' => $this->getValue(),
            'weight' => $this->getWeight(),
        ];
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return int
     */
    public function getWeight(): int
    {
        return $this->weight;
    }
}
