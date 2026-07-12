<?php
declare(strict_types=1);

namespace BailaYa\Dto;

use BailaYa\Support\Json;
use BailaYa\Support\Phone;

final class StudioProfile implements \JsonSerializable
{
    /** @param array<string,string> $description
     *  @param list<StudioType> $studioTypes
     *  @param list<StudioLocation> $locations
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $country,
        public readonly string $address,
        public readonly ?string $unit,
        public readonly ?string $businessHours,
        public readonly array $description,
        public readonly ?string $logo,
        public readonly ?string $phone,
        public readonly ?string $defaultCurrency,
        public readonly ?int $studioSize,
        public readonly ?string $website,
        public readonly ?int $yearEstablished,
        public readonly string $timezone,
        public readonly bool $whatsappEnabled,
        public readonly ?string $whatsappPhone,
        public readonly array $studioTypes,
        public readonly array $locations = [],
        /**
         * `phone` normalized to a dialable E.164 string (e.g. "+525534669220"), or
         * null when the studio has no phone number.
         *
         * Use this for `tel:` links: studios often store the number without a leading
         * "+", which a dialer can misread as a national number and misdial. `phone` is
         * left exactly as the studio typed it, so keep using that for display.
         */
        public readonly ?string $phoneE164 = null,
        /**
         * Whether the studio has any class in the upcoming window the classes endpoint
         * serves. Use it to hide a "Classes" link that would lead to an empty page.
         * Null on older API deployments, which callers should treat as "show it".
         */
        public readonly ?bool $hasUpcomingClasses = null,
        /**
         * Whether the studio has any event in the upcoming window the events endpoint
         * serves. Use it to hide an "Events" link that would lead to an empty page.
         * Null on older API deployments, which callers should treat as "show it".
         */
        public readonly ?bool $hasUpcomingEvents = null,
    ) {}

    /** @param array<string,mixed> $raw RawStudioProfile */
    public static function fromRaw(array $raw): self
    {
        $types = [];
        foreach (($raw['studioTypes'] ?? []) as $t) {
            $types[] = StudioType::fromRaw($t);
        }

        $locations = [];
        foreach (($raw['locations'] ?? []) as $l) {
            $locations[] = StudioLocation::fromRaw($l);
        }

        return new self(
            id: $raw['id'],
            name: $raw['name'],
            country: $raw['country'],
            address: $raw['address'],
            unit: $raw['unit'] ?? null,
            businessHours: $raw['businessHours'] ?? null,
            description: Json::mapOrEmpty($raw['description'] ?? '{}'),
            logo: $raw['logo'] ?? null,
            phone: $raw['phone'] ?? null,
            defaultCurrency: $raw['defaultCurrency'] ?? null,
            studioSize: isset($raw['studioSize']) ? (int)$raw['studioSize'] : null,
            website: $raw['website'] ?? null,
            yearEstablished: isset($raw['yearEstablished']) ? (int)$raw['yearEstablished'] : null,
            timezone: $raw['timezone'],
            whatsappEnabled: isset($raw['whatsappEnabled']) ? (bool)$raw['whatsappEnabled'] : false,
            whatsappPhone: $raw['whatsappPhone'] ?? null,
            studioTypes: $types,
            locations: $locations,
            phoneE164: Phone::toE164($raw['phone'] ?? null, $raw['country'] ?? null),
            hasUpcomingClasses: isset($raw['hasUpcomingClasses']) ? (bool)$raw['hasUpcomingClasses'] : null,
            hasUpcomingEvents: isset($raw['hasUpcomingEvents']) ? (bool)$raw['hasUpcomingEvents'] : null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country' => $this->country,
            'address' => $this->address,
            'unit' => $this->unit,
            'businessHours' => $this->businessHours,
            'description' => $this->description,
            'logo' => $this->logo,
            'phone' => $this->phone,
            'defaultCurrency' => $this->defaultCurrency,
            'studioSize' => $this->studioSize,
            'website' => $this->website,
            'yearEstablished' => $this->yearEstablished,
            'timezone' => $this->timezone,
            'whatsappEnabled' => $this->whatsappEnabled,
            'whatsappPhone' => $this->whatsappPhone,
            'studioTypes' => $this->studioTypes,
            'locations' => $this->locations,
            'phoneE164' => $this->phoneE164,
            'hasUpcomingClasses' => $this->hasUpcomingClasses,
            'hasUpcomingEvents' => $this->hasUpcomingEvents,
        ];
    }
}



