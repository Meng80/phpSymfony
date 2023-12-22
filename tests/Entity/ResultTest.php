<?php

namespace App\Tests\Entity;

use App\Entity\Result;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    public function testConstructor(): void
    {
        $result = new Result(42);
        self::assertSame(42, $result->getResult());
        self::assertInstanceOf(\DateTime::class, $result->getTime());
    }

    public function testSetAndGetId(): void
    {
        $result = new Result();
        $result->setId(1);
        self::assertSame(1, $result->getId());
    }

    public function testSetAndGetResult(): void
    {
        $result = new Result();
        $result->setResult(100);
        self::assertSame(100, $result->getResult());
    }

    public function testSetAndGetTime(): void
    {
        $result = new Result();
        $time = new \DateTime('2023-01-01 12:00:00');
        $result->setTime($time);
        self::assertSame($time, $result->getTime());
    }

    public function testSetTimeFromString(): void
    {
        $result = new Result();
        $timeString = '2023-01-01 12:00:00';
        $result->setTimeFromString($timeString);
        self::assertInstanceOf(\DateTime::class, $result->getTime());
        self::assertSame($timeString, $result->getTime()->format('Y-m-d H:i:s'));
    }

    public function testSetAndGetUser(): void
    {
        $result = new Result();
        $user = new User();
        $result->setUser($user);
        self::assertSame($user, $result->getUser());
    }

    public function testJsonSerialize(): void
    {
        $user = new User();
        $time = new \DateTime('2023-01-01 12:00:00');
        $result = new Result(42, $user, $time);
        $result->setId(1);

        $expectedData = [
            'id' => 1,
            'result' => 42,
            'user' => $user,
            'time' => '2023-01-01 12:00:00',
        ];

        self::assertSame($expectedData, $result->jsonSerialize());
    }


}
