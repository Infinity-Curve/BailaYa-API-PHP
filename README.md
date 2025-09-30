# bailaya/core

> A PHP client for accessing the BailaYa public API

## Overview

This package provides an easy-to-use wrapper around the BailaYa API, allowing PHP developers to fetch studio profiles, instructors, classes, events, and more.
It includes DTOs for structured responses, automatic date parsing, and graceful handling of invalid JSON.

## Features

* DTO classes for typed API responses
* Automatic `DateTimeImmutable` parsing
* Optional studio ID configuration
* Graceful JSON fallback (`bio`, `description`, etc.)
* Supports querying by date and class type
* Built on [Guzzle](https://github.com/guzzle/guzzle)

## Installation

```bash
composer require bailaya/core
```

## Usage

### Basic Setup

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Bailaya\Client;

$client = new Client([
    'studioId' => 'your-studio-id', // Optional: can also be set per call or via env var BAILAYA_STUDIO_ID
]);
```

### Get Studio Profile

```php
$profile = $client->getStudioProfile();
echo $profile->name;
```

### Get User Profile

```php
$user = $client->getUserProfile('user-123');
echo $user->bio['en'] ?? '';
```

### Get Instructors

```php
$instructors = $client->getInstructors();
foreach ($instructors as $instr) {
    echo $instr->name . PHP_EOL;
}
```

### Get Upcoming Classes

```php
$classes = $client->getClasses(new DateTimeImmutable('today'));
```

### Get Classes by Dance Type

```php
$bachata = $client->getClassesByType('bachata', new DateTimeImmutable('today'));
```

### Get Upcoming Events

```php
$events = $client->getEvents(new DateTimeImmutable('2025-08-01'));
```

## API Reference

### `new Client(array $options = [])`

Creates a new instance of the client.

#### Options:

* `baseUrl?: string` — *(Optional)* Custom API base URL. Defaults to the official BailaYa API endpoint.
* `studioId?: string` — *(Optional)* Default studio ID used for methods that require it.
* `guzzle?: array` — *(Optional)* Custom Guzzle client configuration (e.g., `['handler' => $mock]` for testing).

---

### `getStudioProfile(?string $overrideId = null): StudioProfile`

Fetches a studio profile, including metadata and supported dance types.

---

### `getUserProfile(string $userId): UserProfile`

Fetches a specific user profile, including their bio, image, and dance specialities.

---

### `getInstructors(?string $overrideId = null): Instructor[]`

Retrieves all instructors linked to a studio.

---

### `getClasses(?DateTimeInterface $from = null, ?string $overrideId = null): StudioClass[]`

Fetches all upcoming classes in a 7-day window, starting from the given date.

---

### `getClassesByType(string $typeName, ?DateTimeInterface $from = null, ?string $overrideId = null): StudioClass[]`

Fetches upcoming classes for a specific dance type (e.g., "Salsa", "Bachata") within a 7-day window.

---

### `getEvents(?DateTimeInterface $from = null, ?string $overrideId = null): StudioEvent[]`

Fetches upcoming events for a studio within a 7-day window.

---

## DTOs

All responses are returned as dedicated DTO classes:

* `StudioProfile`
* `UserProfile`
* `Instructor`
* `StudioClass`
* `StudioEvent`
* `StudioType`

Each DTO implements `JsonSerializable` for easy conversion:

```php
echo json_encode($profile, JSON_PRETTY_PRINT);
```

---

## License

ISC
