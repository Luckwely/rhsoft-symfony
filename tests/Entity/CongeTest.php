<?php

namespace App\Tests\Entity;

use App\Entity\Conge;
use PHPUnit\Framework\TestCase;

class CongeTest extends TestCase
{
    public function testDateFieldsAcceptMutableDateTimeAndNormalizeToImmutable(): void
    {
        $conge = new Conge();
        $date = new \DateTime('2024-01-15');

        $conge->setDateDebut($date);
        $conge->setDateFin($date);
        $conge->setValideLe($date);

        $this->assertInstanceOf(\DateTimeImmutable::class, $conge->getDateDebut());
        $this->assertInstanceOf(\DateTimeImmutable::class, $conge->getDateFin());
        $this->assertInstanceOf(\DateTimeImmutable::class, $conge->getValideLe());
        $this->assertSame('2024-01-15', $conge->getDateDebut()->format('Y-m-d'));
    }
}
