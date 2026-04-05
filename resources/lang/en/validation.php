<?php

return [
    'required' => 'The :attribute field is required.',
    'enum' => 'The selected :attribute is invalid.',
    'array' => 'The :attribute must be an array.',
    'regex' => 'The :attribute format is invalid.',
    'min' => [
        'array' => 'The :attribute must have at least :min items.',
    ],
    'process_number' => [
        'regex' => 'The filing number must have exactly 23 numeric digits.',
    ],
    'file' => [
        'required' => 'The file field is required.',
        'file' => 'The field must be a valid file.',
        'mimes' => 'The file must be of type: :values.',
    ],
    'organization_id' => [
        'required' => 'The organization is required.',
        'exists' => 'The selected organization is not valid.',
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
            'regex' => 'The phone must have 10 digits (mobile). The +53 prefix will be used by default.',
        ],
        'email' => [
            'required' => 'The email address is required.',
            'email' => 'The email address is not valid.',
            'unique' => 'An organization with this email address is already registered.',
        ],
        'contact_person' => [
            'required_if' => 'The contact person is required for legal entities.',
        ],
        'identification' => [
            'required' => 'The identification is required.',
            'unique_cedula' => 'An organization with this identification number is already registered.',
            'unique_nit' => 'An organization with this NIT is already registered.',
        ],
    ],
    'custom' => [
        'alert_slug_only_for_alerta' => 'The alert type filter (alert_slug) only applies when type is actuacion_alerta.',
    ],
];
