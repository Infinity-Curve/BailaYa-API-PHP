<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use BailaYa\Client;
use BailaYa\Dto\PublicClass;
use BailaYa\Dto\PublicPackage;
use BailaYa\Dto\PublicInstructor;
use BailaYa\Dto\PaymentStatus;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

final class ClientPublicDetailsTest extends TestCase
{
    private const BASE = 'https://test.api';

    private function client(array $body): Client
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR)),
        ]);
        return new Client([
            'baseUrl' => self::BASE,
            'guzzle' => ['handler' => HandlerStack::create($mock)],
        ]);
    }

    public function testGetPublicClassParsesDate(): void
    {
        $raw = [
            'id' => 'c1', 'name' => 'Salsa', 'level' => 'Beginner',
            'date' => '2026-04-09', 'startTime' => '18:00', 'endTime' => '19:00',
            'price' => 100, 'capacity' => 20, 'enrolledCount' => 5, 'availableSpots' => 15,
            'studio' => ['id' => 's1', 'name' => 'My Studio', 'defaultCurrency' => 'MXN'],
        ];
        $result = $this->client($raw)->getPublicClass('c1');

        $this->assertInstanceOf(PublicClass::class, $result);
        $this->assertInstanceOf(DateTimeImmutable::class, $result->date);
        $this->assertSame('2026', $result->date->format('Y'));
        $this->assertSame(15, $result->availableSpots);
        $this->assertSame('My Studio', $result->studio->name);
    }

    public function testGetPublicPackage(): void
    {
        $raw = [
            'id' => 'p1', 'name' => '10-Class Bundle', 'description' => 'Best value',
            'price' => 2000, 'sessions' => 10, 'durationMonths' => 3,
            'allowedLevels' => [], 'allowedDanceTypes' => ['Salsa'],
            'studio' => ['id' => 's1', 'name' => 'My Studio'],
        ];
        $result = $this->client($raw)->getPublicPackage('p1');

        $this->assertInstanceOf(PublicPackage::class, $result);
        $this->assertSame(10, $result->sessions);
        $this->assertSame(['Salsa'], $result->allowedDanceTypes);
    }

    public function testGetPublicInstructor(): void
    {
        $raw = [
            'id' => 'i1', 'name' => 'Ana', 'lastname' => 'Lopez', 'studioId' => 's1',
            'studio' => ['id' => 's1', 'name' => 'My Studio'],
            'instructorAvailability' => [
                ['id' => 'a1', 'dayOfWeek' => 1, 'startTime' => '09:00', 'endTime' => '17:00'],
            ],
            'privateLessonPricing' => [
                ['id' => 'pr1', 'durationMins' => 60, 'price' => 50, 'currency' => 'MXN'],
            ],
        ];
        $result = $this->client($raw)->getPublicInstructor('i1');

        $this->assertInstanceOf(PublicInstructor::class, $result);
        $this->assertCount(1, $result->instructorAvailability);
        $this->assertSame(60, $result->privateLessonPricing[0]->durationMins);
    }

    public function testGetPaymentStatusParsesDates(): void
    {
        $raw = [
            'payment' => [
                'id' => 'pay1', 'status' => 'paid', 'type' => 'INDIVIDUAL_CLASS',
                'amount' => 100, 'createdAt' => '2026-04-01T10:00:00.000Z',
                'paidAt' => '2026-04-01T10:05:00.000Z',
            ],
            'bookingStatus' => 'enrolled',
        ];
        $result = $this->client($raw)->getPaymentStatus('pay1');

        $this->assertInstanceOf(PaymentStatus::class, $result);
        $this->assertInstanceOf(DateTimeImmutable::class, $result->payment->createdAt);
        $this->assertInstanceOf(DateTimeImmutable::class, $result->payment->paidAt);
        $this->assertSame('enrolled', $result->bookingStatus);
    }

    public function testGetPaymentStatusNullPaidAt(): void
    {
        $raw = [
            'payment' => [
                'id' => 'pay2', 'status' => 'pending', 'type' => 'INDIVIDUAL_CLASS',
                'amount' => 100, 'createdAt' => '2026-04-01T10:00:00.000Z', 'paidAt' => null,
            ],
            'bookingStatus' => 'unknown',
        ];
        $result = $this->client($raw)->getPaymentStatus('pay2');

        $this->assertNull($result->payment->paidAt);
    }

    public function testRequiresIdArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Client(['baseUrl' => self::BASE]))->getPublicPackage('');
    }
}
