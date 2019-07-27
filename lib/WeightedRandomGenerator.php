<?php
declare(strict_types=1);

namespace FrankHouweling\WeightedRandom;

use Assert\Assertion;

/**
 * Class WeightedRandomGenerator
 *
 * Generate a random value out of a set of registered values, where the probability of generating the value is
 * determined by the given weight. The probability grows linearly with the set weight.
 */
final class WeightedRandomGenerator
{
    /** @var array|mixed[] */
    private $values = [];

    /** @var array|int[] */
    private $weights = [];

    /** @var int|null */
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
     * Register (add or update) a possible return value for the weighted random generator.
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
        foreach ($valueCollection as $value => $weight) {
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
     * Remove a value from the generator. After removing the value, it will not be returned by calling generate.
     *
     * @param $value
     */
    public function removeValue($value): void
    {
        $key = $this->getExistingValueKey($value);
        if ($key === null) {
            throw new \InvalidArgumentException('Given value is not registered.');
        }
        unset($this->values[$key], $this->weights[$key]);
        $this->resetTotalWeightCount();
    }

    /**
     * Remove a value from the generator. After removing the value, it will not be returned by calling generate.
     *
     * @param WeightedValue $weightedValue
     */
    public function removeWeightedValue(WeightedValue $weightedValue): void
    {
        $this->removeValue($weightedValue->getValue());
    }

    /**
     * Return a generator that generated WeightedValue instances for all registered values.
     *
     * @return \Generator
     */
    public function getWeightedValues(): \Generator
    {
        foreach ($this->values as $key => $value) {
            yield new WeightedValue(
                $value,
                $this->weights[$key]
            );
        }
    }

    /**
     * Get the WeightedValue valueobject with the value and weight for a given value.
     *
     * @param $value
     * @return WeightedValue
     */
    public function getWeightedValue($value): WeightedValue
    {
        $key = $this->getExistingValueKey($value);
        if ($key === null) {
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
        foreach ($this->weights as $key => $weight) {
            if ($weight >= $randomValue) {
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
        Assertion::notEq($sampleCount, 0, 'The sample count should be higher then 0.');

        for ($i = 0; $i < $sampleCount; $i++) {
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
        Assertion::notEq($sampleCount, 0, 'The sample count should be higher then 0.');
        Assertion::lessOrEqualThan(
            $sampleCount,
            count($this->values),
            'The sample count should be less or equal to the registered value count.'
        );

        $returnedCollection = [];
        while (count($returnedCollection) < $sampleCount) {
            $sample = $this->generate();
            if (in_array($sample, $returnedCollection, true)) {
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
     * Get a valuekey for the given value. This is the existing key if the value was already registered. If the value
     * was not registered yet, the value is stored and the key returned.
     *
     * @param $value
     * @return int
     */
    private function getValueKey($value): int
    {
        if (in_array($value, $this->values, true) === false) {
            $this->values[] = $value;
        }
        return $this->getExistingValueKey($value) ?? 0;
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
     * Get the key for a given value, or NULL when the value was not yet registered.
     *
     * @param $value
     * @return int|null
     */
    private function getExistingValueKey($value): ?int
    {
        $key = array_search($value, $this->values, true);
        if ($key === false) {
            return null;
        }
        return $key;
    }

    /**
     * Reset the total weight count. Should be called when a value is added/removed or when it's weight has changed.
     */
    private function resetTotalWeightCount(): void
    {
        $this->totalWeightCount = null;
    }

    /**
     * Return the total weight of all values that are registered.
     *
     * Cached in self::totalWeightCount, and only recalculated when necessary.
     *
     * @return int
     */
    private function getTotalWeightCount(): int
    {
        if ($this->totalWeightCount === null) {
            $count = 0;
            foreach ($this->weights as $key => $weight) {
                $count += $weight;
            }
            $this->totalWeightCount = $count;
        }
        return $this->totalWeightCount;
    }
}
