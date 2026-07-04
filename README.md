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

## Authentication (Management API)

Beyond the read-only public endpoints, the client can talk to the authenticated
**Management API** under `/v1`. These endpoints let you create, read, update and
delete classes, events, students, instructors, team members and packages.

There are two ways to authenticate:

* **API key** — a long-lived studio key that looks like `bya_live_…`. Best for
  server-to-server integrations.
* **User session token** — a short-lived JWT obtained with `login()`. Best for
  acting on behalf of a signed-in user.

Credentials can be passed in the constructor or via environment variables:

```php
use Bailaya\Client;

$client = new Client([
    'studioId'    => 'your-studio-id', // sent as X-Studio-Id when using a user token
    'apiKey'      => 'bya_live_xxx',    // or env BAILAYA_API_KEY
    // 'accessToken' => '...',          // or env BAILAYA_API_TOKEN
]);
```

The API key (if present) is preferred over the access token. When `studioId` is
configured it is sent as the `X-Studio-Id` header to scope user-token requests.
Calling an authenticated method with no credentials throws a `RuntimeException`.

### Logging in with email + password

```php
$client = new Client(['baseUrl' => 'https://www.bailaya.com/api']);

$auth = $client->login('owner@example.com', 'secret', 'My Integration');
// $auth['accessToken'], $auth['refreshToken'], $auth['userId'], …
// Tokens are stored on the client automatically.

$client->refresh();  // exchange the stored refresh token for a fresh access token
$client->logout();   // revoke the session and clear stored tokens
```

### Managing classes

```php
// Creating a class may produce several occurrences (weekly recurrence),
// so createClass() returns a LIST of classes.
$created = $client->createClass([
    'name'         => 'Salsa Level 1',
    'discipline'   => 'Salsa',
    'level'        => 'Beginner',
    'startTime'    => '18:00',
    'endTime'      => '19:00',
    'teamMemberId' => 'team-member-id',
    'date'         => '2025-08-01',
    'repeatUntil'  => '2025-12-31', // optional weekly recurrence
    'capacity'     => 20,
    'price'        => 20,
]);

$classes = $client->listClasses(['from' => '2025-08-01', 'limit' => 50]);
$class   = $client->getClass($created[0]->id);
$updated = $client->updateClass($class->id, ['capacity' => 25]);
$client->deleteClass($class->id, applyToSeries: true);
```

### Managing students, team members and packages

```php
$student = $client->createStudent([
    'name'     => 'Jane',
    'lastname' => 'Doe',
    'email'    => 'jane@example.com',
    'level'    => 'Beginner',
]);
$students = $client->listStudents(['limit' => 100]);

$member = $client->createTeamMember([
    'name'  => 'Carlos',
    'email' => 'carlos@example.com',
    'role'  => 'instructor',
]);

$package = $client->createPackage([
    'name'           => '10-Class Bundle',
    'price'          => 150,
    'sessions'       => 10,
    'durationMonths' => 3,
]);
```

Every management method maps the response into a typed DTO
(`ManagementClass`, `ManagementStudent`, `ManagementTeamMember`,
`ManagementPackage`). List methods return arrays of DTOs; delete methods return
the decoded `data` payload. On a non-2xx response the client parses the
`{ "error": { "code", "message" } }` envelope and throws a `RuntimeException`
carrying the server message.

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
* `ManagementClass` *(Management API)*
* `ManagementStudent` *(Management API)*
* `ManagementTeamMember` *(Management API)*
* `ManagementPackage` *(Management API)*

Each DTO implements `JsonSerializable` for easy conversion:

```php
echo json_encode($profile, JSON_PRETTY_PRINT);
```

---

## License

ISC
