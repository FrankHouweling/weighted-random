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
}