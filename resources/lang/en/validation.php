<?php

return [
    'custom' => [
        'alert_slug_only_for_alerta' => 'The alert type filter (alert_slug) only applies when type is actuacion_alerta.',
    ],
    'regex' => 'The :attribute format is invalid.',
    'process_number.regex' => 'The filing number must have exactly 23 numeric digits.',
    'file' => [
        'required' => 'The file field is required.',
        'file' => 'The field must be a valid file.',
        'mimes' => 'The file must be of type: :values.',
    ],
    'organization' => [
        'name' => [
            'required' => 'The organization name is required.',
        ],
        'type' => [
            'required' => 'The organization type is required.',
            'in' => 'The type must be natural person or legal entity.',
        ],
        'phone' => [
            'required' => 'The phone number is required.',
            'regex' => 'The phone must have 10 digits (mobile). The +56 prefix will be used by default.',
        ],
        'email' => [
            'required' => 'The email address is required.',
            'email' => 'The email address is not valid.',
            'unique' => 'An organization with this email address is already registered.',
        ],
        'contact_person' => [
            'required_if' => 'The contact person is required for legal entities.',
        ],
    ],
];
