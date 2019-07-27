<?php
declare(strict_types=1);

namespace FrankHouweling\WeightedRandom;

use Assert\Assertion;

/**
 * Class WeightedRandomGenerator
 */
final class WeightedRandomGenerator
{
    /** @var array|mixed[] */
    private $values = [];

    /** @var array|int[] */
    private $weights = [];

    /** @var string */
    private $totalWeightCount;

    /** @var callable */
    private $randomNumberGenerator;

    /**
     * WeightedRandomGenerator constructor.
     */
    public function __construct()
    {
        $this->randomNumberGenerator = 'random_int';
    }

    /**
     * Add or update a possible return value for the weighted random generator.
     *
     * @param mixed $value The possible return value.
     * @param int $weight Weight of the possibility of getting this value as a whole number.
     */
    public function registerValue($value, int $weight = 1): void
    {
        Assertion::min($weight, 1, 'Weight can not be 0.');
        $key = $this->getValueKey($value);
        $this->setKeyWeight($key, $weight);
        $this->resetTotalWeightCount();
    }

    /**
     * Register a value -> weight pair array as values.
     * For example: $generator->registerValues(['small_chance' => 1, 'large_chance' => 100]);
     *
     * @param array $valueCollection Key - value pairs where the key is the value and the value is weight.
     */
    public function registerValues(array $valueCollection): void
    {
        foreach ($valueCollection as $value => $weight)
        {
            Assertion::integer($weight, 'Weight should be a whole number.');
            $this->registerValue($value, $weight);
        }
    }

    /**
     * Add or update a possible return value for the weighted random generator.
     *
     * @param WeightedValue $weightedValue
     */
    public function registerWeightedValue(WeightedValue $weightedValue): void
    {
        $this->registerValue($weightedValue->getValue(), $weightedValue->getWeight());
    }

    /**
     * @param $value
     */
    public function removeValue($value): void
    {
        $key = $this->getExistingValueKey($value);
        if ($key === null)
        {
            throw new \InvalidArgumentException('Given value is not registered.');
        }
        unset($this->values[$key], $this->weights[$key]);
        $this->resetTotalWeightCount();
    }

    /**
     * @param WeightedValue $weightedValue
     */
    public function removeWeightedValue(WeightedValue $weightedValue): void
    {
        $this->removeValue($weightedValue->getValue());
    }

    /**
     * Return a generator that generated WeightedValue instances for all registered values.
     *
     * @return \Generator|WeightedValue[]
     */
    public function getWeightedValues(): \Generator
    {
        foreach ($this->values as $key => $value)
        {
            yield new WeightedValue(
                $value,
                $this->weights[$key]
            );
        }
    }

    /**
     * @param $value
     * @return WeightedValue
     */
    public function getWeightedValue($value): WeightedValue
    {
        $key = $this->getExistingValueKey($value);
        if ($key === null)
        {
            throw new \InvalidArgumentException('Given value is not registered.');
        }
        return new WeightedValue(
            $this->values[$key],
            $this->weights[$key]
        );
    }

    /**
     * Generate a random sample of one value from the registered values.
     *
     * @return mixed
     */
    public function generate()
    {
        Assertion::notEmpty($this->values, 'At least one value should be registered.');

        $totalWeightCount = $this->getTotalWeightCount();
        $randomNumberGenerator = $this->randomNumberGenerator;
        $randomValue = $randomNumberGenerator(0, $totalWeightCount);
        foreach ($this->weights as $key => $weight)
        {
            if ($weight >= $randomValue)
            {
                return $this->values[$key];
            }
            $randomValue -= $weight;
        }
    }

    /**
     * Generate a sample of $sampleCount random entries from the registered values. May contain duplicate values.
     *
     * @see WeightedRandomGenerator::generateMultipleWithoutDuplicates()
     *
     * @param int $sampleCount The amount of samples we should generated.
     * @return \Generator
     */
    public function generateMultiple(int $sampleCount): \Generator
    {
        Assertion::notEq($sampleCount,0, 'The sample count should be higher then 0.');

        for ($i = 0; $i < $sampleCount; $i++)
        {
            yield $this->generate();
        }
    }

    /**
     * Generate a sample of $sampleCount random entries from the registered values. This method will never return the
     * same two values in one call. Separate calls may generate the same values.
     *
     * @param int $sampleCount
     * @return \Generator
     */
    public function generateMultipleWithoutDuplicates(int $sampleCount): \Generator
    {
        Assertion::notEq($sampleCount,0, 'The sample count should be higher then 0.');
        Assertion::lessOrEqualThan(
            $sampleCount,
            count($this->values),
            'The sample count should be less or equal to the registered value count.'
        );

        $returnedCollection = [];
        while (count($returnedCollection) < $sampleCount)
        {
            $sample = $this->generate();
            if (in_array($sample, $returnedCollection, true))
            {
                continue;
            }
            $returnedCollection[] = $sample;
            yield $sample;
        }
    }

    /**
     * Set a custom random number generator.
     *
     * @deprecated This method should only be used for testing.
     * @param callable $randomNumberGenerator A callable with a $min and a $max argument, returning a random INT.
     */
    public function setRandomNumberGenerator(callable $randomNumberGenerator): void
    {
        $this->randomNumberGenerator = $randomNumberGenerator;
    }

    /**
     * @param $value
     * @return int
     */
    private function getValueKey($value): int
    {
        if (in_array($value, $this->values, true) === false)
        {
            $this->values[] = $value;
        }
        return $this->getExistingValueKey($value);
    }

    /**
     * Set the weight for a given key.
     *
     * @param int $key
     * @param int $weight
     */
    private function setKeyWeight(int $key, int $weight): void
    {
        $this->weights[$key] = $weight;
    }

    /**
     * @param $value
     * @return int|null
     */
    private function getExistingValueKey($value): ?int
    {
        $key = array_search($value, $this->values, true);
        if ($key === false)
        {
            return null;
        }
        return $key;
    }

    /**
     *
     */
    private function resetTotalWeightCount(): void
    {
        $this->totalWeightCount = null;
    }

    /**
     * @return int
     */
    private function getTotalWeightCount(): int
    {
        if ($this->totalWeightCount === null)
        {
            $count = 0;
            foreach ($this->weights as $key => $weight)
            {
                $count += $weight;
            }
            $this->totalWeightCount = $count;
        }
        return $this->totalWeightCount;
    }
}