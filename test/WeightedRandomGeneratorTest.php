<?php
declare(strict_types=1);

use FrankHouweling\WeightedRandom\WeightedRandomGenerator;
use FrankHouweling\WeightedRandom\WeightedValue;
use PHPUnit\Framework\TestCase;

/**
 * Class WeightedRandomGeneratorTest
 */
final class WeightedRandomGeneratorTest extends TestCase
{
    /**
     * @var WeightedRandomGenerator
     */
    private $generator;

    /**
     *
     */
    public function setUp(): void
    {
        $this->generator = new WeightedRandomGenerator();
    }

    /**
     * Test registering values of multiple types.
     */
    public function testRegisterValue()
    {
        $values = [
            123,
            '123',
            [1,2,3],
            new stdClass(),
            false,
            null
        ];

        // Mock random number generator to return 1,2,3,4
        $mockRandomNumberGenerator = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $mockRandomNumberGenerator->method('__invoke')
            ->withAnyParameters()
            ->will($this->onConsecutiveCalls(...range(1,count($values))));

        $this->generator->setRandomNumberGenerator($mockRandomNumberGenerator);

        foreach ($values as $value)
        {
            $this->generator->registerValue($value);
        }

        $sample = iterator_to_array($this->generator->generateMultipleWithoutDuplicates(count($values)));
        $this->assertEquals($values, $sample);
    }

    /**
     * Test registering a value with the WeightedValue model.
     */
    public function testRegisterValueWithModel()
    {
        $weightedValue = new WeightedValue('test', 3);
        $this->generator->registerWeightedValue($weightedValue);

        $retrievedWeightedValue = $this->generator->getWeightedValue($weightedValue->getValue());

        $this->assertEquals($weightedValue->getArrayCopy(), $retrievedWeightedValue->getArrayCopy());
    }

    /**
     * Test registering multiple values and weights via the registerValue method.
     */
    public function testRegisterValues()
    {
        $values = ['foobar' => 10, 'foo' => 20, 'bar' => 30];
        $this->generator->registerValues($values);

        foreach ($this->generator->getWeightedValues() as $weightedValue)
        {
            $this->assertArrayHasKey($weightedValue->getValue(), $values);
            $this->assertEquals($values[$weightedValue->getValue()], $weightedValue->getWeight());
        }
    }

    /**
     * Test registering a value with a weight of 0.
     */
    public function testRegisterValueWeightZero()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->generator->registerValue('test', 0);
    }

    /**
     * Test registering a value with a weight of 0, using the registerValues method.
     */
    public function testRegisterValuesWeightZero()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->generator->registerValues(['test' => 0]);
    }

    /**
     * Remove a registered value.
     */
    public function testRemoveValue()
    {
        $value = new \stdClass();
        $this->generator->registerValue($value);
        $this->generator->removeValue($value);

        $registeredValues = iterator_to_array($this->generator->getWeightedValues());
        $this->assertEquals(0, count($registeredValues));
    }

    /**
     * Try to remove a unregistered value.
     */
    public function testRemoveUnregisteredValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->generator->removeValue(new \stdClass());
    }

    /**
     * Try to remove a registered weighted value.
     */
    public function testRemoveWeightedValue()
    {
        $value = new \stdClass();
        $weightedValue = new WeightedValue($value, 2);
        $this->generator->registerWeightedValue($weightedValue);
        $this->generator->removeWeightedValue($weightedValue);

        $registeredValues = iterator_to_array($this->generator->getWeightedValues());
        $this->assertEquals(0, count($registeredValues));
    }

    /**
     * Test that generate multiple can and will return the same value multiple times.
     */
    public function testGenerateMultipleDuplicateValues()
    {
        $registeredValue = new \stdClass();
        $this->generator->registerValue($registeredValue);

        $values = iterator_to_array($this->generator->generateMultiple(10));

        $this->assertCount(10, $values);
        foreach ($values as $value)
        {
            $this->assertEquals($value, $registeredValue);
        }
    }

    /**
     * Test the generateMultipleWithoutDuplicates for removing duplicate items from the results.
     */
    public function testGenerateMultipleNoDuplicateValues()
    {
        $registeredValues = [
            '1' => 1,
            '2' => 1,
            '3' => 1,
        ];
        $this->generator->registerValues($registeredValues);

        // Mock random number generator to return the first item twice, then return items two and three.
        $mockRandomNumberGenerator = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $mockRandomNumberGenerator->method('__invoke')
            ->withAnyParameters()
            ->will($this->onConsecutiveCalls(1,1,2,3));

        $this->generator->setRandomNumberGenerator($mockRandomNumberGenerator);

        $sample = iterator_to_array($this->generator->generateMultipleWithoutDuplicates(count($registeredValues)));
        $this->assertEquals(array_keys($registeredValues), $sample);
    }

    /**
     * Test getting a weighted value for a non existing value, which should result in an invalid argument exception
     */
    public function testGetNonExistingWeightedValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->generator->getWeightedValue(new \stdClass());
    }
}