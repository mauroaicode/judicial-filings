<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Data;

use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\RequiredIf;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\Organization\Enums\OrganizationType;

class StoreOrganizationData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, StringType]
        public readonly string $name,

        #[Required, In([OrganizationType::NATURAL->value, OrganizationType::JURIDICAL->value])]
        public readonly string $type,

        /** Phone: 10 digits (user input). Stored with +56 prefix by service. */
        #[Required, Regex('/^\d{10}$/')]
        public readonly string $phone,

        #[Required, Email]
        public readonly string $email,

        public readonly ?string $identification = null,

        public readonly ?string $address = null,

        #[RequiredIf('type', OrganizationType::JURIDICAL->value), StringType]
        public readonly ?string $contact_person = null,
    ) {}

    public static function withValidator(Validator $validator): void
    {
        $validator->setCustomMessages([
            'name.required' => __('validation.organization.name.required'),
            'type.required' => __('validation.organization.type.required'),
            'type.in' => __('validation.organization.type.in'),
            'phone.required' => __('validation.organization.phone.required'),
            'phone.regex' => __('validation.organization.phone.regex'),
            'email.required' => __('validation.organization.email.required'),
            'email.email' => __('validation.organization.email.email'),
            'email.unique' => __('validation.organization.email.unique'),
            'contact_person.required_if' => __('validation.organization.contact_person.required_if'),
        ]);

        $validator->after(function (Validator $validator): void {
            $data = $validator->getData();

            if (! isset($data['email'])) {
                return;
            }

            $exists = \Src\Domain\Organization\Models\Organization::query()
                ->where('email', $data['email'])
                ->exists();

            if ($exists) {
                $validator->errors()->add('email', __('validation.organization.email.unique'));
            }
        });
    }
}
